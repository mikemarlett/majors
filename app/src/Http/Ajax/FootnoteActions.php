<?php

declare(strict_types=1);

namespace Majors\Http\Ajax;

use Majors\Auth\User;
use Majors\Support\Request;

final class FootnoteActions extends BaseAction
{
    public function editForm(Request $r, User $user): array
    {
        $id  = $r->id('degree_map_id') ?? throw new ActionException('Missing degree_map_id.');
        $map = $this->maps->find($id) ?? throw new ActionException('Degree map not found.', 404);
        $this->assertCanEdit($map, $user);
        return ['success' => true, 'modal' => $this->forms->footnotesForm($map)];
    }

    /** footnotes[<id|new_x>][note] in the order posted (or a JSON list from the drag handler). */
    public function save(Request $r, User $user): array
    {
        $id = $r->id('degree_map_id') ?? throw new ActionException('Degree map ID is missing.');
        $this->mapForEdit($id, $user);

        $posted = $r->arr('footnotes');
        if ($posted === [] && $r->str('footnotes') !== '') {
            $decoded = json_decode($r->str('footnotes'), true);
            $posted  = is_array($decoded) ? $decoded : [];
        }
        $list = [];
        foreach ($posted as $key => $f) {
            if (!is_array($f)) {
                continue;
            }
            $list[] = ['id' => (string) ($f['id'] ?? $key), 'note' => (string) ($f['note'] ?? '')];
        }
        $this->editor->saveFootnotes($id, $list);
        return ['success' => true, 'message' => 'Footnotes updated.'];
    }

    public function delete(Request $r, User $user): array
    {
        $id  = $r->id('degree_map_id') ?? throw new ActionException('Degree map ID is missing.');
        $this->mapForEdit($id, $user);
        $fid = $r->str('remove_footnote');
        if ($fid === '' ) {
            throw new ActionException('Footnote ID is missing.');
        }
        if (str_starts_with($fid, 'new')) {
            return ['success' => true, 'message' => '']; // never saved; nothing to delete
        }
        if (!$this->editor->deleteFootnote((int) $fid, $id)) {
            throw new ActionException('Footnote not found.', 404);
        }
        return ['success' => true, 'message' => 'Footnote deleted.'];
    }

    /** Select2 source: [{id, text}] */
    public function list(Request $r, User $user): array
    {
        $id  = $r->id('degree_map_id') ?? throw new ActionException('Invalid map ID.');
        $out = [];
        foreach ($this->maps->footnotes($id) as $f) {
            $out[] = ['id' => (string) $f['id'], 'text' => $f['order'] . '. ' . mb_substr(strip_tags((string) $f['note']), 0, 80)];
        }
        return $out;
    }
}
