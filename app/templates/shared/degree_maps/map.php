<?php
/**
 * One printable degree map. Design-neutral: only dm-* classes plus the
 * chrome's table token. Letter-portrait print rules live in degree-map.css.
 *
 * Variables: $map (MapRenderer::viewModel), optional $versions (list of header rows,
 *            newest first), $latest_id, $link_base, $latest_url
 * @var \Majors\View\Layout $t
 */
$versions   = $versions ?? [];
$latest_id  = $latest_id ?? $map['id'];
$is_latest  = (int) $latest_id === (int) $map['id'];
$link_base  = $link_base ?? '';
$latest_url = $latest_url ?? '';
$new        = $t->design() === 'new';   // no decorative yellow rules, standard headings
$rule       = $new ? '' : '<hr class="dm-rule noprint">';
$logo       = $t->site('logo', '/_resources/images/logo-blacktype.svg');
$logo       = str_starts_with($logo, '/') || str_starts_with($logo, 'http') ? $logo : $t->asset($logo);
?>
<article class="dm" id="degree-map-<?= (int) $map['id'] ?>" data-map-id="<?= (int) $map['id'] ?>">
<?php if (count($versions) > 1 || $latest_url !== ''): ?>
	<nav class="dm-versions noprint" aria-label="Catalog years for this degree map">
<?php if (!$is_latest): ?>
		<p class="dm-versions__notice"><strong>You are viewing the <?= $t->e($map['year_label']) ?> catalog-year version.</strong>
			<a href="<?= $t->e($link_base . (int) $latest_id) ?>">View the latest version</a>.</p>
<?php endif; ?>
<?php if (count($versions) > 1): ?>
		<p class="dm-versions__list"><span>Catalog year:</span>
<?php foreach ($versions as $v): ?>
<?php $cur = (int) $v['id'] === (int) $map['id']; ?>
			<?php if ($cur): ?><strong aria-current="page"><?= (int) $v['academic_year'] - 1 ?>–<?= (int) $v['academic_year'] ?></strong><?php else: ?><a href="<?= $t->e($link_base . (int) $v['id']) ?>"><?= (int) $v['academic_year'] - 1 ?>–<?= (int) $v['academic_year'] ?></a><?php endif; ?>

<?php endforeach; ?>
		</p>
<?php endif; ?>
<?php if ($latest_url !== ''): ?>
		<p class="dm-versions__permalink"><span>Permanent link (always the newest year):</span> <a href="<?= $t->e($latest_url) ?>"><?= $t->e($latest_url) ?></a></p>
<?php endif; ?>
	</nav>
<?php endif; ?>

<?= $rule ?>
	<h2 class="<?= $new ? $t->cls('heading3') : 'dm-heading' ?> noprint">Degree Map</h2>
<?= $rule ?>

	<header class="dm-header">
		<img src="<?= $t->e($logo) ?>" alt="Wichita State University" class="dm-logo">
		<div class="dm-header__text">
			<h3 class="dm-college"><?= $t->e($map['college']) ?></h3>
			<h4 class="dm-title"><?= $t->e($map['title']) ?> (<?= $t->e($map['year_label']) ?>)</h4>
		</div>
	</header>
<?php if (!$new): ?>
	<hr class="dm-rule dm-rule--header">
<?php endif; ?>

<?php if ($map['note'] !== ''): ?>
<?= $t->partial('partials/alert', ['level' => 'info', 'title' => 'Note', 'class' => 'dm-note', 'body' => '<p>' . $t->e($map['note']) . '</p>']) ?>
<?php endif; ?>

<?php foreach ($map['years'] as $i => $yr): ?>
<?php if ($i > 0): ?>
<?= $rule ?>
<?php endif; ?>
	<div class="<?= $t->cls('table_wrap') ?> dm-year">
		<table class="<?= $t->cls('table') ?> dm-table<?= $yr['summer'] ? ' dm-table--summer' : '' ?>">
			<colgroup>
<?php foreach ($yr['semesters'] as $s => $name): ?>
				<col class="dm-col-course"><col class="dm-col-hours">
<?php endforeach; ?>
			</colgroup>
			<caption><?= $t->e($yr['label']) ?></caption>
			<thead>
				<tr>
<?php foreach ($yr['semesters'] as $s => $name): ?>
					<th scope="col" class="dm-course"><?= $t->e($name) ?> Semester</th>
					<th scope="col" class="dm-hours"><span class="dm-semester-word"><?= $t->e($name) ?> </span>Hours</th>
<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
<?php foreach ($yr['rows'] as $row): ?>
				<tr>
<?php foreach ($yr['semesters'] as $s => $name): ?>
<?php if (isset($row[$s])): ?>
					<td class="dm-course"><?= $row[$s]['info'] ?></td>
					<td class="dm-hours"><?= $t->e($row[$s]['hours']) ?></td>
<?php else: ?>
					<td class="dm-course dm-blank"></td><td class="dm-hours dm-blank"></td>
<?php endif; ?>
<?php endforeach; ?>
				</tr>
<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
<?php foreach ($yr['semesters'] as $s => $name): ?>
					<th scope="row" class="dm-course"><?= $t->e($name) ?> Total Hours</th>
					<td class="dm-hours"><?= $t->e($yr['totals'][$s] ?? 0) ?></td>
<?php endforeach; ?>
				</tr>
			</tfoot>
		</table>
	</div>
	<p class="dm-year-total">Total hours for <?= $t->e($yr['label']) ?>: <strong><?= $t->e($yr['total']) ?></strong></p>
<?php endforeach; ?>

<?php if ($map['hours_to_graduate'] !== ''): ?>
<?php if (!$new): ?>
	<hr class="dm-rule">
<?php endif; ?>
	<p class="dm-graduate <?= $t->cls('heading6') ?>">Hours needed to complete the degree: <?= $t->e($map['hours_to_graduate']) ?></p>
<?php endif; ?>

<?php if ($map['note_footnote'] || $map['footnotes']): ?>
<?php if (!$new): ?>
	<hr class="dm-rule">
<?php endif; ?>
	<section class="dm-footnotes" aria-label="Footnotes">
<?php if ($map['note_footnote']): ?>
		<div class="dm-footnotes__note"><strong>Note: </strong><?= $map['note_footnote']['note'] ?></div>
<?php endif; ?>
<?php if ($map['footnotes']): ?>
		<ol>
<?php foreach ($map['footnotes'] as $f): ?>
			<li id="footnote_<?= (int) $f['id'] ?>"><?= $f['note'] ?></li>
<?php endforeach; ?>
		</ol>
<?php endif; ?>
	</section>
<?php endif; ?>

	<section class="dm-sge-key" aria-label="Systemwide General Education key">
		<h3 class="<?= $t->cls('heading5') ?>">Systemwide General Education (SGE) Key</h3>
		<ul>
<?php foreach ($map['sge_key'] as $code => $label): ?>
			<li><span class="dm-sge dm-sge-<?= $t->e($code) ?>"><?= $t->e($code) ?></span> <?= $t->e($label) ?></li>
<?php endforeach; ?>
		</ul>
	</section>
</article>
