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
        $parts = $this->parts($program, $degreeMaps);
        return $parts['top'] . $parts['content'] . $parts['bottom'];
    }

    /**
     * The page in the layout's three slots. A design that has a full-width
     * program card / similar-programs band (templates majors/program_card and
     * majors/program_similar) gets them in top / bottom; otherwise everything
     * is in content.
     *
     * @return array{top:string,content:string,bottom:string}
     */
    public function parts(array $program, array $degreeMaps): array
    {
        $vars = $this->vars($program, $degreeMaps);
        $top    = $this->layout->exists('majors/program_card') ? $this->layout->render('majors/program_card', $vars) : '';
        $bottom = $this->layout->exists('majors/program_similar') ? $this->layout->render('majors/program_similar', $vars) : '';
        return ['top' => $top, 'content' => $this->layout->render('majors/program', $vars + ['split' => $top !== '']), 'bottom' => $bottom];
    }

    /** @return array<string,mixed> template variables shared by the program templates */
    private function vars(array $program, array $degreeMaps): array
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
        $pick = static fn (string $new, string $old): string => (string) (($program[$new] ?? '') !== '' ? $program[$new] : ($c[$old] ?? ''));
        $buttons = $json($program['buttons'] ?? null);
        if ($buttons === []) {
            $buttons = array_map(static fn ($l) => ['text' => $l['link_text'] ?? '', 'href' => $l['href'] ?? '#'], $json($c['learn_how_links'] ?? null));
        }
        $crumbs = [];
        foreach ([['college', 'college_url'], ['department', 'department_url']] as [$name, $url]) {
            if (!empty($program[$url]) && !empty($program[$name])) {
                $crumbs[] = ['text' => (string) $program[$name], 'href' => (string) $program[$url]];
            }
        }
        if ($crumbs === []) {
            $crumbs = array_values(array_filter(array_map(static fn ($l) => ['text' => $l['link_text'] ?? '', 'href' => $l['href'] ?? ''], $json($c['program_links'] ?? null)), static fn ($l) => $l['text'] !== 'All Programs'));
        }
        $sections = $program['sections'] ?? [];
        if ($sections === [] && $c !== []) {
            $sections = self::sectionsFromFlat($c);
        }
        return [
            'p'              => $program,
            'c'              => $c,
            'title'          => self::title($program),
            'kind'           => (string) (($program['credential'] ?? '') !== '' ? $program['credential'] : ($program['program_simple_type'] ?? '')),
            'is_certificate' => str_contains((string) ($program['program_type'] ?? ''), 'Certificate'),
            'crumbs'         => $crumbs,
            'description'    => $pick('description', 'description'),
            'learn_how'      => $pick('learn_how', 'learn_how'),
            'buttons'        => $buttons,
            'image'          => $pick('image_url', 'main_image_url') !== '' ? [
                'url' => $pick('image_url', 'main_image_url'), 'alt' => $pick('image_alt', 'main_image_alt'),
                'caption' => $pick('image_caption', 'main_image_caption'), 'credit' => $pick('image_credit', 'main_image_credit'),
            ] : null,
            'sections'       => $sections,
            'degree_maps'    => $maps,
            'similar'        => $program['similar_programs'] ?? [],
            'nav_items'      => $this->sectionNav($program),
            'program_url'    => $this->layout->url('index.php') . '?id=',
        ];
    }

    /** Sections for a program that only has the legacy flat row (pre-import data, fixtures). */
    public static function sectionsFromFlat(array $c): array
    {
        $mk = static fn (string $kind, string $headline, string $body, ?string $lt, ?string $lu, ?array $img = null): array => [
            'id' => 0, 'kind' => $kind, 'label' => $kind === 'feature' ? 'Inside the Program' : '', 'headline' => $headline, 'body' => $body,
            'links' => $lt && $lu ? [['text' => $lt, 'href' => $lu]] : [], 'image' => $img, 'block_id' => null, 'shared' => false,
        ];
        $out = [];
        if (!empty($c['wildcard_headline'])) {
            $out[] = $mk('teaser', $c['wildcard_headline'], (string) ($c['wildcard_text'] ?? ''), $c['wildcard_link_text'] ?? null, $c['wildcard_link_url'] ?? null);
        }
        if (!empty($c['admissions_headline'])) {
            $out[] = $mk('teaser', $c['admissions_headline'], (string) ($c['admissions_text'] ?? ''), $c['admissions_link_text'] ?? null, $c['admissions_link_url'] ?? null);
        }
        if (!empty($c['inside_the_program_headline'])) {
            $out[] = $mk('feature', $c['inside_the_program_headline'], (string) ($c['inside_the_program_text'] ?? ''), $c['inside_the_program_link_text'] ?? null, $c['inside_the_program_link_url'] ?? null,
                !empty($c['inside_the_program_image_url']) ? ['url' => $c['inside_the_program_image_url'], 'alt' => (string) ($c['inside_the_program_image_alt'] ?? '')] : null);
        }
        if (!empty($c['curriculum_text'])) {
            $out[] = $mk('teaser', 'Curriculum', (string) $c['curriculum_text'], $c['curriculum_link_text'] ?? null, $c['curriculum_link_url'] ?? null);
        }
        if (!empty($c['careers_headline'])) {
            $out[] = $mk('teaser', $c['careers_headline'], (string) ($c['careers_text'] ?? ''), $c['careers_link_text'] ?? null, $c['careers_link_url'] ?? null);
        }
        return $out;
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
