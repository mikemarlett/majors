<?php

declare(strict_types=1);

namespace Majors\Http\Ajax;

use Majors\Auth\User;
use Majors\DegreeMaps\Forms;
use Majors\DegreeMaps\Lookups;
use Majors\DegreeMaps\MapEditor;
use Majors\DegreeMaps\MapRepository;
use Majors\Kernel;

abstract class BaseAction
{
    protected readonly MapRepository $maps;
    protected readonly MapEditor $editor;
    protected readonly Lookups $lookups;
    protected readonly Forms $forms;

    public function __construct(protected readonly Kernel $app)
    {
        $this->maps    = $app->maps();
        $this->editor  = new MapEditor($app->db());
        $this->lookups = new Lookups($app->db(), (array) $app->config->get('college_aliases', []));
        $this->forms   = new Forms($app->layout(), $this->maps, $this->lookups);
    }

    /** Header row of a map the user may READ in the admin (any signed-in editor). */
    protected function mapHeader(int $id): array
    {
        $map = $this->maps->header($id);
        if ($map === null) {
            throw new ActionException('Degree map not found.', 404);
        }
        return $map;
    }

    /**
     * Header row of a map the user may CHANGE: the map must be for a future
     * catalog year and, unless the user is a super admin, belong to one of the
     * advisor's colleges.
     */
    protected function mapForEdit(int $id, User $user): array
    {
        $map = $this->mapHeader($id);
        $this->assertCanEdit($map, $user);
        return $map;
    }

    protected function assertCanEdit(array $map, User $user): void
    {
        if (!MapRepository::isEditable($map)) {
            throw new ActionException('This degree map is for a current or past catalog year and is read-only. Clone it into a future year to make changes.', 403);
        }
        $this->assertCollege((string) ($map['college'] ?? ''), $user);
    }

    protected function assertCollege(string $collegeName, User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }
        $collegeId = $this->lookups->collegeId($collegeName);
        if (!$user->canEditCollege($collegeId)) {
            throw new ActionException('You can only edit degree maps for your own college.', 403);
        }
    }
}
