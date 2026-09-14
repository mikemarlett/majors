<?php
/**
 * One program's marketing page — current design (program-card / teaser components).
 * Port of the legacy display_major(). Content fields are trusted admin HTML.
 *
 * Variables: $p (program), $c (content), $title, $is_certificate, $program_links, $learn_how_links,
 *            $degree_maps (id,label,url), $similar, $nav_items, $program_url
 * @var \Majors\View\Layout $t
 */
$nav_html = '';
foreach ($nav_items as $label => $href) {
    $nav_html .= '<li><a href="' . $t->e($href) . '">' . $t->e($label) . '</a></li>' . "\n";
}
$teaser = function (string $headline, string $text, ?string $linkText, ?string $linkUrl, bool $editableHeadline = true) use ($t): string {
    $out = '<div class="teaser collection__item"><div class="teaser__body">'
        . '<div class="teaser__headline"><h3 class="headline-group"><span class="head">' . $t->e($headline) . '</span></h3></div>'
        . '<div class="teaser__editorial">' . $text . '</div>';
    if ($linkText && $linkUrl) {
        $out .= '<div class="teaser__links"><a class="link--rich" href="' . $t->e($linkUrl) . '"><span>' . $t->e($linkText) . '</span></a></div>';
    }
    return $out . '</div></div>';
};
$mapsBlock = function () use ($degree_maps, $t): string {
    if ($degree_maps === []) {
        return '';
    }
    $out = '<div class="teaser__headline"><h3 class="headline-group"><span class="head">' . (count($degree_maps) > 1 ? 'Degree Maps' : 'Degree Map') . '</span></h3></div><div class="teaser__links">';
    foreach ($degree_maps as $m) {
        $out .= '<a class="link--rich" href="' . $t->e($m['url']) . '" style="margin-bottom:0;"><span>' . $t->e($m['label']) . '</span></a>';
    }
    return $out . '</div>';
};
$curriculum = '<div class="teaser collection__item"><div class="teaser__body">'
    . '<div class="teaser__headline"><h3 class="headline-group"><span class="head">Curriculum</span></h3></div>'
    . '<div class="teaser__editorial">' . ($c['curriculum_text'] ?? '') . '</div>';
if (!empty($c['curriculum_link_url'])) {
    $curriculum .= '<div class="teaser__links"><a class="link--rich" href="' . $t->e($c['curriculum_link_url']) . '"><span>' . $t->e($c['curriculum_link_text'] ?: 'View the curriculum') . '</span></a></div>';
}
$curriculum .= $mapsBlock() . '</div></div>';
$admissions = $teaser((string) ($c['admissions_headline'] ?? 'Admission'), (string) ($c['admissions_text'] ?? ''), $c['admissions_link_text'] ?? null, $c['admissions_link_url'] ?? null);
?>
<?= $t->partial('partials/page_header', ['title' => 'Details: ' . $title, 'nav_html' => $nav_html, 'noprint' => false]) ?>

<section class="section-wrap section-wrap--shade-light">
	<div class="program-card">
		<div class="program-card__body">
			<h2 class="headline-group">
				<span class="superhead"><?= $t->e($p['program_simple_type'] ?? '') ?></span>
				<span class="head"><?= $t->e($p['academic_program']) ?></span>
			</h2>
<?php if ($program_links): ?>
			<div class="link-collection"><ul>
<?php foreach ($program_links as $l): ?>
				<li><a href="<?= $t->e($l['href'] ?? '#') ?>"><?= $t->e($l['link_text'] ?? '') ?></a></li>
<?php endforeach; ?>
			</ul></div>
<?php endif; ?>
			<div class="program-card__detail"><?= $c['description'] ?? '' ?></div>
<?php if (!empty($c['learn_how'])): ?>
			<div class="program-card__cta">
				<h3 class="heading4"><?= $c['learn_how'] ?></h3>
				<div class="button-collection landing-panel__buttons button-collection--accent-first">
<?php foreach ($learn_how_links as $l): ?>
					<a href="<?= $t->e($l['href'] ?? '#') ?>" role="button" class="button"><?= $t->e($l['link_text'] ?? '') ?> <?= $t->icon('design--arrow-right', 'icon button__trailing-icon') ?></a>
<?php endforeach; ?>
				</div>
			</div>
<?php endif; ?>
		</div>
<?php if (!empty($c['main_image_url'])): ?>
		<div class="program-card__image">
			<div class="captioned-media captioned-media--right"><figure>
				<div class="figure-wrapper">
					<img src="<?= $t->e($c['main_image_url']) ?>" alt="<?= $t->e($c['main_image_alt'] ?? '') ?>">
<?php if (!empty($c['main_image_credit'])): ?>
					<cite class="cite--photo-credit"><?= $t->icon('design--camera') ?> <?= $t->e($c['main_image_credit']) ?></cite>
<?php endif; ?>
				</div>
<?php if (!empty($c['main_image_caption'])): ?>
				<figcaption><p><?= $c['main_image_caption'] ?></p></figcaption>
<?php endif; ?>
			</figure></div>
		</div>
<?php endif; ?>
	</div>
</section>

<?php if (!$is_certificate && (!empty($c['wildcard_headline']) || !empty($c['admissions_headline']))): ?>
<section class="teaser-collection section-wrap collection--two-columns"><div class="collection__items">
	<?= $teaser((string) ($c['wildcard_headline'] ?? ''), (string) ($c['wildcard_text'] ?? ''), $c['wildcard_link_text'] ?? null, $c['wildcard_link_url'] ?? null) ?>
	<?= $admissions ?>
</div></section>
<?php elseif ($is_certificate && (!empty($c['curriculum_text']) || !empty($c['admissions_headline']))): ?>
<section class="teaser-collection section-wrap collection--two-columns"><div class="collection__items">
	<?= $curriculum ?>
	<?= $admissions ?>
</div></section>
<?php endif; ?>

<?php if (!empty($c['inside_the_program_headline'])): ?>
<section class="section-wrap section-wrap--wheat">
	<header class="section-header section-header--no-border"><h2>Inside the Program</h2></header>
	<div class="teaser teaser--columned-intro">
<?php if (!empty($c['inside_the_program_image_url'])): ?>
		<div class="teaser__image"><img src="<?= $t->e($c['inside_the_program_image_url']) ?>" alt="<?= $t->e($c['inside_the_program_image_alt'] ?? '') ?>"></div>
<?php endif; ?>
		<div class="teaser__body">
			<div class="teaser__headline"><h3 class="headline-group"><span class="head"><?= $t->e($c['inside_the_program_headline']) ?></span></h3></div>
			<div class="teaser__editorial"><?= $c['inside_the_program_text'] ?? '' ?></div>
<?php if (!empty($c['inside_the_program_link_url'])): ?>
			<div class="teaser__links"><a class="link--rich" href="<?= $t->e($c['inside_the_program_link_url']) ?>"><span><?= $t->e($c['inside_the_program_link_text'] ?? 'Learn more') ?></span></a></div>
<?php endif; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if (!$is_certificate && (!empty($c['curriculum_text']) || !empty($c['careers_headline']))): ?>
<section class="teaser-collection section-wrap collection--two-columns section-wrap--nipple-down"><div class="collection__items">
	<?= $curriculum ?>
	<?= $teaser((string) ($c['careers_headline'] ?? 'Careers'), (string) ($c['careers_text'] ?? ''), $c['careers_link_text'] ?? null, $c['careers_link_url'] ?? null) ?>
</div></section>
<?php endif; ?>

<?php if ($similar): ?>
<section class="teaser-collection section-wrap section-wrap--shade-dark section-wrap--image-background-texturize collection--two-columns collection--two-columns-early-break">
	<header class="section-header section-header--centered section-header--no-border collection__header"><h2>Similar Programs</h2></header>
	<div class="collection__items">
<?php foreach ($similar as $s): ?>
		<a href="<?= $t->e($program_url . (int) $s['id']) ?>" class="teaser collection__item teaser--card-wide teaser--card">
<?php if (!empty($s['main_image_url'])): ?>
			<div class="teaser__image"><img src="<?= $t->e($s['main_image_url']) ?>" alt="" width="1000" height="1000"></div>
<?php endif; ?>
			<div class="teaser__body"><div class="teaser__headline"><div class="headline-group"><span class="head"><?= $t->e($s['academic_program']) ?> (<?= $t->e($s['program_simple_type'] ?? $s['program_type']) ?>)</span></div></div></div>
		</a>
<?php endforeach; ?>
	</div>
	<div class="section-wrap__image"><img src="<?= $t->e($c['main_image_url'] ?? '/_resources/images/wichita.jpg') ?>" alt="" width="1000" height="1000"></div>
</section>
<?php endif; ?>
