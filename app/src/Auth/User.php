<?php

declare(strict_types=1);

namespace Majors\Auth;

/**
 * A signed-in, approved user (a row of majors_users). Roles:
 *   advisor        edits degree maps for the colleges in $colleges (future years)
 *   advisor_admin  edits degree maps for every college (future years); no Majors, no users
 *   marketing      edits the Majors marketing pages
 *   super_admin    everything, plus user management and published-year edits
 *   none           on the list but disabled
 */
final class User
{
    public const ROLES = ['advisor', 'advisor_admin', 'marketing', 'super_admin', 'none'];

    /** Roles that satisfy a check for another role. */
    private const IMPLIES = [
        'advisor_admin' => ['advisor'],
        'super_admin'   => ['advisor', 'advisor_admin', 'marketing'],
    ];

    /** @param list<int> $colleges ids from majors_user_colleges */
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly ?string $netid,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $role,
        public readonly array $colleges = [],
        public readonly ?int $defaultCollegeId = null,
        public readonly ?int $defaultDepartmentId = null,
    ) {
    }

    public function name(): string
    {
        $n = trim($this->firstName . ' ' . $this->lastName);
        return $n !== '' ? $n : $this->email;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /** True when the user's role is one of $roles or implies one (super_admin implies all, advisor_admin implies advisor). */
    public function hasRole(string ...$roles): bool
    {
        if (in_array($this->role, $roles, true)) {
            return true;
        }
        foreach (self::IMPLIES[$this->role] ?? [] as $implied) {
            if (in_array($implied, $roles, true)) {
                return true;
            }
        }
        return false;
    }

    /** May edit degree maps for any college (still only future years unless super admin). */
    public function isDegreeMapsAdmin(): bool
    {
        return $this->role === 'advisor_admin' || $this->isSuperAdmin();
    }

    public function canEditDegreeMaps(): bool
    {
        return $this->hasRole('advisor');
    }

    public function canEditMajors(): bool
    {
        return $this->hasRole('marketing');
    }

    /** Advisor scope: may this user edit a map that belongs to $collegeId? */
    public function canEditCollege(?int $collegeId): bool
    {
        if ($this->isDegreeMapsAdmin()) {
            return true;
        }
        if ($this->role !== 'advisor' || $collegeId === null) {
            return false;
        }
        return in_array($collegeId, $this->colleges, true);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'email'     => $this->email,
            'netid'     => $this->netid,
            'first'     => $this->firstName,
            'last'      => $this->lastName,
            'role'      => $this->role,
            'colleges'  => $this->colleges,
            'college'   => $this->defaultCollegeId,
            'dept'      => $this->defaultDepartmentId,
        ];
    }

    /** @param array<string,mixed> $s */
    public static function fromArray(array $s): self
    {
        return new self(
            (int) $s['id'],
            (string) $s['email'],
            isset($s['netid']) ? (string) $s['netid'] : null,
            (string) ($s['first'] ?? ''),
            (string) ($s['last'] ?? ''),
            (string) ($s['role'] ?? 'none'),
            array_map('intval', (array) ($s['colleges'] ?? [])),
            isset($s['college']) ? (int) $s['college'] : null,
            isset($s['dept']) ? (int) $s['dept'] : null,
        );
    }
}
