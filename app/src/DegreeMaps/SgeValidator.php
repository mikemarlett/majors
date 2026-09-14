<?php

declare(strict_types=1);

namespace Majors\DegreeMaps;

/**
 * Kansas Systemwide General Education check: does the map carry the required
 * hours in each SGE bucket? Returns warning lines, or [] when everything fits.
 */
final class SgeValidator
{
    /** code => [min, max] required hours */
    public const REQUIRED = [
        '010' => [6, 6],
        '020' => [3, 3],
        '030' => [3, 3],
        '040' => [4, 5],
        '050' => [6, 6],
        '060' => [6, 6],
        '070' => [6, 6],
    ];

    /**
     * @param array<int,array<int,array<int,array<string,mixed>>>> $courses [year][semester][order]
     * @return list<string>
     */
    public static function warnings(array $courses): array
    {
        $tally = [];
        foreach (self::REQUIRED as $code => $_) {
            $tally[$code] = ['floor' => 0.0, 'ceiling' => 0.0, 'count' => 0];
        }
        foreach ($courses as $year) {
            foreach ($year as $semester) {
                foreach ($semester as $c) {
                    $code = (string) ($c['sge'] ?? '');
                    if (!isset($tally[$code])) {
                        continue;
                    }
                    $r = HoursCalculator::range($c['hours'] ?? '');
                    $tally[$code]['floor']   += $r['floor'];
                    $tally[$code]['ceiling'] += $r['ceiling'];
                    $tally[$code]['count']++;
                }
            }
        }

        $out = [];
        foreach (self::REQUIRED as $code => [$min, $max]) {
            $t = $tally[$code];
            $tooFew  = $t['ceiling'] < $min;
            $tooMany = $t['floor'] > $max;
            if (!$tooFew && !$tooMany) {
                continue;
            }
            // A single over-sized course (e.g. a 5-hour science) is fine; skip that case like the legacy check did.
            if ($tooMany && $t['count'] === 1) {
                continue;
            }
            $have = HoursCalculator::format($t['floor'], $t['ceiling']);
            $need = $min === $max ? "{$min} hours" : "a range of {$min}-{$max} hours";
            $out[] = sprintf('%s (%s) has %s hours and is not within the required %s.', MapRenderer::SGE_KEY[$code], $code, $have, $need);
        }
        return $out;
    }
}
