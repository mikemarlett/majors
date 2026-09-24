<?php

declare(strict_types=1);

namespace Majors\DegreeMaps;

use Majors\Support\Html;
use Majors\View\Layout;

/**
 * Turns a full map array (MapRepository::find) into the printable degree map.
 * Data in, HTML out; the markup lives in templates/shared/degree_maps/map.php
 * and uses only app-owned `dm-` classes so it renders identically under
 * either site design. Port of the legacy display_degree_map().
 */
final class MapRenderer
{
    public const SGE_KEY = [
        '010' => 'English',
        '020' => 'Communications',
        '030' => 'Math/Statistics',
        '040' => 'Natural and Physical Science',
        '050' => 'Social and Behavioral Science',
        '060' => 'Arts and Humanities',
        '070' => 'Institutionally Designated',
    ];

    public function __construct(private readonly Layout $layout)
    {
    }

    /** @param array<string,mixed> $map from MapRepository::find() */
    public function render(array $map, array $opts = []): string
    {
        return $this->layout->render('degree_maps/map', ['map' => $this->viewModel($map)] + $opts);
    }

    /**
     * Everything the template needs, precomputed.
     *
     * @param array<string,mixed> $map
     * @return array<string,mixed>
     */
    public function viewModel(array $map): array
    {
        $footnotes = $map['footnotes'] ?? [];
        $hours     = $map['hours'] ?? [];
        $years     = [];

        foreach (($map['courses'] ?? []) as $yearNo => $semesters) {
            $yearNo    = (int) $yearNo;
            $hasSummer = !empty($semesters[3]);
            $semList   = $hasSummer ? [1, 2, 3] : [1, 2];

            $rows        = [];
            $totals      = [];
            $yearTotal   = 0;
            $manualYear  = $hours[$yearNo]['total_hours'] ?? null;

            foreach ($semList as $s) {
                $i = 0;
                foreach (($semesters[$s] ?? []) as $course) {
                    $rows[$i][$s] = [
                        'info'  => $this->courseInfo($course, $footnotes),
                        'hours' => (string) ($course['hours'] ?? ''),
                    ];
                    $i++;
                }
                // Printed semester total: the value saved on the Hours form, or, when
                // nothing was saved for this semester, the sum of the courses listed.
                $manual     = $hours[$yearNo][$s]['hours'] ?? null;
                $totals[$s] = $manual === null || trim((string) $manual) === ''
                    ? HoursCalculator::semester($map['courses'], $yearNo, $s)
                    : $manual;
            }
            if ($manualYear !== null && trim((string) $manualYear) !== '') {
                $yearTotal = $manualYear;
            } elseif (!empty($hours[$yearNo][0]['hours'])) {
                $yearTotal = $hours[$yearNo][0]['hours']; // legacy override: semester 0 row = whole-year manual hours
            } else {
                // Sum the semester totals; a course range like "2-4" keeps the year a range too.
                $min = $max = 0.0;
                foreach ($totals as $tv) {
                    $r    = HoursCalculator::range($tv);
                    $min += $r['floor'];
                    $max += $r['ceiling'];
                }
                $yearTotal = HoursCalculator::format($min, $max);
            }

            $years[] = [
                'number'    => $yearNo,
                'label'     => Html::ordinal($yearNo) . ' year',
                'summer'    => $hasSummer,
                'semesters' => array_intersect_key(MapRepository::SEMESTERS, array_flip($semList)),
                'rows'      => array_values($rows),
                'totals'    => $totals,
                'total'     => $this->trimNumber($yearTotal),
            ];
        }

        $noteFootnote = null;
        $list         = [];
        foreach ($footnotes as $f) {
            if ((string) $f['order'] === '0') {
                $noteFootnote = $f;
            } else {
                $list[] = $f;
            }
        }

        $ay = (int) ($map['academic_year'] ?? 0);
        return [
            'id'                => (int) $map['id'],
            'college'           => (string) ($map['college'] ?? ''),
            'major'             => (string) ($map['major'] ?? ''),
            'degree_type'       => (string) ($map['degree_type'] ?? ''),
            'title'             => trim(($map['degree_type'] ?? '') . ' in ' . ($map['major'] ?? '')),
            'academic_year'     => $ay,
            'year_label'        => ($ay - 1) . ' - ' . $ay,
            'note'              => (string) ($map['note'] ?? ''),
            'hours_to_graduate' => (string) ($map['hours_to_graduate'] ?? ''),
            'years'             => $years,
            'note_footnote'     => $noteFootnote,
            'footnotes'         => $list,
            'sge_key'           => self::SGE_KEY,
        ];
    }

    /**
     * Course cell HTML: stored course_info (trusted admin HTML) with footnote
     * superscripts turned into tooltip links, the SGE badge, and the optional
     * extra line. Same transformations as the legacy renderer.
     *
     * @param array<string,mixed> $course
     * @param array<int,array<string,mixed>> $footnotes keyed by id
     */
    public function courseInfo(array $course, array $footnotes): string
    {
        $info = (string) ($course['course_info'] ?? '');
        $sups = '';

        $ids = $course['footnote_ids'] ?? null;
        if (is_string($ids) && $ids !== '') {
            $ids = json_decode($ids, true);
        }
        if (is_array($ids)) {
            foreach ($ids as $fid) {
                $f = $footnotes[(int) $fid] ?? null;
                if ($f === null) {
                    continue;
                }
                $n    = (string) $f['order'];
                $link = '&nbsp;<sup><a class="dm-footnote-link" aria-label="footnote ' . Html::e($n)
                    . '" data-tippy-content="' . Html::e($f['note']) . '" href="#footnote_' . (int) $f['id'] . '">' . Html::e($n) . '</a></sup>';
                $plain = '<sup>' . $n . '</sup>';
                if (str_contains($info, $plain)) {
                    $info = str_replace($plain, $link, $info);
                } else {
                    $sups .= $link;
                }
            }
        }

        $badge = '';
        if (!empty($course['sge'])) {
            $sge   = Html::e($course['sge']);
            $badge = '&nbsp;<span class="dm-sge dm-sge-' . $sge . '">' . $sge . '</span>';
        }

        $html = '<span class="dm-course-text">' . $info . '</span>' . $sups . $badge;
        if (!empty($course['extra'])) {
            $html .= '<span class="dm-extra">' . Html::e($course['extra']) . '</span>';
        }
        return $html;
    }

    private function trimNumber(mixed $n): string
    {
        if (is_float($n)) {
            return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        }
        return (string) $n;
    }
}
