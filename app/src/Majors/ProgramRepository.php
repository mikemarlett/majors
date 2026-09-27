<?php

declare(strict_types=1);

namespace Majors\Majors;

use mysqli;

/**
 * Read side of the Majors tables: majors_academic_programs (the catalog of
 * programs), majors_programs_content (marketing copy per program) and
 * majors_similar_programs. Port of the data functions in majors_functions.php.
 */
final class ProgramRepository
{
    public const FILTERS = ['all', 'undergrad', 'graduate', 'online', 'minors', 'certificates', 'badges'];

    public function __construct(private readonly mysqli $db)
    {
    }

    /** @return list<string> distinct college names used by programs */
    public function colleges(): array
    {
        $out = [];
        $res = $this->db->query('SELECT DISTINCT `college` FROM `majors_academic_programs` WHERE `status` = "active" AND `college` <> "" AND `college` IS NOT NULL ORDER BY `college`');
        while ($r = $res->fetch_assoc()) {
            $out[] = (string) $r['college'];
        }
        return $out;
    }

    /** @return list<string> every department active programs name, including their additional ones */
    public function departments(): array
    {
        $out = [];
        $res = $this->db->query('SELECT `department`, `more_departments` FROM `majors_academic_programs` WHERE `status` = "active"');
        while ($r = $res->fetch_assoc()) {
            foreach (self::departmentsOf($r) as $d) {
                $out[$d['text']] = true;
            }
        }
        $out = array_map('strval', array_keys($out));
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }

    /**
     * A program's departments in order: the main one, then the additional ones
     * (more_departments, JSON [{text, href}]). @return list<array{text:string,href:string}>
     */
    public static function departmentsOf(array $p): array
    {
        $out = [];
        if (trim((string) ($p['department'] ?? '')) !== '') {
            $out[] = ['text' => trim((string) $p['department']), 'href' => trim((string) ($p['department_url'] ?? ''))];
        }
        $more = json_decode((string) ($p['more_departments'] ?? ''), true);
        foreach (is_array($more) ? $more : [] as $d) {
            $t = trim((string) ($d['text'] ?? ''));
            if ($t !== '' && !in_array($t, array_column($out, 'text'), true)) {
                $out[] = ['text' => $t, 'href' => trim((string) ($d['href'] ?? ''))];
            }
        }
        return $out;
    }

    /** Every line of the All Programs list, A–Z (the listing page with no filters). @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->listing(['filter' => 'all', 'order' => 'alpha']);
    }

    /**
     * Listing lines for a filter, optionally narrowed by a search, a college or a department.
     *
     * @param array{filter?:string,college?:?string,department?:?string,order?:string} $f
     * @return list<array<string,mixed>>
     */
    public function search(string $text, array $f = []): array
    {
        return $this->listing(['search' => $text] + $f);
    }

    /**
     * The lines a listing page shows. Each row is the program joined with one of its listing
     * entries: list_name / list_detail are what the line says (the entry's own words, or the
     * program's name and degree), list_key is the list the view is for.
     *
     *   filter  all | undergrad | graduate | online | minors (Undergrad lines of minors) | certificates | badges
     *   order   alpha (A–Z view) | college (by-college view)
     *
     * @param array{filter?:string,order?:string,search?:string,college?:?string,department?:?string} $f
     * @return list<array<string,mixed>>
     */
    public function listing(array $f): array
    {
        $filter = in_array($f['filter'] ?? 'all', self::FILTERS, true) ? (string) ($f['filter'] ?? 'all') : 'all';
        $list   = $filter === 'minors' ? 'undergrad' : $filter;
        $order  = ($f['order'] ?? 'alpha') === 'college' ? 'college' : 'alpha';
        $sql = 'SELECT p.`id`, p.`basename`, p.`academic_program`, p.`sort_title`, p.`program_type`, p.`program_simple_type`, p.`credential`, p.`college`,
                       p.`department`, p.`department_url`, p.`more_departments`, p.`graduate`, p.`minor`, p.`certificate`, p.`badge`, p.`online_learning`,
                       p.`online_only`, p.`note`, p.`timestamp`,
                       e.`id` AS entry_id, e.`position` AS entry_position, e.`name` AS entry_name, e.`detail` AS entry_detail, e.`lists`, e.`shown_in`,
                       e.`cert_section`, e.`cert_topics`,
                       COALESCE(NULLIF(TRIM(e.`name`), ""), p.`academic_program`) AS list_name
                  FROM `majors_listing_entries` e
                  JOIN `majors_academic_programs` p ON p.`id` = e.`program_id`
                 WHERE p.`status` = "active" AND FIND_IN_SET(?, e.`lists`) AND e.`shown_in` IN ("both", ?)';
        $types  = 'ss';
        $params = [$list, $order === 'college' ? 'college' : 'az'];
        if ($filter === 'minors') {
            $sql .= ' AND (p.`credential` = "Minor" OR p.`minor` = 1)';
        }
        $text = trim((string) ($f['search'] ?? ''));
        if ($text !== '') {
            $like = '%' . $text . '%';
            $sql .= ' AND (COALESCE(NULLIF(TRIM(e.`name`), ""), p.`academic_program`) LIKE ? OR p.`academic_program` LIKE ? OR p.`sort_title` LIKE ?
                           OR p.`department` LIKE ? OR p.`more_departments` LIKE ? OR p.`note` LIKE ? OR p.`meta_keywords` LIKE ?)';
            $types .= 'sssssss';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }
        if (!empty($f['college']) && $f['college'] !== 'all') {
            $sql .= ' AND p.`college` = ?';
            $types .= 's';
            $params[] = (string) $f['college'];
        }
        if (!empty($f['department'])) {
            $sql .= ' AND (p.`department` = ? OR (CASE WHEN JSON_VALID(p.`more_departments`) THEN JSON_SEARCH(p.`more_departments`, "one", ?, NULL, "$[*].text") END) IS NOT NULL)';
            $types .= 'ss';
            $params[] = (string) $f['department'];
            $params[] = (string) $f['department'];
        }
        $sql .= $order === 'college'
            ? ' ORDER BY p.`college`, list_name, e.`detail`, p.`id`'
            : ' ORDER BY list_name, e.`detail`, p.`id`';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as &$r) {
            $r['list_key']    = $list;
            $r['list_detail'] = Listings::detailHtml(['detail' => $r['entry_detail']], $r);
        }
        unset($r);
        return $rows;
    }

    /** A program's listing entries in order. @return list<array<string,mixed>> */
    public function listingEntries(int $programId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM `majors_listing_entries` WHERE `program_id` = ? ORDER BY `position`, `id`');
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** @return array<string,mixed>|null program row with 'content', 'sections' and 'similar_programs' */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `majors_academic_programs` WHERE `id` = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $this->hydrate($row) : null;
    }

    /** The public key: the CMS page's basename (unique). @return array<string,mixed>|null */
    public function findByBasename(string $basename): ?array
    {
        if ($basename === '') {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM `majors_academic_programs` WHERE `basename` = ? LIMIT 1');
        $stmt->bind_param('s', $basename);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $this->hydrate($row) : null;
    }

    /** The program an earlier page name now belongs to (it forwards there), or null. @return array<string,mixed>|null */
    /**
     * Where a retired program forwards to: its forward_to target, following a chain of retired
     * programs that forward on (a merge of a merge), up to a few steps. Null when the chain does
     * not end at an active program.
     *
     * @param array<string,mixed> $program
     * @return array<string,mixed>|null
     */
    public function forwardTarget(array $program): ?array
    {
        $seen = [(int) ($program['id'] ?? 0) => true];
        $to   = (int) ($program['forward_to'] ?? 0);
        for ($hop = 0; $hop < 5 && $to > 0 && !isset($seen[$to]); $hop++) {
            $seen[$to] = true;
            $stmt = $this->db->prepare('SELECT `id`, `basename`, `academic_program`, `status`, `forward_to` FROM `majors_academic_programs` WHERE `id` = ?');
            $stmt->bind_param('i', $to);
            $stmt->execute();
            $next = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            if ($next === null) {
                return null;
            }
            if (($next['status'] ?? 'active') !== 'retired') {
                return $next;
            }
            $to = (int) ($next['forward_to'] ?? 0);
        }
        return null;
    }

    public function findByAlias(string $basename): ?array
    {
        if ($basename === '') {
            return null;
        }
        $stmt = $this->db->prepare('SELECT p.* FROM `majors_program_aliases` a JOIN `majors_academic_programs` p ON p.`id` = a.`program_id` WHERE a.`basename` = ? LIMIT 1');
        $stmt->bind_param('s', $basename);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private function hydrate(array $row): array
    {
        $id = (int) $row['id'];
        $row['content']          = $this->content($id) ?? [];
        $row['sections']         = $this->sections($id);
        $row['similar_programs'] = $this->similar($id);
        $row['similar_editing']  = $this->similarForEditing($id);
        return $row;
    }

    /**
     * The page body in order. A section that points at a shared block takes
     * its headline/body/links from the block (so editing the block changes
     * every page that uses it); 'shared' says which.
     *
     * @return list<array{id:int,kind:string,label:string,headline:string,body:string,links:list<array{text:string,href:string}>,image:?array{url:string,alt:string},block_id:?int,shared:bool}>
     */
    public function sections(int $programId): array
    {
        $stmt = $this->db->prepare('SELECT s.`id`, s.`kind`, s.`label`, s.`block_id`, s.`image_url`, s.`image_alt`, s.`theme`,
                                           COALESCE(s.`headline`, b.`headline`, "") AS headline, COALESCE(s.`body`, b.`body`, "") AS body, COALESCE(s.`links`, b.`links`, "[]") AS links
                                      FROM `majors_program_sections` s
                                 LEFT JOIN `majors_content_blocks` b ON b.`id` = s.`block_id`
                                     WHERE s.`program_id` = ? AND s.`kind` IN ("' . implode('", "', ProgramEditor::KINDS) . '") ORDER BY s.`position`, s.`id`');
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $out = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $links = json_decode((string) $r['links'], true);
            $out[] = [
                'id'       => (int) $r['id'],
                'kind'     => (string) $r['kind'],
                'label'    => (string) $r['label'],
                'headline' => (string) $r['headline'],
                'body'     => (string) $r['body'],
                'links'    => is_array($links) ? array_values(array_filter($links, static fn ($l) => is_array($l) && ($l['href'] ?? '') !== '')) : [],
                'image'    => (string) $r['image_url'] !== '' ? ['url' => (string) $r['image_url'], 'alt' => (string) $r['image_alt']] : null,
                'block_id' => $r['block_id'] !== null ? (int) $r['block_id'] : null,
                'shared'   => $r['block_id'] !== null,
                'theme'    => (string) ($r['theme'] ?? '') !== '' ? (string) $r['theme'] : ($r['kind'] === 'band' ? 'light' : null),
            ];
        }
        $stmt->close();
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function content(int $programId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `majors_programs_content` WHERE `academic_program_id` = ? LIMIT 1');
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Up to six related programs: the curated list first, then programs that
     * point back at this one, each resolved to id/name/type/image. Every row
     * says where it came from ('source' => curated | reverse).
     *
     * @return list<array<string,mixed>>
     */
    public function similar(int $programId, int $limit = 6): array
    {
        $ids  = [];
        $reverse = [];
        $stmt = $this->db->prepare('SELECT s.`similar_academic_program_id` FROM `majors_similar_programs` s
                                      JOIN `majors_academic_programs` p ON p.`id` = s.`similar_academic_program_id` AND p.`status` = "active"
                                     WHERE s.`main_academic_program_id` = ? ORDER BY s.`id` LIMIT ?');
        $stmt->bind_param('ii', $programId, $limit);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $ids[] = (int) $r['similar_academic_program_id'];
        }
        $stmt->close();

        if (count($ids) < $limit) {
            $remaining = $limit - count($ids);
            $sql = 'SELECT s.`main_academic_program_id` FROM `majors_similar_programs` s
                      JOIN `majors_academic_programs` p ON p.`id` = s.`main_academic_program_id` AND p.`status` = "active"
                     WHERE s.`similar_academic_program_id` = ?';
            $types = 'i';
            $params = [$programId];
            if ($ids !== []) {
                $sql .= ' AND s.`main_academic_program_id` NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $types .= str_repeat('i', count($ids));
                $params = array_merge($params, $ids);
            }
            $sql .= ' ORDER BY s.`id` LIMIT ?';
            $types .= 'i';
            $params[] = $remaining;
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                $ids[] = (int) $r['main_academic_program_id'];
                $reverse[(int) $r['main_academic_program_id']] = true;
            }
            $stmt->close();
        }
        if ($ids === []) {
            return [];
        }

        $ids  = array_values(array_unique($ids));
        $sql  = 'SELECT t1.`id`, t1.`basename`, t1.`academic_program`, t1.`program_type`, t1.`program_simple_type`, t1.`credential`,
                        COALESCE(NULLIF(t1.`image_url`, ""), t2.`main_image_url`) AS main_image_url
                   FROM `majors_academic_programs` t1
                   LEFT JOIN `majors_programs_content` t2 ON t1.`id` = t2.`academic_program_id`
                  WHERE t1.`status` = "active" AND t1.`id` IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        // Keep the curated order.
        $byId = array_column($rows, null, 'id');
        $out  = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id] + ['source' => isset($reverse[$id]) ? 'reverse' : 'curated'];
            }
        }
        return $out;
    }

    /**
     * What the editor shows in the Similar Programs band: the whole curated
     * list (retired ones too, flagged) followed by the reverse links the
     * public page would add, flagged so they get no remove button.
     *
     * @return list<array<string,mixed>>
     */
    public function similarForEditing(int $programId): array
    {
        $stmt = $this->db->prepare('SELECT t1.`id`, t1.`basename`, t1.`academic_program`, t1.`program_type`, t1.`program_simple_type`, t1.`credential`, t1.`status`,
                                           COALESCE(NULLIF(t1.`image_url`, ""), t2.`main_image_url`) AS main_image_url
                                      FROM `majors_similar_programs` s
                                      JOIN `majors_academic_programs` t1 ON t1.`id` = s.`similar_academic_program_id`
                                 LEFT JOIN `majors_programs_content` t2 ON t1.`id` = t2.`academic_program_id`
                                     WHERE s.`main_academic_program_id` = ? ORDER BY s.`id`');
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $out = [];
        $shown = 0;
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $retired = ($r['status'] ?? 'active') === 'retired';
            $hidden  = !$retired && ++$shown > 6;   // the public band shows the first six that render
            $out[] = $r + ['source' => 'curated', 'retired' => $retired, 'hidden' => $hidden];
        }
        $stmt->close();
        foreach ($this->similar($programId) as $r) {
            if ($r['source'] === 'reverse') {
                $out[] = $r + ['retired' => false, 'hidden' => false];
            }
        }
        return $out;
    }

    /**
     * Admin listing: every program with its content timestamp, section count
     * and the curated similar programs (id and name, for the control panel's
     * popover), active first.
     *
     * @return list<array<string,mixed>> each with 'similar' => list<array{id:int,name:string}>
     */
    public function adminList(): array
    {
        $sql = 'SELECT m.*,
                       (SELECT COUNT(*) FROM `majors_program_sections` x WHERE x.`program_id` = m.`id` AND x.`kind` IN ("' . implode('", "', ProgramEditor::KINDS) . '")) AS `section_count`,
                       (m.`description` IS NOT NULL AND m.`description` <> "") AS `has_description`,
                       (m.`image_url` IS NOT NULL AND m.`image_url` <> "") AS `has_image`,
                       (m.`image_url` IS NOT NULL AND m.`image_url` <> "" AND (m.`image_alt` IS NULL OR m.`image_alt` = "")) AS `photo_no_alt`,
                       EXISTS (SELECT 1 FROM `majors_program_sections` y WHERE y.`program_id` = m.`id` AND y.`image_url` <> "" AND (y.`image_alt` IS NULL OR y.`image_alt` = "")) AS `section_photo_no_alt`,
                       (m.`department` IS NULL OR m.`department` = "") AS `no_department`,
                       EXISTS (SELECT 1 FROM `majors_program_sections` z WHERE z.`program_id` = m.`id` AND z.`block_id` IS NULL AND CHAR_LENGTH(COALESCE(z.`headline`, "")) > 120) AS `long_headline`,
                       (SELECT f.`academic_program` FROM `majors_academic_programs` f WHERE f.`id` = m.`forward_to`) AS `forward_name`
                  FROM `majors_academic_programs` m
                 ORDER BY (m.`status` = "retired"), m.`academic_program`, m.`program_type`';
        $rows = $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
        $similar = [];
        $res = $this->db->query('SELECT s.`main_academic_program_id` AS pid, p.`id`, p.`academic_program` FROM `majors_similar_programs` s
                                   JOIN `majors_academic_programs` p ON p.`id` = s.`similar_academic_program_id`
                                  ORDER BY s.`main_academic_program_id`, s.`id`');
        while ($r = $res->fetch_assoc()) {
            $similar[(int) $r['pid']][] = ['id' => (int) $r['id'], 'name' => (string) $r['academic_program']];
        }
        $listing = [];
        $res = $this->db->query('SELECT `program_id`, `lists`, `name` FROM `majors_listing_entries` ORDER BY `program_id`, `position`');
        while ($r = $res->fetch_assoc()) {
            $pid = (int) $r['program_id'];
            $listing[$pid]['lines'] = ($listing[$pid]['lines'] ?? 0) + 1;
            foreach (Listings::listsOf($r) as $l) {
                $listing[$pid]['lists'][$l] = true;
            }
            if ((string) $r['name'] !== '') {
                $listing[$pid]['names'][] = (string) $r['name'];
            }
        }
        foreach ($rows as &$row) {
            $row['similar']       = $similar[(int) $row['id']] ?? [];
            $row['similar_count'] = count($row['similar']);
            $l = $listing[(int) $row['id']] ?? [];
            $row['listing_lines'] = (int) ($l['lines'] ?? 0);
            $row['listing_lists'] = array_values(array_filter(array_keys(Listings::LISTS), static fn ($k) => isset($l['lists'][$k])));
            $row['listing_names'] = array_values(array_unique($l['names'] ?? []));
        }
        unset($row);
        return $rows;
    }
}
