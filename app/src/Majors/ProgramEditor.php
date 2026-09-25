<?php

declare(strict_types=1);

namespace Majors\Majors;

use Majors\Support\Html;
use mysqli;
use RuntimeException;

/**
 * Write side of the Majors tables for the marketing editor: the program row
 * (identity, Program Card, details, meta), its ordered sections, the shared
 * content blocks, and similar programs. Every write that changes what a page
 * shows also refreshes the legacy flat row (majors_programs_content) so
 * ai-meta.php on www keeps reading the right thing.
 */
final class ProgramEditor
{
    public const KINDS      = ['teaser', 'feature'];
    public const MODALITIES = ['', 'On Campus', 'Online', 'Hybrid'];

    /** Columns the form may write, with their max widths. */
    private const PROGRAM_FIELDS = [
        'academic_program' => 255, 'sort_title' => 255, 'credential' => 100, 'program_type' => 255, 'program_simple_type' => 255,
        'college' => 255, 'department' => 255, 'college_url' => 255, 'department_url' => 255, 'college_code' => 10,
        'description' => 16000000, 'learn_how' => 255, 'image_url' => 255, 'image_alt' => 65000, 'image_caption' => 65000, 'image_credit' => 255,
        'meta_description' => 65000, 'meta_keywords' => 65000, 'note' => 65000,
        'catalog_url' => 255, 'degree_title' => 120, 'credit_hours' => 40, 'modality' => 40, 'entry_terms' => 80,
        'coordinator_name' => 120, 'coordinator_email' => 120, 'coordinator_phone' => 40, 'basename' => 200,
    ];
    private const PROGRAM_FLAGS = ['graduate', 'certificate', 'minor', 'badge', 'online_learning', 'online_only', 'is_stem'];

    private bool $inTransaction = false;

    public function __construct(private readonly mysqli $db)
    {
    }

    // ---- programs --------------------------------------------------------------

    /**
     * @param array<string,mixed> $d name + credential at least; graduate is
     *        inferred from the credential when not given; college/department
     *        optional. @return int new id
     */
    public function create(array $d): int
    {
        $name = trim((string) ($d['academic_program'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('A program name is required.');
        }
        $cred = trim((string) ($d['credential'] ?? ''));
        $type = trim((string) ($d['program_type'] ?? ''));
        $base = self::cleanBasename((string) ($d['basename'] ?? ''));
        $base = $this->uniqueBasename($base !== '' ? $base : self::basenameFor($name, $type, $cred));
        $flags = self::flagsFor($cred);
        $grad  = array_key_exists('graduate', $d) && $d['graduate'] !== null && $d['graduate'] !== '' ? (int) (bool) $d['graduate'] : $flags['graduate'];
        $insert = function (string $basename) use ($name, $cred, $type, $d, $grad, $flags): void {
            $this->exec('INSERT INTO `majors_academic_programs` (`academic_program`, `credential`, `program_simple_type`, `program_type`, `basename`, `college`, `department`, `status`, `graduate`, `minor`, `certificate`, `badge`, `sort_order`, `timestamp`)
                         VALUES (?, ?, ?, ?, ?, ?, ?, "active", ?, ?, ?, ?, 0, NOW())', 'sssssssiiii',
                [$name, $cred, $cred, $type !== '' ? $type : $cred, $basename, mb_substr(trim((string) ($d['college'] ?? '')), 0, 255), mb_substr(trim((string) ($d['department'] ?? '')), 0, 255),
                 $grad, $flags['minor'], $flags['certificate'], $flags['badge']]);
        };
        try {
            $insert($base);
        } catch (\mysqli_sql_exception $e) {
            if ($e->getCode() !== 1062) {          // not "duplicate key": someone else took the name between the check and the insert
                throw $e;
            }
            $insert($this->uniqueBasename($base));
        }
        $id = (int) $this->db->insert_id;
        $this->syncFlat($id);
        return $id;
    }

    /**
     * The listing flags a credential implies (the public filters read the
     * flags, not the credential). @return array{graduate:int,minor:int,certificate:int,badge:int}
     */
    public static function flagsFor(string $credential): array
    {
        return [
            'graduate'    => (int) (bool) preg_match('/Master|Doctor|\bGraduate|Postbacc/i', $credential),   // \b: "Undergraduate Certificate" is not graduate
            'minor'       => (int) (strcasecmp(trim($credential), 'Minor') === 0),
            'certificate' => (int) (stripos($credential, 'Certificate') !== false),
            'badge'       => (int) (stripos($credential, 'Badge') !== false),
        ];
    }

    /**
     * Partial update: only the posted keys are written, so a popover that
     * posts one field leaves everything else alone.
     *
     * @param array<string,mixed> $d posted fields (only known ones are written)
     */
    public function saveProgram(int $id, array $d): void
    {
        if (array_key_exists('academic_program', $d) && trim((string) $d['academic_program']) === '') {
            throw new RuntimeException('The program name is required.');
        }
        // A changed credential re-derives the listing flags unless the form set them itself.
        if (array_key_exists('credential', $d)) {
            $current = (string) ($this->scalar('SELECT COALESCE(`credential`, "") FROM `majors_academic_programs` WHERE `id` = ?', 'i', [$id]) ?? '');
            if (trim((string) $d['credential']) !== $current) {
                foreach (self::flagsFor(trim((string) $d['credential'])) as $flag => $on) {
                    if (!array_key_exists($flag, $d)) {
                        $d[$flag] = $on;
                    }
                }
            }
        }
        $sets = [];
        $types = '';
        $vals = [];
        foreach (self::PROGRAM_FIELDS as $col => $max) {
            if (!array_key_exists($col, $d)) {
                continue;
            }
            $v = trim((string) $d[$col]);
            if ($col === 'basename') {
                $v = self::cleanBasename($v);
                if ($v === '') {
                    continue;
                }
                $cur = $this->db->query('SELECT `basename`, `cms_path` FROM `majors_academic_programs` WHERE `id` = ' . $id)->fetch_assoc() ?: [];
                $curBase = (string) ($cur['basename'] ?? '');
                if ($v === $curBase || $v === self::cleanBasename($curBase)) {   // unchanged (an imported name may hold "__", which cleaning collapses)
                    continue;
                }
                if ((string) ($cur['cms_path'] ?? '') !== '') {
                    throw new RuntimeException('This page name comes from the CMS page the program was imported from and is the key the importer matches on; it cannot be changed here.');
                }
                if ($this->scalar('SELECT `id` FROM `majors_academic_programs` WHERE `basename` = ? AND `id` <> ?', 'si', [$v, $id]) !== null) {
                    throw new RuntimeException("Another program already uses the page name \"$v\".");
                }
            }
            if ($col === 'modality' && !in_array($v, self::MODALITIES, true)) {
                throw new RuntimeException('Modality must be On Campus, Online or Hybrid.');
            }
            if ($col === 'coordinator_email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The coordinator email is not a valid address.');
            }
            if ($col === 'description') {
                $v = Html::clean($v);
            }
            if (in_array($col, ['college_url', 'department_url', 'catalog_url', 'image_url'], true)) {
                $safe = Html::safeUrl($v);
                if ($v !== '' && $safe === '') {
                    throw new RuntimeException(str_replace('_', ' ', $col) . ' must be a web address (https://…) or a path on the site (/academics/…).');
                }
                $v = $safe;
            }
            $sets[] = "`$col` = ?";
            $types .= 's';
            $vals[] = mb_substr($v, 0, $max);
        }
        foreach (self::PROGRAM_FLAGS as $flag) {
            if (array_key_exists($flag, $d)) {
                $sets[] = "`$flag` = ?";
                $types .= 'i';
                $vals[] = (int) (bool) $d[$flag];
            }
        }
        if (array_key_exists('status', $d)) {
            $sets[] = '`status` = ?';
            $types .= 's';
            $vals[] = $d['status'] === 'retired' ? 'retired' : 'active';
        }
        if (array_key_exists('buttons', $d)) {
            $sets[] = '`buttons` = ?';
            $types .= 's';
            $vals[] = json_encode(self::links($d['buttons']), JSON_UNESCAPED_SLASHES);
        }
        if ($sets === []) {
            return;
        }
        $sets[] = '`timestamp` = NOW()';
        $this->exec('UPDATE `majors_academic_programs` SET ' . implode(', ', $sets) . ' WHERE `id` = ?', $types . 'i', [...$vals, $id]);
        $this->syncFlat($id);
    }

    /**
     * The page name a new program gets: <name>_<type>, e.g. data_science_ms,
     * the way the CMS pages were named (they also carried a catalog number).
     * Apostrophes vanish (bachelors_to_masters), accents are transliterated.
     */
    public static function basenameFor(string $name, string $type = '', string $credential = ''): string
    {
        $tail = trim($type) !== '' ? $type : $credential;
        $base = self::cleanBasename($name . ' ' . $tail);
        return $base !== '' ? $base : 'program';
    }

    /** Lower-case ASCII letters, digits and single underscores; '' when nothing is left. */
    public static function cleanBasename(string $s): string
    {
        $s = str_replace(["'", "\u{2019}", "\u{2018}"], '', trim($s));
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($t) && $t !== '') {
                $s = $t;
            }
        }
        $s = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $s) ?? '');
        $s = trim(preg_replace('/_+/', '_', $s) ?? '', '_');
        return mb_substr($s, 0, 120);
    }

    /** $base, or $base_2, $base_3… until no other program has it. */
    public function uniqueBasename(string $base, ?int $exceptId = null): string
    {
        $base = self::cleanBasename($base) ?: 'program';
        $b    = $base;
        for ($i = 2; $this->scalar('SELECT `id` FROM `majors_academic_programs` WHERE `basename` = ? AND `id` <> ?', 'si', [$b, (int) $exceptId]) !== null; $i++) {
            $b = $base . '_' . $i;
        }
        return $b;
    }

    /** Normalise a posted list of links ([['text'=>..,'href'=>..], …] or parallel arrays). @return list<array{text:string,href:string}> */
    public static function links(mixed $raw): array
    {
        $out = [];
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }
        if (isset($raw['text']) && is_array($raw['text'])) {          // parallel arrays from the form
            foreach ($raw['text'] as $i => $text) {
                $raw[] = ['text' => $text, 'href' => $raw['href'][$i] ?? ''];
            }
            unset($raw['text'], $raw['href']);
        }
        foreach ($raw as $l) {
            if (!is_array($l)) {
                continue;
            }
            $text = trim(strip_tags((string) ($l['text'] ?? $l['link_text'] ?? '')));
            $href = Html::safeUrl((string) ($l['href'] ?? $l['url'] ?? ''));
            if ($text !== '' && $href !== '') {
                $out[] = ['text' => mb_substr($text, 0, 200), 'href' => mb_substr($href, 0, 500)];
            }
        }
        return $out;
    }

    // ---- sections --------------------------------------------------------------

    /**
     * @param array<string,mixed> $d kind, label, headline, body, links, image_url, image_alt, block_id
     * @param bool $allowEmpty the in-place editor adds a blank section and fills it on the page; the public page skips it until then
     */
    public function saveSection(int $programId, ?int $sectionId, array $d, bool $allowEmpty = false): int
    {
        $kind    = in_array($d['kind'] ?? '', self::KINDS, true) ? $d['kind'] : 'teaser';
        $blockId = !empty($d['block_id']) ? (int) $d['block_id'] : null;
        if ($blockId !== null && $this->scalar('SELECT `id` FROM `majors_content_blocks` WHERE `id` = ?', 'i', [$blockId]) === null) {
            throw new RuntimeException('That shared block no longer exists.');
        }
        $headline = $blockId ? null : mb_substr(trim(strip_tags((string) ($d['headline'] ?? ''))), 0, 255);
        $body     = $blockId ? null : Html::clean((string) ($d['body'] ?? ''));
        $links    = $blockId ? null : json_encode(self::links($d['links'] ?? []), JSON_UNESCAPED_SLASHES);
        if ($blockId === null && $headline === '' && $body === '' && !$allowEmpty) {
            throw new RuntimeException('A section needs a headline or some text, or a shared block.');
        }
        $label = mb_substr(trim((string) ($d['label'] ?? '')), 0, 100);
        $img   = mb_substr(Html::safeUrl((string) ($d['image_url'] ?? '')), 0, 255);
        $alt   = trim((string) ($d['image_alt'] ?? ''));
        if ($sectionId !== null) {
            $this->exec('UPDATE `majors_program_sections` SET `kind` = ?, `label` = ?, `headline` = ?, `body` = ?, `links` = ?, `image_url` = ?, `image_alt` = ?, `block_id` = ?, `updated_at` = NOW()
                          WHERE `id` = ? AND `program_id` = ?', 'sssssssiii', [$kind, $label, $headline, $body, $links, $img, $alt, $blockId, $sectionId, $programId]);
        } else {
            $pos = (int) ($this->scalar('SELECT COALESCE(MAX(`position`), 0) + 1 FROM `majors_program_sections` WHERE `program_id` = ?', 'i', [$programId]) ?? 1);
            $this->exec('INSERT INTO `majors_program_sections` (`program_id`, `position`, `kind`, `label`, `headline`, `body`, `links`, `image_url`, `image_alt`, `block_id`, `updated_at`)
                         VALUES (?,?,?,?,?,?,?,?,?,?,NOW())', 'iisssssssi', [$programId, $pos, $kind, $label, $headline, $body, $links, $img, $alt, $blockId]);
            $sectionId = (int) $this->db->insert_id;
        }
        $this->syncFlat($programId);
        return $sectionId;
    }

    /** Copy the shared block's content into the section so it can be customised for this program. */
    public function detachSection(int $programId, int $sectionId): void
    {
        $this->section($programId, $sectionId);
        $this->exec('UPDATE `majors_program_sections` s JOIN `majors_content_blocks` b ON b.`id` = s.`block_id`
                        SET s.`headline` = b.`headline`, s.`body` = b.`body`, s.`links` = b.`links`, s.`block_id` = NULL, s.`updated_at` = NOW()
                      WHERE s.`id` = ? AND s.`program_id` = ?', 'ii', [$sectionId, $programId]);
    }

    /**
     * Remove a section. Returns what was removed (its fields and the id of
     * the section before it, 0 = it was first) so the client can offer Undo
     * by re-creating it.
     *
     * @return array<string,mixed>
     */
    public function deleteSection(int $programId, int $sectionId): array
    {
        $row = $this->section($programId, $sectionId);
        $ids = $this->sectionIds($programId);
        $i   = array_search($sectionId, $ids, true);
        $this->exec('DELETE FROM `majors_program_sections` WHERE `id` = ? AND `program_id` = ?', 'ii', [$sectionId, $programId]);
        $this->syncFlat($programId);
        return [
            'kind' => (string) $row['kind'], 'label' => (string) $row['label'], 'headline' => (string) ($row['headline'] ?? ''), 'body' => (string) ($row['body'] ?? ''),
            'links' => (string) ($row['links'] ?? ''), 'image_url' => (string) ($row['image_url'] ?? ''), 'image_alt' => (string) ($row['image_alt'] ?? ''),
            'block_id' => $row['block_id'] !== null ? (int) $row['block_id'] : 0, 'after' => $i !== false && $i > 0 ? $ids[$i - 1] : -1,   // -1: it was the first section
        ];
    }

    /**
     * Renumber the sections in the given order. Ids that are not this
     * program's are ignored and any of its sections missing from the list keep
     * their relative order at the end, so a stale list can never lose a
     * section or leave duplicate positions.
     *
     * @param list<int> $ids section ids in the new order
     */
    public function reorderSections(int $programId, array $ids): void
    {
        $this->transaction(function () use ($programId, $ids): void {
            $current = $this->sectionIds($programId, true);
            $wanted  = array_values(array_filter(array_map('intval', $ids), static fn (int $id) => in_array($id, $current, true)));
            $order   = array_values(array_unique(array_merge($wanted, array_diff($current, $wanted))));
            $pos = 0;
            foreach ($order as $id) {
                $pos++;
                $this->exec('UPDATE `majors_program_sections` SET `position` = ? WHERE `id` = ? AND `program_id` = ?', 'iii', [$pos, $id, $programId]);
            }
        });
        $this->syncFlat($programId);
    }

    /**
     * The section row, or an error when it is not this program's. Used by the
     * in-place editor's per-field saves.
     */
    public function section(int $programId, int $sectionId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM `majors_program_sections` WHERE `id` = ? AND `program_id` = ?');
        $stmt->bind_param('ii', $sectionId, $programId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: throw new RuntimeException('That section is not on this page (it may have been removed).');
    }

    /**
     * Change only the given fields of a section that owns its text
     * (headline, body, label, links, image_url, image_alt). A shared section
     * refuses: edit the block, or detach first.
     *
     * @param array<string,mixed> $fields
     */
    public function updateSection(int $programId, int $sectionId, array $fields): void
    {
        $row = $this->section($programId, $sectionId);
        $sets = [];
        $types = '';
        $vals = [];
        foreach (['headline' => 255, 'label' => 100, 'image_url' => 255, 'image_alt' => 65000, 'body' => 16000000] as $col => $max) {
            if (!array_key_exists($col, $fields)) {
                continue;
            }
            if ($row['block_id'] !== null && in_array($col, ['headline', 'body'], true)) {
                throw new SharedSectionException('This text is shared with other pages. Edit the shared block, or customize this page first.');
            }
            $sets[] = "`$col` = ?";
            $types .= 's';
            $vals[] = mb_substr($col === 'body' ? Html::clean((string) $fields[$col]) : ($col === 'image_url' ? Html::safeUrl((string) $fields[$col]) : trim(strip_tags((string) $fields[$col]))), 0, $max);
        }
        if (array_key_exists('links', $fields)) {
            if ($row['block_id'] !== null) {
                throw new SharedSectionException('The links are part of the shared text. Edit the shared block, or customize this page first.');
            }
            $sets[] = '`links` = ?';
            $types .= 's';
            $vals[] = json_encode(self::links($fields['links']), JSON_UNESCAPED_SLASHES);
        }
        if ($sets === []) {
            return;
        }
        $sets[] = '`updated_at` = NOW()';
        $this->exec('UPDATE `majors_program_sections` SET ' . implode(', ', $sets) . ' WHERE `id` = ? AND `program_id` = ?', $types . 'ii', [...$vals, $sectionId, $programId]);
        $this->syncFlat($programId);
    }

    /** Change only the given fields of a shared block (headline, body, links); every page using it changes. */
    public function updateBlock(int $blockId, array $fields): void
    {
        $this->block($blockId) ?? throw new RuntimeException('That shared block no longer exists.');
        $sets = [];
        $types = '';
        $vals = [];
        foreach (['headline' => 255, 'body' => 16000000] as $col => $max) {
            if (array_key_exists($col, $fields)) {
                $sets[] = "`$col` = ?";
                $types .= 's';
                $vals[] = mb_substr($col === 'body' ? Html::clean((string) $fields[$col]) : trim(strip_tags((string) $fields[$col])), 0, $max);
            }
        }
        if (array_key_exists('links', $fields)) {
            $sets[] = '`links` = ?';
            $types .= 's';
            $vals[] = json_encode(self::links($fields['links']), JSON_UNESCAPED_SLASHES);
        }
        if ($sets === []) {
            return;
        }
        $sets[] = '`updated_at` = NOW()';
        $this->exec('UPDATE `majors_content_blocks` SET ' . implode(', ', $sets) . ' WHERE `id` = ?', $types . 'i', [...$vals, $blockId]);
        foreach ($this->blockUsers($blockId) as $p) {
            $this->syncFlat($p['id']);
        }
    }

    /**
     * A new section right after $afterId (0 = at the end, -1 = at the start).
     * It starts blank unless $d carries text (Undo of a removal does), shows
     * placeholders in the editor and stays off the public page until written.
     * @return int new id
     */
    public function insertSectionAfter(int $programId, int $afterId, array $d): int
    {
        if (($d['kind'] ?? 'teaser') === 'feature' && empty($d['label'])) {
            $d['label'] = 'Inside the Program';
        }
        return $this->transaction(function () use ($programId, $afterId, $d): int {
            $id  = $this->saveSection($programId, null, $d, true);
            $ids = $this->sectionIds($programId, true);
            if ($afterId < 0 || ($afterId > 0 && in_array($afterId, $ids, true))) {
                $ids = array_values(array_diff($ids, [$id]));
                array_splice($ids, $afterId < 0 ? 0 : array_search($afterId, $ids, true) + 1, 0, [$id]);
                $this->reorderSections($programId, $ids);
            }
            return $id;
        });
    }

    /**
     * A program imported before the sections model (a legacy flat row, no
     * section rows) gets its flat content materialised as sections the first
     * time the editor opens it, so what marketing sees is what the public
     * page shows. Returns how many sections were created (0 = nothing to do).
     */
    public function materializeFlat(int $programId): int
    {
        if ($this->sectionIds($programId) !== []) {
            return 0;
        }
        $content = (new ProgramRepository($this->db))->content($programId) ?? [];
        $flat    = $content !== [] ? ProgramRenderer::sectionsFromFlat($content) : [];
        if ($flat === []) {
            return 0;
        }
        return $this->transaction(function () use ($programId, $flat): int {
            $n = 0;
            foreach ($flat as $s) {
                $this->saveSection($programId, null, [
                    'kind' => $s['kind'], 'label' => $s['label'], 'headline' => $s['headline'], 'body' => $s['body'], 'links' => $s['links'],
                    'image_url' => (string) ($s['image']['url'] ?? ''), 'image_alt' => (string) ($s['image']['alt'] ?? ''),
                ], true);
                $n++;
            }
            return $n;
        });
    }

    /** Swap a section with its neighbour ('up' | 'down'). */
    public function moveSection(int $programId, int $sectionId, string $dir): void
    {
        $this->section($programId, $sectionId);
        $this->transaction(function () use ($programId, $sectionId, $dir): void {
            $ids = $this->sectionIds($programId, true);
            $i   = array_search($sectionId, $ids, true);
            $j   = $dir === 'up' ? $i - 1 : $i + 1;
            if ($i === false || $j < 0 || $j >= count($ids)) {
                return;
            }
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            $this->reorderSections($programId, $ids);
        });
    }

    /** Run $fn inside a transaction (nested calls join the outer one). @return mixed */
    private function transaction(callable $fn): mixed
    {
        if ($this->inTransaction) {
            return $fn();
        }
        $this->inTransaction = true;
        $this->db->begin_transaction();
        try {
            $out = $fn();
            $this->db->commit();
            return $out;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        } finally {
            $this->inTransaction = false;
        }
    }

    /** Point a section at a shared block (its own text is dropped; kind, label and image stay). */
    public function swapSectionBlock(int $programId, int $sectionId, int $blockId): void
    {
        $this->section($programId, $sectionId);
        $this->block($blockId) ?? throw new RuntimeException('That shared block no longer exists.');
        $this->exec('UPDATE `majors_program_sections` SET `block_id` = ?, `headline` = NULL, `body` = NULL, `links` = NULL, `updated_at` = NOW() WHERE `id` = ? AND `program_id` = ?', 'iii', [$blockId, $sectionId, $programId]);
        $this->syncFlat($programId);
    }

    /** @return list<int> section ids in page order (cards and features only; legacy kind=similar rows are ignored). $lock = FOR UPDATE inside a transaction. */
    public function sectionIds(int $programId, bool $lock = false): array
    {
        $stmt = $this->db->prepare('SELECT `id` FROM `majors_program_sections` WHERE `program_id` = ? AND `kind` IN ("teaser", "feature") ORDER BY `position`, `id`' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
        $stmt->close();
        return $ids;
    }

    /** @return array<int,int> block id => number of sections using it */
    public function blockUseCounts(): array
    {
        $out = [];
        foreach ($this->db->query('SELECT `block_id`, COUNT(DISTINCT `program_id`) AS n FROM `majors_program_sections` WHERE `block_id` IS NOT NULL GROUP BY `block_id`')->fetch_all(MYSQLI_ASSOC) as $r) {
            $out[(int) $r['block_id']] = (int) $r['n'];
        }
        return $out;
    }

    // ---- similar programs --------------------------------------------------------

    /**
     * Replace the curated similar-programs list. Only ids of programs that
     * exist are kept (in the posted order, duplicates and self dropped); the
     * delete + inserts run in one transaction so two editors cannot interleave.
     *
     * @param list<int> $ids
     */
    public function saveSimilar(int $programId, array $ids): void
    {
        $wanted = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && $id !== $programId && !in_array($id, $wanted, true)) {
                $wanted[] = $id;
            }
        }
        $exists = [];
        if ($wanted !== []) {
            $stmt = $this->db->prepare('SELECT `id` FROM `majors_academic_programs` WHERE `id` IN (' . implode(',', array_fill(0, count($wanted), '?')) . ')');
            $stmt->bind_param(str_repeat('i', count($wanted)), ...$wanted);
            $stmt->execute();
            $exists = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
            $stmt->close();
        }
        $this->transaction(function () use ($programId, $wanted, $exists): void {
            $this->exec('DELETE FROM `majors_similar_programs` WHERE `main_academic_program_id` = ?', 'i', [$programId]);
            foreach ($wanted as $id) {
                if (in_array($id, $exists, true)) {
                    $this->exec('INSERT IGNORE INTO `majors_similar_programs` (`main_academic_program_id`, `similar_academic_program_id`, `timestamp`) VALUES (?, ?, NOW())', 'ii', [$programId, $id]);
                }
            }
        });
        $this->syncFlat($programId);
    }

    /** @return list<array{id:int,text:string}> for the similar-programs picker */
    public function search(string $q, int $limit = 20): array
    {
        $like = '%' . $q . '%';
        $stmt = $this->db->prepare('SELECT `id`, `academic_program`, COALESCE(NULLIF(`credential`, ""), `program_type`) AS kind FROM `majors_academic_programs`
                                     WHERE `status` = "active" AND (`academic_program` LIKE ? OR `sort_title` LIKE ?) ORDER BY `academic_program` LIMIT ?');
        $stmt->bind_param('ssi', $like, $like, $limit);
        $stmt->execute();
        $out = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $out[] = ['id' => (int) $r['id'], 'text' => $r['academic_program'] . ' (' . $r['kind'] . ')'];
        }
        $stmt->close();
        return $out;
    }

    // ---- shared blocks -----------------------------------------------------------

    /** @return list<array<string,mixed>> blocks with a use count */
    public function blocks(): array
    {
        return $this->db->query('SELECT b.*, (SELECT COUNT(DISTINCT s.`program_id`) FROM `majors_program_sections` s WHERE s.`block_id` = b.`id`) AS uses
                                   FROM `majors_content_blocks` b ORDER BY uses DESC, b.`headline`')->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Blocks for the in-place picker: many share a headline ("Admission to the
     * program" ×17, one per college), so each carries the colleges that use it
     * and an excerpt of its text. @return list<array{id:int,headline:string,uses:int,colleges:string,excerpt:string}>
     */
    public function blocksForPicker(): array
    {
        $rows = $this->db->query('SELECT b.`id`, b.`headline`, b.`slug`, b.`body`, COUNT(DISTINCT s.`program_id`) AS uses,
                                         GROUP_CONCAT(DISTINCT p.`college` ORDER BY p.`college` SEPARATOR ", ") AS colleges
                                    FROM `majors_content_blocks` b
                               LEFT JOIN `majors_program_sections` s ON s.`block_id` = b.`id`
                               LEFT JOIN `majors_academic_programs` p ON p.`id` = s.`program_id`
                                GROUP BY b.`id` ORDER BY b.`headline`, uses DESC')->fetch_all(MYSQLI_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($r['body'] ?? ''))) ?? '');
            $out[] = [
                'id'       => (int) $r['id'],
                'headline' => (string) ($r['headline'] !== '' ? $r['headline'] : $r['slug']),
                'uses'     => (int) $r['uses'],
                'colleges' => (string) ($r['colleges'] ?? ''),
                'excerpt'  => mb_substr($text, 0, 110) . (mb_strlen($text) > 110 ? '…' : ''),
            ];
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function block(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `majors_content_blocks` WHERE `id` = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** @return list<array{id:int,academic_program:string,credential:string}> */
    public function blockUsers(int $blockId): array
    {
        $stmt = $this->db->prepare('SELECT DISTINCT p.`id`, p.`academic_program`, COALESCE(p.`credential`, "") AS credential FROM `majors_program_sections` s JOIN `majors_academic_programs` p ON p.`id` = s.`program_id`
                                     WHERE s.`block_id` = ? ORDER BY p.`academic_program`');
        $stmt->bind_param('i', $blockId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'academic_program' => (string) $r['academic_program'], 'credential' => (string) $r['credential']], $rows);
    }

    /** @param array<string,mixed> $d slug, headline, body, links, note. @return int id */
    public function saveBlock(?int $id, array $d): int
    {
        $headline = mb_substr(trim(strip_tags((string) ($d['headline'] ?? ''))), 0, 255);
        $body     = Html::clean((string) ($d['body'] ?? ''));
        if ($headline === '' && $body === '') {
            throw new RuntimeException('A block needs a headline or some text.');
        }
        $slug = Importer::slug((string) (($d['slug'] ?? '') !== '' ? $d['slug'] : $headline));
        if ($slug === '') {
            throw new RuntimeException('A block needs a name.');
        }
        if ($this->scalar('SELECT `id` FROM `majors_content_blocks` WHERE `slug` = ? AND `id` <> ?', 'si', [$slug, (int) $id]) !== null) {
            throw new RuntimeException("Another block is already called \"$slug\".");
        }
        $links = json_encode(self::links($d['links'] ?? []), JSON_UNESCAPED_SLASHES);
        $note  = mb_substr(trim((string) ($d['note'] ?? '')), 0, 255);
        if ($id !== null) {
            $this->exec('UPDATE `majors_content_blocks` SET `slug` = ?, `headline` = ?, `body` = ?, `links` = ?, `note` = ?, `updated_at` = NOW() WHERE `id` = ?', 'sssssi', [$slug, $headline, $body, $links, $note, $id]);
            // Every program using it now shows the new text: refresh their flat rows.
            foreach ($this->blockUsers($id) as $p) {
                $this->syncFlat($p['id']);
            }
            return $id;
        }
        $this->exec('INSERT INTO `majors_content_blocks` (`slug`, `headline`, `body`, `links`, `note`, `updated_at`) VALUES (?,?,?,?,?,NOW())', 'sssss', [$slug, $headline, $body, $links, $note]);
        return (int) $this->db->insert_id;
    }

    public function deleteBlock(int $id): void
    {
        $uses = (int) ($this->scalar('SELECT COUNT(*) FROM `majors_program_sections` WHERE `block_id` = ?', 'i', [$id]) ?? 0);
        if ($uses > 0) {
            throw new RuntimeException("This block is used on $uses page(s). Detach those sections first, or edit the block instead.");
        }
        $this->exec('DELETE FROM `majors_content_blocks` WHERE `id` = ?', 'i', [$id]);
    }

    // ---- legacy flat row (ai-meta.php on www) ----------------------------------------

    public function syncFlat(int $programId): void
    {
        $p = $this->db->query('SELECT * FROM `majors_academic_programs` WHERE `id` = ' . $programId)->fetch_assoc();
        if (!$p) {
            return;
        }
        $stored   = (new ProgramRepository($this->db))->sections($programId);
        $sections = array_values(array_filter($stored, static fn ($s) => $s['shared'] || trim($s['headline']) !== '' || trim(strip_tags($s['body'])) !== ''));   // unfinished sections are not on the public page
        $keepFlat = $stored === [];   // pre-import program: its legacy section columns are still the page; leave them alone
        $find = static function (array $heads, string $kind = 'teaser') use ($sections): ?array {
            foreach ($sections as $s) {
                if ($s['kind'] === $kind && ($heads === [] || in_array($s['headline'], $heads, true))) {
                    return $s;
                }
            }
            return null;
        };
        $known = ['Curriculum', 'Admission to the program', 'How to enroll', 'Careers'];
        $cur = $find(['Curriculum']);
        $adm = $find(['Admission to the program', 'How to enroll']);
        $car = $find(['Careers']);
        $ins = $find([], 'feature');
        $wild = null;
        foreach ($sections as $s) {
            if ($s['kind'] === 'teaser' && !in_array($s['headline'], $known, true)) {
                $wild = $s;
                break;
            }
        }
        $link = static fn (?array $s): array => [(string) ($s['links'][0]['text'] ?? ''), (string) ($s['links'][0]['href'] ?? '')];
        $buttons = json_decode((string) ($p['buttons'] ?? '[]'), true) ?: [];
        $similar = array_map('intval', array_column($this->db->query('SELECT `similar_academic_program_id` FROM `majors_similar_programs` WHERE `main_academic_program_id` = ' . $programId)->fetch_all(MYSQLI_ASSOC), 'similar_academic_program_id'));
        $vals = [
            'academic_program_title' => (string) $p['academic_program'], 'description' => (string) ($p['description'] ?? ''), 'learn_how' => mb_substr((string) ($p['learn_how'] ?? ''), 0, 200),
            'learn_how_links' => json_encode(array_map(static fn ($b) => ['link_text' => $b['text'] ?? '', 'href' => $b['href'] ?? ''], $buttons), JSON_UNESCAPED_SLASHES),
            'main_image_url' => mb_substr((string) ($p['image_url'] ?? ''), 0, 200), 'main_image_caption' => (string) ($p['image_caption'] ?? ''), 'main_image_credit' => mb_substr((string) ($p['image_credit'] ?? ''), 0, 200), 'main_image_alt' => (string) ($p['image_alt'] ?? ''),
            'curriculum_text' => $cur['body'] ?? '', 'curriculum_link_text' => mb_substr($link($cur)[0], 0, 200), 'curriculum_link_url' => mb_substr($link($cur)[1], 0, 200),
            'admissions_headline' => mb_substr($adm['headline'] ?? '', 0, 300), 'admissions_text' => $adm['body'] ?? '', 'admissions_link_text' => mb_substr($link($adm)[0], 0, 200), 'admissions_link_url' => mb_substr($link($adm)[1], 0, 200),
            'inside_the_program_headline' => mb_substr($ins['headline'] ?? '', 0, 300), 'inside_the_program_text' => $ins['body'] ?? '', 'inside_the_program_link_text' => mb_substr($link($ins)[0], 0, 200), 'inside_the_program_link_url' => mb_substr($link($ins)[1], 0, 200),
            'inside_the_program_image_url' => mb_substr((string) ($ins['image']['url'] ?? ''), 0, 200), 'inside_the_program_image_alt' => (string) ($ins['image']['alt'] ?? ''),
            'wildcard_headline' => mb_substr($wild['headline'] ?? '', 0, 300), 'wildcard_text' => $wild['body'] ?? '', 'wildcard_link_text' => mb_substr($link($wild)[0], 0, 200), 'wildcard_link_url' => mb_substr($link($wild)[1], 0, 200),
            'careers_headline' => mb_substr($car['headline'] ?? '', 0, 300), 'careers_text' => $car['body'] ?? '', 'careers_link_text' => mb_substr($link($car)[0], 0, 200), 'careers_link_url' => mb_substr($link($car)[1], 0, 200),
            'academic_year' => (int) date('Y') + ((int) date('n') >= 8 ? 1 : 0), 'basename' => (string) ($p['basename'] ?? ''),
            'program_links' => json_encode(array_values(array_filter([
                ['link_text' => 'All Programs', 'href' => '/academics/majors/index.php'],
                !empty($p['college']) ? ['link_text' => (string) $p['college'], 'href' => (string) ($p['college_url'] ?? '')] : null,
                !empty($p['department']) ? ['link_text' => (string) $p['department'], 'href' => (string) ($p['department_url'] ?? '')] : null,
            ])), JSON_UNESCAPED_SLASHES),
            'similar_programs' => json_encode($similar), 'meta_description' => (string) ($p['meta_description'] ?? ''), 'meta_keywords' => (string) ($p['meta_keywords'] ?? ''),
        ];
        if ($keepFlat) {
            foreach (array_keys($vals) as $k) {
                if (preg_match('/^(curriculum|admissions|inside_the_program|wildcard|careers)_/', $k)) {
                    unset($vals[$k]);
                }
            }
        }
        $existing = $this->scalar('SELECT `id` FROM `majors_programs_content` WHERE `academic_program_id` = ?', 'i', [$programId]);
        if ($existing !== null) {
            $set = implode(', ', array_map(static fn ($k) => "`$k` = ?", array_keys($vals))) . ', `timestamp` = NOW()';
            $this->exec("UPDATE `majors_programs_content` SET $set WHERE `id` = ?", str_repeat('s', count($vals)) . 'i', [...array_values($vals), (int) $existing]);
        } else {
            $cols = implode(',', array_map(static fn ($k) => "`$k`", array_keys($vals)));
            $qs   = implode(',', array_fill(0, count($vals), '?'));
            $this->exec("INSERT INTO `majors_programs_content` (`academic_program_id`, $cols, `timestamp`) VALUES (?, $qs, NOW())", 'i' . str_repeat('s', count($vals)), [$programId, ...array_values($vals)]);
        }
    }

    // ---- helpers ------------------------------------------------------------------
    private function exec(string $sql, string $types, array $params): void
    {
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
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
}
