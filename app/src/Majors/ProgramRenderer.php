<?php

declare(strict_types=1);

namespace Majors\Majors;

use Majors\View\Layout;

/**
 * Prepares the marketing page for one program and the A–Z / by-college
 * listings. The page markup is design-specific (templates/<design>/majors/program.php)
 * because it is built from the design system's own components; this class
 * only shapes the data.
 */
final class ProgramRenderer
{
    public function __construct(private readonly Layout $layout)
    {
    }

    /**
     * @param array<string,mixed> $program from ProgramRepository::find()
     * @param list<array<string,mixed>> $degreeMaps header rows linked to the program
     */
    public function page(array $program, array $degreeMaps): string
    {
        $c = $program['content'] ?? [];
        $json = static function (mixed $v): array {
            if (is_string($v) && $v !== '') {
                $d = json_decode($v, true);
                return is_array($d) ? $d : [];
            }
            return is_array($v) ? $v : [];
        };
        $maps = [];
        foreach ($degreeMaps as $m) {
            $y = (int) $m['academic_year'];
            $maps[] = [
                'id'    => (int) $m['id'],
                'label' => $m['degree_type'] . ' in ' . $m['major'] . ' (’' . substr((string) ($y - 1), -2) . '-’' . substr((string) $y, -2) . ')',
                'url'   => $this->layout->url('degree_maps/maps.php') . '?degree_map_id=' . (int) $m['id'],
            ];
        }
        return $this->layout->render('majors/program', [
            'p'              => $program,
            'c'              => $c,
            'title'          => self::title($program),
            'is_certificate' => str_contains((string) ($program['program_type'] ?? ''), 'Certificate'),
            'program_links'  => $json($c['program_links'] ?? null),
            'learn_how_links' => $json($c['learn_how_links'] ?? null),
            'degree_maps'    => $maps,
            'similar'        => $program['similar_programs'] ?? [],
            'nav_items'      => $this->sectionNav($program),
            'program_url'    => $this->layout->url('index.php') . '?id=',
        ]);
    }

    public static function title(array $program): string
    {
        $t = (string) ($program['academic_program'] ?? '');
        if (!empty($program['program_simple_type'])) {
            $t .= ', ' . $program['program_simple_type'];
        }
        return $t;
    }

    /** Section menu entries (label => href). */
    public function sectionNav(?array $program = null): array
    {
        $base  = $this->layout->url('index.php');
        $items = [
            'All Programs'          => $base,
            'Programs By College'   => $base . '?order=college',
            'Online Degrees'        => $base . '?filter=online',
            'Undergraduate Degrees' => $base . '?filter=undergrad',
            'Graduate Degrees'      => $base . '?filter=graduate',
            'Certificates'          => $base . '?filter=certificates',
            'Badges'                => $base . '?filter=badges',
            'Degree Maps'           => $this->layout->url('degree_maps/maps.php'),
        ];
        if (!empty($program['college'])) {
            $items['Degrees from ' . $program['college']] = $base . '?college=' . rawurlencode((string) $program['college']);
        }
        if (!empty($program['department'])) {
            $items['Degrees from ' . $program['department']] = $base . '?department=' . rawurlencode((string) $program['department']);
        }
        return $items;
    }

    /** Group rows for the listing. @return list<array{key:string,label:string,items:list<array<string,mixed>>}> */
    public static function group(array $rows, string $order): array
    {
        $groups = [];
        foreach ($rows as $r) {
            if ($order === 'college') {
                $label = (string) ($r['college'] ?? '');
                $key   = rawurlencode(mb_strtolower($label));
            } else {
                $label = mb_strtoupper(mb_substr((string) $r['academic_program'], 0, 1));
                $key   = $label;
            }
            $groups[$key] ??= ['key' => $key, 'label' => $label, 'items' => []];
            $groups[$key]['items'][] = $r;
        }
        return array_values($groups);
    }

    /** "Undergraduate Degrees in College of X from Dept" style headline for the current filters. */
    public static function headline(string $filter, ?string $college, ?string $department, string $search): string
    {
        $h = match ($filter) {
            'undergrad'    => 'Undergraduate Degrees',
            'graduate'     => 'Graduate Degrees',
            'online'       => 'Online Degrees',
            'minors'       => 'Minors',
            'certificates' => 'Certificates',
            'badges'       => 'Badges',
            default        => 'All Degrees',
        };
        if ($college) {
            $h .= ' in ' . $college;
        }
        if ($department) {
            $h .= ' from ' . $department;
        }
        if ($search !== '') {
            $h = '"' . $search . '" in ' . $h;
        }
        return $h;
    }
}
