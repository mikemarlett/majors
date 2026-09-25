<?php

declare(strict_types=1);

namespace Majors\Majors;

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

    public function __construct(private readonly mysqli $db)
    {
    }

    // ---- programs --------------------------------------------------------------

    /** @param array<string,mixed> $d name + credential at least. @return int new id */
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
        $this->exec('INSERT INTO `majors_academic_programs` (`academic_program`, `credential`, `program_simple_type`, `program_type`, `basename`, `status`, `graduate`, `sort_order`, `timestamp`)
                     VALUES (?, ?, ?, ?, ?, "active", ?, 0, NOW())', 'sssssi',
            [$name, $cred, $cred, $type !== '' ? $type : $cred, $base, (int) (bool) ($d['graduate'] ?? preg_match("/Master|Doctor|Graduate|Postbacc/i", $cred))]);
        $id = (int) $this->db->insert_id;
        $this->syncFlat($id);
        return $id;
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
            $text = trim((string) ($l['text'] ?? $l['link_text'] ?? ''));
            $href = trim((string) ($l['href'] ?? $l['url'] ?? ''));
            if ($text !== '' && $href !== '') {
                $out[] = ['text' => mb_substr($text, 0, 200), 'href' => mb_substr($href, 0, 500)];
            }
        }
        return $out;
    }

    // ---- sections --------------------------------------------------------------

    /** @param array<string,mixed> $d kind, label, headline, body, links, image_url, image_alt, block_id */
    public function saveSection(int $programId, ?int $sectionId, array $d): int
    {
        $kind    = in_array($d['kind'] ?? '', self::KINDS, true) ? $d['kind'] : 'teaser';
        $blockId = !empty($d['block_id']) ? (int) $d['block_id'] : null;
        if ($blockId !== null && $this->scalar('SELECT `id` FROM `majors_content_blocks` WHERE `id` = ?', 'i', [$blockId]) === null) {
            throw new RuntimeException('That shared block no longer exists.');
        }
        $headline = $blockId ? null : mb_substr(trim((string) ($d['headline'] ?? '')), 0, 255);
        $body     = $blockId ? null : trim((string) ($d['body'] ?? ''));
        $links    = $blockId ? null : json_encode(self::links($d['links'] ?? []), JSON_UNESCAPED_SLASHES);
        if ($blockId === null && $headline === '' && $body === '') {
            throw new RuntimeException('A section needs a headline or some text, or a shared block.');
        }
        $label = mb_substr(trim((string) ($d['label'] ?? '')), 0, 100);
        $img   = mb_substr(trim((string) ($d['image_url'] ?? '')), 0, 255);
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
        $this->exec('UPDATE `majors_program_sections` s JOIN `majors_content_blocks` b ON b.`id` = s.`block_id`
                        SET s.`headline` = b.`headline`, s.`body` = b.`body`, s.`links` = b.`links`, s.`block_id` = NULL, s.`updated_at` = NOW()
                      WHERE s.`id` = ? AND s.`program_id` = ?', 'ii', [$sectionId, $programId]);
    }

    public function deleteSection(int $programId, int $sectionId): void
    {
        $this->exec('DELETE FROM `majors_program_sections` WHERE `id` = ? AND `program_id` = ?', 'ii', [$sectionId, $programId]);
        $this->syncFlat($programId);
    }

    /** @param list<int> $ids section ids in the new order */
    public function reorderSections(int $programId, array $ids): void
    {
        $pos = 0;
        foreach ($ids as $id) {
            $pos++;
            $this->exec('UPDATE `majors_program_sections` SET `position` = ? WHERE `id` = ? AND `program_id` = ?', 'iii', [$pos, (int) $id, $programId]);
        }
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
            $vals[] = mb_substr(trim((string) $fields[$col]), 0, $max);
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
                $vals[] = mb_substr(trim((string) $fields[$col]), 0, $max);
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
     * A new section right after $afterId (0 = at the end), with a visible
     * default so it can be clicked and edited in place. @return int new id
     */
    public function insertSectionAfter(int $programId, int $afterId, array $d): int
    {
        if (empty($d['headline']) && empty($d['body']) && empty($d['block_id'])) {
            $d['headline'] = ($d['kind'] ?? 'teaser') === 'feature' ? 'New feature' : 'New card';
            $d['body']     = '<p>Click to write the text.</p>';
        }
        if (($d['kind'] ?? 'teaser') === 'feature' && empty($d['label'])) {
            $d['label'] = 'Inside the Program';
        }
        $id  = $this->saveSection($programId, null, $d);
        $ids = $this->sectionIds($programId);
        if ($afterId > 0 && in_array($afterId, $ids, true)) {
            $ids = array_values(array_diff($ids, [$id]));
            array_splice($ids, array_search($afterId, $ids, true) + 1, 0, [$id]);
            $this->reorderSections($programId, $ids);
        }
        return $id;
    }

    /** Swap a section with its neighbour ('up' | 'down'). */
    public function moveSection(int $programId, int $sectionId, string $dir): void
    {
        $this->section($programId, $sectionId);
        $ids = $this->sectionIds($programId);
        $i   = array_search($sectionId, $ids, true);
        $j   = $dir === 'up' ? $i - 1 : $i + 1;
        if ($i === false || $j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $this->reorderSections($programId, $ids);
    }

    /** Point a section at a shared block (its own text is dropped; kind, label and image stay). */
    public function swapSectionBlock(int $programId, int $sectionId, int $blockId): void
    {
        $this->section($programId, $sectionId);
        $this->block($blockId) ?? throw new RuntimeException('That shared block no longer exists.');
        $this->exec('UPDATE `majors_program_sections` SET `block_id` = ?, `headline` = NULL, `body` = NULL, `links` = NULL, `updated_at` = NOW() WHERE `id` = ? AND `program_id` = ?', 'iii', [$blockId, $sectionId, $programId]);
        $this->syncFlat($programId);
    }

    /** @return list<int> section ids in page order */
    public function sectionIds(int $programId): array
    {
        $stmt = $this->db->prepare('SELECT `id` FROM `majors_program_sections` WHERE `program_id` = ? ORDER BY `position`, `id`');
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
        foreach ($this->db->query('SELECT `block_id`, COUNT(*) AS n FROM `majors_program_sections` WHERE `block_id` IS NOT NULL GROUP BY `block_id`')->fetch_all(MYSQLI_ASSOC) as $r) {
            $out[(int) $r['block_id']] = (int) $r['n'];
        }
        return $out;
    }

    // ---- similar programs --------------------------------------------------------

    /** @param list<int> $ids */
    public function saveSimilar(int $programId, array $ids): void
    {
        $this->exec('DELETE FROM `majors_similar_programs` WHERE `main_academic_program_id` = ?', 'i', [$programId]);
        $done = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0 || $id === $programId || isset($done[$id])) {
                continue;
            }
            $done[$id] = true;
            $this->exec('INSERT INTO `majors_similar_programs` (`main_academic_program_id`, `similar_academic_program_id`, `timestamp`) VALUES (?, ?, NOW())', 'ii', [$programId, $id]);
        }
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
        return $this->db->query('SELECT b.*, (SELECT COUNT(*) FROM `majors_program_sections` s WHERE s.`block_id` = b.`id`) AS uses
                                   FROM `majors_content_blocks` b ORDER BY uses DESC, b.`headline`')->fetch_all(MYSQLI_ASSOC);
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
        $headline = mb_substr(trim((string) ($d['headline'] ?? '')), 0, 255);
        $body     = trim((string) ($d['body'] ?? ''));
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
        $sections = (new ProgramRepository($this->db))->sections($programId);
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
