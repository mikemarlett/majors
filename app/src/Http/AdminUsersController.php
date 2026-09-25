<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\Support\Request;

/** degree_maps/admin/manage_users.php — the approved list (super admins only). */
final class AdminUsersController extends Controller
{
    public function handle(Request $r): void
    {
        $user   = $this->app->guard()->require('super_admin');
        $layout = $this->app->layout();
        $csrf   = $this->app->guard()->csrfToken();

        $this->page($layout->render('degree_maps/admin/users', ['user' => $user, 'csrf' => $csrf]), [
            'title'      => 'Manage Users',
            'page_header' => 'Manage Users',
            'user'       => $user,
            'csrf'       => $csrf,
            'body_class' => 'majors-admin',
            'head'       => [
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">',
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('jquery-ui-1.14.1/jquery-ui.min.css')) . '">',
                '<link rel="stylesheet" href="' . $layout->e($layout->asset('admin.css')) . '">',
            ],
            'foot'       => [
                '<script>window.jQuery || document.write(\'<script src="https://code.jquery.com/jquery-3.7.1.min.js"><\/script>\')</script>',
                '<script src="' . $layout->e($layout->asset('jquery-ui-1.14.1/jquery-ui.min.js')) . '"></script>',
                $layout->jsConfig('MajorsAdmin', [
                    'ajax' => $layout->url('degree_maps/admin/ajax.php'),
                    'csrf' => $csrf,
                ]),
                '<script src="' . $layout->e($layout->asset('admin/manage-users.js')) . '" defer></script>',
            ],
        ]);
    }
}
