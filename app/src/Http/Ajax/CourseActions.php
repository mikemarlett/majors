<?php

declare(strict_types=1);

namespace Majors\Http\Ajax;

use Majors\Auth\User;
use Majors\Support\Request;

final class CourseActions extends BaseAction
{
    /** Course modal for an existing course (id) or a new one (degree_map_id + year + semester). */
    public function editForm(Request $r, User $user): array
    {
        $mapId = $r->id('degree_map_id') ?? throw new ActionException('Missing degree_map_id.');
        $map   = $this->maps->find($mapId) ?? throw new ActionException('Degree map not found.', 404);
        $this->assertCanEdit($map, $user);

        $year     = $r->int('year') ?: 1;
        $semester = $r->int('semester') ?: 1;
        $courseId = $r->id('id') ?? $r->id('course_id');

        if ($courseId !== null) {
            $course = $this->maps->course($courseId);
            if ($course === null || (int) $course['degree_map_id'] !== $mapId) {
                throw new ActionException('Course not found on this map.', 404);
            }
            $year     = (int) $course['year'];
            $semester = (int) $course['semester'];
        } else {
            $course = $this->forms->blankCourse($mapId, $year, $semester);
        }
        return ['success' => true, 'modal' => $this->forms->courseForm($course, $map, $year, $semester)];
    }

    public function save(Request $r, User $user): array
    {
        $mapId = $r->id('degree_map_id') ?? throw new ActionException('Missing degree_map_id.');
        $this->mapForEdit($mapId, $user);

        foreach (['course_info', 'year', 'semester'] as $f) {
            if ($r->str($f) === '') {
                throw new ActionException("Missing required field: {$f}");
            }
        }
        $courseId = $r->id('course_id');
        if ($courseId !== null) {
            $existing = $this->maps->course($courseId);
            if ($existing === null || (int) $existing['degree_map_id'] !== $mapId) {
                throw new ActionException('Course not found on this map.', 404);
            }
        }
        $footnotes = array_values(array_filter(array_map('intval', $r->arr('footnotes')), static fn (int $i) => $i > 0));
        $sge       = $r->str('sge');
        if ($sge !== '' && !preg_match('/^0[1-7]0$/', $sge)) {
            throw new ActionException('Invalid SGE code.');
        }
        $year     = $r->int('year');
        $semester = $r->int('semester');
        if ($year < 1 || $year > 4 || $semester < 1 || $semester > 3) {
            throw new ActionException('Year must be 1-4 and semester 1-3.');
        }

        $id = $this->editor->saveCourse([
            'id'                => $courseId,
            'degree_map_id'     => $mapId,
            'course_info'       => $r->str('course_info'),
            'hours'             => $r->str('hours'),
            'year'              => $year,
            'semester'          => $semester,
            'order'             => $r->int('order') ?: null,
            'footnote_ids'      => $footnotes,
            'sge'               => $sge,
            'extra'             => $r->strOrNull('extra'),
            'scbcrse_subj_code' => $r->strOrNull('scbcrse_subj_code'),
            'scbcrse_crse_numb' => $r->strOrNull('scbcrse_crse_numb'),
        ]);
        return ['success' => true, 'course_id' => $id, 'message' => 'Course saved.'];
    }

    public function delete(Request $r, User $user): array
    {
        $courseId = $r->id('course_id') ?? throw new ActionException('Invalid course ID.');
        $course   = $this->maps->course($courseId) ?? throw new ActionException('Course not found.', 404);
        $mapId    = (int) $course['degree_map_id'];
        $this->mapForEdit($mapId, $user);
        if (!$this->editor->deleteCourse($courseId, $mapId)) {
            throw new ActionException('Failed to delete course.');
        }
        return ['success' => true, 'status' => 'success', 'message' => 'Course deleted.'];
    }

    /** Drag-and-drop: updatedOrder = JSON list of {id, order, year, semester}. */
    public function saveOrder(Request $r, User $user): array
    {
        $mapId = $r->id('degree_map_id') ?? throw new ActionException('Missing degree_map_id.');
        $this->mapForEdit($mapId, $user);
        $items = json_decode($r->str('updatedOrder'), true);
        if (!is_array($items)) {
            throw new ActionException('Invalid order data.');
        }
        $clean = [];
        foreach ($items as $i) {
            if (!is_array($i)) {
                continue;
            }
            $y = (int) ($i['year'] ?? 0);
            $s = (int) ($i['semester'] ?? 0);
            if ($y < 1 || $y > 4 || $s < 1 || $s > 3) {
                continue;
            }
            $clean[] = ['id' => (int) ($i['id'] ?? 0), 'order' => (int) ($i['order'] ?? 0), 'year' => $y, 'semester' => $s];
        }
        $this->editor->saveCourseOrder($mapId, $clean);
        return ['success' => true];
    }
}
