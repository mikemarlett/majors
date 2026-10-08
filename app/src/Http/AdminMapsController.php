<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\DegreeMaps\Forms;
use Majors\DegreeMaps\Lookups;
use Majors\DegreeMaps\MapRenderer;
use Majors\DegreeMaps\MapRepository;
use Majors\Support\Request;

/**
 * degree_maps/admin/maps.php — list, view, edit degree maps (advisors and super admins).
 *
 *   maps.php                                  every map of the newest catalog year, as a table
 *   maps.php?selected_year=2028               another year (the table filters by college,
 *                                             approval and text on the client, map-list.js)
 *   maps.php?degree_map_id=N                  view one map with admin actions
 *   maps.php?degree_map_id=N&editMap=Edit     the editor (future years, own college)
 *
 * Approval: a map is public only once an advisor admin or a super admin has
 * approved it. The table shows the state of every map and, for those two
 * roles, approves or withdraws a selection at once; the Actions row does the
 * same for one map. Clone and delete are ajax actions (clone_degree_map /
 * delete_degree_map) so they carry the CSRF token; the old
 * ?delete=1&secret=squirrel is gone.
 */
final class AdminMapsController extends Controller
{
    public function handle(Request $r): void
    {
        $user    = $this->app->guard()->require('advisor');
        $maps    = $this->app->maps()->includingUnapproved(); // the admin sees what students cannot yet
        $layout  = $this->app->layout();
        $lookups = new Lookups($this->app->db(), (array) $this->app->config->get('college_aliases', []));

        $mapId   = $r->id('degree_map_id');
        $order   = $r->enum('order', ['alpha', 'college'], 'alpha');
        $year    = $r->int('selected_year') ?: $maps->latestYear();
        $college = $r->strOrNull('selected_college');
        $status  = $r->enum('selected_status', ['all', 'approved', 'pending'], 'all');
        $wantEdit = $r->str('editMap') === 'Edit' || $r->str('edit') === '1';
        if ($r->str('viewMap') === 'View') {
            $wantEdit = false;
        }

        $mode = 'list';
        $map = null;
        $canEdit = false;
        $canClone = false;
        $editableYear = false;
        $canApprove = $user->isDegreeMapsAdmin();
        $title = 'Edit Degree Maps';

        if ($mapId !== null) {
            $map = $maps->find($mapId);
            if ($map === null) {
                $this->notFound('That degree map could not be found.');
            }
            $year         = (int) $map['academic_year'];
            $editableYear = MapRepository::isEditable($map);
            $canClone     = $user->canEditCollege($lookups->collegeId((string) $map['college']));
            $canEdit      = $canClone && ($editableYear || $user->isSuperAdmin()); // super admins may touch published years
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
            // Every college: the table narrows by college, approval state and text on the client.
            $rows      = $maps->list($year, 'alpha');
            $dups      = $maps->duplicateIds($year); // same degree twice in one year: probably a double clone
            $collegeOk = [];
            foreach ($rows as &$row) {
                $c = (string) $row['college'];
                $collegeOk[$c] ??= $user->canEditCollege($lookups->collegeId($c));
                $row['can_edit']  = $collegeOk[$c] && (MapRepository::isEditable($row) || $user->isSuperAdmin());
                $row['duplicate'] = in_array((int) $row['id'], $dups, true);
            }
            unset($row);
            $results = $layout->render('degree_maps/admin/table', [
                'rows'        => $rows,
                'user'        => $user,
                'year'        => $year,
                'can_approve' => $canApprove,
                'college'     => $college,
                'status'      => $status,
                'view_base'   => $layout->url('degree_maps/admin/maps.php') . '?degree_map_id=',
                'public_base' => $layout->url('degree_maps/maps.php') . '?degree_map_id=',
            ]);
        }

        $csrf = $this->app->guard()->csrfToken();
        $toolbar = $layout->render('degree_maps/admin/toolbar', [
            'user'          => $user,
            'mode'          => $mode,
            'map'           => $map,
            'order'         => $order,
            'year'          => $year,
            // Existing years plus next year, so a brand-new catalog year is reachable before its first map exists.
            'years'         => array_keys($lookups->academicYearOptions($maps->years())),
            'colleges'      => $maps->colleges($year),
            'college'       => $college,
            'status'        => $status,
            'can_edit'      => $canEdit,
            'can_clone'     => $canClone,
            'can_approve'   => $canApprove,
            'editable_year' => $editableYear,
            'self_url'      => $layout->url('degree_maps/admin/maps.php'),
            'search_url'    => $layout->url('degree_maps/search.php'),
        ]);
        $content = $layout->render('degree_maps/admin/page', [
            'results' => $results,
            'flash'   => $r->strOrNull('flash'),
        ]);

        $foot = [
            '<script>window.jQuery || document.write(\'<script src="https://code.jquery.com/jquery-3.7.1.min.js"><\/script>\')</script>',
            '<script src="' . $layout->e($layout->asset('jquery-ui-1.14.1/jquery-ui.min.js')) . '"></script>',
            '<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>',
            '<script src="https://unpkg.com/@popperjs/core@2"></script>',
            '<script src="https://unpkg.com/tippy.js@6"></script>',
            $layout->jsConfig('MajorsAdmin', [
                'ajax'     => $layout->url('degree_maps/admin/ajax.php'),
                'self'     => $layout->url('degree_maps/admin/maps.php'),
                'csrf'     => $csrf,
                'mapId'    => $map ? (int) $map['id'] : null,
                'mode'     => $mode,
            ]),
            '<script src="' . $layout->e($layout->asset('admin/ma-ui.js')) . '"></script>',
            '<script src="' . $layout->e($layout->asset('admin/map-edit.js')) . '" defer></script>',
            '<script src="' . $layout->e($layout->asset('admin/map-list.js')) . '" defer></script>',
        ];
        if ($mode !== 'list') {
            // Live search from a map's page goes to the server (degree-maps.js); the table filters itself.
            $foot[] = '<script src="' . $layout->e($layout->asset('degree-maps.js')) . '" defer></script>';
        }

        $this->page($content, [
            'top'        => $toolbar,
            'title'      => $title,
            'page_header' => 'Edit Degree Maps',
            'user'       => $user,
            'csrf'       => $csrf,
            'body_class' => 'majors-admin majors-degree-map',
            'head'       => [
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">',
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('jquery-ui-1.14.1/jquery-ui.min.css')) . '">',
                '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">',
                '<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css">',
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('admin/ma-ui.css')) . '">',
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('admin.css')) . '">',
            ],
            'foot'       => $foot,
        ]);
    }
}
