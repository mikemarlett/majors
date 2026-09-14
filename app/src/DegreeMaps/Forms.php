<?php

declare(strict_types=1);

namespace Majors\DegreeMaps;

use Majors\Auth\User;
use Majors\View\Layout;

/**
 * Builds the admin editor and its modal forms from templates in
 * templates/shared/degree_maps/admin/. Port of the form builders in the
 * legacy map_edit_functions.php.
 */
final class Forms
{
    public const YEARS     = [1 => 'First', 2 => 'Second', 3 => 'Third', 4 => 'Fourth'];
    public const SEMESTERS = [1 => 'Fall', 2 => 'Spring', 3 => 'Summer'];

    public function __construct(
        private readonly Layout $layout,
        private readonly MapRepository $maps,
        private readonly Lookups $lookups,
    ) {
    }

    /** SGE select options (code => label). */
    public static function sgeOptions(): array
    {
        $out = ['' => 'None'];
        foreach (MapRenderer::SGE_KEY as $code => $label) {
            $out[$code] = "{$label} ({$code})";
        }
        return $out;
    }

    /** A map array with every key the forms expect. @param array<string,mixed> $overrides */
    public function emptyMap(array $overrides = []): array
    {
        $map = [
            'id'                => null,
            'program_id'        => null,
            'major'             => '(Draft) New Major',
            'college'           => '',
            'degree_type'       => '',
            'department'        => null,
            'note'              => null,
            'academic_year'     => MapRepository::currentAcademicYear() + 1,
            'hours_to_graduate' => '120',
            'footnotes'         => [],
            'courses'           => [],
            'hours'             => [],
        ];
        $map = array_replace($map, $overrides);
        $map['courses'] = self::padCourses($map['courses'] ?? []);
        $map['hours']   = self::padHours($map['hours'] ?? []);
        return $map;
    }

    /** Ensure [1..4][1..3] exist so templates can loop without isset() noise. */
    public static function padCourses(array $courses): array
    {
        for ($y = 1; $y <= 4; $y++) {
            for ($s = 1; $s <= 3; $s++) {
                $courses[$y][$s] = is_array($courses[$y][$s] ?? null) ? $courses[$y][$s] : [];
            }
        }
        ksort($courses);
        return $courses;
    }

    public static function padHours(array $hours): array
    {
        for ($y = 1; $y <= 4; $y++) {
            for ($s = 1; $s <= 3; $s++) {
                if (!isset($hours[$y][$s])) {
                    $hours[$y][$s] = ['hours' => $s === 3 ? null : '15'];
                } elseif (!is_array($hours[$y][$s])) {
                    $hours[$y][$s] = ['hours' => $hours[$y][$s]];
                }
            }
            $hours[$y]['total_hours'] ??= '30';
        }
        return $hours;
    }

    public function blankCourse(int $mapId, int $year, int $semester): array
    {
        return [
            'id' => null, 'degree_map_id' => $mapId, 'course_info' => '', 'hours' => '', 'year' => $year, 'semester' => $semester,
            'order' => null, 'footnote_ids' => null, 'sge' => null, 'extra' => null, 'scbcrse_subj_code' => null, 'scbcrse_crse_numb' => null,
        ];
    }

    // ---- the editor body ---------------------------------------------------------

    /** @param array<string,mixed> $map full map (find()) */
    public function editor(array $map, User $user): string
    {
        $renderer  = new MapRenderer($this->layout);
        $footnotes = $map['footnotes'] ?? [];
        $courses   = self::padCourses($map['courses'] ?? []);
        $hours     = $map['hours'] ?? [];

        $years = [];
        for ($y = 1; $y <= 4; $y++) {
            $sems = [];
            $yearTotal = 0.0;
            foreach (self::SEMESTERS as $s => $name) {
                $items = [];
                foreach ($courses[$y][$s] as $c) {
                    $items[] = [
                        'id'    => (int) $c['id'],
                        'info'  => $renderer->courseInfo($c, $footnotes),
                        'hours' => (string) ($c['hours'] ?? ''),
                    ];
                }
                $manual = $hours[$y][$s]['hours'] ?? 0;
                $manual = ($manual === null || $manual === '') ? 0 : $manual;
                $sems[$s] = ['name' => $name, 'courses' => $items, 'manual' => $manual, 'calc' => HoursCalculator::semester($courses, $y, $s)];
                if (empty($hours[$y]['total_hours'])) {
                    $yearTotal += is_numeric($manual) ? (float) $manual : 0;
                }
            }
            $total = !empty($hours[$y]['total_hours']) ? (string) $hours[$y]['total_hours'] : HoursCalculator::format($yearTotal, $yearTotal);
            $years[$y] = ['label' => \Majors\Support\Html::ordinal($y) . ' year', 'semesters' => $sems, 'total' => $total, 'calc' => HoursCalculator::year($courses, $y)];
        }

        $note = null;
        $list = [];
        foreach ($footnotes as $f) {
            if ((string) $f['order'] === '0') {
                $note = $f;
            } else {
                $list[] = $f;
            }
        }
        $ay = (int) $map['academic_year'];

        return $this->layout->render('degree_maps/admin/edit_map', [
            'map'          => $map,
            'years'        => $years,
            'year_label'   => ($ay - 1) . ' - ' . $ay,
            'title'        => trim($map['degree_type'] . ' in ' . $map['major']),
            'sge_warnings' => SgeValidator::warnings($courses),
            'note_footnote' => $note,
            'footnotes'    => $list,
            'sge_key'      => MapRenderer::SGE_KEY,
            'permalink'    => $this->layout->url('degree_maps/maps.php') . '?latest=' . (int) $map['id'],
            'public_url'   => $this->layout->url('degree_maps/maps.php') . '?degree_map_id=' . (int) $map['id'],
        ]);
    }

    // ---- modal forms -----------------------------------------------------------------

    public function mapForm(array $map, User $user): string
    {
        $map = $this->emptyMap($map);
        $colleges = $this->lookups->collegeNames();
        if (!$user->isSuperAdmin()) {
            $colleges = array_intersect_key($colleges, array_flip($user->colleges));
        }
        $collegeOptions = [];
        foreach ($colleges as $name) {
            $collegeOptions[$name] = $name;
        }
        if ($map['college'] !== '' && !isset($collegeOptions[$map['college']])) {
            $collegeOptions[$map['college']] = $map['college']; // keep an existing value visible even if out of scope
        }
        return $this->layout->render('degree_maps/admin/form_map', [
            'map'         => $map,
            'colleges'    => $collegeOptions,
            'departments' => $this->lookups->departmentNames($map['college'] ?: null),
            'years'       => $this->lookups->academicYearOptions($this->maps->years()),
            'programs'    => $this->lookups->programOptions($map['id'] ? $map : null),
            'is_new'      => empty($map['id']),
        ]);
    }

    public function hoursForm(array $map): string
    {
        $map = $this->emptyMap($map);
        if (empty($map['hours_to_graduate'])) {
            $map['hours_to_graduate'] = 120;
        }
        $calc = [];
        for ($y = 1; $y <= 4; $y++) {
            foreach ([1, 2, 3] as $s) {
                $calc[$y][$s] = HoursCalculator::semester($map['courses'], $y, $s);
            }
            $calc[$y]['total_hours'] = HoursCalculator::year($map['courses'], $y);
        }
        return $this->layout->render('degree_maps/admin/form_hours', ['map' => $map, 'calc' => $calc, 'years' => self::YEARS, 'semesters' => self::SEMESTERS]);
    }

    public function footnotesForm(array $map): string
    {
        $map = $this->emptyMap($map);
        return $this->layout->render('degree_maps/admin/form_footnotes', ['map' => $map, 'footnotes' => array_values($map['footnotes'])]);
    }

    public function courseForm(array $course, array $map, int $year, int $semester): string
    {
        $map    = $this->emptyMap($map);
        $course = array_replace($this->blankCourse((int) ($map['id'] ?? 0), $year, $semester), $course);
        $count = count($map['courses'][$year][$semester] ?? []);
        if (empty($course['id'])) {
            $count++;
        }
        if (empty($course['order'])) {
            $course['order'] = $count;
        }
        $orders = [];
        for ($i = 1; $i <= max(1, $count); $i++) {
            $orders[$i] = (string) $i;
        }
        $selected = [];
        $ids = $course['footnote_ids'] ?? null;
        if (is_string($ids) && $ids !== '') {
            $ids = json_decode($ids, true);
        }
        if (is_array($ids)) {
            $selected = array_map('intval', $ids);
        }
        $footnoteOptions = [];
        foreach ($map['footnotes'] as $f) {
            $footnoteOptions[(int) $f['id']] = $f['order'] . '. ' . mb_substr(strip_tags((string) $f['note']), 0, 60);
        }
        return $this->layout->render('degree_maps/admin/form_course', [
            'course'    => $course,
            'map'       => $map,
            'headline'  => empty($course['course_info']) ? 'New Course' : strip_tags((string) $course['course_info']),
            'years'     => self::YEARS,
            'semesters' => self::SEMESTERS,
            'sge'       => self::sgeOptions(),
            'orders'    => $orders,
            'footnotes' => $footnoteOptions,
            'selected_footnotes' => $selected,
        ]);
    }
}
