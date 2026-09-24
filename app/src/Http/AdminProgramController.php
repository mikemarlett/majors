<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\Majors\ProgramEditor;
use Majors\Majors\ProgramRenderer;
use Majors\Support\Request;

/**
 * _admin/program.php?id=N — the marketing editor for one program: its
 * Program Card and details, the ordered sections (shared blocks attach
 * here), and similar programs. ?new=1 shows the small create form.
 */
final class AdminProgramController extends Controller
{
    public function handle(Request $r): void
    {
        $user   = $this->app->guard()->require('marketing');
        $layout = $this->app->layout();
        $csrf   = $this->app->guard()->csrfToken();
        $db     = $this->app->db();

        $head = [
            '<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">',
            '<link rel="stylesheet" href="' . $layout->e($layout->asset('jquery-ui-1.14.1/jquery-ui.min.css')) . '">',
            '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">',
            '<link rel="stylesheet" href="' . $layout->e($layout->asset('admin.css')) . '">',
        ];
        $foot = [
            '<script>window.jQuery || document.write(\'<script src="https://code.jquery.com/jquery-3.7.1.min.js"><\/script>\')</script>',
            '<script src="' . $layout->e($layout->asset('jquery-ui-1.14.1/jquery-ui.min.js')) . '"></script>',
            '<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>',
            '<script src="https://cdn.jsdelivr.net/npm/@ckeditor/ckeditor5-build-classic@41.4.2/build/ckeditor.js"></script>',
            '<script>window.MajorsAdmin = ' . json_encode(['ajax' => $layout->url('_admin/ajax.php'), 'csrf' => $csrf, 'search' => $layout->url('_admin/ajax.php') . '?action=program_search'], JSON_UNESCAPED_SLASHES) . ';</script>',
            '<script src="' . $layout->e($layout->asset('admin/program-edit.js')) . '" defer></script>',
        ];
        $common = ['user' => $user, 'csrf' => $csrf, 'body_class' => 'majors-admin', 'head' => $head, 'foot' => $foot];

        if ($r->int('new') === 1) {
            $this->page($layout->render('majors/admin/program_new', ['csrf' => $csrf]), ['title' => 'New program', 'page_header' => 'New program'] + $common);
            return;
        }
        $id      = $r->id('id') ?? $this->notFound('Which program? Add ?id= to the address.');
        $program = $this->app->programs()->find($id) ?? $this->notFound('That program could not be found.');
        $colleges = array_column($db->query('SELECT `name` FROM `majors_colleges` ORDER BY `name`')->fetch_all(MYSQLI_ASSOC), 'name');
        $departments = $db->query('SELECT c.`name` AS college, d.`department` FROM `majors_departments` d JOIN `majors_colleges` c ON c.`id` = d.`college_id` ORDER BY c.`name`, d.`department`')->fetch_all(MYSQLI_ASSOC);
        $editor  = new ProgramEditor($db);

        $content = $layout->render('majors/admin/program', [
            'program'     => $program,
            'sections'    => $program['sections'],
            'similar'     => $program['similar_programs'],
            'colleges'    => $colleges,
            'departments' => $departments,
            'blocks'      => $editor->blocks(),
            'modalities'  => ProgramEditor::MODALITIES,
            'facts'       => ProgramRenderer::facts($program),
            'public_url'  => $layout->url('index.php') . '?id=' . (int) $program['id'],
            'cms_url'     => !empty($program['cms_path']) ? 'https://www.wichita.edu' . preg_replace('/\.pcf$/', '.php', (string) $program['cms_path']) : '',
            'degree_maps' => $this->app->maps()->forProgram((int) $program['id']),
            'maps_url'    => $layout->url('degree_maps/admin/maps.php') . '?degree_map_id=',
            'flash'       => $r->strOrNull('flash'),
            'csrf'        => $csrf,
        ]);
        $this->page($content, ['title' => 'Edit: ' . ProgramRenderer::title($program), 'page_header' => 'Edit program'] + $common);
    }
}
