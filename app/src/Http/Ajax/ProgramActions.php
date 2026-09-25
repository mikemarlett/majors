<?php

declare(strict_types=1);

namespace Majors\Http\Ajax;

use Majors\Auth\User;
use Majors\Kernel;
use Majors\Majors\ProgramEditor;
use Majors\Majors\ProgramRenderer;
use Majors\Majors\ProgramRepository;
use Majors\Majors\SharedSectionException;
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
        } catch (SharedSectionException $e) {
            throw new ActionException($e->getMessage(), 409);
        } catch (\mysqli_sql_exception $e) {
            error_log('[majors] ' . get_class($e) . ': ' . $e->getMessage());
            throw new ActionException($this->app->isDev() ? $e->getMessage() : 'Database error — nothing was saved.', 500);
        } catch (RuntimeException $e) {
            throw new ActionException($e->getMessage(), 422);
        }
    }

    /**
     * The program page re-rendered with editing markers, for the in-place
     * editor to swap in after a change. @return array{parts:array{top:string,content:string,bottom:string},title:string}
     */
    private function parts(int $programId): array
    {
        $p = $this->programs->find($programId) ?? throw new ActionException('Program not found.', 404);
        $r = new ProgramRenderer($this->app->layout());
        return ['parts' => $r->parts($p, $this->app->maps()->forProgram($programId), true, $this->editor->blockUseCounts()), 'title' => ProgramRenderer::title($p)];
    }

    /** The subset of $keys that were posted (so a popover's partial save writes only its own fields). @return array<string,mixed> */
    private static function posted(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $_POST)) {
                $out[$k] = $_POST[$k];
            }
        }
        return $out;
    }

    // ---- programs ----
    public function search(Request $r, User $user): array
    {
        return ['success' => true, 'results' => $this->editor->search($r->str('q'))];
    }

    public function create(Request $r, User $user): array
    {
        return $this->guard(function () use ($r) {
            $d = ['academic_program' => $r->str('academic_program'), 'credential' => $r->str('credential'), 'program_type' => $r->str('program_type'),
                'basename' => $r->str('basename'), 'college' => $r->str('college'), 'department' => $r->str('department')];
            if ($r->has('graduate')) {           // absent → inferred from the credential
                $d['graduate'] = $r->int('graduate');
            }
            $id = $this->editor->create($d);
            $p  = $this->programs->find($id) ?? [];
            return ['success' => true, 'program_id' => $id, 'redirect' => $this->app->layout()->editUrl($p) . '&flash=' . rawurlencode('Program created. Click anything on the page to write it.')];
        });
    }

    /** GET: the page name a new program would get. */
    public function basenamePreview(Request $r, User $user): array
    {
        $b = $r->str('basename') !== '' ? ProgramEditor::cleanBasename($r->str('basename')) : ProgramEditor::basenameFor($r->str('academic_program'), $r->str('program_type'), $r->str('credential'));
        return ['success' => true, 'basename' => $this->editor->uniqueBasename($b)];
    }

    /**
     * Partial save: only the posted fields change. Flags (checkboxes) are
     * written only when posted, as 0/1 — every form posts them explicitly
     * (a hidden 0 under each checkbox).
     */
    public function saveProgram(Request $r, User $user): array
    {
        $p = $this->program($r);
        return $this->guard(function () use ($r, $p) {
            $d = $_POST;                       // the whole form; the editor only writes known fields
            foreach (['graduate', 'certificate', 'minor', 'badge', 'online_learning', 'online_only', 'is_stem'] as $flag) {
                if (array_key_exists($flag, $d)) {
                    $d[$flag] = $r->int($flag);   // the forms post every box as 0/1 (hidden 0 + checkbox 1)
                }
            }
            $this->editor->saveProgram((int) $p['id'], $d);
            $fresh = $this->programs->find((int) $p['id']) ?? [];
            return ['success' => true, 'message' => 'Saved.', 'fields' => self::stored($fresh, array_keys($d))] + $this->parts((int) $p['id']);
        });
    }

    /** The stored values of the posted keys, so the client can update a field in place without swapping the page. @return array<string,string> */
    private static function stored(array $row, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (is_string($k) && array_key_exists($k, $row) && (is_scalar($row[$k]) || $row[$k] === null)) {
                $out[$k] = (string) ($row[$k] ?? '');
            }
        }
        return $out;
    }

    /** GET: the page re-rendered with editing markers. */
    public function render(Request $r, User $user): array
    {
        $p = $this->program($r);
        return ['success' => true] + $this->parts((int) $p['id']);
    }

    /** GET: the Page settings dialog (fields that are not on the page). */
    public function settingsForm(Request $r, User $user): string
    {
        $p = $this->program($r);
        return $this->app->layout()->render('majors/admin/inplace/settings_form', [
            'program'     => $p,
            'modalities'  => ProgramEditor::MODALITIES,
            'basename_locked' => (string) ($p['cms_path'] ?? '') !== '',
            'cms_url'     => !empty($p['cms_path']) ? 'https://www.wichita.edu' . preg_replace('/\.pcf$/', '.php', (string) $p['cms_path']) : '',
            'degree_maps' => $this->app->maps()->forProgram((int) $p['id']),
            'maps_url'    => $this->app->layout()->url('degree_maps/admin/maps.php') . '?degree_map_id=',
        ]);
    }

    /** GET: photos already on the site (docroot/academics/majors/_images), newest first. */
    public function listImages(Request $r, User $user): array
    {
        $dir = rtrim($this->app->webRoot, '/') . '/_images';
        $q   = mb_strtolower($r->str('q'));
        $out = [];
        if (is_dir($dir)) {
            foreach (scandir($dir, SCANDIR_SORT_NONE) ?: [] as $f) {
                if ($f[0] === '.' || !preg_match('/\.(jpe?g|png|gif|webp)$/i', $f) || !is_file("$dir/$f")) {
                    continue;
                }
                if ($q !== '' && !str_contains(mb_strtolower($f), $q)) {
                    continue;
                }
                $out[] = ['name' => $f, 'url' => '/academics/majors/_images/' . rawurlencode($f), 'mtime' => (int) filemtime("$dir/$f")];
            }
        }
        usort($out, static fn ($a, $b) => $b['mtime'] <=> $a['mtime'] ?: strcmp($a['name'], $b['name']));
        return ['success' => true, 'images' => array_slice($out, 0, 400), 'total' => count($out)];
    }

    // ---- sections ----
    public function saveSection(Request $r, User $user): array
    {
        $p = $this->program($r);
        return $this->guard(function () use ($r, $p) {
            $id = $this->editor->saveSection((int) $p['id'], $r->id('section_id'), [
                'kind' => $r->str('kind'), 'label' => $r->str('label'), 'headline' => $r->str('headline'), 'body' => (string) ($_POST['body'] ?? ''),
                'links' => $_POST['links'] ?? [], 'image_url' => $r->str('image_url'), 'image_alt' => $r->str('image_alt'), 'block_id' => $r->id('block_id'),
            ]);
            return ['success' => true, 'section_id' => $id] + $this->parts((int) $p['id']);
        });
    }

    public function detachSection(Request $r, User $user): array
    {
        $p = $this->program($r);
        return $this->guard(function () use ($r, $p) {
            $this->editor->detachSection((int) $p['id'], $r->id('section_id') ?? throw new ActionException('Missing section_id.'));
            return ['success' => true] + $this->parts((int) $p['id']);
        });
    }

    /** Removes the section; 'removed' carries its fields and predecessor so the client can offer Undo (re-create via add_section). */
    public function deleteSection(Request $r, User $user): array
    {
        $p = $this->program($r);
        return $this->guard(function () use ($r, $p) {
            $removed = $this->editor->deleteSection((int) $p['id'], $r->id('section_id') ?? throw new ActionException('Missing section_id.'));
            return ['success' => true, 'removed' => $removed] + $this->parts((int) $p['id']);
        });
    }

    public function saveSectionOrder(Request $r, User $user): array
    {
        $p = $this->program($r);
        return $this->guard(function () use ($r, $p) {
            $this->editor->reorderSections((int) $p['id'], array_map('intval', (array) ($_POST['order'] ?? [])));
            return ['success' => true] + $this->parts((int) $p['id']);
        });
    }

    // ---- in-place editor: per-field saves and structure ----

    /** Only the posted fields of a section that owns its text; 409 when it shows a shared block. */
    public function saveSectionFields(Request $r, User $user): array
    {
        $p = $this->program($r);
        return $this->guard(function () use ($r, $p) {
            $sid    = $r->id('section_id') ?? throw new ActionException('Missing section_id.');
            $posted = self::posted(['headline', 'body', 'label', 'links', 'image_url', 'image_alt']);
            $this->editor->updateSection((int) $p['id'], $sid, $posted);
            $row    = $this->editor->section((int) $p['id'], $sid);
            $fields = self::stored($row, array_keys($posted));
            if (isset($fields['label']) && $fields['label'] === '') {
                $fields['label'] = 'Inside the Program';   // what the page shows for an empty band label
            }
            return ['success' => true, 'message' => 'Saved.', 'fields' => $fields] + $this->parts((int) $p['id']);
        });
    }

    /** Only the posted fields of a shared block; every page that uses it changes. program_id says which page to re-render. */
    public function saveBlockFields(Request $r, User $user): array
    {
        $p = $this->program($r);
        return $this->guard(function () use ($r, $p) {
            $bid    = $r->id('block_id') ?? throw new ActionException('Missing block_id.');
            $posted = self::posted(['headline', 'body', 'links']);
            $this->editor->updateBlock($bid, $posted);
            $uses = $this->editor->blockUseCounts()[$bid] ?? 0;
            return ['success' => true, 'message' => "Saved on all $uses pages.", 'uses' => $uses, 'fields' => self::stored($this->editor->block($bid) ?? [], array_keys($posted))] + $this->parts((int) $p['id']);
        });
    }

    /** A new card / feature (or a section showing a shared block) right after a section (after=0: at the end). */
    public function addSection(Request $r, User $user): array
    {
        $p = $this->program($r);
        return $this->guard(function () use ($r, $p) {
            // Undo of a removal posts the removed section's fields back through here.
            $id = $this->editor->insertSectionAfter((int) $p['id'], $r->int('after'), [
                'kind' => $r->str('kind') ?: 'teaser', 'block_id' => $r->id('block_id'), 'label' => $r->str('label'),
                'headline' => $r->str('headline'), 'body' => (string) ($_POST['body'] ?? ''), 'links' => $_POST['links'] ?? [],
                'image_url' => $r->str('image_url'), 'image_alt' => $r->str('image_alt'),
            ]);
            return ['success' => true, 'section_id' => $id] + $this->parts((int) $p['id']);
        });
    }

    public function moveSection(Request $r, User $user): array
    {
        $p = $this->program($r);
        return $this->guard(function () use ($r, $p) {
            $this->editor->moveSection((int) $p['id'], $r->id('section_id') ?? throw new ActionException('Missing section_id.'), $r->enum('dir', ['up', 'down'], 'down'));
            return ['success' => true] + $this->parts((int) $p['id']);
        });
    }

    public function swapSectionBlock(Request $r, User $user): array
    {
        $p = $this->program($r);
        return $this->guard(function () use ($r, $p) {
            $this->editor->swapSectionBlock((int) $p['id'], $r->id('section_id') ?? throw new ActionException('Missing section_id.'), $r->id('block_id') ?? throw new ActionException('Missing block_id.'));
            return ['success' => true] + $this->parts((int) $p['id']);
        });
    }

    // ---- similar ----
    /** Replaces the curated list; the response carries the curated list as stored (what the control panel's popover shows). */
    public function saveSimilar(Request $r, User $user): array
    {
        $p = $this->program($r);
        return $this->guard(function () use ($r, $p) {
            $this->editor->saveSimilar((int) $p['id'], array_map('intval', (array) ($_POST['similar'] ?? [])));
            $curated = array_values(array_filter($this->programs->similarForEditing((int) $p['id']), static fn ($s) => $s['source'] === 'curated'));
            return ['success' => true, 'message' => 'Similar programs saved.', 'similar' => array_map(static fn ($s) => ['id' => (int) $s['id'], 'name' => (string) $s['academic_program'], 'retired' => !empty($s['retired'])], $curated)] + $this->parts((int) $p['id']);
        });
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
