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
    public function page(array $program, array $degreeMaps, bool $editing = false, array $blockUses = []): string
    {
        $parts = $this->parts($program, $degreeMaps, $editing, $blockUses);
        return $parts['top'] . $parts['content'] . $parts['bottom'];
    }

    /**
     * The page in the layout's three slots. A design that has a full-width
     * program card / similar-programs band (templates majors/program_card and
     * majors/program_similar) gets them in top / bottom; otherwise everything
     * is in content.
     *
     * With $editing the same templates add the in-place editor's markers and
     * placeholders (see EditMarks); $blockUses (block id => pages) feeds the
     * "shared by N pages" badges.
     *
     * @return array{top:string,content:string,bottom:string}
     */
    public function parts(array $program, array $degreeMaps, bool $editing = false, array $blockUses = []): array
    {
        $vars = $this->vars($program, $degreeMaps, $editing, $blockUses);
        $top    = $this->layout->exists('majors/program_card') ? $this->layout->render('majors/program_card', $vars) : '';
        $bottom = $this->layout->exists('majors/program_similar') ? $this->layout->render('majors/program_similar', $vars) : '';
        return ['top' => $top, 'content' => $this->layout->render('majors/program', $vars + ['split' => $top !== '']), 'bottom' => $bottom];
    }

    /** @return array<string,mixed> template variables shared by the program templates */
    private function vars(array $program, array $degreeMaps, bool $editing = false, array $blockUses = []): array
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
        if (!empty($program['college_url']) && !empty($program['college'])) {
            $crumbs[] = ['text' => (string) $program['college'], 'href' => (string) $program['college_url']];
        }
        foreach (ProgramRepository::departmentsOf($program) as $dep) {      // every department with a link (joint programs list both)
            if ($dep['href'] !== '') {
                $crumbs[] = $dep;
            }
        }
        if ($crumbs === []) {
            $crumbs = array_values(array_filter(array_map(static fn ($l) => ['text' => $l['link_text'] ?? '', 'href' => $l['href'] ?? ''], $json($c['program_links'] ?? null)), static fn ($l) => $l['text'] !== 'All Programs'));
        }
        $sections = $program['sections'] ?? [];
        if ($sections === [] && $c !== [] && !$editing) {   // pre-import rows: the editor shows the empty page and its add bar instead
            $sections = self::sectionsFromFlat($c);
        }
        $n = count($sections);
        foreach ($sections as $i => &$sec) {
            $sec['first'] = $i === 0;
            $sec['last']  = $i === $n - 1;
        }
        unset($sec);
        return [
            'p'              => $program,
            'c'              => $c,
            'editing'        => $editing,
            'ed'             => new EditMarks($editing, $blockUses),
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
            'facts'          => self::facts($program),
            'is_stem'        => (bool) ($program['is_stem'] ?? false),
            'coordinator'    => !empty($program['coordinator_name']) || !empty($program['coordinator_email']) ? [
                'name' => (string) ($program['coordinator_name'] ?? ''), 'email' => (string) ($program['coordinator_email'] ?? ''), 'phone' => (string) ($program['coordinator_phone'] ?? ''),
            ] : null,
            'degree_maps'    => $maps,
            'similar'        => $editing && isset($program['similar_editing']) ? $program['similar_editing'] : ($program['similar_programs'] ?? []),
            'similar_bg'     => trim((string) ($program['similar_bg_url'] ?? '')),
            'nav_items'      => $this->sectionNav($program),
        ];
    }

    /**
     * The Program Details box (Strat Comm's graduate template): only the facts
     * that are filled in, in a fixed order. @return list<array{label:string,value:string}>
     */
    public static function facts(array $program): array
    {
        $out = [];
        foreach ([['degree_title', 'Degree'], ['modality', 'Modality'], ['credit_hours', 'Credit Hours'], ['entry_terms', 'Entry Term']] as [$col, $label]) {
            $v = trim((string) ($program[$col] ?? ''));
            if ($v !== '') {
                $out[] = ['label' => $label, 'value' => $v];
            }
        }
        return $out;
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

    /** A Similar Programs card's label: "Name (Major)", or just the name when the program has no credential or type. */
    public static function cardLabel(array $s): string
    {
        $kind = '';
        foreach (['credential', 'program_simple_type', 'program_type'] as $k) {
            if (trim((string) ($s[$k] ?? '')) !== '') {
                $kind = trim((string) $s[$k]);
                break;
            }
        }
        $name = trim((string) ($s['academic_program'] ?? ''));
        return $kind !== '' ? $name . ' (' . $kind . ')' : $name;
    }

    public static function title(array $program): string
    {
        $t = (string) ($program['academic_program'] ?? '');
        if (!empty($program['program_simple_type'])) {
            $t .= ', ' . $program['program_simple_type'];
        }
        return $t;
    }

    /** Section menu entries (label => href): the listing pages, then the program's college and departments. */
    public function sectionNav(?array $program = null): array
    {
        $base  = $this->layout->url('');
        $items = [
            'All Programs'              => Listings::url($base, 'all'),
            'Programs By College'       => Listings::url($base, 'all', 'college'),
            'Undergrad Majors & Minors' => Listings::url($base, 'undergrad'),
            'Graduate Degrees'          => Listings::url($base, 'graduate'),
            'Online'                    => Listings::url($base, 'online'),
            'Certificates'              => Listings::url($base, 'certificates'),
            'Badges'                    => Listings::BADGES_URL,
            'Degree Maps'               => $this->layout->url('degree_maps/maps.php'),
        ];
        $list = $this->layout->url('index.php');
        if (!empty($program['college'])) {
            $items['Degrees from ' . $program['college']] = $list . '?college=' . rawurlencode((string) $program['college']);
        }
        foreach ($program !== null ? ProgramRepository::departmentsOf($program) : [] as $d) {
            $items['Degrees from ' . $d['text']] = $list . '?department=' . rawurlencode($d['text']);
        }
        return $items;
    }

    /**
     * Group listing lines: A–Z by the first letter of what the line says, or by college.
     * @return list<array{key:string,label:string,items:list<array<string,mixed>>}>
     */
    public static function group(array $rows, string $order): array
    {
        $groups = [];
        foreach ($rows as $r) {
            if ($order === 'college') {
                $label = (string) ($r['college'] ?? '');
                $key   = rawurlencode(mb_strtolower($label));
            } else {
                $name  = (string) ($r['list_name'] ?? $r['academic_program'] ?? '');
                $label = mb_strtoupper(mb_substr(ltrim($name), 0, 1));
                $key   = $label;
            }
            $groups[$key] ??= ['key' => $key, 'label' => $label, 'items' => []];
            $groups[$key]['items'][] = $r;
        }
        return array_values($groups);
    }

    /**
     * The Certificates page layout: Graduate Certificates, then Undergraduate Certificates,
     * each grouped by topic (a certificate listed under two topics shows in both), topics in
     * the page's own order.
     *
     * @return list<array{key:string,label:string,topics:list<array{key:string,label:string,items:list<array<string,mixed>>}>}>
     */
    public static function groupCertificates(array $rows): array
    {
        $sections = [];
        foreach ($rows as $r) {
            $sec = $r['cert_section'] === 'undergraduate' || ($r['cert_section'] === null && empty($r['graduate'])) ? 'undergraduate' : 'graduate';
            $topics = array_values(array_filter(explode('|', (string) ($r['cert_topics'] ?? ''))));
            foreach ($topics !== [] ? $topics : ['Other Certificates'] as $topic) {
                $sections[$sec][$topic][] = $r;
            }
        }
        $rank = array_flip(Listings::CERT_TOPICS);
        $out  = [];
        foreach (Listings::CERT_SECTIONS as $sec => $label) {
            if (empty($sections[$sec])) {
                continue;
            }
            $topics = $sections[$sec];
            uksort($topics, static fn ($a, $b) => [($rank[$a] ?? 99), $a] <=> [($rank[$b] ?? 99), $b]);
            $list = [];
            foreach ($topics as $topic => $items) {
                usort($items, static fn ($a, $b) => strnatcasecmp((string) $a['list_name'], (string) $b['list_name']));
                $list[] = ['key' => $sec . '-' . rawurlencode(mb_strtolower($topic)), 'label' => $topic, 'items' => $items];
            }
            $out[] = ['key' => $sec, 'label' => $label, 'topics' => $list];
        }
        return $out;
    }

    /** "Graduate Degrees in College of X from Dept" style headline for the current filters. */
    public static function headline(string $filter, ?string $college, ?string $department, string $search): string
    {
        $h = $filter === 'minors' ? 'Minors' : (Listings::LISTS[$filter]['headline'] ?? Listings::LISTS['all']['headline']);
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
