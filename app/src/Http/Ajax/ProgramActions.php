<?php

declare(strict_types=1);

namespace Majors\Http\Ajax;

use Majors\Auth\User;
use Majors\Kernel;
use Majors\Majors\ProgramEditor;
use Majors\Majors\ProgramRepository;
use Majors\Support\Request;
use RuntimeException;

/** Majors editor actions (role marketing; super admins implied). Routed by AjaxKernel. */
final class ProgramActions
{
    private readonly ProgramEditor $editor;
    private readonly ProgramRepository $programs;

    public function __construct(private readonly Kernel $app)
    {
        $this->editor   = new ProgramEditor($app->db());
        $this->programs = $app->programs();
    }

    private function program(Request $r, string $key = 'program_id'): array
    {
        $id = $r->id($key) ?? throw new ActionException("Missing $key.");
        return $this->programs->find($id) ?? throw new ActionException('Program not found.', 404);
    }

    /** @return array<string,mixed> */
    private function guard(callable $fn): array
    {
        try {
            return $fn();
        } catch (RuntimeException $e) {
            throw new ActionException($e->getMessage(), 422);
        }
    }

    // ---- programs ----
    public function search(Request $r, User $user): array
    {
        return ['success' => true, 'results' => $this->editor->search($r->str('q'))];
    }

    public function create(Request $r, User $user): array
    {
        return $this->guard(function () use ($r) {
            $id = $this->editor->create(['academic_program' => $r->str('academic_program'), 'credential' => $r->str('credential'), 'program_type' => $r->str('program_type'), 'graduate' => $r->int('graduate')]);
            return ['success' => true, 'program_id' => $id, 'redirect' => $this->app->layout()->url('_admin/program.php') . '?id=' . $id . '&flash=' . rawurlencode('Program created. Fill in the card, then add sections.')];
        });
    }

    public function saveProgram(Request $r, User $user): array
    {
        $p = $this->program($r);
        return $this->guard(function () use ($r, $p) {
            $d = $_POST;                       // the whole form; the editor only writes known fields
            foreach (['graduate', 'certificate', 'minor', 'badge', 'online_learning', 'online_only', 'is_stem'] as $flag) {
                $d[$flag] = $r->int($flag);    // unchecked boxes are absent → 0
            }
            $this->editor->saveProgram((int) $p['id'], $d);
            return ['success' => true, 'message' => 'Saved.'];
        });
    }

    // ---- sections ----
    public function sectionForm(Request $r, User $user): string
    {
        $p  = $this->program($r);
        $id = $r->id('section_id');
        $section = null;
        foreach ($p['sections'] as $s) {
            if ($id !== null && $s['id'] === $id) {
                $section = $s;
            }
        }
        if ($id !== null && $section === null) {
            throw new ActionException('Section not found.', 404);
        }
        // For a shared section, show the block's text read-only (editing happens on the block).
        return $this->app->layout()->render('majors/admin/section_form', [
            'program' => $p, 'section' => $section, 'blocks' => $this->editor->blocks(), 'kinds' => ProgramEditor::KINDS,
            'default_kind' => $r->str('kind') !== '' ? $r->str('kind') : 'teaser',
        ]);
    }

    public function saveSection(Request $r, User $user): array
    {
        $p = $this->program($r);
        return $this->guard(function () use ($r, $p) {
            $id = $this->editor->saveSection((int) $p['id'], $r->id('section_id'), [
                'kind' => $r->str('kind'), 'label' => $r->str('label'), 'headline' => $r->str('headline'), 'body' => (string) ($_POST['body'] ?? ''),
                'links' => $_POST['links'] ?? [], 'image_url' => $r->str('image_url'), 'image_alt' => $r->str('image_alt'), 'block_id' => $r->id('block_id'),
            ]);
            return ['success' => true, 'section_id' => $id, 'html' => $this->sectionsHtml((int) $p['id'])];
        });
    }

    public function detachSection(Request $r, User $user): array
    {
        $p = $this->program($r);
        $this->editor->detachSection((int) $p['id'], $r->id('section_id') ?? throw new ActionException('Missing section_id.'));
        return ['success' => true, 'html' => $this->sectionsHtml((int) $p['id'])];
    }

    public function deleteSection(Request $r, User $user): array
    {
        $p = $this->program($r);
        $this->editor->deleteSection((int) $p['id'], $r->id('section_id') ?? throw new ActionException('Missing section_id.'));
        return ['success' => true, 'html' => $this->sectionsHtml((int) $p['id'])];
    }

    public function saveSectionOrder(Request $r, User $user): array
    {
        $p   = $this->program($r);
        $ids = array_map('intval', (array) ($_POST['order'] ?? []));
        $this->editor->reorderSections((int) $p['id'], $ids);
        return ['success' => true];
    }

    private function sectionsHtml(int $programId): string
    {
        $p = $this->programs->find($programId) ?? [];
        return $this->app->layout()->render('majors/admin/sections', ['program' => $p, 'sections' => $p['sections'] ?? []]);
    }

    // ---- similar ----
    public function saveSimilar(Request $r, User $user): array
    {
        $p = $this->program($r);
        $this->editor->saveSimilar((int) $p['id'], array_map('intval', (array) ($_POST['similar'] ?? [])));
        return ['success' => true, 'message' => 'Similar programs saved.'];
    }

    // ---- shared blocks ----
    public function blockForm(Request $r, User $user): string
    {
        $id    = $r->id('block_id');
        $block = $id !== null ? ($this->editor->block($id) ?? throw new ActionException('Block not found.', 404)) : null;
        return $this->app->layout()->render('majors/admin/block_form', [
            'block' => $block, 'users' => $id !== null ? $this->editor->blockUsers($id) : [], 'program_url' => $this->app->layout()->url('_admin/program.php') . '?id=',
        ]);
    }

    public function saveBlock(Request $r, User $user): array
    {
        return $this->guard(function () use ($r) {
            $id = $this->editor->saveBlock($r->id('block_id'), ['slug' => $r->str('slug'), 'headline' => $r->str('headline'), 'body' => (string) ($_POST['body'] ?? ''), 'links' => $_POST['links'] ?? [], 'note' => $r->str('note')]);
            return ['success' => true, 'block_id' => $id, 'message' => 'Block saved. Every page that uses it shows the new text.'];
        });
    }

    public function deleteBlock(Request $r, User $user): array
    {
        return $this->guard(function () use ($r) {
            $this->editor->deleteBlock($r->id('block_id') ?? throw new ActionException('Missing block_id.'));
            return ['success' => true];
        });
    }
}
