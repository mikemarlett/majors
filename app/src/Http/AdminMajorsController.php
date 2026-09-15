<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\Support\Request;

/**
 * _admin/index.php — Majors admin (marketing role). This pass: the program
 * inventory with content status and links to preview each page. The
 * field-by-field marketing editor is the next phase (see README).
 */
final class AdminMajorsController extends Controller
{
    public function handle(Request $r): void
    {
        $user   = $this->app->guard()->require('marketing');
        $layout = $this->app->layout();
        $csrf   = $this->app->guard()->csrfToken();

        $content = $layout->render('majors/admin/index', [
            'user'        => $user,
            'csrf'        => $csrf,
            'programs'    => $this->app->programs()->adminList(),
            'program_url' => $layout->url('index.php') . '?id=',
        ]);

        $this->page($content, [
            'title'      => 'Majors Admin',
            'page_header' => 'Majors Admin',
            'user'       => $user,
            'csrf'       => $csrf,
            'body_class' => 'majors-admin',
            'head'       => [
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">',
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('admin.css')) . '">',
            ],
            'foot'       => ['<script src="' . $layout->e($layout->asset('admin/majors-admin.js')) . '" defer></script>'],
        ]);
    }
}
