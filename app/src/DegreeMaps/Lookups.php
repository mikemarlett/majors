<?php

declare(strict_types=1);

namespace Majors\DegreeMaps;

use mysqli;

/**
 * Reference lists shared by the degree-map and majors admins:
 * colleges (majors_colleges), departments (majors_departments) and programs
 * (majors_academic_programs). Degree maps store the college/department
 * *names*; users are scoped by college *id*, so both directions are here.
 */
final class Lookups
{
    /** @var array<int,array<string,mixed>>|null */
    private ?array $colleges = null;

    /** @param array<string,string> $aliases alternate college name => name in majors_colleges */
    public function __construct(private readonly mysqli $db, private readonly array $aliases = [])
    {
    }

    /** @return array<int,array<string,mixed>> id => row (id, name, code) */
    public function colleges(): array
    {
        if ($this->colleges === null) {
            $this->colleges = [];
            $res = $this->db->query('SELECT * FROM `majors_colleges` ORDER BY `name` ASC');
            while ($r = $res->fetch_assoc()) {
                $this->colleges[(int) $r['id']] = $r;
            }
        }
        return $this->colleges;
    }

    /** @return array<int,string> id => name */
    public function collegeNames(): array
    {
        return array_map(static fn (array $c): string => (string) $c['name'], $this->colleges());
    }

    public function collegeId(?string $name): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }
        $candidates = [$name];
        foreach ($this->aliases as $alias => $canonical) {
            if (strcasecmp($alias, $name) === 0) {
                $candidates[] = $canonical;
            } elseif (strcasecmp($canonical, $name) === 0) {
                $candidates[] = $alias;
            }
        }
        foreach ($this->colleges() as $id => $c) {
            foreach ($candidates as $n) {
                if (strcasecmp((string) $c['name'], $n) === 0) {
                    return $id;
                }
            }
        }
        return null;
    }

    /**
     * Names to offer when creating a map: every name a college is known by,
     * so a new map can use the current name even if majors_colleges still
     * holds the old one. @return array<string,int> name => college id
     */
    public function collegeNameChoices(): array
    {
        $out = [];
        foreach ($this->colleges() as $id => $c) {
            $out[(string) $c['name']] = $id;
        }
        foreach ($this->aliases as $alias => $canonical) {
            $id = $this->collegeId($canonical);
            if ($id !== null) {
                $out[$alias] = $id;
            }
        }
        ksort($out);
        return $out;
    }

    public function collegeName(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }
        $c = $this->colleges()[$id] ?? null;
        return $c === null ? null : (string) $c['name'];
    }

    /** @return array<int,array<string,mixed>> id => row, optionally for one college id */
    public function departments(?int $collegeId = null): array
    {
        $out = [];
        if ($collegeId === null) {
            $res = $this->db->query('SELECT * FROM `majors_departments` ORDER BY `department` ASC');
        } else {
            $stmt = $this->db->prepare('SELECT * FROM `majors_departments` WHERE `college_id` = ? ORDER BY `department` ASC');
            $stmt->bind_param('i', $collegeId);
            $stmt->execute();
            $res = $stmt->get_result();
        }
        while ($r = $res->fetch_assoc()) {
            $out[(int) $r['id']] = $r;
        }
        return $out;
    }

    /** @return array<string,string> name => name, for a college name (or all) */
    public function departmentNames(?string $collegeName = null): array
    {
        $rows = $this->departments($this->collegeId($collegeName));
        $out  = [];
        foreach ($rows as $r) {
            $out[(string) $r['department']] = (string) $r['department'];
        }
        return $out;
    }

    public function departmentId(?string $name): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }
        $stmt = $this->db->prepare('SELECT `id` FROM `majors_departments` WHERE `department` = ? LIMIT 1');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Undergraduate programs to link a map to: id => "Program - Type".
     * With a map, narrow to its college/department/degree type first.
     *
     * @return array<int,string>
     */
    public function programOptions(?array $map = null): array
    {
        $out = [];
        if ($map !== null && !empty($map['college'])) {
            $stmt = $this->db->prepare(
                'SELECT `id`, CONCAT(`academic_program`, " - ", `program_type`) AS `name` FROM `majors_academic_programs`
                  WHERE `college` = ? AND `department` = ? AND `program_type` = ? AND (`graduate` IS NULL OR `graduate` = 0)
                  ORDER BY `name`'
            );
            $stmt->bind_param('sss', $map['college'], $map['department'], $map['degree_type']);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                $out[(int) $r['id']] = (string) $r['name'];
            }
            $stmt->close();
        }
        if ($out === []) {
            $res = $this->db->query(
                'SELECT `id`, CONCAT(`academic_program`, " - ", `program_type`) AS `name` FROM `majors_academic_programs`
                  WHERE (`graduate` IS NULL OR `graduate` = 0) ORDER BY `name`'
            );
            while ($r = $res->fetch_assoc()) {
                $out[(int) $r['id']] = (string) $r['name'];
            }
        }
        return $out;
    }

    /** @return array<int,string> year => "2025 - 2026", newest first, always including next year */
    public function academicYearOptions(array $existingYears): array
    {
        $next = MapRepository::currentAcademicYear() + 1;
        if (!in_array($next, $existingYears, true)) {
            array_unshift($existingYears, $next);
        }
        $out = [];
        foreach ($existingYears as $y) {
            $out[(int) $y] = ((int) $y - 1) . ' - ' . (int) $y;
        }
        return $out;
    }
}
