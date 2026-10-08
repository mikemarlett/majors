<?php
/**
 * Current design: the programs listing in the markup of the CMS listing pages it
 * replaced (the 2018 library's alpha-list organism), plus the letter index that
 * library designed and the pages never had (partials/alpha_nav).
 *
 * The round yellow letter comes from `.alpha-list .section-header h2…h6`, so only
 * the A–Z headings get the section-header wrapper; college and certificate-topic
 * headings are bare, as on the original pages (a word in a 60px circle is wrong).
 * The Certificates view's "Select View" links are buttons ("View Graduate
 * Certificates" …), the same as on the redesign.
 * The redesign has its own template (templates/new/majors/listing.php).
 *
 * Each line is a listing entry: its name and the detail after the dash (list_name / list_detail).
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
<nav class="majors-view-buttons noprint <?= $t->cls('button_collection') ?>" aria-label="Jump to a section">
<?php foreach ($cert_sections as $sec): ?>
	<a href="#<?= $t->e($sec['key']) ?>" role="button" class="<?= $t->cls('button') ?>">View <?= $t->e($sec['label']) ?></a>
<?php endforeach; ?>
</nav>
<hr>
<?php foreach ($cert_sections as $sec): ?>
<section class="majors-cert-section" aria-labelledby="<?= $t->e($sec['key']) ?>">
	<h2 class="<?= $t->cls('heading3') ?>" id="<?= $t->e($sec['key']) ?>"><?= $t->e($sec['label']) ?></h2>
<?php if (!empty($intros[$sec['key']])): ?>
	<div class="majors-callout <?= $t->cls('callout') ?>">
<?php if ($intros[$sec['key']]['headline'] !== ''): ?>
		<h3 class="<?= $t->cls('heading4') ?>"><?= $t->e($intros[$sec['key']]['headline']) ?></h3>
<?php endif; ?>
		<div class="<?= $t->cls('prose') ?>"><?= $intros[$sec['key']]['body'] ?></div>
	</div>
<?php endif; ?>
<?php foreach ($sec['topics'] as $i => $g): ?>
<?php if ($i): ?>
	<hr>
<?php endif; ?>
	<div class="<?= $t->cls('alpha_list') ?> dm-listing">
		<div class="<?= $t->cls('alpha_list.items') ?>">
			<h3 class="<?= $t->cls('heading4') ?>" id="<?= $t->e($g['key']) ?>"><?= $t->e($g['label']) ?></h3>
		</div>
		<ul>
<?php foreach ($g['items'] as $p): ?>
			<?= $line($p) ?>

<?php endforeach; ?>
		</ul>
	</div>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php endif; ?>
<?php elseif ($groups === []): ?>
<p class="<?= $t->cls('heading3') ?>">No programs match.</p>
<?php else: ?>
<?php if ($order === 'alpha'): ?>
<?php
// Letter index. Section ids are the group keys; every numeric group is reached from '#'.
$links = [];
foreach ($groups as $g) {
    $letter = preg_match('/^[A-Z]$/', (string) $g['key']) ? (string) $g['key'] : '#';
    $links[$letter] ??= (string) $g['key'];
}
echo $t->partial('partials/alpha_nav', ['links' => $links]);
?>
<hr>
<?php endif; ?>
<div class="<?= $t->cls('alpha_list') ?> dm-listing">
<?php foreach ($groups as $g): ?>
	<div class="<?= $t->cls('alpha_list.items') ?>">
<?php if ($order === 'alpha'): ?>
		<header class="<?= $t->cls('section.header') ?>"><h3 class="<?= $t->cls('heading4') ?>" id="<?= $t->e($g['key']) ?>"><?= $t->e($g['label']) ?></h3></header>
<?php else: ?>
		<header><h3 class="<?= $t->cls('heading4') ?>" id="<?= $t->e($g['key']) ?>"><?= $t->e($g['label']) ?></h3></header>
<?php endif; ?>
	</div>
	<ul>
<?php foreach ($g['items'] as $p): ?>
		<?= $line($p) ?>

<?php endforeach; ?>
	</ul>
<?php endforeach; ?>
</div>
<?php endif; ?>
