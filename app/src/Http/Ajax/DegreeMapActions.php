<?php

declare(strict_types=1);

namespace Majors\Http\Ajax;

use Majors\Auth\User;
use Majors\DegreeMaps\MapRepository;
use Majors\Support\Html;
use Majors\Support\Request;

final class DegreeMapActions extends BaseAction
{
    /** The drag-and-drop editor body (HTML), reloaded after every save. */
    public function editorHtml(Request $r, User $user): string
    {
        $id  = $r->id('degree_map_id') ?? throw new ActionException('Missing degree_map_id.');
        $map = $this->maps->find($id) ?? throw new ActionException('Degree map not found.', 404);
        $this->assertCanEdit($map, $user);
        return $this->forms->editor($map, $user);
    }

    /** @return array{modal:string} */
    public function editForm(Request $r, User $user): array
    {
        $id  = $r->id('degree_map_id') ?? throw new ActionException('Missing degree_map_id.');
        $map = $this->maps->find($id) ?? throw new ActionException('Degree map not found.', 404);
        $this->assertCanEdit($map, $user);
        return ['success' => true, 'modal' => $this->forms->mapForm($map, $user)];
    }

    /** @return array{modal:string} */
    public function newForm(Request $r, User $user): array
    {
        $map = $this->forms->emptyMap([
            'college'       => $r->strOrNull('college') ?? '',
            'major'         => $r->strOrNull('major') ?? '(Draft) New Major',
            'degree_type'   => $r->strOrNull('degree_type') ?? '',
            'department'    => $r->strOrNull('department'),
            'academic_year' => $r->int('academic_year') ?: MapRepository::currentAcademicYear() + 1,
        ]);
        if ($map['college'] === 'all') {
            $map['college'] = '';
        }
        return ['success' => true, 'modal' => $this->forms->mapForm($map, $user)];
    }

    /** Create or update the header. @return array<string,mixed> */
    public function saveDetails(Request $r, User $user): array
    {
        $id   = $r->id('degree_map_id');
        $data = [
            'major'         => $r->str('major'),
            'degree_type'   => $r->str('degree_type'),
            'college'       => $r->str('college'),
            'department'    => $r->strOrNull('department'),
            'note'          => $r->strOrNull('note'),
            'academic_year' => $r->int('academic_year') ?: $r->int('academic_year_hidden'),
            'program_id'    => $r->id('program_id'),
        ];
        foreach (['major', 'degree_type', 'college'] as $f) {
            if ($data[$f] === '') {
                throw new ActionException('Please fill out all required fields: major, degree type and college.');
            }
        }
        if ($this->lookups->collegeId($data['college']) === null) {
            throw new ActionException('Unknown college.');
        }
        if ($data['academic_year'] <= MapRepository::currentAcademicYear()) {
            throw new ActionException('New and edited maps must be for a future catalog year.');
        }
        $this->assertCollege($data['college'], $user);

        if ($id !== null) {
            $existing = $this->mapForEdit($id, $user);
            // Year is fixed once created (it defines the version); ignore attempts to change it.
            $data['academic_year'] = (int) $existing['academic_year'];
        }
        $id = $this->editor->saveHeader($data, $id);
        return ['success' => true, 'message' => 'Map saved successfully.', 'degree_map_id' => $id];
    }

    /** @return array{modal:string} */
    public function hoursForm(Request $r, User $user): array
    {
        $id  = $r->id('degree_map_id') ?? throw new ActionException('Missing degree_map_id.');
        $map = $this->maps->find($id) ?? throw new ActionException('Degree map not found.', 404);
        $this->assertCanEdit($map, $user);
        return ['success' => true, 'modal' => $this->forms->hoursForm($map)];
    }

    public function saveHours(Request $r, User $user): array
    {
        $id  = $r->id('degree_map_id') ?? throw new ActionException('Degree map ID is missing.');
        $this->mapForEdit($id, $user);
        $htg = $r->str('hours_to_graduate');
        if ($htg === '') {
            throw new ActionException('You must set a number of hours to graduate.');
        }
        $dm    = $r->arr('degree_map');
        $hours = isset($dm['hours']) && is_array($dm['hours']) ? $dm['hours'] : null;
        if ($hours === null) {
            throw new ActionException('Invalid hours data.');
        }
        $this->editor->saveHours($id, $hours, $htg);
        return ['success' => true, 'message' => 'Degree map hours updated.'];
    }

    public function delete(Request $r, User $user): array
    {
        $id  = $r->id('degree_map_id') ?? throw new ActionException('Missing degree_map_id.');
        $map = $this->mapHeader($id);
        $this->editor->delete($id);
        return ['success' => true, 'message' => sprintf('Deleted %s in %s (%d).', $map['degree_type'], $map['major'], $map['academic_year'])];
    }

    /** Copy a map into the next catalog year (or reuse the copy that already exists). */
    public function clone(Request $r, User $user): array
    {
        $id      = $r->id('degree_map_id') ?? throw new ActionException('Missing degree_map_id.');
        $map     = $this->mapHeader($id);
        $this->assertCollege((string) $map['college'], $user);
        $newYear = MapRepository::currentAcademicYear() + 1;
        foreach ($this->maps->versions($map) as $v) {
            if ((int) $v['academic_year'] === $newYear) {
                return ['success' => true, 'existing' => true, 'degree_map_id' => (int) $v['id'],
                    'message' => 'A ' . ($newYear - 1) . '-' . $newYear . ' version already exists; opening it.'];
            }
        }
        $newId = $this->editor->clone($id, $newYear);
        return ['success' => true, 'degree_map_id' => $newId, 'message' => 'Map cloned into ' . ($newYear - 1) . '-' . $newYear . '.'];
    }

    /** <option>s for the department select, given a college name (HTML). */
    public function departmentOptions(Request $r, User $user): string
    {
        $college  = $r->str('college');
        $selected = $r->strOrNull('department');
        if ($this->lookups->collegeId($college) === null) {
            return '<option value="">None selected</option>';
        }
        return '<option value="">None selected</option>' . Html::options($this->lookups->departmentNames($college), $selected);
    }
}
