<?php
/**
 * Redesign: the part of the results that lives in the full-width top band,
 * under the search/select bar: the results headline with its buttons (Heading
 * and Button atoms), the letter index (AlphaNav) for the A–Z view, and the
 * "View … Certificates" buttons for the Certificates view. The listing itself starts in
 * the main column beside the sidebar (majors/listing.php). majors.js swaps
 * this block (#majors-results-top) together with the results.
 *
 * Variables: $groups, $cert_sections (null unless the Certificates layout), $headline, $order,
 *            $results_url (nullable), $all_url
 * @var \Majors\View\Layout $t
 */
$cert_sections = $cert_sections ?? null;
$headingClass  = 'nc-heading text-[length:--text-size] sm:text-[length:--text-size-sm] md:text-[length:--text-size-md]';
ob_start();
?>
	<header class="majors-results-header">
		<h2 data-style-level="2" class="<?= $headingClass ?>"><?= $t->e($headline) ?></h2>
		<ul role="list" class="<?= $t->cls('button_collection') ?> majors-results-header__buttons">
			<li><a class="nc-button" href="<?= $t->e($all_url) ?>"><span class="nc-button-text">All Degree Programs</span></a></li>
<?php if ($results_url): ?>
			<li><a class="nc-button" href="<?= $t->e($results_url) ?>"><span class="nc-button-text">Link to These Results</span></a></li>
<?php endif; ?>
		</ul>
	</header>
<?php if ($cert_sections !== null && $cert_sections !== []): ?>
	<nav class="majors-view-buttons noprint" aria-label="Jump to a section">
		<ul role="list" class="<?= $t->cls('button_collection') ?>">
<?php foreach ($cert_sections as $sec): ?>
			<li><a class="nc-button" href="#<?= $t->e($sec['key']) ?>"><span class="nc-button-text">View <?= $t->e($sec['label']) ?></span></a></li>
<?php endforeach; ?>
		</ul>
	</nav>
<?php endif; ?>
<?php $body = (string) ob_get_clean(); ?>
<div id="majors-results-top">
<?= $t->partial('partials/slab', ['theme' => 'neutral-200', 'class' => 'majors-slab--results', 'body' => $body]) ?>
<?php if ($cert_sections === null && $order === 'alpha' && $groups !== []): ?>
<?php
// Letter index. Section ids are the group keys; every numeric group is reached from '#'.
$links = [];
foreach ($groups as $g) {
    $letter = preg_match('/^[A-Z]$/', (string) $g['key']) ? (string) $g['key'] : '#';
    $links[$letter] ??= (string) $g['key'];
}
echo $t->partial('partials/alpha_nav', ['links' => $links]);
?>
<?php endif; ?>
</div>
