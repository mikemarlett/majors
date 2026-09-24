<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\DegreeMaps\Listing;
use Majors\DegreeMaps\MapRenderer;
use Majors\Support\Json;
use Majors\Support\Request;

/**
 * /academics/majors/degree_maps/search.php — JSON used by degree-maps.js.
 * Replaces the CMS-hosted _resources/php/degree_maps_search_process.php.
 * Same contract: {success, results (HTML), message}.
 */
final class MapsSearchController extends Controller
{
    private const CAP = 50;

    public function handle(Request $r): void
    {
        $maps   = $this->app->maps();
        $layout = $this->app->layout();

        $text    = $r->str('searchList');
        $college = $r->strOrNull('selected_college');
        $order   = $r->enum('order', ['alpha', 'college'], 'alpha');
        $year    = $r->int('selected_year') ?: $maps->defaultYear();
        $linkBase = ($r->strOrNull('link_base') !== null && str_starts_with($r->str('link_base'), '/'))
            ? $r->str('link_base')
            : $layout->url('degree_maps/maps.php') . '?degree_map_id=';

        if ($text !== '' && mb_strlen($text) < 2) {
            Json::send(['success' => false, 'message' => 'Your search must have two or more letters.',
                'results' => $layout->render('degree_maps/message', ['message' => 'Your search must have two or more letters.'])]);
        }

        if ($college !== null && !in_array($college, $maps->colleges($year), true)) {
            $college = null;
        }

        $rows = $text === '' ? $maps->list($year, $order, $college) : $maps->search($text, $year, $college, $order);

        $note = '';
        if ($rows === []) {
            Json::send(['success' => true, 'results' => $layout->render('degree_maps/message', ['message' => 'No results'])]);
        }
        if (count($rows) === 1 && $text !== '') {
            $map = $maps->find((int) $rows[0]['id']);
            Json::send(['success' => true, 'results' => (new MapRenderer($layout))->render($map), 'map_id' => (int) $rows[0]['id']]);
        }
        if (count($rows) > self::CAP && $college === null && $text !== '') {
            $rows = array_slice($rows, 0, self::CAP);
            $note = $layout->render('degree_maps/message', ['message' => 'Results are capped at ' . self::CAP . '. Refine your search.']);
        }
        $groups = $order === 'college' ? Listing::byCollege($rows) : Listing::byAlpha($rows);
        Json::send([
            'success' => true,
            'results' => $note . $layout->render('degree_maps/listing', ['groups' => $groups, 'link_base' => $linkBase, 'alpha_nav' => $order === 'alpha']),
            'count'   => count($rows),
        ]);
    }
}
