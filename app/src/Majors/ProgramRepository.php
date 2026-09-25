<?php

declare(strict_types=1);

namespace Majors\Majors;

use mysqli;

/**
 * Read side of the Majors tables: majors_academic_programs (the catalog of
 * programs), majors_programs_content (marketing copy per program) and
 * majors_similar_programs. Port of the data functions in majors_functions.php.
 */
final class ProgramRepository
{
    public const FILTERS = ['all', 'undergrad', 'graduate', 'online', 'minors', 'certificates', 'badges'];

    public function __construct(private readonly mysqli $db)
    {
    }

    /** @return list<string> distinct college names used by programs */
    public function colleges(): array
    {
        $out = [];
        $res = $this->db->query('SELECT DISTINCT `college` FROM `majors_academic_programs` WHERE `status` = "active" AND `college` <> "" AND `college` IS NOT NULL ORDER BY `college`');
        while ($r = $res->fetch_assoc()) {
            $out[] = (string) $r['college'];
        }
        return $out;
    }

    /** @return list<string> */
    public function departments(): array
    {
        $out = [];
        $res = $this->db->query('SELECT DISTINCT `department` FROM `majors_academic_programs` WHERE `status` = "active" AND `department` <> "" AND `department` IS NOT NULL ORDER BY `department`');
        while ($r = $res->fetch_assoc()) {
            $out[] = (string) $r['department'];
        }
        return $out;
    }

    /**
     * Every program, A–Z, with an extra entry under the sort title for
     * programs that have one (so "Engineering, Aerospace" also lists under E).
     *
     * @return list<array<string,mixed>>
     */
    public function all(): array
    {
        $sql = 'SELECT s.`program` AS `academic_program`, m.`id`, m.`basename`, m.`program_type`, m.`program_simple_type`, m.`credential`, m.`college`, m.`department`,
                       m.`online_learning`, m.`online_only`, m.`graduate`, m.`note`, m.`timestamp`
                  FROM `majors_academic_programs` m
                  JOIN (SELECT `academic_program` AS `program`, `id` FROM `majors_academic_programs`
                        UNION ALL
                        SELECT `sort_title` AS `program`, `id` FROM `majors_academic_programs` WHERE `sort_title` IS NOT NULL AND `sort_title` <> "") s
                    ON m.`id` = s.`id`
                 WHERE m.`status` = "active"
                 ORDER BY s.`program`, m.`program_type`';
        return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Filtered / searched programs.
     *
     * @param array{filter?:string,college?:?string,department?:?string,order?:string} $f
     * @return list<array<string,mixed>>
     */
    public function search(string $text, array $f = []): array
    {
        $types  = '';
        $params = [];
        if ($text !== '') {
            $like   = '%' . $text . '%';
            $sql    = 'SELECT DISTINCT m1.* FROM `majors_academic_programs` m1
                       LEFT JOIN `majors_programs_content` m2 ON m1.`id` = m2.`academic_program_id`
                       WHERE m1.`status` = "active" AND (m1.`academic_program` LIKE ? OR m1.`sort_title` LIKE ? OR m1.`department` LIKE ? OR m1.`note` LIKE ? OR m2.`meta_keywords` LIKE ?)';
            $types  = 'sssss';
            $params = [$like, $like, $like, $like, $like];
        } else {
            $sql = 'SELECT m1.* FROM `majors_academic_programs` m1 WHERE m1.`status` = "active"';
        }
        if (!empty($f['college']) && $f['college'] !== 'all') {
            $sql   .= ' AND m1.`college` = ?';
            $types .= 's';
            $params[] = $f['college'];
        }
        if (!empty($f['department'])) {
            $sql   .= ' AND m1.`department` = ?';
            $types .= 's';
            $params[] = $f['department'];
        }
        $sql .= match ($f['filter'] ?? 'all') {
            'undergrad'    => ' AND (m1.`graduate` IS NULL OR m1.`graduate` = 0)',
            'graduate'     => ' AND m1.`graduate` = 1',
            'online'       => ' AND m1.`online_learning` = 1',
            'minors'       => ' AND m1.`minor` = 1',
            'certificates' => ' AND m1.`certificate` = 1',
            'badges'       => ' AND m1.`badge` = 1',
            default        => '',
        };
        $sql .= ($f['order'] ?? 'alpha') === 'college'
            ? ' ORDER BY m1.`college`, m1.`academic_program`, m1.`program_type`'
            : ' ORDER BY m1.`academic_program`, m1.`program_type`';

        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** @return array<string,mixed>|null program row with 'content', 'sections' and 'similar_programs' */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `majors_academic_programs` WHERE `id` = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $this->hydrate($row) : null;
    }

    /** The public key: the CMS page's basename (unique). @return array<string,mixed>|null */
    public function findByBasename(string $basename): ?array
    {
        if ($basename === '') {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM `majors_academic_programs` WHERE `basename` = ? LIMIT 1');
        $stmt->bind_param('s', $basename);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): array
    {
        $id = (int) $row['id'];
        $row['content']          = $this->content($id) ?? [];
        $row['sections']         = $this->sections($id);
        $row['similar_programs'] = $this->similar($id);
        $row['similar_editing']  = $this->similarForEditing($id);
        return $row;
    }

    /**
     * The page body in order. A section that points at a shared block takes
     * its headline/body/links from the block (so editing the block changes
     * every page that uses it); 'shared' says which.
     *
     * @return list<array{id:int,kind:string,label:string,headline:string,body:string,links:list<array{text:string,href:string}>,image:?array{url:string,alt:string},block_id:?int,shared:bool}>
     */
    public function sections(int $programId): array
    {
        $stmt = $this->db->prepare('SELECT s.`id`, s.`kind`, s.`label`, s.`block_id`, s.`image_url`, s.`image_alt`,
                                           COALESCE(s.`headline`, b.`headline`, "") AS headline, COALESCE(s.`body`, b.`body`, "") AS body, COALESCE(s.`links`, b.`links`, "[]") AS links
                                      FROM `majors_program_sections` s
                                 LEFT JOIN `majors_content_blocks` b ON b.`id` = s.`block_id`
                                     WHERE s.`program_id` = ? AND s.`kind` IN ("teaser", "feature") ORDER BY s.`position`, s.`id`');
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $out = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $links = json_decode((string) $r['links'], true);
            $out[] = [
                'id'       => (int) $r['id'],
                'kind'     => (string) $r['kind'],
                'label'    => (string) $r['label'],
                'headline' => (string) $r['headline'],
                'body'     => (string) $r['body'],
                'links'    => is_array($links) ? array_values(array_filter($links, static fn ($l) => is_array($l) && ($l['href'] ?? '') !== '')) : [],
                'image'    => (string) $r['image_url'] !== '' ? ['url' => (string) $r['image_url'], 'alt' => (string) $r['image_alt']] : null,
                'block_id' => $r['block_id'] !== null ? (int) $r['block_id'] : null,
                'shared'   => $r['block_id'] !== null,
            ];
        }
        $stmt->close();
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function content(int $programId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `majors_programs_content` WHERE `academic_program_id` = ? LIMIT 1');
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Up to six related programs: the curated list first, then programs that
     * point back at this one, each resolved to id/name/type/image. Every row
     * says where it came from ('source' => curated | reverse).
     *
     * @return list<array<string,mixed>>
     */
    public function similar(int $programId, int $limit = 6): array
    {
        $ids  = [];
        $reverse = [];
        $stmt = $this->db->prepare('SELECT s.`similar_academic_program_id` FROM `majors_similar_programs` s
                                      JOIN `majors_academic_programs` p ON p.`id` = s.`similar_academic_program_id` AND p.`status` = "active"
                                     WHERE s.`main_academic_program_id` = ? ORDER BY s.`id` LIMIT ?');
        $stmt->bind_param('ii', $programId, $limit);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $ids[] = (int) $r['similar_academic_program_id'];
        }
        $stmt->close();

        if (count($ids) < $limit) {
            $remaining = $limit - count($ids);
            $sql = 'SELECT s.`main_academic_program_id` FROM `majors_similar_programs` s
                      JOIN `majors_academic_programs` p ON p.`id` = s.`main_academic_program_id` AND p.`status` = "active"
                     WHERE s.`similar_academic_program_id` = ?';
            $types = 'i';
            $params = [$programId];
            if ($ids !== []) {
                $sql .= ' AND s.`main_academic_program_id` NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $types .= str_repeat('i', count($ids));
                $params = array_merge($params, $ids);
            }
            $sql .= ' ORDER BY s.`id` LIMIT ?';
            $types .= 'i';
            $params[] = $remaining;
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                $ids[] = (int) $r['main_academic_program_id'];
                $reverse[(int) $r['main_academic_program_id']] = true;
            }
            $stmt->close();
        }
        if ($ids === []) {
            return [];
        }

        $ids  = array_values(array_unique($ids));
        $sql  = 'SELECT t1.`id`, t1.`basename`, t1.`academic_program`, t1.`program_type`, t1.`program_simple_type`, t1.`credential`,
                        COALESCE(NULLIF(t1.`image_url`, ""), t2.`main_image_url`) AS main_image_url
                   FROM `majors_academic_programs` t1
                   LEFT JOIN `majors_programs_content` t2 ON t1.`id` = t2.`academic_program_id`
                  WHERE t1.`status` = "active" AND t1.`id` IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        // Keep the curated order.
        $byId = array_column($rows, null, 'id');
        $out  = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id] + ['source' => isset($reverse[$id]) ? 'reverse' : 'curated'];
            }
        }
        return $out;
    }

    /**
     * What the editor shows in the Similar Programs band: the whole curated
     * list (retired ones too, flagged) followed by the reverse links the
     * public page would add, flagged so they get no remove button.
     *
     * @return list<array<string,mixed>>
     */
    public function similarForEditing(int $programId): array
    {
        $stmt = $this->db->prepare('SELECT t1.`id`, t1.`basename`, t1.`academic_program`, t1.`program_type`, t1.`program_simple_type`, t1.`credential`, t1.`status`,
                                           COALESCE(NULLIF(t1.`image_url`, ""), t2.`main_image_url`) AS main_image_url
                                      FROM `majors_similar_programs` s
                                      JOIN `majors_academic_programs` t1 ON t1.`id` = s.`similar_academic_program_id`
                                 LEFT JOIN `majors_programs_content` t2 ON t1.`id` = t2.`academic_program_id`
                                     WHERE s.`main_academic_program_id` = ? ORDER BY s.`id`');
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $out = [];
        $shown = 0;
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $retired = ($r['status'] ?? 'active') === 'retired';
            $hidden  = !$retired && ++$shown > 6;   // the public band shows the first six that render
            $out[] = $r + ['source' => 'curated', 'retired' => $retired, 'hidden' => $hidden];
        }
        $stmt->close();
        foreach ($this->similar($programId) as $r) {
            if ($r['source'] === 'reverse') {
                $out[] = $r + ['retired' => false, 'hidden' => false];
            }
        }
        return $out;
    }

    /**
     * Admin listing: every program with its content timestamp, section count
     * and the curated similar programs (id and name, for the control panel's
     * popover), active first.
     *
     * @return list<array<string,mixed>> each with 'similar' => list<array{id:int,name:string}>
     */
    public function adminList(): array
    {
        $sql = 'SELECT m.*,
                       (SELECT COUNT(*) FROM `majors_program_sections` x WHERE x.`program_id` = m.`id` AND x.`kind` IN ("teaser", "feature")) AS `section_count`,
                       (m.`description` IS NOT NULL AND m.`description` <> "") AS `has_description`,
                       (m.`image_url` IS NOT NULL AND m.`image_url` <> "") AS `has_image`,
                       (m.`image_url` IS NOT NULL AND m.`image_url` <> "" AND (m.`image_alt` IS NULL OR m.`image_alt` = "")) AS `photo_no_alt`,
                       EXISTS (SELECT 1 FROM `majors_program_sections` y WHERE y.`program_id` = m.`id` AND y.`image_url` <> "" AND (y.`image_alt` IS NULL OR y.`image_alt` = "")) AS `section_photo_no_alt`,
                       (m.`department` IS NULL OR m.`department` = "") AS `no_department`,
                       EXISTS (SELECT 1 FROM `majors_program_sections` z WHERE z.`program_id` = m.`id` AND z.`block_id` IS NULL AND CHAR_LENGTH(COALESCE(z.`headline`, "")) > 120) AS `long_headline`
                  FROM `majors_academic_programs` m
                 ORDER BY (m.`status` = "retired"), m.`academic_program`, m.`program_type`';
        $rows = $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
        $similar = [];
        $res = $this->db->query('SELECT s.`main_academic_program_id` AS pid, p.`id`, p.`academic_program` FROM `majors_similar_programs` s
                                   JOIN `majors_academic_programs` p ON p.`id` = s.`similar_academic_program_id`
                                  ORDER BY s.`main_academic_program_id`, s.`id`');
        while ($r = $res->fetch_assoc()) {
            $similar[(int) $r['pid']][] = ['id' => (int) $r['id'], 'name' => (string) $r['academic_program']];
        }
        foreach ($rows as &$row) {
            $row['similar']       = $similar[(int) $row['id']] ?? [];
            $row['similar_count'] = count($row['similar']);
        }
        unset($row);
        return $rows;
    }
}
