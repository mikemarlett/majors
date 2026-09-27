<?php

declare(strict_types=1);

namespace Majors\Majors;

use mysqli;

/**
 * Writes parsed CMS program pages (CmsPageParser output) into the majors
 * tables:
 *
 *   majors_academic_programs   one row per program; ids are stable (matched by
 *                              basename, then by the catalog number in the slug
 *                              so a renamed page keeps its id and its degree maps)
 *   majors_program_sections    the page body, in order: teaser | feature | similar
 *   majors_content_blocks      text that many pages share verbatim ("Applied
 *                              learning at Wichita State", "Admission to the
 *                              program"…) stored once; sections point at it, so
 *                              changing the block changes every page
 *   majors_similar_programs    from each page's Similar Programs card
 *   majors_programs_content    the old flat row per program, kept in step for
 *                              anything still reading it (ai-meta.php on www)
 *
 * Programs whose page has disappeared are marked status='retired', never deleted.
 */
final class Importer
{
    public const SHARED_MIN_PAGES = 5;

    /** @var array<string,int> */
    private array $counts = ['created' => 0, 'updated' => 0, 'renamed' => 0, 'retired' => 0, 'sections' => 0, 'blocks' => 0, 'similar' => 0, 'similar_unresolved' => 0];
    /** @var list<string> */
    private array $notes = [];

    public function __construct(private readonly mysqli $db, private readonly bool $dry = false)
    {
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        return $this->counts;
    }

    /** @return list<string> */
    public function notes(): array
    {
        return $this->notes;
    }

    /**
     * @param list<array<string,mixed>> $pages parsed pages, each with extra keys cms_path, cms_file_date
     */
    public function run(array $pages, bool $retireMissing = true): void
    {
        if (!$this->dry) {
            $this->db->begin_transaction();
        }
        try {
            $this->import($pages, $retireMissing);
            if (!$this->dry) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if (!$this->dry) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    private function import(array $pages, bool $retireMissing): void
    {
        $existing = $this->existingPrograms();                       // basename => row, plus '#<catalog>' => row
        $aliases  = $this->aliasedPrograms();                         // earlier page name => row (renamed in the editor)
        $cmsNames = array_flip(array_column($pages, 'basename'));
        $blocks   = $this->sharedBlocks($pages);                      // key => ['slug','headline','body','links','count']
        $blockIds = $this->writeBlocks($blocks);
        $seenIds  = [];
        $idByBasename = [];

        foreach ($pages as $p) {
            $row = $existing[$p['basename']] ?? null;
            $how = 'updated';
            $keepName = null;
            // A page renamed in the editor (possible once the CMS import was switched off) is found by
            // its earlier name and keeps the new one, unless its new name is itself a CMS page.
            if ($row === null && isset($aliases[$p['basename']]) && !isset($cmsNames[(string) $aliases[$p['basename']]['basename']])) {
                $row = $aliases[$p['basename']];
                $keepName = (string) $row['basename'];
                $this->notes[] = "kept the editor's page name: {$p['basename']} is now {$keepName} (id {$row['id']})";
            }
            if ($row === null && $p['catalog_number'] !== null && isset($existing['#' . $p['catalog_number']])) {
                $row = $existing['#' . $p['catalog_number']];
                $how = 'renamed';
                $this->notes[] = "renamed: {$row['basename']} → {$p['basename']} (id {$row['id']})";
            }
            if ($row === null) {
                $how = 'created';
            }
            $id = $this->writeProgram($row, $p, $keepName);
            $seenIds[$id] = true;
            $idByBasename[$p['basename']] = $id;
            $this->counts[$how]++;
            $this->writeSections($id, $p, $blocks, $blockIds);
            $this->writeFlatContent($id, $p);
            if ($how === 'created' && !$this->dry && $this->hasListings()) {
                (new ProgramEditor($this->db))->addDefaultListing($id, 'seed');   // listed by default until the listing import says otherwise
            }
        }
        // Similar programs need every id first.
        foreach ($pages as $p) {
            $this->writeSimilar($idByBasename[$p['basename']], $p, $idByBasename);
        }
        if ($retireMissing) {
            foreach ($this->db->query('SELECT `id`, `basename`, `status`, `cms_path` FROM `majors_academic_programs`')->fetch_all(MYSQLI_ASSOC) as $row) {
                if (isset($seenIds[(int) $row['id']]) || ($row['status'] ?? 'active') === 'retired') {
                    continue;
                }
                $row['basename'] = (string) ($row['basename'] ?? '') !== '' ? $row['basename'] : '(no page)';
                if ((string) ($row['cms_path'] ?? '') === '') {       // made in the editor: it never had a CMS page to lose
                    $this->notes[] = "kept: {$row['basename']} (id {$row['id']}) — not from the CMS (made in the editor)";
                    continue;
                }
                $this->exec('UPDATE `majors_academic_programs` SET `status` = "retired", `imported_at` = NOW() WHERE `id` = ?', 'i', [(int) $row['id']]);
                $this->counts['retired']++;
                $this->notes[] = "retired: {$row['basename']} (id {$row['id']}) — no page in the CMS";
            }
        }
    }

    /**
     * Programs by an earlier page name (majors_program_aliases, migration 008), in the shape
     * existingPrograms() returns. @return array<string,array<string,mixed>>
     */
    private function aliasedPrograms(): array
    {
        if (!$this->hasListings()) {
            return [];
        }
        $out = [];
        foreach ($this->db->query('SELECT a.`basename` AS alias, p.`id`, p.`status`, p.`catalog_number`, p.`basename`
                                     FROM `majors_program_aliases` a JOIN `majors_academic_programs` p ON p.`id` = a.`program_id`')->fetch_all(MYSQLI_ASSOC) as $r) {
            $out[(string) $r['alias']] = $r;
        }
        return $out;
    }

    /** @return array<string,array<string,mixed>> */
    private function existingPrograms(): array
    {
        $out = [];
        $res = $this->db->query('SELECT p.`id`, p.`status`, p.`catalog_number`, COALESCE(NULLIF(p.`basename`, ""), c.`basename`) AS basename
                                   FROM `majors_academic_programs` p
                              LEFT JOIN `majors_programs_content` c ON c.`academic_program_id` = p.`id`');
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
            $b = (string) ($r['basename'] ?? '');
            if ($b !== '') {
                $out[$b] = $r;
            }
            $n = $r['catalog_number'] !== null ? (int) $r['catalog_number'] : (preg_match('/_(\d+)$/', $b, $m) ? (int) $m[1] : null);
            if ($n !== null && !isset($out['#' . $n])) {
                $out['#' . $n] = $r;
            }
        }
        return $out;
    }

    /** Teasers repeated verbatim on many pages become shared blocks. @return array<string,array<string,mixed>> */
    private function sharedBlocks(array $pages): array
    {
        $seen = [];
        foreach ($pages as $p) {
            foreach ($p['sections'] as $s) {
                if ($s['kind'] !== 'teaser') {
                    continue;
                }
                $key = self::sectionKey($s);
                $seen[$key] ??= ['headline' => $s['headline'], 'body' => $s['body'], 'links' => $s['links'], 'count' => 0];
                $seen[$key]['count']++;
            }
        }
        $blocks = [];
        $slugs  = [];
        foreach ($seen as $key => $b) {
            if ($b['count'] < self::SHARED_MIN_PAGES) {
                continue;
            }
            $base = self::slug($b['headline']);
            $slug = $base;
            for ($i = 2; isset($slugs[$slug]); $i++) {
                $slug = $base . '-' . $i;
            }
            $slugs[$slug] = true;
            $blocks[$key] = $b + ['slug' => $slug];
        }
        return $blocks;
    }

    public static function sectionKey(array $s): string
    {
        $norm = static fn (string $h): string => strtolower(trim(preg_replace('/\s+/', ' ', strip_tags($h)) ?? ''));
        return md5($norm($s['headline']) . '|' . $norm($s['body']) . '|' . json_encode($s['links']));
    }

    public static function slug(string $s): string
    {
        $s = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $s) ?? '', '-'));
        return $s !== '' ? substr($s, 0, 60) : 'block';
    }

    /** @return array<string,int> key => block id */
    private function writeBlocks(array $blocks): array
    {
        $ids = [];
        foreach ($blocks as $key => $b) {
            $b     = self::clip($b, ['headline' => 255]);
            $links = json_encode($b['links'], JSON_UNESCAPED_SLASHES);
            $note  = 'Imported from the CMS; used verbatim on ' . $b['count'] . ' pages';
            $id    = $this->scalar('SELECT `id` FROM `majors_content_blocks` WHERE `slug` = ?', 's', [$b['slug']]);
            if ($id !== null) {
                $this->exec('UPDATE `majors_content_blocks` SET `headline` = ?, `body` = ?, `links` = ?, `note` = ?, `updated_at` = NOW() WHERE `id` = ?', 'ssssi', [$b['headline'], $b['body'], $links, $note, (int) $id]);
                $ids[$key] = (int) $id;
            } else {
                $ids[$key] = $this->insert('INSERT INTO `majors_content_blocks` (`slug`,`headline`,`body`,`links`,`note`,`updated_at`) VALUES (?,?,?,?,?,NOW())', 'sssss', [$b['slug'], $b['headline'], $b['body'], $links, $note]);
            }
            $this->counts['blocks']++;
        }
        return $ids;
    }

    /** @param string|null $keepName the program's own page name, when it was renamed in the editor */
    private function writeProgram(?array $row, array $p, ?string $keepName = null): int
    {
        $crumbs   = $p['breadcrumb'];
        $college  = $crumbs[1]['text'] ?? '';
        $dept     = $crumbs[2]['text'] ?? '';
        $cred     = (string) $p['credential'];
        $kw       = $p['keyword_list'];
        $type     = self::programType($cred, $kw, $p['basename']);
        $graduate = (int) (in_array('Graduate', $kw, true) || preg_match("/Master|Doctor|\\bGraduate Certificate|Postbacc|Post Master|Specialist/i", $cred));
        $isCert   = (int) str_contains($cred, 'Certificate');
        $isMinor  = (int) ($cred === 'Minor');
        $isBadge  = (int) (stripos($cred, 'Badge') !== false);
        $online   = $row === null ? (int) (bool) preg_grep('/^online$/i', $kw) : null; // keep the existing flag on updates
        $img      = $p['image'] ?? [];
        $vals = [
            'academic_program'    => $p['name'],
            'program_type'        => $type,
            'program_simple_type' => $cred,
            'department'          => $dept,
            'college'             => $college,
            'graduate'            => $graduate,
            'certificate'         => $isCert,
            'minor'               => $isMinor,
            'badge'               => $isBadge,
            'status'              => 'active',
            'basename'            => $p['basename'],
            'catalog_number'      => $p['catalog_number'],
            'credential'          => $cred,
            'college_code'        => $p['college_code'],
            'description'         => $p['description'],
            'learn_how'           => $p['learn_how'],
            'buttons'             => json_encode($p['buttons'], JSON_UNESCAPED_SLASHES),
            'image_url'           => (string) ($img['url'] ?? ''),
            'image_alt'           => (string) ($img['alt'] ?? ''),
            'image_caption'       => (string) ($img['caption'] ?? ''),
            'image_credit'        => (string) ($img['credit'] ?? ''),
            'college_url'         => (string) ($crumbs[1]['href'] ?? ''),
            'department_url'      => (string) ($crumbs[2]['href'] ?? ''),
            'meta_description'    => $p['meta_description'],
            'meta_keywords'       => $p['meta_keywords'],
            'cms_path'            => (string) ($p['cms_path'] ?? ''),
            'cms_file_date'       => $p['cms_file_date'] ?? null,
        ];
        if ($this->hasListings()) {                      // migration 008 has run
            $vals['similar_bg_url'] = (string) ($p['similar_bg'] ?? '');
        }
        if ($online !== null) {
            $vals['online_learning'] = $online;
        }
        if ($keepName !== null) {
            $vals['basename'] = $keepName;
        }
        $vals = self::clip($vals, self::PROGRAM_WIDTHS);
        $this->syncCollege($college, $dept);
        if ($row !== null) {
            $set = implode(', ', array_map(static fn ($k) => "`$k` = ?", array_keys($vals))) . ', `imported_at` = NOW(), `timestamp` = NOW()';
            $this->exec("UPDATE `majors_academic_programs` SET $set WHERE `id` = ?", str_repeat('s', count($vals)) . 'i', [...array_values($vals), (int) $row['id']]);
            $old = (string) ($row['basename'] ?? '');
            if ($old !== '' && $old !== $vals['basename'] && $this->hasListings()) {
                // The CMS page was renamed (matched by its catalog number): the old address keeps forwarding.
                $this->exec('INSERT INTO `majors_program_aliases` (`basename`, `program_id`, `created_at`) VALUES (?, ?, NOW())
                             ON DUPLICATE KEY UPDATE `program_id` = VALUES(`program_id`)', 'si', [$old, (int) $row['id']]);
                $this->exec('DELETE FROM `majors_program_aliases` WHERE `basename` = ?', 's', [(string) $vals['basename']]);
            }
            return (int) $row['id'];
        }
        $vals['sort_order'] = 0;
        $cols = implode(',', array_map(static fn ($k) => "`$k`", array_keys($vals)));
        $qs   = implode(',', array_fill(0, count($vals), '?'));
        return $this->insert("INSERT INTO `majors_academic_programs` ($cols, `imported_at`, `timestamp`) VALUES ($qs, NOW(), NOW())", str_repeat('s', count($vals)), array_values($vals));
    }

    public static function programType(string $credential, array $keywords, string $basename): string
    {
        $k1 = $keywords[1] ?? '';
        if (preg_match('/^(?:[A-Z][A-Za-z]{1,6}|Minor|Certificate - (?:Under)?graduate)$/', $k1) && !in_array($k1, ['Major', 'Graduate', 'Undergraduate'], true)) {
            return $k1;
        }
        if (str_contains($credential, 'Certificate')) {
            return str_starts_with($credential, 'Under') ? 'Certificate - Undergraduate' : 'Certificate - Graduate';
        }
        if ($credential === 'Minor') {
            return 'Minor';
        }
        if (preg_match('/_([a-z]{2,5})(?:_\d+)?$/', $basename, $m) && !in_array($m[1], ['graduate', 'minor'], true)) {
            return strtoupper($m[1]);
        }
        return $credential;
    }

    private function syncCollege(string $college, string $dept): void
    {
        if ($college === '') {
            return;
        }
        $cid = $this->scalar('SELECT `id` FROM `majors_colleges` WHERE `name` = ?', 's', [$college]);
        if ($cid === null) {
            $cid = $this->insert('INSERT INTO `majors_colleges` (`name`) VALUES (?)', 's', [$college]);
            $this->notes[] = "new college: $college";
        }
        if ($dept !== '' && $this->scalar('SELECT `id` FROM `majors_departments` WHERE `department` = ? AND `college_id` = ?', 'si', [$dept, (int) $cid]) === null) {
            $this->insert('INSERT INTO `majors_departments` (`college_id`, `department`) VALUES (?, ?)', 'is', [(int) $cid, $dept]);
        }
    }

    private function writeSections(int $id, array $p, array $blocks, array $blockIds): void
    {
        $this->exec('DELETE FROM `majors_program_sections` WHERE `program_id` = ?', 'i', [$id]);
        $pos = 0;
        foreach ($p['sections'] as $s) {
            if (!in_array($s['kind'], ProgramEditor::KINDS, true) || ($s['kind'] === 'band' && !$this->hasListings())) {
                continue;                        // the Similar Programs card is data for majors_similar_programs, not a page section
            }
            $pos++;
            $key     = $s['kind'] === 'teaser' ? self::sectionKey($s) : '';
            $blockId = $key !== '' && isset($blockIds[$key]) ? $blockIds[$key] : null;
            $img     = $s['image'] ?? [];
            $s       = self::clip($s, ['headline' => 255, 'label' => 100]);
            if ($this->hasListings()) {
                $this->exec('INSERT INTO `majors_program_sections` (`program_id`,`position`,`kind`,`label`,`headline`,`body`,`links`,`image_url`,`image_alt`,`block_id`,`theme`,`updated_at`)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())', 'iisssssssis', [
                    $id, $pos, $s['kind'], (string) $s['label'],
                    $blockId ? null : $s['headline'],
                    $blockId ? null : $s['body'],
                    $blockId ? null : json_encode($s['links'], JSON_UNESCAPED_SLASHES),
                    mb_substr((string) ($img['url'] ?? ''), 0, 255), (string) ($img['alt'] ?? ''),
                    $blockId, $s['theme'] ?? null,
                ]);
                $this->counts['sections']++;
                continue;
            }
            $this->exec('INSERT INTO `majors_program_sections` (`program_id`,`position`,`kind`,`label`,`headline`,`body`,`links`,`image_url`,`image_alt`,`block_id`,`updated_at`)
                         VALUES (?,?,?,?,?,?,?,?,?,?,NOW())', 'iisssssssi', [
                $id, $pos, $s['kind'], (string) $s['label'],
                $blockId ? null : $s['headline'],
                $blockId ? null : $s['body'],
                $blockId ? null : json_encode($s['links'], JSON_UNESCAPED_SLASHES),
                mb_substr((string) ($img['url'] ?? ''), 0, 255), (string) ($img['alt'] ?? ''),
                $blockId,
            ]);
            $this->counts['sections']++;
        }
    }

    private function writeSimilar(int $id, array $p, array $idByBasename): void
    {
        $this->exec('DELETE FROM `majors_similar_programs` WHERE `main_academic_program_id` = ?', 'i', [$id]);
        $done = [];
        foreach ($p['sections'] as $s) {
            if ($s['kind'] !== 'similar') {
                continue;
            }
            foreach ($s['links'] as $l) {
                $b = preg_match('~/academics/majors/([A-Za-z0-9_-]+)\.php~', (string) $l['href'], $m) ? $m[1] : null;
                if ($b !== null && isset($idByBasename[$b]) && $idByBasename[$b] !== $id && !isset($done[$idByBasename[$b]])) {
                    $done[$idByBasename[$b]] = true;
                    $this->exec('INSERT INTO `majors_similar_programs` (`main_academic_program_id`,`similar_academic_program_id`,`timestamp`) VALUES (?,?,NOW())', 'ii', [$id, $idByBasename[$b]]);
                    $this->counts['similar']++;
                } else {
                    $this->counts['similar_unresolved']++;
                }
            }
        }
    }

    /** The legacy one-row-per-program layout, so ai-meta.php on www keeps working. */
    private function writeFlatContent(int $id, array $p): void
    {
        $find = static function (array $sections, array $heads, string $kind = 'teaser'): ?array {
            foreach ($sections as $s) {
                if ($s['kind'] === $kind && ($heads === [] || in_array($s['headline'], $heads, true))) {
                    return $s;
                }
            }
            return null;
        };
        $known = ['Curriculum', 'Admission to the program', 'How to enroll', 'Careers'];
        $cur   = $find($p['sections'], ['Curriculum']);
        $adm   = $find($p['sections'], ['Admission to the program', 'How to enroll']);
        $car   = $find($p['sections'], ['Careers']);
        $ins   = $find($p['sections'], [], 'feature');
        $wild  = null;
        foreach ($p['sections'] as $s) {
            if ($s['kind'] === 'teaser' && !in_array($s['headline'], $known, true)) {
                $wild = $s;
                break;
            }
        }
        $img   = $p['image'] ?? [];
        $link  = static fn (?array $s, int $i = 0): array => [$s['links'][$i]['text'] ?? '', $s['links'][$i]['href'] ?? ''];
        $vals = [
            'academic_program_title'       => $p['name'],
            'description'                  => $p['description'],
            'learn_how'                    => $p['learn_how'],
            'learn_how_links'              => json_encode(array_map(static fn ($b) => ['link_text' => $b['text'], 'href' => $b['href']], $p['buttons']), JSON_UNESCAPED_SLASHES),
            'main_image_url'               => (string) ($img['url'] ?? ''),
            'main_image_caption'           => (string) ($img['caption'] ?? ''),
            'main_image_credit'            => (string) ($img['credit'] ?? ''),
            'main_image_alt'               => (string) ($img['alt'] ?? ''),
            'curriculum_text'              => $cur['body'] ?? '',
            'curriculum_link_text'         => $link($cur)[0],
            'curriculum_link_url'          => $link($cur)[1],
            'admissions_headline'          => $adm['headline'] ?? '',
            'admissions_text'              => $adm['body'] ?? '',
            'admissions_link_text'         => $link($adm)[0],
            'admissions_link_url'          => $link($adm)[1],
            'inside_the_program_headline'  => $ins['headline'] ?? '',
            'inside_the_program_text'      => $ins['body'] ?? '',
            'inside_the_program_link_text' => $link($ins)[0],
            'inside_the_program_link_url'  => $link($ins)[1],
            'inside_the_program_image_url' => (string) ($ins['image']['url'] ?? ''),
            'inside_the_program_image_alt' => (string) ($ins['image']['alt'] ?? ''),
            'wildcard_headline'            => $wild['headline'] ?? '',
            'wildcard_text'                => $wild['body'] ?? '',
            'wildcard_link_text'           => $link($wild)[0],
            'wildcard_link_url'            => $link($wild)[1],
            'careers_headline'             => $car['headline'] ?? '',
            'careers_text'                 => $car['body'] ?? '',
            'careers_link_text'            => $link($car)[0],
            'careers_link_url'             => $link($car)[1],
            'academic_year'                => (int) date('Y') + ((int) date('n') >= 8 ? 1 : 0),
            'basename'                     => $p['basename'],
            'program_links'                => json_encode(array_map(static fn ($c) => ['link_text' => $c['text'], 'href' => $c['href']], $p['breadcrumb']), JSON_UNESCAPED_SLASHES),
            'meta_description'             => $p['meta_description'],
            'meta_keywords'                => $p['meta_keywords'],
        ];
        $vals     = self::clip($vals, self::FLAT_WIDTHS);
        $existing = $this->scalar('SELECT `id` FROM `majors_programs_content` WHERE `academic_program_id` = ?', 'i', [$id]);
        if ($existing !== null) {
            $set = implode(', ', array_map(static fn ($k) => "`$k` = ?", array_keys($vals))) . ', `timestamp` = NOW()';
            $this->exec("UPDATE `majors_programs_content` SET $set WHERE `id` = ?", str_repeat('s', count($vals)) . 'i', [...array_values($vals), (int) $existing]);
        } else {
            $cols = implode(',', array_map(static fn ($k) => "`$k`", array_keys($vals)));
            $qs   = implode(',', array_fill(0, count($vals), '?'));
            $this->insert("INSERT INTO `majors_programs_content` (`academic_program_id`, $cols, `timestamp`) VALUES (?, $qs, NOW())", 'i' . str_repeat('s', count($vals)), [$id, ...array_values($vals)]);
        }
    }

    /** Clip strings to the column widths of the tables (the CMS lets long text into small cells). */
    private static function clip(array $vals, array $widths): array
    {
        foreach ($widths as $col => $max) {
            if (isset($vals[$col]) && is_string($vals[$col]) && mb_strlen($vals[$col]) > $max) {
                $vals[$col] = mb_substr($vals[$col], 0, $max);
            }
        }
        return $vals;
    }

    private const PROGRAM_WIDTHS = ['academic_program' => 255, 'program_type' => 255, 'program_simple_type' => 255, 'department' => 255, 'college' => 255,
        'basename' => 200, 'credential' => 100, 'college_code' => 10, 'learn_how' => 255, 'image_url' => 255, 'image_credit' => 255, 'similar_bg_url' => 255,
        'college_url' => 255, 'department_url' => 255, 'cms_path' => 255];
    private const FLAT_WIDTHS = ['learn_how' => 200, 'learn_how_links' => 65000, 'main_image_url' => 200, 'main_image_credit' => 200,
        'curriculum_link_text' => 200, 'curriculum_link_url' => 200, 'admissions_headline' => 300, 'admissions_link_text' => 200, 'admissions_link_url' => 200,
        'inside_the_program_headline' => 300, 'inside_the_program_link_text' => 200, 'inside_the_program_link_url' => 200, 'inside_the_program_image_url' => 200,
        'wildcard_headline' => 300, 'wildcard_link_text' => 200, 'wildcard_link_url' => 200, 'careers_headline' => 300, 'careers_link_text' => 200, 'careers_link_url' => 200, 'basename' => 200];

    // ---- db helpers ------------------------------------------------------------
    private function exec(string $sql, string $types, array $params): void
    {
        if ($this->dry) {
            return;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    private function insert(string $sql, string $types, array $params): int
    {
        if ($this->dry) {
            return -1 - random_int(0, 1_000_000); // fake id, never persisted
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    private function scalar(string $sql, string $types, array $params): ?string
    {
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();
        return $row === null ? null : (string) $row[0];
    }

    /**
     * Add only what earlier imports did not capture, without touching anything else (so edits
     * made in the editor survive): full-width sections for pages that have none yet, and the
     * Similar Programs background photo where it is still empty. Returns SQL keyed by page name
     * when $sql is true (for a server where the import cannot run), else applies the change.
     *
     * @param list<array<string,mixed>> $pages parsed pages
     * @return array{bands:int,backgrounds:int,sql:string}
     */
    public function supplement(array $pages, bool $sql = false): array
    {
        $q = static fn (?string $v): string => $v === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "''"], $v) . "'";
        $bands = 0;
        $backgrounds = 0;
        $out = "-- Full-width sections and Similar Programs background photos from the CMS pages.\n"
             . "-- Adds only what is missing; nothing that exists is changed. Keyed by page name. Meant to run once,\n"
             . "-- right after 008: a photo cleared in the editor stays cleared, but a band removed since would come back.\n"
             . "SET NAMES utf8mb4;\nSTART TRANSACTION;\n";
        $existing = $this->existingPrograms();
        foreach ($pages as $p) {
            $row   = $existing[$p['basename']] ?? null;
            $pBand = array_values(array_filter($p['sections'], static fn ($s) => $s['kind'] === 'band'));
            $bg    = (string) ($p['similar_bg'] ?? '');
            if ($pBand === [] && $bg === '') {
                continue;
            }
            if ($sql) {
                $out .= "\n-- {$p['basename']}\nSET @p := (SELECT `id` FROM `majors_academic_programs` WHERE `basename` = " . $q($p['basename']) . ");\n";
                if ($bg !== '') {
                    $out .= 'UPDATE `majors_academic_programs` SET `similar_bg_url` = ' . $q(mb_substr($bg, 0, 255)) . " WHERE `id` = @p AND `similar_bg_url` IS NULL;\n";
                    $backgrounds++;
                }
                if ($pBand !== []) {
                    // Only beside the page's own sections: a program still on the old flat row would otherwise show just the band.
                    $out .= "SET @has := (SELECT COUNT(*) FROM `majors_program_sections` WHERE `program_id` = @p AND `kind` = 'band')"
                          . " + IF((SELECT COUNT(*) FROM `majors_program_sections` WHERE `program_id` = @p AND `kind` IN ('teaser', 'feature')) = 0, 1, 0);\n";
                    foreach ($pBand as $b) {
                        $out .= "SET @pos := (SELECT COALESCE(MAX(`position`), 0) + 1 FROM `majors_program_sections` WHERE `program_id` = @p);\n"
                              . "INSERT INTO `majors_program_sections` (`program_id`, `position`, `kind`, `label`, `headline`, `body`, `links`, `image_url`, `image_alt`, `theme`, `updated_at`) "
                              . "SELECT @p, @pos, 'band', '', " . $q(mb_substr((string) $b['headline'], 0, 255)) . ', ' . $q((string) $b['body']) . ", '[]', '', '', " . $q((string) $b['theme']) . ", NOW() FROM DUAL WHERE @p IS NOT NULL AND @has = 0;\n";
                        $bands++;
                    }
                }
                continue;
            }
            if ($row === null) {
                $this->notes[] = "supplement: no program for {$p['basename']}";
                continue;
            }
            $id = (int) $row['id'];
            if ($bg !== '' && $this->scalar('SELECT `id` FROM `majors_academic_programs` WHERE `id` = ? AND `similar_bg_url` IS NULL', 'i', [$id]) !== null) {
                $this->exec('UPDATE `majors_academic_programs` SET `similar_bg_url` = ? WHERE `id` = ?', 'si', [mb_substr($bg, 0, 255), $id]);
                $backgrounds++;
            }
            if ($pBand !== [] && (int) $this->scalar('SELECT COUNT(*) FROM `majors_program_sections` WHERE `program_id` = ? AND `kind` = "band"', 'i', [$id]) === 0
                && (int) $this->scalar('SELECT COUNT(*) FROM `majors_program_sections` WHERE `program_id` = ? AND `kind` IN ("teaser", "feature")', 'i', [$id]) > 0) {
                foreach ($pBand as $b) {
                    $pos = (int) $this->scalar('SELECT COALESCE(MAX(`position`), 0) + 1 FROM `majors_program_sections` WHERE `program_id` = ?', 'i', [$id]);
                    $this->exec('INSERT INTO `majors_program_sections` (`program_id`, `position`, `kind`, `label`, `headline`, `body`, `links`, `image_url`, `image_alt`, `theme`, `updated_at`)
                                 VALUES (?, ?, "band", "", ?, ?, "[]", "", "", ?, NOW())', 'iisss', [$id, $pos, mb_substr((string) $b['headline'], 0, 255), (string) $b['body'], (string) $b['theme']]);
                    $bands++;
                }
            }
        }
        return ['bands' => $bands, 'backgrounds' => $backgrounds, 'sql' => $out . "\nCOMMIT;\n"];
    }

    private ?bool $listingsTable = null;

    /** Whether migration 008 (listing entries) has run on this database. */
    private function hasListings(): bool
    {
        return $this->listingsTable ??= $this->db->query("SHOW TABLES LIKE 'majors_listing_entries'")->num_rows > 0;
    }
}
