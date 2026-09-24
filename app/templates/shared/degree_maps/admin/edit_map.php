<?php
/**
 * The editor: header with edit buttons, SGE warnings, four year tables whose
 * cells are drag-and-drop lists of courses, footnotes and the SGE key.
 * Reloaded via ajax (get_degree_map) after each save.
 *
 * Variables: $map, $years[y => label, semesters[s => name, courses[], manual, calc], total, calc],
 *            $year_label, $title, $sge_warnings[], $note_footnote, $footnotes[], $sge_key, $permalink, $public_url
 * @var \Majors\View\Layout $t
 */
$pencil = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="ma-icon" aria-hidden="true"><path d="M21.731 2.269a2.625 2.625 0 0 0-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 0 0 0-3.712ZM19.513 8.199l-3.712-3.712-8.4 8.4a5.25 5.25 0 0 0-1.32 2.214l-.8 2.685a.75.75 0 0 0 .933.933l2.685-.8a5.25 5.25 0 0 0 2.214-1.32l8.4-8.4Z"/><path d="M5.25 5.25a3 3 0 0 0-3 3v10.5a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3V13.5a.75.75 0 0 0-1.5 0v5.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V8.25a1.5 1.5 0 0 1 1.5-1.5h5.25a.75.75 0 0 0 0-1.5H5.25Z"/></svg>';
$grip   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="ma-icon" aria-hidden="true"><path fill-rule="evenodd" d="M6.97 2.47a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 0 1-1.06 1.06L8.25 4.81V16.5a.75.75 0 0 1-1.5 0V4.81L3.53 8.03a.75.75 0 0 1-1.06-1.06l4.5-4.5Zm9.53 4.28a.75.75 0 0 1 .75.75v11.69l3.22-3.22a.75.75 0 1 1 1.06 1.06l-4.5 4.5a.75.75 0 0 1-1.06 0l-4.5-4.5a.75.75 0 1 1 1.06-1.06l3.22 3.22V7.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd"/></svg>';
$plus   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="ma-icon" aria-hidden="true"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 9a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25V15a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V9Z" clip-rule="evenodd"/></svg>';
?>
<div class="dm ma-editor" id="degree-map-editor" data-map-id="<?= (int) $map['id'] ?>">
	<input type="hidden" name="degree_map_id" id="degree_map_id" value="<?= (int) $map['id'] ?>">

	<header class="dm-header">
		<img src="<?= $t->e($t->site('logo', '/_resources/images/logo-blacktype.svg')) ?>" alt="Wichita State University" class="dm-logo">
		<div class="dm-header__text">
			<h3 class="dm-college"><?= $t->e($map['college']) ?></h3>
			<h4 class="dm-title"><?= $t->e($title) ?> (<?= $t->e($year_label) ?>)</h4>
			<p class="ma-actions">
				<a class="<?= $t->cls('button') ?>" id="edit_map_details" role="button" href="#" data-map-id="<?= (int) $map['id'] ?>">Edit Map Details <?= $pencil ?></a>
				<a class="<?= $t->cls('button') ?>" id="edit_map_hours" role="button" href="#" data-map-id="<?= (int) $map['id'] ?>">Edit Map Hours <?= $pencil ?></a>
				<a class="<?= $t->cls('button') ?>" id="edit_map_footnotes" role="button" href="#" data-map-id="<?= (int) $map['id'] ?>">Edit Footnotes <?= $pencil ?></a>
			</p>
			<p class="ma-links"><a href="<?= $t->e($public_url) ?>" target="_blank" rel="noopener">View public page</a> · Permanent link to newest year: <code><?= $t->e($permalink) ?></code></p>
		</div>
	</header>
	<hr class="dm-rule dm-rule--header">

<?php if ($map['note']): ?>
<?= $t->partial('partials/alert', ['level' => 'info', 'title' => 'Note', 'class' => 'dm-note', 'body' => '<p>' . $t->e($map['note']) . '</p>']) ?>
<?php endif; ?>

<?php if ($sge_warnings): ?>
<?php $sgeBody = '<ul>' . implode('', array_map(static fn ($w) => '<li>' . $t->e($w) . '</li>', $sge_warnings)) . '</ul>'
    . '<p>Too many hours in a category is usually fine; the warning is there to help spot a mis-categorized course.</p>'; ?>
<?= $t->partial('partials/alert', ['level' => 'warning', 'title' => 'Check the general-education hours', 'class' => 'ma-sge-warning', 'attrs' => 'role="status"', 'body' => $sgeBody]) ?>
<?php endif; ?>

<?php foreach ($years as $y => $yr): ?>
<?php if ($y > 1): ?>
	<hr class="dm-rule">
<?php endif; ?>
	<div class="<?= $t->cls('table_wrap') ?> dm-year">
		<table class="<?= $t->cls('table') ?> dm-table ma-table">
			<colgroup><col class="ma-col"><col class="ma-col"><col class="ma-col"></colgroup>
			<caption><?= $t->e($yr['label']) ?></caption>
			<thead><tr>
<?php foreach ($yr['semesters'] as $s => $sem): ?>
				<th scope="col"><?= $t->e($sem['name']) ?> Semester</th>
<?php endforeach; ?>
			</tr></thead>
			<tbody><tr>
<?php foreach ($yr['semesters'] as $s => $sem): ?>
				<td class="ma-cell">
					<ul class="semester-list" id="<?= $y ?>-<?= strtolower($sem['name']) ?>-semester" data-semester="<?= $s ?>" data-year="<?= $y ?>">
<?php foreach ($sem['courses'] as $c): ?>
						<li class="course-item" data-course-id="<?= $c['id'] ?>">
							<span class="drag-handle" title="Drag to reorder or move"><?= $grip ?></span>
							<div class="content"><?= $c['info'] ?> <span class="hours">(<?= $t->e($c['hours']) ?> hrs)</span></div>
							<a href="#" class="edit_course <?= $t->cls('button') ?>" data-course-id="<?= $c['id'] ?>" title="Edit course"><?= $pencil ?><span class="sr-only">Edit course</span></a>
						</li>
<?php endforeach; ?>
						<li class="course-item course-item--add">
							<a href="#" class="new_course <?= $t->cls('button') ?>" data-semester="<?= $s ?>" data-year="<?= $y ?>" title="Add course to this semester"><?= $plus ?><span class="sr-only">Add course</span></a>
							<div class="content">Add course to this semester</div>
						</li>
					</ul>
				</td>
<?php endforeach; ?>
			</tr></tbody>
			<tfoot><tr>
<?php foreach ($yr['semesters'] as $s => $sem): ?>
				<th scope="col"><?= $t->e($sem['name']) ?> Total Hours: <?= $t->e($sem['manual']) ?>
					<?php if ((string) $sem['calc'] !== (string) $sem['manual'] && $sem['calc'] !== '0'): ?><span class="calculated_hours warning" title="Sum of the courses listed"><?= $t->e($sem['calc']) ?></span><?php endif; ?></th>
<?php endforeach; ?>
			</tr></tfoot>
		</table>
	</div>
	<p class="dm-year-total">Total hours for <?= $t->e($yr['label']) ?>: <strong><?= $t->e($yr['total']) ?></strong>
		<?php if ((string) $yr['calc'] !== (string) $yr['total'] && $yr['calc'] !== '0'): ?><span class="calculated_hours warning" title="Sum of the courses listed"><?= $t->e($yr['calc']) ?></span><?php endif; ?></p>
<?php endforeach; ?>

<?php if ($map['hours_to_graduate']): ?>
	<hr class="dm-rule">
	<p class="<?= $t->cls('heading6') ?>">Hours needed to complete the degree: <?= $t->e($map['hours_to_graduate']) ?></p>
<?php endif; ?>

<?php if ($note_footnote || $footnotes): ?>
	<hr class="dm-rule">
	<h3 class="<?= $t->cls('heading5') ?>">Footnotes</h3>
<?php if ($note_footnote): ?>
	<div><strong>Note: </strong><?= $note_footnote['note'] ?></div>
<?php endif; ?>
	<ol>
<?php foreach ($footnotes as $f): ?>
		<li id="footnote_<?= (int) $f['id'] ?>"><?= $f['note'] ?></li>
<?php endforeach; ?>
	</ol>
<?php endif; ?>

	<div class="dm-sge-key">
		<h3 class="<?= $t->cls('heading5') ?>">Systemwide General Education (SGE) Key</h3>
		<ul>
<?php foreach ($sge_key as $code => $label): ?>
			<li><span class="dm-sge dm-sge-<?= $t->e($code) ?>"><?= $t->e($code) ?></span> <?= $t->e($label) ?></li>
<?php endforeach; ?>
		</ul>
	</div>
</div>
