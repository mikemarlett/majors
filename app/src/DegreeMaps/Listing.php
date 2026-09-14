<?php

declare(strict_types=1);

namespace Majors\DegreeMaps;

/**
 * Groups map header rows for the A–Z and by-college listings.
 * @return list<array{key:string,label:string,items:list<array<string,mixed>>}>
 */
final class Listing
{
    /** @param list<array<string,mixed>> $maps */
    public static function byAlpha(array $maps): array
    {
        $groups = [];
        foreach ($maps as $m) {
            $letter = mb_strtoupper(mb_substr((string) $m['major'], 0, 1));
            $groups[$letter] ??= ['key' => $letter, 'label' => $letter, 'items' => []];
            $groups[$letter]['items'][] = $m;
        }
        return array_values($groups);
    }

    /** @param list<array<string,mixed>> $maps */
    public static function byCollege(array $maps): array
    {
        $groups = [];
        foreach ($maps as $m) {
            $college = (string) $m['college'];
            $key     = rawurlencode(mb_strtolower($college));
            $groups[$key] ??= ['key' => $key, 'label' => $college, 'items' => []];
            $groups[$key]['items'][] = $m;
        }
        return array_values($groups);
    }
}
