<?php
/**
 * The program editor page.
 * Variables: $program, $sections, $similar, $colleges, $departments (college/department rows), $blocks, $modalities, $facts,
 *            $public_url, $cms_url, $degree_maps, $maps_url, $flash, $csrf
 * @var \Majors\View\Layout $t
 */
$p = $program;
$v = static fn (string $k): string => (string) ($p[$k] ?? '');
$buttons = json_decode((string) ($p['buttons'] ?? '[]'), true) ?: [];
$deptsByCollege = [];
foreach ($departments as $d) {
    $deptsByCollege[$d['college']][] = $d['department'];
}
?>
<?php if (!empty($flash)): ?>
<div class="<?= $t->cls('section.shade') ?> noprint"><p class="ma-flash"><?= $t->e($flash) ?></p></div>
<?php endif; ?>

<section class="<?= $t->cls('section') ?> ma-editor-head">
	<p class="ma-links">
		<a href="<?= $t->e($t->url('_admin/index.php')) ?>">← All programs</a> ·
		<a href="<?= $t->e($public_url) ?>" target="_blank" rel="noopener">Preview the page</a>
<?php if ($cms_url !== ''): ?> · <a href="<?= $t->e($cms_url) ?>" target="_blank" rel="noopener">CMS page it was imported from</a><?php endif; ?>
		· <a href="<?= $t->e($t->url('_admin/blocks.php')) ?>">Shared blocks</a>
		<?php if ($v('status') === 'retired'): ?><span class="ma-danger"> · RETIRED — not listed publicly</span><?php endif; ?>
	</p>
</section>

<section class="<?= $t->cls('section') ?>">
	<form id="programForm" class="ma-user-form" method="post" data-program-id="<?= (int) $p['id'] ?>">
		<input type="hidden" name="program_id" value="<?= (int) $p['id'] ?>">
		<h2 class="<?= $t->cls('heading4') ?>">Program</h2>
		<div class="ma-row">
			<div class="ma-col-6"><label for="f_name">Name <span class="ma-required">*</span></label><input type="text" id="f_name" name="academic_program" value="<?= $t->e($v('academic_program')) ?>" required maxlength="255"></div>
			<div class="ma-col-3"><label for="f_cred">Credential</label><input type="text" id="f_cred" name="credential" value="<?= $t->e($v('credential')) ?>" list="credentials" maxlength="100"><datalist id="credentials"><?php foreach (['Major', 'Minor', "Master's", 'Doctorate', 'Graduate Certificate', 'Undergraduate Certificate', "Bachelor's to Master's", 'Badge', 'Field Major', 'Postbaccalaureate'] as $c): ?><option value="<?= $t->e($c) ?>"><?php endforeach; ?></datalist></div>
			<div class="ma-col-3"><label for="f_type">Type</label><input type="text" id="f_type" name="program_type" value="<?= $t->e($v('program_type')) ?>" maxlength="60" placeholder="BS, MA, MACC…"></div>
		</div>
		<div class="ma-row">
			<div class="ma-col-4"><label for="f_college">College</label><input type="text" id="f_college" name="college" value="<?= $t->e($v('college')) ?>" list="colleges" maxlength="255"><datalist id="colleges"><?php foreach ($colleges as $c): ?><option value="<?= $t->e($c) ?>"><?php endforeach; ?></datalist></div>
			<div class="ma-col-4"><label for="f_dept">Department</label><input type="text" id="f_dept" name="department" value="<?= $t->e($v('department')) ?>" list="depts" maxlength="255"><datalist id="depts"><?php foreach ($departments as $d): ?><option value="<?= $t->e($d['department']) ?>"><?= $t->e($d['college']) ?></option><?php endforeach; ?></datalist></div>
			<div class="ma-col-2"><label for="f_college_url">College link</label><input type="text" id="f_college_url" name="college_url" value="<?= $t->e($v('college_url')) ?>" placeholder="/academics/…"></div>
			<div class="ma-col-2"><label for="f_dept_url">Department link</label><input type="text" id="f_dept_url" name="department_url" value="<?= $t->e($v('department_url')) ?>" placeholder="/academics/…"></div>
		</div>
		<div class="ma-row ma-flags">
			<label><input type="checkbox" name="graduate" value="1"<?= !empty($p['graduate']) ? ' checked' : '' ?>> Graduate</label>
			<label><input type="checkbox" name="certificate" value="1"<?= !empty($p['certificate']) ? ' checked' : '' ?>> Certificate</label>
			<label><input type="checkbox" name="minor" value="1"<?= !empty($p['minor']) ? ' checked' : '' ?>> Minor</label>
			<label><input type="checkbox" name="badge" value="1"<?= !empty($p['badge']) ? ' checked' : '' ?>> Badge</label>
			<label><input type="checkbox" name="online_learning" value="1"<?= !empty($p['online_learning']) ? ' checked' : '' ?>> Available online</label>
			<label><input type="checkbox" name="online_only" value="1"<?= !empty($p['online_only']) ? ' checked' : '' ?>> Online only</label>
			<label><input type="checkbox" name="is_stem" value="1"<?= !empty($p['is_stem']) ? ' checked' : '' ?>> STEM program</label>
			<label>Status <select name="status"><option value="active"<?= $v('status') !== 'retired' ? ' selected' : '' ?>>Active</option><option value="retired"<?= $v('status') === 'retired' ? ' selected' : '' ?>>Retired (hidden)</option></select></label>
		</div>
		<div class="ma-row">
			<div class="ma-col-4"><label for="f_sort">Sort as (optional, e.g. "Engineering, Aerospace")</label><input type="text" id="f_sort" name="sort_title" value="<?= $t->e($v('sort_title')) ?>" maxlength="255"></div>
			<div class="ma-col-4"><label for="f_basename">Page name (basename)</label><input type="text" id="f_basename" name="basename" value="<?= $t->e($v('basename')) ?>" maxlength="200" pattern="[a-z0-9_]+"><span class="help-block">Letters, digits, underscores. This is the CMS page's name, and the key the importer matches on.</span></div>
			<div class="ma-col-4"><label for="f_note">Listing note</label><input type="text" id="f_note" name="note" value="<?= $t->e($v('note')) ?>" maxlength="255"></div>
		</div>

		<h2 class="<?= $t->cls('heading4') ?>">Program card</h2>
		<label for="f_desc">Description</label>
		<textarea id="f_desc" name="description" class="ma-html" rows="5"><?= $t->e($v('description')) ?></textarea>
		<div class="ma-row">
			<div class="ma-col-6"><label for="f_learn">"Learn how…" line</label><input type="text" id="f_learn" name="learn_how" value="<?= $t->e($v('learn_how')) ?>" maxlength="255" placeholder="Learn how aerospace engineering is the right fit for you."></div>
			<div class="ma-col-6"><label>Buttons</label>
				<div class="ma-links-edit" data-name="buttons">
<?php foreach (array_merge($buttons, [['text' => '', 'href' => '']]) as $i => $b): ?>
					<div class="ma-link-row"><input type="text" name="buttons[text][]" value="<?= $t->e($b['text'] ?? '') ?>" placeholder="Button text" maxlength="200"><input type="text" name="buttons[href][]" value="<?= $t->e($b['href'] ?? '') ?>" placeholder="https://…" maxlength="500"><button type="button" class="ma-link-remove" title="Remove">×</button></div>
<?php endforeach; ?>
					<button type="button" class="<?= $t->cls('button.small') ?> ma-link-add">+ button</button>
				</div>
			</div>
		</div>
		<div class="ma-row">
			<div class="ma-col-4"><label for="f_img">Photo (URL)</label><input type="text" id="f_img" name="image_url" value="<?= $t->e($v('image_url')) ?>" maxlength="255" placeholder="/academics/majors/_images/…"></div>
			<div class="ma-col-4"><label for="f_img_alt">Photo alt text</label><input type="text" id="f_img_alt" name="image_alt" value="<?= $t->e($v('image_alt')) ?>"></div>
			<div class="ma-col-2"><label for="f_img_cap">Caption</label><input type="text" id="f_img_cap" name="image_caption" value="<?= $t->e($v('image_caption')) ?>"></div>
			<div class="ma-col-2"><label for="f_img_cred">Credit</label><input type="text" id="f_img_cred" name="image_credit" value="<?= $t->e($v('image_credit')) ?>" maxlength="255"></div>
		</div>
<?php if ($v('image_url') !== ''): ?>
		<p><img src="<?= $t->e($t->img($v('image_url'))) ?>" alt="" style="max-height:120px"></p>
<?php endif; ?>

		<h2 class="<?= $t->cls('heading4') ?>">Program details <small class="help-block" style="display:inline">(the box on graduate pages; leave blank what doesn't apply)</small></h2>
		<div class="ma-row">
			<div class="ma-col-3"><label for="f_degree">Degree</label><input type="text" id="f_degree" name="degree_title" value="<?= $t->e($v('degree_title')) ?>" maxlength="120" placeholder="PhD, MS, Graduate Certificate…"></div>
			<div class="ma-col-3"><label for="f_modality">Modality</label><select id="f_modality" name="modality"><?php foreach ($modalities as $m): ?><option value="<?= $t->e($m) ?>"<?= $v('modality') === $m ? ' selected' : '' ?>><?= $m === '' ? '—' : $t->e($m) ?></option><?php endforeach; ?></select></div>
			<div class="ma-col-3"><label for="f_hours">Credit hours</label><input type="text" id="f_hours" name="credit_hours" value="<?= $t->e($v('credit_hours')) ?>" maxlength="40" placeholder="30 or 30–36"></div>
			<div class="ma-col-3"><label for="f_terms">Entry term</label><input type="text" id="f_terms" name="entry_terms" value="<?= $t->e($v('entry_terms')) ?>" maxlength="80" placeholder="Fall, Spring · Any"></div>
		</div>
		<div class="ma-row">
			<div class="ma-col-4"><label for="f_coord">Program coordinator</label><input type="text" id="f_coord" name="coordinator_name" value="<?= $t->e($v('coordinator_name')) ?>" maxlength="120"></div>
			<div class="ma-col-4"><label for="f_coord_email">Coordinator email</label><input type="email" id="f_coord_email" name="coordinator_email" value="<?= $t->e($v('coordinator_email')) ?>" maxlength="120"></div>
			<div class="ma-col-2"><label for="f_coord_phone">Phone</label><input type="text" id="f_coord_phone" name="coordinator_phone" value="<?= $t->e($v('coordinator_phone')) ?>" maxlength="40"></div>
			<div class="ma-col-2"><label for="f_catalog">Catalog page</label><input type="text" id="f_catalog" name="catalog_url" value="<?= $t->e($v('catalog_url')) ?>" maxlength="255"></div>
		</div>

		<h2 class="<?= $t->cls('heading4') ?>">Search engines</h2>
		<div class="ma-row">
			<div class="ma-col-8"><label for="f_meta">Meta description</label><textarea id="f_meta" name="meta_description" rows="2"><?= $t->e($v('meta_description')) ?></textarea></div>
			<div class="ma-col-4"><label for="f_kw">Keywords</label><textarea id="f_kw" name="meta_keywords" rows="2"><?= $t->e($v('meta_keywords')) ?></textarea></div>
		</div>
		<div class="ma-actions"><button type="submit" class="<?= $t->cls('button.accent') ?>">Save program</button> <span class="ma-save-status" aria-live="polite"></span></div>
	</form>
</section>

<section class="<?= $t->cls('section') ?>">
	<h2 class="<?= $t->cls('heading4') ?>">Page sections</h2>
	<p class="help-block">Drag to reorder. Cards sit two across in page order; a feature (Inside the Program) is a full-width band with a photo. A section that uses a <strong>shared block</strong> shows the same text as every other page using it — edit the block to change them all, or Customize to change this page only.</p>
	<div id="sectionsWrap"><?= $t->render('majors/admin/sections', ['program' => $p, 'sections' => $sections]) ?></div>
	<div class="ma-actions">
		<button type="button" class="<?= $t->cls('button') ?> add-section" data-kind="teaser">+ Card</button>
		<button type="button" class="<?= $t->cls('button') ?> add-section" data-kind="feature">+ Feature</button>
		<button type="button" class="<?= $t->cls('button.subtle') ?> add-section" data-kind="teaser" data-block="1">+ Card from a shared block</button>
	</div>
</section>

<section class="<?= $t->cls('section') ?>">
	<h2 class="<?= $t->cls('heading4') ?>">Similar programs</h2>
	<form id="similarForm" method="post">
		<input type="hidden" name="program_id" value="<?= (int) $p['id'] ?>">
		<label for="f_similar" class="<?= $t->cls('sr_only') ?>">Similar programs</label>
		<select id="f_similar" name="similar[]" multiple class="select2-programs" data-placeholder="Type a program name…" style="width:100%">
<?php foreach ($similar as $s): ?>
			<option value="<?= (int) $s['id'] ?>" selected><?= $t->e($s['academic_program'] . ' (' . ($s['credential'] ?: $s['program_type']) . ')') ?></option>
<?php endforeach; ?>
		</select>
		<div class="ma-actions"><button type="submit" class="<?= $t->cls('button') ?>">Save similar programs</button> <span class="ma-save-status" aria-live="polite"></span></div>
	</form>
</section>

<?php if ($degree_maps): ?>
<section class="<?= $t->cls('section') ?>">
	<h2 class="<?= $t->cls('heading4') ?>">Degree maps linked to this program</h2>
	<ul>
<?php foreach ($degree_maps as $m): ?>
		<li><a href="<?= $t->e($maps_url . (int) $m['id']) ?>"><?= $t->e($m['degree_type'] . ' in ' . $m['major'] . ' (' . ($m['academic_year'] - 1) . '–' . $m['academic_year'] . ')') ?></a></li>
<?php endforeach; ?>
	</ul>
	<p class="help-block">Maps are linked by their own "Program page" setting in the degree-maps admin; they show under Curriculum on the public page.</p>
</section>
<?php endif; ?>
