<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\Support\Request;

/**
 * _admin/index.php — Majors control panel (marketing role): every program in
 * a sortable, filterable table with a few things editable in place (status,
 * similar programs); the name previews the public page, Edit opens the
 * in-place editor.
 */
final class AdminMajorsController extends Controller
{
    public function handle(Request $r): void
    {
        $user   = $this->app->guard()->require('marketing');
        $layout = $this->app->layout();
        $csrf   = $this->app->guard()->csrfToken();
        $rows   = $this->app->programs()->adminList();

        $credentials = array_values(array_unique(array_filter(array_map(static fn ($p) => (string) ($p['credential'] ?? ''), $rows))));
        sort($credentials, SORT_NATURAL | SORT_FLAG_CASE);
        $colleges = array_values(array_unique(array_filter(array_map(static fn ($p) => (string) ($p['college'] ?? ''), $rows))));
        sort($colleges, SORT_NATURAL | SORT_FLAG_CASE);

        $content = $layout->render('majors/admin/index', [
            'user'        => $user,
            'csrf'        => $csrf,
            'programs'    => $rows,
            'credentials' => $credentials,
            'colleges'    => $colleges,
        ]);

        $this->page($content, [
            'title'       => 'Majors Admin',
            'page_header' => 'Majors Admin',
            'user'        => $user,
            'csrf'        => $csrf,
            'body_class'  => 'majors-admin majors-admin--panel',
            'head'        => [
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">',
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('admin.css')) . '">',
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('admin/ma-ui.css')) . '">',
            ],
            'foot'        => [
                '<script>window.MajorsAdmin = ' . json_encode(['ajax' => $layout->url('_admin/ajax.php'), 'csrf' => $csrf, 'search' => $layout->url('_admin/ajax.php') . '?action=program_search'], JSON_UNESCAPED_SLASHES) . ';</script>',
                '<script src="' . $layout->e($layout->asset('admin/ma-ui.js')) . '"></script>',
                '<script src="' . $layout->e($layout->asset('admin/majors-admin.js')) . '" defer></script>',
            ],
        ]);
    }
}
