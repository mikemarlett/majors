<?php

declare(strict_types=1);

namespace Majors\Http\Ajax;

use Majors\Auth\User;
use Majors\DegreeMaps\CourseCatalog;
use Majors\Support\Request;

final class CatalogActions extends BaseAction
{
    /** jQuery UI autocomplete source: list of {label, value, hours, ...}. */
    public function search(Request $r, User $user): array
    {
        return (new CourseCatalog($this->app->db()))->search($r->str('term'));
    }
}
