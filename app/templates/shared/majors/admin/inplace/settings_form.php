<?php
/**
 * In-place editor: the Page settings form (things that are not on the page).
 * Loaded into a popover; posts to save_program. Variables: $program, $cms_url, $degree_maps, $maps_url, $modalities, $basename_locked
 * @var \Majors\View\Layout $t
 */
$p = $program;
$v = static fn (string $k): string => (string) ($p[$k] ?? '');
$flags = ['online_learning' => 'Available online', 'online_only' => 'Online only', 'minor' => 'Minor', 'certificate' => 'Certificate', 'badge' => 'Badge'];
?>
<form class="ma-settings" data-ma-settings-form>
	<input type="hidden" name="program_id" value="<?= (int) $p['id'] ?>">
	<div class="ma-grid-2">
		<div class="ma-field"><label for="ms_sort">Sort as (optional)</label><input type="text" id="ms_sort" name="sort_title" value="<?= $t->e($v('sort_title')) ?>" maxlength="255" placeholder="e.g. Engineering, Aerospace"><div class="ma-help">Also lists the program under this name in the A–Z list.</div></div>
		<div class="ma-field"><label for="ms_note">Listing note</label><input type="text" id="ms_note" name="note" value="<?= $t->e($v('note')) ?>" maxlength="255"></div>
<?php if (!empty($basename_locked)): ?>
		<div class="ma-field ma-field--span"><label>Page name (the public address)</label><div class="ma-static"><?= $t->e($t->url('index.php')) ?>?program=<strong><?= $t->e($v('basename')) ?></strong></div><div class="ma-help">This is the name of the CMS page the program was imported from and the key the importer matches on, so it is fixed here.</div></div>
<?php else: ?>
		<div class="ma-field ma-field--span"><label for="ms_basename">Page name (the public address)</label><input type="text" id="ms_basename" name="basename" value="<?= $t->e($v('basename')) ?>" maxlength="120" pattern="[a-z0-9_]+"><div class="ma-help"><?= $t->e($t->url('index.php')) ?>?program=<span data-ma-basename-echo><?= $t->e($v('basename')) ?></span> — lower-case letters, digits and underscores. Changing it changes the public link; anything that linked to the old address breaks.</div></div>
<?php endif; ?>
		<div class="ma-field"><label for="ms_status">Status</label><select id="ms_status" name="status"><option value="active"<?= $v('status') !== 'retired' ? ' selected' : '' ?>>Active</option><option value="retired"<?= $v('status') === 'retired' ? ' selected' : '' ?>>Retired (hidden from the public lists)</option></select></div>
		<div class="ma-field"><label for="ms_catalog">Catalog page</label><input type="url" id="ms_catalog" name="catalog_url" value="<?= $t->e($v('catalog_url')) ?>" maxlength="255" placeholder="https://catalog.wichita.edu/…"><div class="ma-help">Seeded from the catalog; not shown on the page.</div></div>
	</div>
<?php if (empty($p['graduate'])): ?>
	<details class="ma-settings__more">
		<summary>Program details and coordinator (shown on graduate pages; set them here for other programs)</summary>
		<div class="ma-grid-2">
			<div class="ma-field"><label for="ms_degree">Degree</label><input type="text" id="ms_degree" name="degree_title" value="<?= $t->e($v('degree_title')) ?>" maxlength="120" placeholder="PhD, MS, Graduate Certificate…"></div>
			<div class="ma-field"><label for="ms_modality">Modality</label><select id="ms_modality" name="modality"><?php foreach ($modalities as $m): ?><option value="<?= $t->e($m) ?>"<?= $v('modality') === $m ? ' selected' : '' ?>><?= $m === '' ? '—' : $t->e($m) ?></option><?php endforeach; ?></select></div>
			<div class="ma-field"><label for="ms_hours">Credit hours</label><input type="text" id="ms_hours" name="credit_hours" value="<?= $t->e($v('credit_hours')) ?>" maxlength="40" placeholder="30 or 30–36"></div>
			<div class="ma-field"><label for="ms_terms">Entry term</label><input type="text" id="ms_terms" name="entry_terms" value="<?= $t->e($v('entry_terms')) ?>" maxlength="80" placeholder="Fall, Spring · Any"></div>
			<div class="ma-field"><label for="ms_coord">Program coordinator</label><input type="text" id="ms_coord" name="coordinator_name" value="<?= $t->e($v('coordinator_name')) ?>" maxlength="120"></div>
			<div class="ma-field"><label for="ms_coord_email">Coordinator email</label><input type="email" id="ms_coord_email" name="coordinator_email" value="<?= $t->e($v('coordinator_email')) ?>" maxlength="120"></div>
			<div class="ma-field"><label for="ms_coord_phone">Coordinator phone</label><input type="text" id="ms_coord_phone" name="coordinator_phone" value="<?= $t->e($v('coordinator_phone')) ?>" maxlength="40"></div>
		</div>
	</details>
<?php endif; ?>
	<div class="ma-field"><label>Listed under</label>
		<div class="ma-flags-row">
<?php foreach ($flags as $name => $label): ?>
			<label class="ma-field ma-field--check"><input type="hidden" name="<?= $name ?>" value="0"><input type="checkbox" name="<?= $name ?>" value="1"<?= !empty($p[$name]) ? ' checked' : '' ?>> <span><?= $t->e($label) ?></span></label>
<?php endforeach; ?>
		</div>
		<div class="ma-help">Undergraduate / graduate and STEM are set from the program type on the page.</div>
	</div>
	<div class="ma-field"><label for="ms_meta">Search engine description</label><textarea id="ms_meta" name="meta_description" rows="2"><?= $t->e($v('meta_description')) ?></textarea></div>
	<div class="ma-field"><label for="ms_kw">Search keywords</label><textarea id="ms_kw" name="meta_keywords" rows="2"><?= $t->e($v('meta_keywords')) ?></textarea></div>
<?php if ($cms_url !== '' || $degree_maps): ?>
	<div class="ma-help ma-settings__info">
<?php if ($cms_url !== ''): ?>
		<p>Imported from the CMS page <a href="<?= $t->e($cms_url) ?>" target="_blank" rel="noopener"><?= $t->e($cms_url) ?></a>.</p>
<?php endif; ?>
<?php if ($degree_maps): ?>
		<p>Degree maps linked to this program (set in the degree-maps admin; they show under Curriculum):
<?php foreach ($degree_maps as $i => $m): ?><?= $i ? ', ' : '' ?><a href="<?= $t->e($maps_url . (int) $m['id']) ?>" target="_blank" rel="noopener"><?= $t->e($m['degree_type'] . ' in ' . $m['major'] . ' (' . ($m['academic_year'] - 1) . '–' . $m['academic_year'] . ')') ?></a><?php endforeach; ?>.</p>
<?php endif; ?>
	</div>
<?php endif; ?>
	<div class="ma-btn-row">
		<button type="submit" class="ma-btn ma-btn--accent">Save settings</button>
		<button type="button" class="ma-btn ma-btn--ghost" data-ma-cancel>Cancel</button>
	</div>
</form>
