<?php

declare(strict_types=1);

namespace Majors\Auth;

use mysqli;

/**
 * majors_users + majors_user_colleges. The approved list: nobody is created
 * here at sign-in time; super admins add people through Manage Users.
 */
final class UserRepository
{
    public function __construct(private readonly mysqli $db)
    {
    }

    public function findByNetidOrEmail(?string $netid, string $email): ?User
    {
        $row = null;
        if ($netid !== null && $netid !== '') {
            $row = $this->row('SELECT * FROM `majors_users` WHERE `netid` = ? AND `is_active` = 1 LIMIT 1', 's', [$netid]);
        }
        if ($row === null && $email !== '') {
            $row = $this->row('SELECT * FROM `majors_users` WHERE LOWER(`email`) = ? AND `is_active` = 1 LIMIT 1', 's', [strtolower($email)]);
        }
        return $row === null ? null : $this->hydrate($row);
    }

    public function find(int $id): ?User
    {
        $row = $this->row('SELECT * FROM `majors_users` WHERE `id` = ? LIMIT 1', 'i', [$id]);
        return $row === null ? null : $this->hydrate($row);
    }

    /** @return list<array<string,mixed>> raw rows plus a 'colleges' id list, for Manage Users */
    public function all(): array
    {
        $rows = [];
        $res  = $this->db->query('SELECT * FROM `majors_users` ORDER BY `last_name`, `first_name`');
        while ($r = $res->fetch_assoc()) {
            $r['colleges'] = $this->collegesFor((int) $r['id']);
            $rows[]        = $r;
        }
        return $rows;
    }

    /** Record a successful sign-in; fills in the netid the first time we see it. */
    public function touchLogin(int $id, Identity $identity): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `majors_users`
                SET `netid` = COALESCE(`netid`, ?),
                    `first_name` = IF(`first_name` = "" OR `first_name` IS NULL, ?, `first_name`),
                    `last_name`  = IF(`last_name`  = "" OR `last_name`  IS NULL, ?, `last_name`),
                    `last_login_at` = NOW(), `updated_at` = NOW()
              WHERE `id` = ?'
        );
        $netid = $identity->netid;
        $stmt->bind_param('sssi', $netid, $identity->givenName, $identity->surname, $id);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Create or update a user from the Manage Users form.
     *
     * @param array{id?:int,first_name:string,last_name:string,email:string,netid?:?string,role:string,
     *              default_college_id?:?int,default_department_id?:?int,is_active?:int,colleges?:list<int>} $d
     */
    public function save(array $d): int
    {
        $role = in_array($d['role'], User::ROLES, true) ? $d['role'] : 'none';
        $netid = isset($d['netid']) && $d['netid'] !== '' ? strtolower((string) $d['netid']) : null;
        $email = strtolower(trim($d['email']));
        $college = !empty($d['default_college_id']) ? (int) $d['default_college_id'] : null;
        $dept = !empty($d['default_department_id']) ? (int) $d['default_department_id'] : null;
        $active = (int) ($d['is_active'] ?? 1);

        if (!empty($d['id'])) {
            $id   = (int) $d['id'];
            $stmt = $this->db->prepare(
                'UPDATE `majors_users` SET `first_name`=?, `last_name`=?, `email`=?, `netid`=?, `role`=?,
                        `default_college_id`=?, `default_department_id`=?, `is_active`=?, `updated_at`=NOW()
                  WHERE `id`=?'
            );
            $stmt->bind_param('sssssiiii', $d['first_name'], $d['last_name'], $email, $netid, $role, $college, $dept, $active, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO `majors_users` (`first_name`,`last_name`,`email`,`netid`,`role`,`default_college_id`,
                        `default_department_id`,`is_active`,`created_at`,`updated_at`)
                 VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())'
            );
            $stmt->bind_param('sssssiii', $d['first_name'], $d['last_name'], $email, $netid, $role, $college, $dept, $active);
            $stmt->execute();
            $id = (int) $this->db->insert_id;
            $stmt->close();
        }

        $this->setColleges($id, array_map('intval', $d['colleges'] ?? []));
        return $id;
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM `majors_user_colleges` WHERE `user_id` = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $stmt = $this->db->prepare('DELETE FROM `majors_users` WHERE `id` = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    /** @return list<int> */
    public function collegesFor(int $userId): array
    {
        $ids  = [];
        $stmt = $this->db->prepare('SELECT `college_id` FROM `majors_user_colleges` WHERE `user_id` = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $ids[] = (int) $r['college_id'];
        }
        $stmt->close();
        return $ids;
    }

    /** @param list<int> $collegeIds */
    private function setColleges(int $userId, array $collegeIds): void
    {
        $stmt = $this->db->prepare('DELETE FROM `majors_user_colleges` WHERE `user_id` = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
        if ($collegeIds === []) {
            return;
        }
        $stmt = $this->db->prepare('INSERT IGNORE INTO `majors_user_colleges` (`user_id`, `college_id`) VALUES (?, ?)');
        foreach (array_unique($collegeIds) as $cid) {
            if ($cid > 0) {
                $stmt->bind_param('ii', $userId, $cid);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    /** @param array<string,mixed> $r */
    private function hydrate(array $r): User
    {
        $id = (int) $r['id'];
        return new User(
            $id,
            strtolower((string) $r['email']),
            isset($r['netid']) && $r['netid'] !== '' ? (string) $r['netid'] : null,
            (string) ($r['first_name'] ?? ''),
            (string) ($r['last_name'] ?? ''),
            (string) ($r['role'] ?? 'none'),
            $this->collegesFor($id),
            !empty($r['default_college_id']) ? (int) $r['default_college_id'] : null,
            !empty($r['default_department_id']) ? (int) $r['default_department_id'] : null,
        );
    }

    /** @return array<string,mixed>|null */
    private function row(string $sql, string $types, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}
