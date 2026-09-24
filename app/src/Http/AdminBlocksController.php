<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\Majors\ProgramEditor;
use Majors\Support\Request;

/** _admin/blocks.php — the shared content blocks: text that many program pages show verbatim. */
final class AdminBlocksController extends Controller
{
    public function handle(Request $r): void
    {
        $user   = $this->app->guard()->require('marketing');
        $layout = $this->app->layout();
        $csrf   = $this->app->guard()->csrfToken();
        $editor = new ProgramEditor($this->app->db());
        $this->page($layout->render('majors/admin/blocks', ['blocks' => $editor->blocks(), 'csrf' => $csrf, 'program_url' => $layout->url('_admin/program.php') . '?id=']), [
            'title'       => 'Shared content blocks',
            'page_header' => 'Shared content blocks',
            'user'        => $user,
            'csrf'        => $csrf,
            'body_class'  => 'majors-admin',
            'head'        => [
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">',
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('jquery-ui-1.14.1/jquery-ui.min.css')) . '">',
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('admin.css')) . '">',
            ],
            'foot'        => [
                '<script>window.jQuery || document.write(\'<script src="https://code.jquery.com/jquery-3.7.1.min.js"><\/script>\')</script>',
                '<script src="' . $layout->e($layout->asset('jquery-ui-1.14.1/jquery-ui.min.js')) . '"></script>',
                '<script src="https://cdn.jsdelivr.net/npm/@ckeditor/ckeditor5-build-classic@41.4.2/build/ckeditor.js"></script>',
                '<script>window.MajorsAdmin = ' . json_encode(['ajax' => $layout->url('_admin/ajax.php'), 'csrf' => $csrf], JSON_UNESCAPED_SLASHES) . ';</script>',
                '<script src="' . $layout->e($layout->asset('admin/program-edit.js')) . '" defer></script>',
            ],
        ]);
    }
}
