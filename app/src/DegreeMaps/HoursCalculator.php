<?php

declare(strict_types=1);

namespace Majors\DegreeMaps;

/** Credit-hour arithmetic over course rows. Hours may be "3" or a range like "2-4". */
final class HoursCalculator
{
    /** @return array{floor:float,ceiling:float} */
    public static function range(mixed $hours): array
    {
        $h = trim((string) $hours);
        if (is_numeric($h)) {
            return ['floor' => (float) $h, 'ceiling' => (float) $h];
        }
        if (preg_match('/^(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)$/', $h, $m)) {
            return ['floor' => (float) $m[1], 'ceiling' => (float) $m[2]];
        }
        return ['floor' => 0.0, 'ceiling' => 0.0];
    }

    /** Sum of a list of course rows as "n" or "min-max". @param iterable<array<string,mixed>> $courses */
    public static function sum(iterable $courses): string
    {
        $min = $max = 0.0;
        foreach ($courses as $c) {
            $r = self::range($c['hours'] ?? '');
            $min += $r['floor'];
            $max += $r['ceiling'];
        }
        return self::format($min, $max);
    }

    /** @param array<int,array<int,array<int,array<string,mixed>>>> $courses [year][semester][order] */
    public static function semester(array $courses, int $year, int $semester): string
    {
        return self::sum($courses[$year][$semester] ?? []);
    }

    /** @param array<int,array<int,array<int,array<string,mixed>>>> $courses */
    public static function year(array $courses, int $year): string
    {
        $all = [];
        foreach ($courses[$year] ?? [] as $semester) {
            foreach ($semester as $c) {
                $all[] = $c;
            }
        }
        return self::sum($all);
    }

    public static function format(float $min, float $max): string
    {
        $f = static fn (float $n): string => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        return $min === $max ? $f($min) : $f($min) . '-' . $f($max);
    }
}
