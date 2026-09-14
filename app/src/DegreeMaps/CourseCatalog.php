<?php

declare(strict_types=1);

namespace Majors\DegreeMaps;

use mysqli;

/** Banner course catalog copy (`courses`) — autocomplete for the course editor. */
final class CourseCatalog
{
    public function __construct(private readonly mysqli $db)
    {
    }

    /** @return list<array<string,mixed>> jQuery UI autocomplete items */
    public function search(string $term, int $limit = 25): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }
        $like = '%' . $term . '%';
        $stmt = $this->db->prepare(
            'SELECT `id`, `scbcrse_subj_code`, `scbcrse_crse_numb`, `scbcrse_title`, `crs_longtitle`, `credit_hr_low`, `credit_hr_high`
               FROM `courses`
              WHERE `scbcrse_title` LIKE ? OR `crs_longtitle` LIKE ? OR CONCAT(`scbcrse_subj_code`, " ", `scbcrse_crse_numb`) LIKE ?
              ORDER BY `scbcrse_subj_code` ASC, `scbcrse_crse_numb` ASC
              LIMIT ?'
        );
        $stmt->bind_param('sssi', $like, $like, $like, $limit);
        $stmt->execute();
        $out = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $label = trim($r['scbcrse_subj_code'] . ' ' . $r['scbcrse_crse_numb'] . ': ' . ($r['crs_longtitle'] ?: $r['scbcrse_title']));
            $out[] = [
                'id'                => (int) $r['id'],
                'label'             => $label,
                'value'             => $label,
                'hours'             => self::hours($r['credit_hr_low'], $r['credit_hr_high']),
                'fullTitle'         => (string) ($r['crs_longtitle'] ?? ''),
                'scbcrse_subj_code' => (string) $r['scbcrse_subj_code'],
                'scbcrse_crse_numb' => (string) $r['scbcrse_crse_numb'],
            ];
        }
        $stmt->close();
        return $out;
    }

    /** "3", "3-4", "1.5" — whole numbers without decimals, ranges when high > low. */
    public static function hours(mixed $low, mixed $high): string
    {
        $lo = (float) $low;
        $hi = (float) $high;
        $f  = static fn (float $n): string => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        return ($high !== null && $high !== '' && $hi > $lo) ? $f($lo) . '-' . $f($hi) : $f($lo);
    }
}
