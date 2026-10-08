<?php
/**
 * Redesign: the programs listing built from the design system's pieces.
 *   results header  Heading atom + Button atoms (nc-button, no sprite icons)
 *   A–Z / college   AlphaNav (partials/alpha_nav, A–Z only) + AlphaListing sections,
 *                   each a ColumnedLinkList (theme/components/Organism/{AlphaListing,ColumnedLinkList})
 *   Certificates    Graduate / Undergraduate sections, each with its intro Callout
 *                   (majors-callout in degree-map.css) and its topics as AlphaListing sections
 * Each line is a listing entry: its name and the detail after the dash (list_name / list_detail).
 *
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
$headingClass  = 'nc-heading text-[length:--text-size] sm:text-[length:--text-size-sm] md:text-[length:--text-size-md]';
$listClass     = 'leading-snug lg-xs:columns-2 gap-8 sm:gap-12 lg:columns-3 xl:data-[max-cols=4]:columns-4 xl:gap-16 descendants:break-inside-avoid children:pb-5 -mb-5 children:!my-0 !list-none !pl-0 children:!pl-0';
/** One AlphaListing section: heading, dotted rule, columned list. */
$section = static function (string $id, string $label, int $styleLevel, array $items) use ($t, $line, $headingClass, $listClass): string {
    $h = $styleLevel >= 3 ? 'h3' : 'h2';   // sections under an h2 page/section heading
    $out = '<section id="' . $t->e($id) . '" class="grid grid-cols-1 gap-4 scroll-mt-6">'
        . '<' . $h . ' data-style-level="' . $styleLevel . '" class="' . $headingClass . '">' . $t->e($label) . '</' . $h . '>'
        . '<div class="pt-8 border-t-4 border-dotted border-theme-border-color"><div class="prose md:prose-lg max-w-none">'
        . '<ul role="list" data-max-cols="3" class="' . $listClass . '">';
    foreach ($items as $p) {
        $out .= $line($p);
    }
    return $out . '</ul></div></div></section>';
};
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
<?php if ($cert_sections !== null): ?>
<?php if ($cert_sections === []): ?>
<p class="nc-heading text-2xl">No programs match.</p>
<?php else: ?>
<nav class="majors-view-switch noprint" aria-label="Jump to">
	<p><strong>Select View:</strong>
<?php foreach ($cert_sections as $i => $sec): ?>
<?= $i ? ' &nbsp;|&nbsp; ' : ' ' ?><a href="#<?= $t->e($sec['key']) ?>"><?= $t->e($sec['label']) ?></a>
<?php endforeach; ?>
	</p>
</nav>
<div class="majors-cert-sections">
<?php foreach ($cert_sections as $sec): ?>
	<section class="majors-cert-section scroll-mt-6" id="<?= $t->e($sec['key']) ?>" aria-labelledby="<?= $t->e($sec['key']) ?>-heading">
		<h2 data-style-level="3" class="<?= $headingClass ?>" id="<?= $t->e($sec['key']) ?>-heading"><?= $t->e($sec['label']) ?></h2>
<?php if (!empty($intros[$sec['key']])): ?>
		<div class="majors-callout">
<?php if ($intros[$sec['key']]['headline'] !== ''): ?>
			<h3 data-style-level="4" class="<?= $headingClass ?>"><?= $t->e($intros[$sec['key']]['headline']) ?></h3>
<?php endif; ?>
			<div class="prose max-w-none"><?= $intros[$sec['key']]['body'] ?></div>
		</div>
<?php endif; ?>
		<div data-nc-component="alpha-listing" class="grid grid-cols-1 gap-10">
<?php foreach ($sec['topics'] as $g): ?>
<?= $section((string) $g['key'], (string) $g['label'], 4, $g['items']) ?>

<?php endforeach; ?>
		</div>
	</section>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php elseif ($groups === []): ?>
<p class="nc-heading text-2xl">No programs match.</p>
<?php else: ?>
<?php if ($order === 'alpha'): ?>
<?php
$links = [];
foreach ($groups as $g) {
    $letter = preg_match('/^[A-Z]$/', (string) $g['key']) ? (string) $g['key'] : '#';
    $links[$letter] ??= (string) $g['key'];
}
echo $t->partial('partials/alpha_nav', ['links' => $links]);
?>
<?php endif; ?>
<div data-nc-component="alpha-listing" class="grid grid-cols-1 gap-10">
<?php foreach ($groups as $g): ?>
<?= $section((string) $g['key'], (string) $g['label'], 3, $g['items']) ?>

<?php endforeach; ?>
</div>
<?php endif; ?>
