<?php
/**
 * One program's marketing page — current design (program-card / teaser
 * components), rendered from the program's ordered sections.
 *
 * Variables: $p (program row), $title, $kind, $crumbs, $description (HTML), $learn_how, $buttons (text/href), $facts, $is_stem, $coordinator,
 *            $image (url/alt/caption/credit|null), $sections, $degree_maps (id/label/url), $similar, $program_url
 * @var \Majors\View\Layout $t
 */
$links = static function (array $ls) use ($t): string {
    if ($ls === []) {
        return '';
    }
    $out = '<div class="teaser__links">';
    foreach ($ls as $l) {
        $out .= '<a class="link--rich" href="' . $t->e($l['href']) . '"><span>' . $t->e($l['text']) . '</span></a>';
    }
    return $out . '</div>';
};
$mapsHtml = '';
if ($degree_maps !== []) {
    $mapsHtml = '<div class="teaser__headline"><h3 class="headline-group"><span class="head">' . (count($degree_maps) > 1 ? 'Degree Maps' : 'Degree Map') . '</span></h3></div>'
        . $links(array_map(static fn (array $m) => ['text' => $m['label'], 'href' => $m['url']], $degree_maps));
}
$teaser = static function (array $s, string $extra = '') use ($t, $links): string {
    return '<div class="teaser collection__item majors-section" data-section="' . (int) $s['id'] . '"' . ($s['shared'] ? ' data-shared="1"' : '') . '><div class="teaser__body">'
        . '<div class="teaser__headline"><h3 class="headline-group"><span class="head">' . $t->e($s['headline']) . '</span></h3></div>'
        . '<div class="teaser__editorial">' . $s['body'] . '</div>' . $links($s['links']) . $extra . '</div></div>';
};
$groups = [];
$mapsPlaced = false;
foreach ($sections as $s) {
    if ($s['kind'] === 'teaser') {
        $extra = '';
        if (!$mapsPlaced && $s['headline'] === 'Curriculum' && $mapsHtml !== '') {
            $extra = $mapsHtml;
            $mapsPlaced = true;
        }
        if ($groups === [] || end($groups)['type'] !== 'cards') {
            $groups[] = ['type' => 'cards', 'html' => []];
        }
        $groups[count($groups) - 1]['html'][] = $teaser($s, $extra);
    } elseif ($s['kind'] === 'feature') {
        $groups[] = ['type' => 'feature', 's' => $s];
    }
}
if (!$mapsPlaced && $mapsHtml !== '') {
    $groups[] = ['type' => 'cards', 'html' => ['<div class="teaser collection__item"><div class="teaser__body">' . $mapsHtml . '</div></div>']];
}
?>

<section class="section-wrap section-wrap--shade-light">
	<div class="program-card">
		<div class="program-card__body">
			<h2 class="headline-group">
				<span class="superhead"><?= $t->e($kind) ?><?php if ($is_stem): ?> · STEM Program<?php endif; ?></span>
				<span class="head"><?= $t->e($p['academic_program']) ?></span>
			</h2>
<?php if ($crumbs): ?>
			<div class="link-collection"><ul>
<?php foreach ($crumbs as $l): ?>
				<li><a href="<?= $t->e($l['href']) ?>"><?= $t->e($l['text']) ?></a></li>
<?php endforeach; ?>
			</ul></div>
<?php endif; ?>
			<div class="program-card__detail"><?= $description ?></div>
<?php if ($facts): ?>
			<dl class="majors-facts">
<?php foreach ($facts as $f): ?>
				<div><dt><?= $t->e($f['label']) ?></dt><dd><?= $t->e($f['value']) ?></dd></div>
<?php endforeach; ?>
			</dl>
<?php endif; ?>
<?php if ($coordinator): ?>
			<p class="majors-coordinator">Questions? Contact <?= $coordinator['name'] !== '' ? 'Program Coordinator ' . $t->e($coordinator['name']) : 'the program' ?><?php if ($coordinator['email'] !== ''): ?> at <a href="mailto:<?= $t->e($coordinator['email']) ?>"><?= $t->e($coordinator['email']) ?></a><?php endif; ?><?php if ($coordinator['phone'] !== ''): ?> or call <?= $t->e($coordinator['phone']) ?><?php endif; ?>.</p>
<?php endif; ?>
<?php if ($learn_how !== ''): ?>
			<div class="program-card__cta">
				<h3 class="heading4"><?= $t->e($learn_how) ?></h3>
				<div class="button-collection landing-panel__buttons button-collection--accent-first">
<?php foreach ($buttons as $b): ?>
					<a href="<?= $t->e($b['href'] ?? '#') ?>" role="button" class="button"><?= $t->e($b['text'] ?? '') ?> <?= $t->icon('design--arrow-right', 'icon button__trailing-icon') ?></a>
<?php endforeach; ?>
				</div>
			</div>
<?php endif; ?>
		</div>
<?php if ($image): ?>
		<div class="program-card__image">
			<div class="captioned-media captioned-media--right"><figure>
				<div class="figure-wrapper">
					<img src="<?= $t->e($t->img($image['url'])) ?>" alt="<?= $t->e($image['alt']) ?>">
<?php if ($image['credit'] !== ''): ?>
					<cite class="cite--photo-credit"><?= $t->icon('design--camera') ?> <?= $t->e($image['credit']) ?></cite>
<?php endif; ?>
				</div>
<?php if ($image['caption'] !== ''): ?>
				<figcaption><p><?= $t->e($image['caption']) ?></p></figcaption>
<?php endif; ?>
			</figure></div>
		</div>
<?php endif; ?>
	</div>
</section>

<?php foreach ($groups as $i => $g): ?>
<?php if ($g['type'] === 'cards'): ?>
<section class="teaser-collection section-wrap collection--two-columns<?= $i === count($groups) - 1 ? ' section-wrap--nipple-down' : '' ?>"><div class="collection__items">
	<?= implode("\n\t", $g['html']) ?>
</div></section>
<?php else: ?>
<?php $s = $g['s']; ?>
<section class="section-wrap section-wrap--wheat majors-section" data-section="<?= (int) $s['id'] ?>">
	<header class="section-header section-header--no-border"><h2><?= $t->e($s['label'] !== '' ? $s['label'] : 'Inside the Program') ?></h2></header>
	<div class="teaser teaser--columned-intro">
<?php if ($s['image']): ?>
		<div class="teaser__image"><img src="<?= $t->e($t->img($s['image']['url'])) ?>" alt="<?= $t->e($s['image']['alt']) ?>"></div>
<?php endif; ?>
		<div class="teaser__body">
			<div class="teaser__headline"><h3 class="headline-group"><span class="head"><?= $t->e($s['headline']) ?></span></h3></div>
			<div class="teaser__editorial"><?= $s['body'] ?></div>
			<?= $links($s['links']) ?>
		</div>
	</div>
</section>
<?php endif; ?>
<?php endforeach; ?>

<?php if ($similar): ?>
<section class="teaser-collection section-wrap section-wrap--shade-dark section-wrap--image-background-texturize collection--two-columns collection--two-columns-early-break">
	<header class="section-header section-header--centered section-header--no-border collection__header"><h2>Similar Programs</h2></header>
	<div class="collection__items">
<?php foreach ($similar as $s): ?>
		<a href="<?= $t->e($t->programUrl($s)) ?>" class="teaser collection__item teaser--card-wide teaser--card">
<?php if (!empty($s['main_image_url'])): ?>
			<div class="teaser__image"><img src="<?= $t->e($t->img($s['main_image_url'])) ?>" alt="" width="1000" height="1000"></div>
<?php endif; ?>
			<div class="teaser__body"><div class="teaser__headline"><div class="headline-group"><span class="head"><?= $t->e($s['academic_program']) ?> (<?= $t->e($s['credential'] ?? $s['program_simple_type'] ?? $s['program_type']) ?>)</span></div></div></div>
		</a>
<?php endforeach; ?>
	</div>
	<div class="section-wrap__image"><img src="<?= $t->e($t->img($image['url'] ?? '/_resources/images/wichita.jpg')) ?>" alt="" width="1000" height="1000"></div>
</section>
<?php endif; ?>
