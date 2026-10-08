<?php
/**
 * Programs listing: A–Z or by college, or the Certificates layout (Graduate / Undergraduate
 * Certificates grouped by topic, each half with its intro), with the results header.
 * Each line is a listing entry: its name and the detail after the dash (list_name / list_detail).
 * The A–Z view gets the letter index of its design (partials/alpha_nav); the by-college and
 * Certificates views have none, like the pages they replaced.
 * Variables: $groups, $cert_sections (null unless the Certificates layout), $intros (section => headline/body),
 *            $headline, $order, $results_url (nullable), $all_url
 * @var \Majors\View\Layout $t
 */
$line = static function (array $p) use ($t): string {
    return '<li><a href="' . $t->e($t->programUrl($p)) . '">' . $t->e($p['list_name'] ?? $p['academic_program']) . '</a> — '
        . ($p['list_detail'] ?? $t->e($p['program_type'] ?? '')) . '</li>';
};
$cert_sections = $cert_sections ?? null;
$intros        = $intros ?? [];
?>
<header class="<?= $t->cls('section.header') ?> majors-results-header">
	<h2><?= $t->e($headline) ?></h2>
	<div class="<?= $t->cls('button_collection') ?>">
		<a href="<?= $t->e($all_url) ?>" role="button" class="<?= $t->cls('button') ?>">All Degree Programs <?= $t->icon('design--arrow-right', 'icon button__trailing-icon') ?></a>
<?php if ($results_url): ?>
		<a href="<?= $t->e($results_url) ?>" role="button" class="<?= $t->cls('button') ?>">Link to These Results <?= $t->icon('design--arrow-right', 'icon button__trailing-icon') ?></a>
<?php endif; ?>
	</div>
</header>
<?php if ($cert_sections !== null): ?>
<?php if ($cert_sections === []): ?>
<p class="<?= $t->cls('heading3') ?>">No programs match.</p>
<?php else: ?>
<?php foreach ($cert_sections as $sec): ?>
<section class="majors-cert-section" aria-labelledby="<?= $t->e($sec['key']) ?>">
	<h2 class="<?= $t->cls('heading3') ?>" id="<?= $t->e($sec['key']) ?>"><?= $t->e($sec['label']) ?></h2>
<?php if (!empty($intros[$sec['key']])): ?>
	<div class="majors-callout <?= $t->cls('callout') ?>">
<?php if ($intros[$sec['key']]['headline'] !== ''): ?>
		<h3 class="<?= $t->cls('heading5') ?>"><?= $t->e($intros[$sec['key']]['headline']) ?></h3>
<?php endif; ?>
		<div class="<?= $t->cls('prose') ?>"><?= $intros[$sec['key']]['body'] ?></div>
	</div>
<?php endif; ?>
	<div class="<?= $t->cls('alpha_list') ?> dm-listing">
<?php foreach ($sec['topics'] as $g): ?>
		<div class="<?= $t->cls('alpha_list.items') ?>">
			<header class="<?= $t->cls('section.header') ?>"><h3 class="<?= $t->cls('heading4') ?>" id="<?= $t->e($g['key']) ?>"><?= $t->e($g['label']) ?></h3></header>
		</div>
		<ul>
<?php foreach ($g['items'] as $p): ?>
			<?= $line($p) ?>

<?php endforeach; ?>
		</ul>
<?php endforeach; ?>
	</div>
</section>
<?php endforeach; ?>
<?php endif; ?>
<?php elseif ($groups === []): ?>
<p class="<?= $t->cls('heading3') ?>">No programs match.</p>
<?php else: ?>
<?php if ($order === 'alpha'): ?>
<?php
// Letter index (each design's own pattern: alpha-filters on the current site, AlphaNav on the redesign).
// Section ids are the group keys; every numeric group is reached from '#'.
$links = [];
foreach ($groups as $g) {
    $letter = preg_match('/^[A-Z]$/', (string) $g['key']) ? (string) $g['key'] : '#';
    $links[$letter] ??= (string) $g['key'];
}
echo $t->partial('partials/alpha_nav', ['links' => $links]);
?>
<?php endif; ?>
<hr>
<div class="<?= $t->cls('alpha_list') ?> dm-listing">
<?php foreach ($groups as $g): ?>
	<div class="<?= $t->cls('alpha_list.items') ?>">
		<header class="<?= $t->cls('section.header') ?>"><h3 class="<?= $t->cls('heading4') ?>" id="<?= $t->e($g['key']) ?>"><?= $t->e($g['label']) ?></h3></header>
	</div>
	<ul>
<?php foreach ($g['items'] as $p): ?>
		<?= $line($p) ?>

<?php endforeach; ?>
	</ul>
<?php endforeach; ?>
</div>
<?php endif; ?>
