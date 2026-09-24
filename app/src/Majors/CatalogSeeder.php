<?php

declare(strict_types=1);

namespace Majors\Majors;

/**
 * Reads a program's CourseLeaf catalog page (catalog.wichita.edu) and pulls
 * the facts the Program Details box needs:
 *   - degree title from the page title ("PhD in Applied Mathematics" → "PhD");
 *   - credit hours from the "Total Credit Hours" row(s) of the course lists in
 *     the Requirements tab. A page can have several lists (tracks, options,
 *     foundation courses), each with a total; the program's total is the one
 *     under a "Curriculum" heading, else "Degree/Program Requirements", else
 *     the largest. Totals in the Admission tab (prerequisites) are ignored.
 * Modality and entry terms are not in the catalog in any structured way.
 * Pure functions; the CLI does the fetching.
 */
final class CatalogSeeder
{
    /** @return array{title:string,degree_title:string,credit_hours:string,totals:list<array{heading:string,hours:string}>,admission:string} */
    public static function extract(string $html): array
    {
        $title = '';
        if (preg_match('~<title>(.*?)</title>~s', $html, $m)) {
            $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = trim(explode('<', $title)[0]);
        }
        $degree = '';
        if (preg_match('/^(.{1,60}?) in (.+)$/u', $title, $m)) {
            $degree = trim($m[1]);
        } elseif (preg_match('/^([A-Za-z]{2,5}) - /', $title, $m)) {
            $degree = $m[1];                                  // "MACC - Master of Accountancy"
        } elseif (preg_match('/^(Certificate|Minor)\b/i', $title, $m)) {
            $degree = ucfirst(strtolower($m[1]));
        } elseif ($title !== '' && mb_strlen($title) <= 45) {
            $degree = $title;                                  // "Doctor of Nursing Practice"
        }

        $scope = preg_match('~<div id="requirementstextcontainer"[^>]*>(.*?)(?=<div id="[a-z]+textcontainer"|<footer|</body>)~s', $html, $m) ? $m[1] : preg_replace('~<div id="admissiontextcontainer"[^>]*>.*?(?=<div id="[a-z]+textcontainer"|<footer|</body>)~s', '', $html);
        $totals = [];
        $pos    = 0;
        while (preg_match('~<tr[^>]*class="listsum"[^>]*>(.*?)</tr>~s', (string) $scope, $m, PREG_OFFSET_CAPTURE, $pos)) {
            $row = strip_tags($m[1][0]);
            $pos = $m[0][1] + strlen($m[0][0]);
            if (!preg_match('/Total Credit Hours[^0-9]*([0-9]+(?:\s*[-–]\s*[0-9]+)?)/i', $row, $h)) {
                continue;
            }
            $before  = substr((string) $scope, 0, $m[0][1]);
            $heading = preg_match_all('~<h[2-5][^>]*>(.*?)</h[2-5]>~s', $before, $hs) ? trim(html_entity_decode(strip_tags(end($hs[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
            $totals[] = ['heading' => $heading, 'hours' => preg_replace('/\s+/', '', $h[1])];
        }
        $hours = self::choose($totals);

        $adm = '';
        if (preg_match('~<div id="admissiontextcontainer"[^>]*>(.*?)(?=<div id="[a-z]+textcontainer"|<footer|</body>)~s', $html, $m)) {
            $adm = trim(html_entity_decode(preg_replace('/\s+/', ' ', strip_tags($m[1])) ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $adm = preg_replace('/^Admission\s+/', '', $adm) ?? $adm;
        }
        return ['title' => $title, 'degree_title' => $degree, 'credit_hours' => $hours, 'totals' => $totals, 'admission' => $adm];
    }

    /** @param list<array{heading:string,hours:string}> $totals */
    public static function choose(array $totals): string
    {
        if ($totals === []) {
            return '';
        }
        // A page can carry foundation/prerequisite lists under "Program Requirements"
        // before the degree's own list, so the most specific heading wins.
        foreach (['/curriculum/i', '/degree requirements/i', '/program requirements/i', '/^requirements$/i'] as $re) {
            foreach ($totals as $t) {
                if (preg_match($re, $t['heading'])) {
                    return $t['hours'];
                }
            }
        }
        $best = $totals[0]['hours'];
        foreach ($totals as $t) {
            if ((int) $t['hours'] > (int) $best) {
                $best = $t['hours'];
            }
        }
        return $best;
    }
}
