<?php

declare(strict_types=1);

namespace Majors\Majors;

use Majors\Support\Html;
use mysqli;

/**
 * Brings the hand-kept CMS listing pages into majors_listing_entries.
 *
 * The live listings are CMS pages in /academics/majors (index, majors, graduate, online,
 * certificates and the *_by_college twins). Each includes a shared asset that holds the
 * list as plain HTML, one <li><a href="{{f:N}}">Name</a> — Detail</li> per line. Those
 * lists carry years of hand curation: names that differ from the page (the DNP pages are
 * listed as "Nursing Practice - …"), A–Z-only cross references ("Public Health Practice,
 * Advanced"), several lines for one page (Counseling's four concentrations), pages left
 * off on purpose, and a Certificates page grouped by level and topic.
 *
 * plan() turns the parsed lists into entries per page:
 *   - a line in both the A–Z and the by-college list of a listing is one entry shown in both;
 *     a line only in one of them is shown only there (az | college);
 *   - lines with the same name and detail on several listings become one entry on all of them;
 *   - a Certificates line joins the matching entry, or becomes an entry of its own.
 * apply() / sql() then replace the entries of every page the import owns: pages on a CMS
 * list, and imported pages (cms_path) that are on none (they become unlisted). Pages whose
 * listings were changed in the editor (source = editor) are left alone, as are programs
 * created in the editor that are on no CMS list.
 */
final class ListingImporter
{
    /** list key => [A–Z page, by-college page] (basenames in /academics/majors) */
    public const PAGES = [
        'all'          => ['index', 'index_by_college'],
        'undergrad'    => ['majors', 'majors_by_college'],
        'graduate'     => ['graduate', 'graduate_by_college'],
        'online'       => ['online', 'online_by_college'],
        'certificates' => ['certificates', null],
    ];

    /** @var list<string> */
    public array $notes = [];

    /**
     * Lines of one A–Z or by-college list asset, in page order.
     *
     * @return list<array{href:string,name:string,detail:string,group:string}>
     */
    public static function parseList(string $html): array
    {
        $html  = (string) preg_replace('/<!--.*?-->/s', '', $html);
        $start = strpos($html, 'alpha-list');
        $html  = $start === false ? $html : substr($html, $start);
        $out   = [];
        $group = '';
        preg_match_all('~<h2[^>]*>(.*?)</h2>|<li>\s*<a[^>]*href="([^"]+)"[^>]*>(.*?)</a>(.*?)</li>~s', $html, $m, PREG_SET_ORDER);
        foreach ($m as $x) {
            if (($x[2] ?? '') === '') {
                $group = self::text($x[1]);
                continue;
            }
            $out[] = ['href' => trim($x[2]), 'name' => self::text($x[3]), 'detail' => self::detail($x[4]), 'group' => $group];
        }
        return $out;
    }

    /**
     * Lines of the Certificates asset: two halves (graduate / undergraduate), each grouped by topic.
     *
     * @return list<array{href:string,name:string,detail:string,section:string,topic:string}>
     */
    public static function parseCertificates(string $html): array
    {
        $html = (string) preg_replace('/<!--.*?-->/s', '', $html);
        $out  = [];
        preg_match_all('~<div id="(graduate|undergraduate)"|<h3[^>]*>(.*?)</h3>|<li>\s*<a[^>]*href="([^"]+)"[^>]*>(.*?)</a>(.*?)</li>~s', $html, $m, PREG_SET_ORDER);
        $section = '';
        $topic   = '';
        foreach ($m as $x) {
            if (($x[1] ?? '') !== '') {
                $section = $x[1];
                $topic   = '';
            } elseif (($x[3] ?? '') !== '') {
                if ($section !== '') {
                    $out[] = ['href' => trim($x[3]), 'name' => self::text($x[4]), 'detail' => self::detail($x[5]), 'section' => $section, 'topic' => $topic];
                }
            } elseif (($x[2] ?? '') !== '') {
                $topic = self::text($x[2]);
            }
        }
        return $out;
    }

    /**
     * The intro callout above each half of the Certificates page ("What's a certificate?").
     * @return array<string,array{headline:string,body:string}> graduate|undergraduate => intro
     */
    public static function parseCertificateIntros(string $html): array
    {
        $html  = (string) preg_replace('/<!--.*?-->/s', '', $html);
        $grad  = strpos($html, '<div id="graduate"');
        $under = strpos($html, '<div id="undergraduate"');
        $out   = [];
        preg_match_all('~<table[^>]*ou-snippet-universal-callout[^>]*>(.*?)</table>~s', $html, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($m as $x) {
            $at  = $x[0][1];
            $sec = ($grad !== false && $at < $grad) ? 'graduate' : (($under !== false && $at < $under) ? 'undergraduate' : null);
            if ($sec === null || isset($out[$sec])) {
                continue;
            }
            $rows = [];
            preg_match_all('~<tr>\s*<th>(.*?)</th>\s*<td[^>]*>(.*?)</td>\s*</tr>~s', $x[1][0], $r, PREG_SET_ORDER);
            foreach ($r as $row) {
                $rows[strtolower(trim(strip_tags($row[1])))] = $row[2];
            }
            $body = trim((string) preg_replace('/\s+/u', ' ', Html::clean($rows['content'] ?? '')));
            $body = (string) preg_replace('~\s*(</?(?:p|ul|ol|li)>)\s*~', '$1', $body);
            if ($body !== '') {
                $out[$sec] = ['headline' => self::text($rows['header'] ?? ''), 'body' => $body];
            }
        }
        return $out;
    }

    /**
     * Entries per page.
     *
     * @param array<string,array{az:list<array>,college:list<array>}> $lists list key => lines, each line with 'basename' set
     * @param list<array> $certs Certificates lines with 'basename' set
     * @param array<string,string> $pageNames basename => the program's own name (a line with that name stores NULL: "same as the page")
     * @return array<string,list<array{name:?string,detail:string,lists:list<string>,shown_in:string,cert_section:?string,cert_topics:?string}>>
     */
    public static function plan(array $lists, array $certs, array $pageNames): array
    {
        $entries = [];   // basename => key => entry
        $order   = [];   // basename => key => first-seen rank (All A–Z first)
        $rank    = 0;
        $add = static function (string $b, string $name, string $detail, string $list, string $shown) use (&$entries, &$order, &$rank): string {
            $key = self::norm($name) . "\x1f" . self::norm($detail) . "\x1f" . $shown;
            if (!isset($entries[$b][$key])) {
                $entries[$b][$key] = ['name' => $name, 'detail' => $detail, 'lists' => [], 'shown_in' => $shown, 'cert_section' => null, 'cert_topics' => null];
                $order[$b][$key]   = $rank++;
            }
            if (!in_array($list, $entries[$b][$key]['lists'], true)) {
                $entries[$b][$key]['lists'][] = $list;
            }
            return $key;
        };
        foreach (['all', 'undergrad', 'graduate', 'online'] as $list) {
            $az      = $lists[$list]['az'] ?? [];
            $college = $lists[$list]['college'] ?? [];
            $inCollege = [];
            foreach ($college as $l) {
                $inCollege[$l['basename'] . "\x1f" . self::norm($l['name']) . "\x1f" . self::norm($l['detail'])] = true;
            }
            $inAz = [];
            foreach ($az as $l) {
                $k = $l['basename'] . "\x1f" . self::norm($l['name']) . "\x1f" . self::norm($l['detail']);
                $inAz[$k] = true;
                $add($l['basename'], $l['name'], $l['detail'], $list, isset($inCollege[$k]) || $college === [] ? 'both' : 'az');
            }
            foreach ($college as $l) {
                $k = $l['basename'] . "\x1f" . self::norm($l['name']) . "\x1f" . self::norm($l['detail']);
                if (!isset($inAz[$k])) {
                    $add($l['basename'], $l['name'], $l['detail'], $list, 'college');
                }
            }
        }
        foreach ($certs as $l) {
            $b   = $l['basename'];
            $hit = null;
            foreach ($entries[$b] ?? [] as $key => $e) {
                if ($e['shown_in'] !== 'college' && self::norm($e['name']) === self::norm($l['name']) && self::norm($e['detail']) === self::norm($l['detail'])) {
                    $hit = $key;
                    break;
                }
            }
            $key = $hit ?? $add($b, $l['name'], $l['detail'], 'certificates', 'both');
            if (!in_array('certificates', $entries[$b][$key]['lists'], true)) {
                $entries[$b][$key]['lists'][] = 'certificates';
            }
            $entries[$b][$key]['cert_section'] = $l['section'] !== '' ? $l['section'] : $entries[$b][$key]['cert_section'];
            if ($l['topic'] !== '') {        // a certificate can sit under two topics (Kodaly Method: Education and Arts and Humanities)
                $topics = array_filter(explode('|', (string) $entries[$b][$key]['cert_topics']));
                if (!in_array($l['topic'], $topics, true)) {
                    $topics[] = $l['topic'];
                }
                $entries[$b][$key]['cert_topics'] = implode('|', $topics);
            }
        }
        $out = [];
        foreach ($entries as $b => $byKey) {
            uksort($byKey, static fn ($x, $y) => $order[$b][$x] <=> $order[$b][$y]);
            $list = [];
            foreach ($byKey as $e) {
                $own = $pageNames[$b] ?? null;
                if ($own !== null && self::norm($e['name']) === self::norm($own)) {
                    $e['name'] = null;   // same as the page: follows future renames of the program
                }
                $list[] = $e;
            }
            $out[$b] = $list;
        }
        return $out;
    }

    /**
     * Replace the entries of every page the import owns. Returns a summary per page:
     * basename => 'listed' | 'unlisted' | 'kept (edited in the editor)' | 'not in the database'.
     *
     * @param array<string,list<array>> $plan from plan()
     * @return array<string,string>
     */
    public function apply(mysqli $db, array $plan, bool $dry = false): array
    {
        $targets = $this->targets($db, $plan);
        $summary = [];
        if (!$dry) {
            $db->begin_transaction();
        }
        try {
            foreach ($targets as $b => $t) {
                if ($t['id'] === null) {
                    $summary[$b] = 'not in the database';
                    continue;
                }
                if ($t['edited']) {
                    $summary[$b] = 'kept (edited in the editor)';
                    continue;
                }
                $entries = $plan[$b] ?? [];
                $summary[$b] = $entries === [] ? 'unlisted' : 'listed';
                if ($dry) {
                    continue;
                }
                $del = $db->prepare('DELETE FROM `majors_listing_entries` WHERE `program_id` = ?');
                $del->bind_param('i', $t['id']);
                $del->execute();
                $del->close();
                $pos = 0;
                foreach ($entries as $e) {
                    $pos++;
                    $lists = implode(',', $e['lists']);
                    $ins = $db->prepare('INSERT INTO `majors_listing_entries` (`program_id`, `position`, `name`, `detail`, `lists`, `shown_in`, `cert_section`, `cert_topics`, `source`, `updated_at`)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, "cms", NOW())');
                    $ins->bind_param('iissssss', $t['id'], $pos, $e['name'], $e['detail'], $lists, $e['shown_in'], $e['cert_section'], $e['cert_topics']);
                    $ins->execute();
                    $ins->close();
                }
            }
            if (!$dry) {
                $db->commit();
            }
        } catch (\Throwable $ex) {
            if (!$dry) {
                $db->rollback();
            }
            throw $ex;
        }
        ksort($summary);
        return $summary;
    }

    /**
     * The Certificates intros as shared blocks (listing-intro-certificates-graduate / -undergraduate),
     * added only when missing so edits made on the Shared blocks page survive a re-import.
     * @param array<string,array{headline:string,body:string}> $intros
     * @return list<string> slugs added
     */
    public function applyIntros(mysqli $db, array $intros, bool $dry = false): array
    {
        $added = [];
        foreach ($intros as $sec => $i) {
            $slug = 'listing-intro-certificates-' . $sec;
            $has  = $db->prepare('SELECT COUNT(*) FROM `majors_content_blocks` WHERE `slug` = ?');
            $has->bind_param('s', $slug);
            $has->execute();
            $n = (int) $has->get_result()->fetch_row()[0];
            $has->close();
            if ($n > 0) {
                continue;
            }
            $added[] = $slug;
            if (!$dry) {
                $note = 'Intro on the Certificates listing (' . (Listings::CERT_SECTIONS[$sec] ?? $sec) . ')';
                $ins  = $db->prepare('INSERT INTO `majors_content_blocks` (`slug`, `headline`, `body`, `links`, `note`, `updated_at`) VALUES (?, ?, ?, "[]", ?, NOW())');
                $ins->bind_param('ssss', $slug, $i['headline'], $i['body'], $note);
                $ins->execute();
                $ins->close();
            }
        }
        return $added;
    }

    /**
     * The same replacement as SQL keyed by basename, for a server where the import itself
     * cannot run. Each page is guarded: nothing changes for a page whose listings were edited
     * in the editor there.
     *
     * @param array<string,list<array>> $plan
     */
    public function sql(mysqli $db, array $plan, string $header = '', array $intros = []): string
    {
        $q   = static fn (?string $v): string => $v === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "''"], $v) . "'";
        $out = "-- Listing entries from the CMS listing pages. Keyed by page name; pages whose listings\n"
             . "-- were edited in the editor (source = editor) are left as they are.\n" . ($header !== '' ? "-- {$header}\n" : '')
             . "SET NAMES utf8mb4;\nSTART TRANSACTION;\n";
        foreach ($this->targets($db, $plan) as $b => $t) {
            if ($t['id'] === null) {
                continue;
            }
            $out .= "\n-- {$b}\n"
                  . "SET @p := (SELECT `id` FROM `majors_academic_programs` WHERE `basename` = " . $q($b) . ");\n"
                  . "SET @own := (SELECT COUNT(*) FROM `majors_listing_entries` WHERE `program_id` = @p AND `source` = 'editor');\n"
                  . "DELETE FROM `majors_listing_entries` WHERE `program_id` = @p AND @own = 0;\n";
            $pos = 0;
            foreach ($plan[$b] ?? [] as $e) {
                $pos++;
                $out .= "INSERT INTO `majors_listing_entries` (`program_id`, `position`, `name`, `detail`, `lists`, `shown_in`, `cert_section`, `cert_topics`, `source`, `updated_at`) "
                      . "SELECT @p, {$pos}, " . $q($e['name']) . ', ' . $q($e['detail']) . ', ' . $q(implode(',', $e['lists'])) . ', ' . $q($e['shown_in']) . ', '
                      . $q($e['cert_section']) . ', ' . $q($e['cert_topics']) . ", 'cms', NOW() FROM DUAL WHERE @p IS NOT NULL AND @own = 0;\n";
            }
        }
        foreach ($intros as $sec => $i) {
            $out .= "\n-- Certificates intro ({$sec}); kept if it already exists\n"
                  . "INSERT IGNORE INTO `majors_content_blocks` (`slug`, `headline`, `body`, `links`, `note`, `updated_at`) VALUES ("
                  . $q('listing-intro-certificates-' . $sec) . ', ' . $q($i['headline']) . ', ' . $q($i['body']) . ", '[]', "
                  . $q('Intro on the Certificates listing (' . (Listings::CERT_SECTIONS[$sec] ?? $sec) . ')') . ", NOW());\n";
        }
        return $out . "\nCOMMIT;\n";
    }

    /**
     * Pages the import owns: every page on a CMS list, plus imported pages (cms_path) that
     * are on none. @return array<string,array{id:?int,edited:bool}>
     */
    private function targets(mysqli $db, array $plan): array
    {
        $rows = $db->query('SELECT p.`id`, p.`basename`, (p.`cms_path` IS NOT NULL AND p.`cms_path` <> "") AS imported, p.`status`,
                                   (SELECT COUNT(*) FROM `majors_listing_entries` e WHERE e.`program_id` = p.`id` AND e.`source` = "editor") AS edited
                              FROM `majors_academic_programs` p WHERE p.`basename` IS NOT NULL AND p.`basename` <> ""')->fetch_all(MYSQLI_ASSOC);
        $byBase = array_column($rows, null, 'basename');
        $out = [];
        foreach (array_keys($plan) as $b) {
            $r = $byBase[$b] ?? null;
            $out[$b] = ['id' => $r ? (int) $r['id'] : null, 'edited' => $r && (int) $r['edited'] > 0];
            if ($r && $r['status'] === 'retired') {
                $this->notes[] = "listed on the CMS but retired in the database: {$b}";
            }
        }
        foreach ($rows as $r) {
            if ((int) $r['imported'] === 1 && !isset($out[$r['basename']])) {
                $out[$r['basename']] = ['id' => (int) $r['id'], 'edited' => (int) $r['edited'] > 0];
            }
        }
        ksort($out);
        return $out;
    }

    /** Anchor text as plain text. */
    private static function text(string $html): string
    {
        $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{a0}", ' ', $t)));
    }

    /** The part after the dash: "— Master of Arts <em>(…)</em> (Online only)" → cleaned HTML without the dash. */
    private static function detail(string $html): string
    {
        $h = trim((string) preg_replace('/\s+/u', ' ', $html));
        $h = (string) preg_replace('/^(?:&mdash;|&#8212;|—|&ndash;|–|-)\s*/u', '', $h);
        return trim(Html::clean($h));
    }

    /** Comparison form: case-insensitive, one kind of dash and space. */
    public static function norm(string $s): string
    {
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace(["\u{2013}", "\u{2014}", "\u{fffd}", "\u{a0}"], ['-', '-', '-', ' '], $s);
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
    }
}
