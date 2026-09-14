<?php

declare(strict_types=1);

namespace Majors\DegreeMaps;

use mysqli;

/**
 * Read side of the degree_maps* tables. Port of the data functions in the
 * legacy maps_functions.php, with every request-sourced value bound.
 *
 * Shape of a full map (find()):
 *   header columns + 'footnotes' => [id => row], 'hours' => [year => [semester => ['hours'=>..], 'total_hours' => ..]],
 *   'courses' => [year => [semester => [order => row]]]
 */
final class MapRepository
{
    public const SEMESTERS = [1 => 'Fall', 2 => 'Spring', 3 => 'Summer'];

    public function __construct(private readonly mysqli $db)
    {
    }

    /** Academic year label convention: the year in which the spring semester falls. Aug+ rolls over. */
    public static function currentAcademicYear(?int $ts = null): int
    {
        $ts ??= time();
        $month = (int) date('n', $ts);
        $year  = (int) date('Y', $ts);
        return $month >= 8 ? $year + 1 : $year;
    }

    /** @return list<int> distinct academic years, newest first */
    public function years(): array
    {
        $years = [];
        $res   = $this->db->query('SELECT DISTINCT `academic_year` FROM `degree_maps` ORDER BY `academic_year` DESC');
        while ($r = $res->fetch_assoc()) {
            $years[] = (int) $r['academic_year'];
        }
        return $years;
    }

    public function latestYear(): int
    {
        $years = $this->years();
        return $years[0] ?? self::currentAcademicYear();
    }

    /** Year to show by default: the current academic year if maps exist for it, else the newest year that does. */
    public function defaultYear(): int
    {
        $current = self::currentAcademicYear();
        $years   = $this->years();
        if (in_array($current, $years, true)) {
            return $current;
        }
        foreach ($years as $y) {
            if ($y <= $current) {
                return $y;
            }
        }
        return $years[0] ?? $current;
    }

    /** @return list<string> distinct college names that have maps in $year */
    public function colleges(int $year): array
    {
        $out  = [];
        $stmt = $this->db->prepare('SELECT DISTINCT `college` FROM `degree_maps` WHERE `academic_year` = ? AND `college` <> "" ORDER BY `college`');
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $out[] = (string) $r['college'];
        }
        $stmt->close();
        return $out;
    }

    /**
     * Header rows for a year, optionally one college.
     *
     * @return list<array<string,mixed>>
     */
    public function list(int $year, string $order = 'alpha', ?string $college = null): array
    {
        $sql    = 'SELECT * FROM `degree_maps` WHERE `academic_year` = ?';
        $types  = 'i';
        $params = [$year];
        if ($college !== null && $college !== '' && $college !== 'all') {
            $sql   .= ' AND `college` = ?';
            $types .= 's';
            $params[] = $college;
        }
        $sql .= $order === 'college'
            ? ' ORDER BY `college` ASC, `major` ASC, `degree_type` ASC'
            : ' ORDER BY `major` ASC, `degree_type` ASC';
        return $this->rows($sql, $types, $params);
    }

    /**
     * Text search within a year (and optionally a college) across the header fields.
     *
     * @return list<array<string,mixed>>
     */
    public function search(string $text, int $year, ?string $college = null, string $order = 'alpha'): array
    {
        $like   = '%' . $text . '%';
        $sql    = 'SELECT * FROM `degree_maps` WHERE `academic_year` = ?
                   AND (`major` LIKE ? OR `college` LIKE ? OR `degree_type` LIKE ? OR `department` LIKE ? OR `note` LIKE ?)';
        $types  = 'isssss';
        $params = [$year, $like, $like, $like, $like, $like];
        if ($college !== null && $college !== '' && $college !== 'all') {
            $sql   .= ' AND `college` = ?';
            $types .= 's';
            $params[] = $college;
        }
        $sql .= $order === 'college'
            ? ' ORDER BY `college` ASC, `major` ASC, `degree_type` ASC'
            : ' ORDER BY `major` ASC, `degree_type` ASC';
        return $this->rows($sql, $types, $params);
    }

    /** @return array<string,mixed>|null header row only */
    public function header(int $id): ?array
    {
        $rows = $this->rows('SELECT * FROM `degree_maps` WHERE `id` = ? LIMIT 1', 'i', [$id]);
        return $rows[0] ?? null;
    }

    /** @return array<string,mixed>|null header + footnotes + hours + courses */
    public function find(int $id): ?array
    {
        $map = $this->header($id);
        if ($map === null) {
            return null;
        }
        $map['footnotes'] = $this->footnotes($id);
        $map['hours']     = $this->hours($id);
        $map['courses']   = $this->courses($id);
        return $map;
    }

    public function title(int $id): ?string
    {
        $m = $this->header($id);
        if ($m === null) {
            return null;
        }
        return $m['degree_type'] . ' in ' . $m['major'] . ' — ' . $m['academic_year'];
    }

    /** Maps linked to a Majors program, newest first. @return list<array<string,mixed>> */
    public function forProgram(int $programId): array
    {
        return $this->rows(
            'SELECT `id`, `major`, `degree_type`, `academic_year` FROM `degree_maps` WHERE `program_id` = ?
             ORDER BY `major` ASC, `degree_type` ASC, `academic_year` DESC',
            'i',
            [$programId]
        );
    }

    /** @return array<int,array<string,mixed>> keyed by footnote id, in display order */
    public function footnotes(int $mapId): array
    {
        $out = [];
        foreach ($this->rows('SELECT * FROM `degree_maps_footnotes` WHERE `degree_map_id` = ? ORDER BY `order` ASC', 'i', [$mapId]) as $f) {
            $out[(int) $f['id']] = $f;
        }
        return $out;
    }

    /** @return array<int,array<int,array<int,array<string,mixed>>>> [year][semester][order] */
    public function courses(int $mapId): array
    {
        $out = [];
        $sql = 'SELECT * FROM `degree_maps_courses` WHERE `degree_map_id` = ? ORDER BY `year` ASC, `semester` ASC, `order` ASC';
        foreach ($this->rows($sql, 'i', [$mapId]) as $c) {
            $out[(int) $c['year']][(int) $c['semester']][(int) $c['order']] = $c;
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function course(int $courseId): ?array
    {
        $rows = $this->rows('SELECT * FROM `degree_maps_courses` WHERE `id` = ? LIMIT 1', 'i', [$courseId]);
        return $rows[0] ?? null;
    }

    /**
     * Manual hours: [year][semester]['hours'] plus [year]['total_hours'] when a
     * year override exists in degree_maps_year_hours.
     *
     * @return array<int,array<int|string,mixed>>
     */
    public function hours(int $mapId): array
    {
        $out = [];
        foreach ($this->rows('SELECT * FROM `degree_maps_semester_hours` WHERE `degree_map_id` = ? ORDER BY `year`, `semester`', 'i', [$mapId]) as $h) {
            $out[(int) $h['year']][(int) $h['semester']] = $h;
        }
        foreach ($this->rows('SELECT `year`, `hours` FROM `degree_maps_year_hours` WHERE `degree_map_id` = ?', 'i', [$mapId]) as $y) {
            if ($y['hours'] !== null && $y['hours'] !== '') {
                $out[(int) $y['year']]['total_hours'] = $y['hours'];
            }
        }
        return $out;
    }

    /**
     * All catalog-year versions of the same degree map, newest first.
     *
     * Maps have no explicit family id: a new year is a clone that keeps the
     * program_id (when set) and the major / degree_type / college. So the
     * family is "same program_id" OR "same major + degree type + college".
     *
     * @param array<string,mixed> $map header row
     * @return list<array<string,mixed>> header rows (includes $map itself)
     */
    public function versions(array $map): array
    {
        $programId = (int) ($map['program_id'] ?? 0);
        $sql = 'SELECT `id`, `major`, `degree_type`, `college`, `academic_year`, `program_id` FROM `degree_maps`
                WHERE (`major` = ? AND `degree_type` = ? AND `college` = ?)';
        $types  = 'sss';
        $params = [(string) $map['major'], (string) $map['degree_type'], (string) $map['college']];
        if ($programId > 0) {
            $sql     .= ' OR `program_id` = ?';
            $types   .= 'i';
            $params[] = $programId;
        }
        $sql .= ' ORDER BY `academic_year` DESC, `id` DESC';
        return $this->rows($sql, $types, $params);
    }

    /**
     * Another map with the same major, degree type and college in $year (a
     * would-be duplicate), excluding $exceptId. @return array<string,mixed>|null
     */
    public function findDuplicate(string $major, string $degreeType, string $college, int $year, ?int $exceptId = null): ?array
    {
        $rows = $this->rows(
            'SELECT `id`, `major`, `degree_type`, `college`, `academic_year` FROM `degree_maps`
              WHERE `major` = ? AND `degree_type` = ? AND `college` = ? AND `academic_year` = ? AND `id` <> ?
              ORDER BY `id` LIMIT 1',
            'sssii',
            [$major, $degreeType, $college, $year, (int) $exceptId]
        );
        return $rows[0] ?? null;
    }

    /** Ids of maps in $year that share major + degree type + college with another map in the same year. @return list<int> */
    public function duplicateIds(int $year): array
    {
        $rows = $this->rows(
            'SELECT d.`id` FROM `degree_maps` d
               JOIN (SELECT `major`, `degree_type`, `college` FROM `degree_maps` WHERE `academic_year` = ?
                      GROUP BY 1, 2, 3 HAVING COUNT(*) > 1) k
                 ON k.`major` = d.`major` AND k.`degree_type` = d.`degree_type` AND k.`college` = d.`college`
              WHERE d.`academic_year` = ?',
            'ii',
            [$year, $year]
        );
        return array_map(static fn (array $r) => (int) $r['id'], $rows);
    }

    /** Newest version in the family of $map (may be $map itself). @return array<string,mixed> */
    public function latestVersion(array $map): array
    {
        $versions = $this->versions($map);
        return $versions[0] ?? $map;
    }

    /** Maps for a future academic year may be edited; current and past years are read-only. */
    public static function isEditable(array $map): bool
    {
        return (int) ($map['academic_year'] ?? 0) > self::currentAcademicYear();
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $sql, string $types, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
