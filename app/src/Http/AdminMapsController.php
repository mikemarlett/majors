<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\DegreeMaps\Forms;
use Majors\DegreeMaps\Listing;
use Majors\DegreeMaps\Lookups;
use Majors\DegreeMaps\MapRenderer;
use Majors\DegreeMaps\MapRepository;
use Majors\Support\Request;

/**
 * degree_maps/admin/maps.php — list, view, edit degree maps (advisors and super admins).
 *
 *   maps.php                                  list for the newest catalog year
 *   maps.php?degree_map_id=N                  view one map with admin actions
 *   maps.php?degree_map_id=N&editMap=Edit     the editor (future years, own college)
 *
 * Clone and delete are ajax actions (clone_degree_map / delete_degree_map) so
 * they carry the CSRF token; the old ?delete=1&secret=squirrel is gone.
 */
final class AdminMapsController extends Controller
{
    public function handle(Request $r): void
    {
        $user    = $this->app->guard()->require('advisor');
        $maps    = $this->app->maps();
        $layout  = $this->app->layout();
        $lookups = new Lookups($this->app->db());

        $mapId   = $r->id('degree_map_id');
        $order   = $r->enum('order', ['alpha', 'college'], 'alpha');
        $year    = $r->int('selected_year') ?: $maps->latestYear();
        $college = $r->strOrNull('selected_college');
        $wantEdit = $r->str('editMap') === 'Edit' || $r->str('edit') === '1';
        if ($r->str('viewMap') === 'View') {
            $wantEdit = false;
        }

        $mode = 'list';
        $map = null;
        $canEdit = false;
        $editableYear = false;
        $title = 'Edit Degree Maps';

        if ($mapId !== null) {
            $map = $maps->find($mapId);
            if ($map === null) {
                $this->notFound('That degree map could not be found.');
            }
            $year         = (int) $map['academic_year'];
            $editableYear = MapRepository::isEditable($map);
            $canEdit      = $editableYear && $user->canEditCollege($lookups->collegeId((string) $map['college']));
            $title        = ($maps->title($mapId) ?? $title) . ' — Admin';

            if ($wantEdit && $canEdit) {
                $mode    = 'edit';
                $forms   = new Forms($layout, $maps, $lookups);
                $results = $forms->editor($map, $user);
            } else {
                $mode     = 'view';
                $versions = $maps->versions($map);
                $results  = (new MapRenderer($layout))->render($map, [
                    'versions'   => $versions,
                    'latest_id'  => (int) ($versions[0]['id'] ?? $map['id']),
                    'link_base'  => $layout->url('degree_maps/admin/maps.php') . '?degree_map_id=',
                    'latest_url' => $layout->url('degree_maps/maps.php') . '?latest=' . (int) $map['id'],
                ]);
            }
        } else {
            $rows    = $maps->list($year, $order, $college);
            $groups  = $order === 'college' ? Listing::byCollege($rows) : Listing::byAlpha($rows);
            $results = $layout->render('degree_maps/listing', [
                'groups'    => $groups,
                'link_base' => $layout->url('degree_maps/admin/maps.php') . '?degree_map_id=',
            ]);
        }

        $csrf = $this->app->guard()->csrfToken();
        $content = $layout->render('degree_maps/admin/page', [
            'user'          => $user,
            'csrf'          => $csrf,
            'mode'          => $mode,
            'map'           => $map,
            'results'       => $results,
            'order'         => $order,
            'year'          => $year,
            // Existing years plus next year, so a brand-new catalog year is reachable before its first map exists.
            'years'         => array_keys($lookups->academicYearOptions($maps->years())),
            'colleges'      => $maps->colleges($year),
            'college'       => $college,
            'can_edit'      => $canEdit,
            'editable_year' => $editableYear,
            'self_url'      => $layout->url('degree_maps/admin/maps.php'),
            'search_url'    => $layout->url('degree_maps/search.php'),
            'flash'         => $r->strOrNull('flash'),
        ]);

        $this->page($content, [
            'title'      => $title,
            'user'       => $user,
            'csrf'       => $csrf,
            'body_class' => 'majors-admin',
            'head'       => [
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">',
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('jquery-ui-1.14.1/jquery-ui.min.css')) . '">',
                '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">',
                '<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css">',
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('admin.css')) . '">',
            ],
            'foot'       => [
                '<script>window.jQuery || document.write(\'<script src="https://code.jquery.com/jquery-3.7.1.min.js"><\/script>\')</script>',
                '<script src="' . $layout->e($layout->asset('jquery-ui-1.14.1/jquery-ui.min.js')) . '"></script>',
                '<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>',
                '<script src="https://unpkg.com/@popperjs/core@2"></script>',
                '<script src="https://unpkg.com/tippy.js@6"></script>',
                '<script>window.MajorsAdmin = ' . json_encode([
                    'ajax'     => $layout->url('degree_maps/admin/ajax.php'),
                    'self'     => $layout->url('degree_maps/admin/maps.php'),
                    'csrf'     => $csrf,
                    'mapId'    => $map ? (int) $map['id'] : null,
                    'mode'     => $mode,
                ], JSON_UNESCAPED_SLASHES) . ';</script>',
                '<script src="' . $layout->e($layout->asset('admin/map-edit.js')) . '" defer></script>',
                '<script src="' . $layout->e($layout->asset('degree-maps.js')) . '" defer></script>',
            ],
        ]);
    }
}
