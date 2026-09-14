<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\DegreeMaps\Listing;
use Majors\DegreeMaps\MapRenderer;
use Majors\Support\Request;

/**
 * /academics/majors/degree_maps/maps.php — the live public viewer.
 *
 *   maps.php                          A–Z list for the default year
 *   maps.php?order=college            grouped by college
 *   maps.php?selected_year=2027       another catalog year
 *   maps.php?degree_map_id=922        one printable map (legacy alias: ?map_id=)
 */
final class PublicMapsController extends Controller
{
    public function handle(Request $r): void
    {
        $maps   = $this->app->maps();
        $layout = $this->app->layout();

        // Permanent link: ?latest=<any version's id> → newest catalog year of that degree.
        if (($familyId = $r->id('latest')) !== null) {
            $header = $maps->header($familyId);
            if ($header === null) {
                $this->notFound('That degree map could not be found.');
            }
            $newest = $maps->latestVersion($header);
            $this->redirect($layout->url('degree_maps/maps.php') . '?degree_map_id=' . (int) $newest['id']);
        }

        $mapId = $r->id('degree_map_id') ?? $r->id('map_id');
        $order = $r->enum('order', ['alpha', 'college'], 'alpha');
        $year  = $r->int('selected_year') ?: ($r->int('academic_year') ?: $maps->defaultYear());
        $college = $r->strOrNull('selected_college') ?? $r->strOrNull('college');

        $title = 'Degree Maps';
        if ($mapId !== null) {
            $map = $maps->find($mapId);
            if ($map === null) {
                $this->notFound('That degree map could not be found.');
            }
            $title    = $maps->title($mapId) ?? $title;
            $year     = (int) $map['academic_year'];
            $versions = $maps->versions($map);
            $results  = (new MapRenderer($layout))->render($map, [
                'versions'  => $versions,
                'latest_id' => (int) ($versions[0]['id'] ?? $map['id']),
                'link_base' => $layout->url('degree_maps/maps.php') . '?degree_map_id=',
                'latest_url' => $layout->url('degree_maps/maps.php') . '?latest=' . (int) $map['id'],
            ]);
        } else {
            $rows    = $maps->list($year, $order, $college);
            $groups  = $order === 'college' ? Listing::byCollege($rows) : Listing::byAlpha($rows);
            $results = $layout->render('degree_maps/listing', [
                'groups'    => $groups,
                'link_base' => $layout->url('degree_maps/maps.php') . '?degree_map_id=',
            ]);
        }

        $content = $layout->render('degree_maps/index', [
            'title'         => 'Degree Maps',
            'results'       => $results,
            'order'         => $order,
            'year'          => $year,
            'years'         => $maps->years(),
            'colleges'      => $maps->colleges($year),
            'college'       => $college,
            'showing_map'   => $mapId !== null,
            'nav_file'      => $this->app->docroot() . '/' . $layout->site('degree_maps_nav'),
            'search_url'    => $layout->url('degree_maps/search.php'),
            'self_url'      => $layout->url('degree_maps/maps.php'),
        ]);

        $this->page($content, [
            'title'       => $title,
            'description' => 'Degree Maps to guide students through degrees at Wichita State',
            'head'        => [
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">',
                '<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css">',
            ],
            'foot'        => [
                '<script src="https://unpkg.com/@popperjs/core@2"></script>',
                '<script src="https://unpkg.com/tippy.js@6"></script>',
                '<script src="' . $layout->e($layout->asset('degree-maps.js')) . '" defer></script>',
            ],
        ]);
    }
}
