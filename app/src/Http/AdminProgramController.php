<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\Majors\ProgramEditor;
use Majors\Majors\ProgramRenderer;
use Majors\Support\Request;

/**
 * _admin/program.php?program=<basename> (or ?id=N) — the in-place editor:
 * the public program page, rendered by the same templates with editing
 * markers, plus the editor's scripts. ?new=1 shows the small create form.
 */
final class AdminProgramController extends Controller
{
    public function handle(Request $r): void
    {
        $user   = $this->app->guard()->require('marketing');
        $layout = $this->app->layout();
        $csrf   = $this->app->guard()->csrfToken();
        $db     = $this->app->db();
        $editor = new ProgramEditor($db);

        $head = [
            '<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">',
            '<link rel="stylesheet" href="' . $layout->e($layout->asset('admin/ma-ui.css')) . '">',
            '<link rel="stylesheet" href="' . $layout->e($layout->asset('admin/inplace.css')) . '">',
        ];
        $common = ['user' => $user, 'csrf' => $csrf, 'head' => $head];

        if ($r->int('new') === 1) {
            $foot = [
                '<script>window.MajorsAdmin = ' . json_encode(['ajax' => $layout->url('_admin/ajax.php'), 'csrf' => $csrf], JSON_UNESCAPED_SLASHES) . ';</script>',
                '<script src="' . $layout->e($layout->asset('admin/ma-ui.js')) . '"></script>',
                '<script src="' . $layout->e($layout->asset('admin/inplace.js')) . '" defer></script>',
            ];
            $this->page($layout->render('majors/admin/program_new', ['csrf' => $csrf, 'public_base' => $layout->url('index.php') . '?program=']), [
                'title' => 'New program', 'page_header' => 'New program', 'body_class' => 'majors-admin', 'foot' => $foot,
            ] + $common);
            return;
        }

        $basename = $r->strOrNull('program');
        $program  = $basename !== null ? $this->app->programs()->findByBasename($basename) : null;
        if ($program === null) {
            $id      = $r->id('id') ?? $this->notFound('Which program? Open one from the Majors admin list.');
            $program = $this->app->programs()->find($id) ?? $this->notFound('That program could not be found.');
        }
        $pid      = (int) $program['id'];
        $renderer = new ProgramRenderer($layout);
        $parts    = $renderer->parts($program, $this->app->maps()->forProgram($pid), true, $editor->blockUseCounts());
        $blocks   = array_map(static fn (array $b) => ['id' => (int) $b['id'], 'headline' => (string) ($b['headline'] ?: $b['slug']), 'uses' => (int) $b['uses']], $editor->blocks());
        $colleges = array_column($db->query('SELECT `name` FROM `majors_colleges` ORDER BY `name`')->fetch_all(MYSQLI_ASSOC), 'name');
        $departments = array_column($db->query('SELECT DISTINCT `department` FROM `majors_departments` ORDER BY `department`')->fetch_all(MYSQLI_ASSOC), 'department');

        $config = [
            'ajax'        => $layout->url('_admin/ajax.php'),
            'csrf'        => $csrf,
            'programId'   => $pid,
            'title'       => ProgramRenderer::title($program),
            'imageBase'   => $layout->site('image_base', ''),
            'publicUrl'   => $layout->programUrl($program),
            'blocks'      => $blocks,
            'colleges'    => $colleges,
            'departments' => $departments,
            'credentials' => ['Major', 'Minor', "Master's", 'Doctorate', 'Graduate Certificate', 'Undergraduate Certificate', "Bachelor's to Master's", 'Badge', 'Field Major', 'Postbaccalaureate'],
            'modalities'  => ProgramEditor::MODALITIES,
        ];
        $foot = [
            // balloon-block build: formatting balloon on a selection, plus the block handle (⋮) for lists and headings
            '<script src="https://cdn.jsdelivr.net/npm/@ckeditor/ckeditor5-build-balloon-block@41.4.2/build/ckeditor.js"></script>',
            '<script>window.MajorsAdmin = ' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>',
            '<script src="' . $layout->e($layout->asset('admin/ma-ui.js')) . '"></script>',
            '<script src="' . $layout->e($layout->asset('admin/inplace.js')) . '" defer></script>',
        ];
        $editBar = $layout->render('majors/admin/inplace/edit_bar', [
            'program'    => $program,
            'public_url' => $layout->programUrl($program),
            'blocks_url' => $layout->url('_admin/blocks.php'),
            'index_url'  => $layout->url('_admin/index.php'),
        ]);
        $this->page($parts['content'], [
            'top'          => $editBar . $parts['top'],
            'bottom'       => $parts['bottom'],
            'title'        => 'Editing: ' . ProgramRenderer::title($program),
            'page_header'  => 'Details: ' . ProgramRenderer::title($program),
            'nav_items'    => $renderer->sectionNav($program),
            'header_print' => true,
            'body_class'   => 'majors-admin majors-inplace',
            'foot'         => $foot,
        ] + $common);
    }
}
