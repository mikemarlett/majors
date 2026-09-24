<?php

declare(strict_types=1);

namespace Majors\Majors;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Turns one Modern Campus program page (the PCF source of
 * www:/academics/majors/<basename>.pcf) into plain data.
 *
 * Every page is built from the same pieces, in this order:
 *   1. "Section For Program Card" snippet: the Program Card (kind, name,
 *      breadcrumb links, description, "Learn how…" buttons) beside a
 *      Media With Caption snippet (hero image, caption, credit);
 *   2. teaser collections (Curriculum, Admission, Careers, Applied learning…);
 *   3. optionally an "Inside the Program" feature with an image;
 *   4. optionally a "Teaser Card" snippet, which the site uses for Similar Programs.
 *
 * Internal CMS links ({{f:123}} / {{d:456}}) must be resolved to URLs before
 * parsing (see CmsClient::resolveTags); this class is DOM-only and testable.
 */
final class CmsPageParser
{
    /** @return array<string,mixed> */
    public static function parse(string $pcf, string $basename): array
    {
        $prop = static function (string $re) use ($pcf): string {
            return preg_match($re, $pcf, $m) ? html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
        };
        $title    = $prop('~<title>(.*?)</title>~s');
        $heading  = $prop('~name="heading"[^>]*>(.*?)</parameter>~s');
        $metaDesc = $prop('~name="Description"\s+content="([^"]*)"~');
        $keywords = $prop('~name="Keywords"\s+content="([^"]*)"~');
        [$name, $credential, $collegeCode] = self::splitTitle($title);

        $main = preg_match('~<ouc:div[^>]*label="maincontent"[^>]*>(.*?)</ouc:div>~s', $pcf, $m) ? $m[1] : '';
        $main = preg_replace('~<ouc:editor[^>]*/>~', '', $main) ?? $main;

        $page = [
            'basename'         => $basename,
            'title'            => $title,
            'heading'          => $heading,
            'name'             => $name,
            'credential'       => $credential,
            'college_code'     => $collegeCode,
            'meta_description' => $metaDesc,
            'meta_keywords'    => $keywords,
            'keyword_list'     => array_values(array_filter(array_map('trim', explode(',', $keywords)))),
            'catalog_number'   => preg_match('/_(\d+)$/', $basename, $n) ? (int) $n[1] : null,
            'kind'             => '',
            'description'      => '',
            'breadcrumb'       => [],   // [['text','href'], …] after "All Programs": college, department
            'learn_how'        => '',
            'buttons'          => [],   // [['text','href','class'], …]
            'image'            => null, // ['url','alt','caption','credit']
            'sections'         => [],   // ordered: teaser | feature | similar
        ];
        if ($main === '') {
            return $page;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $main . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xp   = new DOMXPath($dom);
        $root = $dom->getElementById('root');
        if ($root === null) {
            return $page;
        }

        foreach ($root->childNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $cls = ' ' . $node->getAttribute('class') . ' ';
            if ($node->tagName === 'table' && str_contains($cls, 'ou-snippet-generic-section-two-column')) {
                self::programCard($xp, $node, $page);
            } elseif ($node->tagName === 'table' && str_contains($cls, 'ou-snippet-section-collection-teaser-card')) {
                $page['sections'][] = self::similar($xp, $node);
            } elseif ($node->tagName === 'section' && str_contains($cls, 'teaser-collection')) {
                foreach ($xp->query('.//div[contains(concat(" ", normalize-space(@class), " "), " teaser ")]', $node) as $teaser) {
                    $page['sections'][] = self::teaser($xp, $teaser, 'teaser');
                }
            } elseif ($node->tagName === 'section') {
                $h2 = $xp->query('.//header//h2', $node)->item(0);
                foreach ($xp->query('.//div[contains(concat(" ", normalize-space(@class), " "), " teaser ")]', $node) as $teaser) {
                    $s = self::teaser($xp, $teaser, 'feature');
                    $s['label'] = $h2 ? trim($h2->textContent) : '';
                    $page['sections'][] = $s;
                }
            }
        }
        return $page;
    }

    /** "Details: Aerospace Engineering, Major, ENG" → [name, credential, college code] */
    public static function splitTitle(string $title): array
    {
        $t     = preg_replace('/^Details:\s*/', '', $title) ?? $title;
        $parts = array_map('trim', explode(',', $t));
        $code  = '';
        if (count($parts) > 1 && preg_match('/^[A-Z]{2,5}$/', end($parts))) {
            $code = array_pop($parts);
        }
        $credential = count($parts) > 1 ? array_pop($parts) : '';
        return [implode(', ', $parts), $credential, $code];
    }

    private static function programCard(DOMXPath $xp, DOMElement $wrap, array &$page): void
    {
        $card = $xp->query('.//table[contains(@class, "ou-snippet-program-card")]', $wrap)->item(0);
        if ($card instanceof DOMElement) {
            $rows = [];
            foreach ($xp->query('./tbody/tr/td', $card) as $td) {
                $rows[] = $td;
            }
            $page['kind'] = isset($rows[0]) ? trim($rows[0]->textContent) : '';
            if (isset($rows[2])) {
                foreach ($xp->query('.//a', $rows[2]) as $a) {
                    $page['breadcrumb'][] = ['text' => trim($a->textContent), 'href' => $a->getAttribute('href')];
                }
            }
            $page['description'] = isset($rows[3]) ? self::innerHtml($rows[3]) : '';
            if (isset($rows[4])) {
                $h = $xp->query('.//h3', $rows[4])->item(0);
                $page['learn_how'] = $h ? trim($h->textContent) : '';
                foreach ($xp->query('.//a', $rows[4]) as $a) {
                    $page['buttons'][] = ['text' => trim($a->textContent), 'href' => $a->getAttribute('href'), 'class' => trim($a->getAttribute('class'))];
                }
            }
        }
        $media = $xp->query('.//table[contains(@class, "ou-snippet-media-with-caption")]', $wrap)->item(0);
        if ($media instanceof DOMElement) {
            $img  = $xp->query('.//img', $media)->item(0);
            $tds  = [];
            foreach ($xp->query('./tbody/tr/td', $media) as $td) {
                $tds[] = $td;
            }
            if ($img instanceof DOMElement && $img->getAttribute('src') !== '') {
                $page['image'] = [
                    'url'     => $img->getAttribute('src'),
                    'alt'     => $img->getAttribute('alt') !== '' ? $img->getAttribute('alt') : $img->getAttribute('title'),
                    'caption' => isset($tds[1]) ? self::cleanText($tds[1]) : '',
                    'credit'  => isset($tds[2]) ? self::cleanText($tds[2]) : '',
                ];
            }
        }
    }

    /** @return array<string,mixed> */
    private static function teaser(DOMXPath $xp, DOMElement $teaser, string $kind): array
    {
        $head = $xp->query('.//*[contains(@class, "head")]', $teaser)->item(0);
        $ed   = $xp->query('.//div[contains(@class, "teaser__editorial")]', $teaser)->item(0);
        $img  = $xp->query('.//div[contains(@class, "teaser__image")]//img', $teaser)->item(0);
        $links = [];
        foreach ($xp->query('.//div[contains(@class, "teaser__links")]//a', $teaser) as $a) {
            $links[] = ['text' => trim(preg_replace('/\s+/', ' ', $a->textContent) ?? ''), 'href' => $a->getAttribute('href')];
        }
        return [
            'kind'     => $kind,
            'label'    => '',
            'headline' => $head ? trim($head->textContent) : '',
            'body'     => $ed instanceof DOMElement ? self::innerHtml($ed) : '',
            'links'    => $links,
            'image'    => $img instanceof DOMElement ? ['url' => $img->getAttribute('src'), 'alt' => $img->getAttribute('alt') !== '' ? $img->getAttribute('alt') : $img->getAttribute('title')] : null,
        ];
    }

    /** The "Teaser Card" snippet as the site uses it: a titled list of related programs. */
    private static function similar(DOMXPath $xp, DOMElement $table): array
    {
        $items = [];
        $title = '';
        $i     = 0;
        foreach ($xp->query('./tbody/tr', $table) as $tr) {
            $i++;
            if ($i === 1) {
                continue; // orientation row
            }
            $tds = $xp->query('./td', $tr);
            $img = $xp->query('.//img', $tr)->item(0);
            $a   = $xp->query('.//a', $tr)->item(0);
            if ($i === 2) {
                $title = $tds->length ? trim($tds->item($tds->length - 1)->textContent) : '';
                continue;
            }
            if ($a instanceof DOMElement) {
                $items[] = ['text' => trim($a->textContent), 'href' => $a->getAttribute('href'), 'image' => $img instanceof DOMElement ? $img->getAttribute('src') : ''];
            }
        }
        return ['kind' => 'similar', 'label' => $title, 'headline' => $title, 'body' => '', 'links' => $items, 'image' => null];
    }

    public static function innerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }
        return trim($html);
    }

    private static function cleanText(DOMNode $node): string
    {
        $t = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
        return $t === "\u{a0}" || $t === '' ? '' : $t;
    }
}
