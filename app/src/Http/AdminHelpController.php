<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\Support\Request;

/**
 * degree_maps/admin/help.php — the advisors' guide to editing a degree map.
 * Gated like the rest of the admin (anyone with a degree-maps role), so it can
 * be frank about how publishing and permissions actually work.
 */
final class AdminHelpController extends Controller
{
    public function handle(Request $r): void
    {
        $user   = $this->app->guard()->require('advisor');
        $layout = $this->app->layout();

        $this->page($layout->render('degree_maps/admin/help', [
            'user'       => $user,
            'contact'    => $layout->site('contact', ''),
            'maps_url'   => $layout->url('degree_maps/admin/maps.php'),
            'public_url' => $layout->url('degree_maps/maps.php'),
        ]), [
            'title'       => 'Degree Maps: How to edit a map',
            'page_header' => 'How to edit a degree map',
            'user'        => $user,
            'body_class'  => 'majors-admin',
            'head'        => ['<link rel="stylesheet" href="' . $layout->e($layout->asset('degree-map.css')) . '">'],
        ]);
    }
}
