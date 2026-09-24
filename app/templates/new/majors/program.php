<?php
/**
 * One program's marketing page — new design, built from the design system's
 * pieces (.prose, .nc-heading, .nc-fancy-link-list, .nc-button) and the
 * program's ordered sections (teaser | feature | similar).
 *
 * Variables: $p (program row), $title, $kind, $crumbs, $description (HTML), $learn_how, $buttons (text/href),
 *            $image (url/alt/caption/credit|null), $sections, $degree_maps (id/label/url), $similar, $program_url
 * @var \Majors\View\Layout $t
 */
$fancy = static function (array $links) use ($t): string {
    if ($links === []) {
        return '';
    }
    $out = '<ul role="list" class="nc-fancy-link-list">';
    foreach ($links as $l) {
        $out .= '<li><span class="nc-fancy-link-wrapper"><a class="nc-fancy-link" href="' . $t->e($l['href']) . '">' . $t->e($l['text']) . '</a></span></li>';
    }
    return $out . '</ul>';
};
$mapLinks = array_map(static fn (array $m) => ['text' => $m['label'], 'href' => $m['url']], $degree_maps);
$mapsHtml = $degree_maps === [] ? '' : '<h4 class="nc-heading text-base mt-6 mb-2">' . (count($degree_maps) > 1 ? 'Degree Maps' : 'Degree Map') . '</h4>' . $fancy($mapLinks);
$card = static function (array $s, string $extra = '') use ($t, $fancy): string {
    $out = '<div class="border border-black/15 p-6 majors-section" data-section="' . (int) $s['id'] . '"' . ($s['shared'] ? ' data-shared="1"' : '') . '>'
        . '<h3 class="nc-heading text-xl mb-3">' . $t->e($s['headline']) . '</h3><div class="prose max-w-none">' . $s['body'] . '</div>';
    if ($s['links']) {
        $out .= '<div class="mt-4">' . $fancy($s['links']) . '</div>';
    }
    return $out . $extra . '</div>';
};
// Group the ordered sections: runs of teasers become one two-column grid; degree maps hang off Curriculum.
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
        $groups[count($groups) - 1]['html'][] = $card($s, $extra);
    } elseif ($s['kind'] === 'feature') {
        $groups[] = ['type' => 'feature', 's' => $s];
    }
}
if (!$mapsPlaced && $mapsHtml !== '') {
    $groups[] = ['type' => 'cards', 'html' => [$card(['id' => 0, 'shared' => false, 'headline' => 'Plan your degree', 'body' => '', 'links' => []], $mapsHtml)]];
}
?>
<section class="vertical-rhythm-standard majors-program-intro">
	<div class="flex flex-col gap-8 md:flex-row md:items-start">
		<div class="grow">
			<p class="<?= $t->cls('headline.super') ?>"><?= $t->e($kind) ?></p>
			<h2 class="nc-heading text-3xl md:text-4xl mb-4"><?= $t->e($p['academic_program']) ?></h2>
<?php if ($crumbs): ?>
			<div class="mb-4 text-sm"><?= $fancy($crumbs) ?></div>
<?php endif; ?>
			<div class="prose max-w-4xl"><?= $description ?></div>
<?php if ($learn_how !== ''): ?>
			<h3 class="nc-heading text-xl mt-8 mb-3"><?= $t->e($learn_how) ?></h3>
			<div class="flex flex-wrap gap-3">
<?php foreach ($buttons as $i => $b): ?>
				<a class="nc-button"<?= $i === 0 ? ' data-variant="1"' : '' ?> href="<?= $t->e($b['href'] ?? '#') ?>"><span class="nc-button-text"><?= $t->e($b['text'] ?? '') ?></span></a>
<?php endforeach; ?>
			</div>
<?php endif; ?>
		</div>
<?php if ($image): ?>
		<figure class="md:basis-2/5 shrink-0">
			<img class="w-full" src="<?= $t->e($t->img($image['url'])) ?>" alt="<?= $t->e($image['alt']) ?>">
<?php if ($image['caption'] !== '' || $image['credit'] !== ''): ?>
			<figcaption class="text-sm text-neutral-500 mt-2"><?= $t->e($image['caption']) ?><?php if ($image['credit'] !== ''): ?> <span class="lowercase">— <?= $t->e($image['credit']) ?></span><?php endif; ?></figcaption>
<?php endif; ?>
		</figure>
<?php endif; ?>
	</div>
</section>

<?php foreach ($groups as $g): ?>
<?php if ($g['type'] === 'cards'): ?>
<section class="vertical-rhythm-standard">
	<div class="grid gap-6 md:grid-cols-2">
<?= implode("\n", $g['html']) ?>
	</div>
</section>
<?php else: ?>
<?php $s = $g['s']; ?>
<section class="vertical-rhythm-standard bg-neutral-200 p-6 majors-section" data-section="<?= (int) $s['id'] ?>">
	<div class="flex flex-col gap-6 md:flex-row md:items-start">
<?php if ($s['image']): ?>
		<img class="w-full md:basis-1/3 shrink-0" src="<?= $t->e($t->img($s['image']['url'])) ?>" alt="<?= $t->e($s['image']['alt']) ?>">
<?php endif; ?>
		<div>
			<p class="<?= $t->cls('headline.super') ?>"><?= $t->e($s['label'] !== '' ? $s['label'] : 'Inside the Program') ?></p>
			<h2 class="nc-heading text-2xl mb-3"><?= $t->e($s['headline']) ?></h2>
			<div class="prose max-w-4xl"><?= $s['body'] ?></div>
<?php if ($s['links']): ?>
			<div class="mt-4"><?= $fancy($s['links']) ?></div>
<?php endif; ?>
		</div>
	</div>
</section>
<?php endif; ?>
<?php endforeach; ?>

<?php if ($similar): ?>
<section class="vertical-rhythm-standard">
	<h2 class="nc-heading text-2xl mb-4">Similar Programs</h2>
	<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
<?php foreach ($similar as $s): ?>
		<a class="block overflow-hidden border border-black/15 text-current no-underline hover:border-black/60" href="<?= $t->e($program_url . (int) $s['id']) ?>">
<?php if (!empty($s['main_image_url'])): ?>
			<img class="w-full aspect-[3/2] object-cover" src="<?= $t->e($t->img($s['main_image_url'])) ?>" alt="">
<?php endif; ?>
			<span class="block p-4 font-bold"><?= $t->e($s['academic_program']) ?> <span class="font-normal text-neutral-500">(<?= $t->e($s['credential'] ?? $s['program_simple_type'] ?? $s['program_type']) ?>)</span></span>
		</a>
<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>
