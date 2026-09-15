<?php
/**
 * One program's marketing page — new design. Built from the design system's
 * own pieces as published on www-dev: .prose editorial copy, .nc-heading,
 * .nc-fancy-link-list link lists, .nc-button CTAs (data-variant="1" = primary).
 *
 * Variables: $p (program), $c (content), $title, $is_certificate, $program_links, $learn_how_links,
 *            $degree_maps (id,label,url), $similar, $program_url (header + section menu come from the layout)
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
$card = static function (string $headline, string $html, ?string $linkText, ?string $linkUrl, string $extra = '') use ($t, $fancy): string {
    $out = '<div class="majors-card"><h3 class="nc-heading text-xl mb-3">' . $t->e($headline) . '</h3><div class="prose max-w-none">' . $html . '</div>';
    if ($linkText && $linkUrl) {
        $out .= '<div class="mt-4">' . $fancy([['text' => $linkText, 'href' => $linkUrl]]) . '</div>';
    }
    return $out . $extra . '</div>';
};
$mapLinks = array_map(static fn (array $m) => ['text' => $m['label'], 'href' => $m['url']], $degree_maps);
$mapsHtml = $degree_maps === [] ? '' : '<h4 class="nc-heading text-base mt-6 mb-2">' . (count($degree_maps) > 1 ? 'Degree Maps' : 'Degree Map') . '</h4>' . $fancy($mapLinks);
?>
<section class="vertical-rhythm-standard majors-program-intro">
	<div class="flex flex-col gap-8 md:flex-row md:items-start">
		<div class="grow">
			<p class="<?= $t->cls('headline.super') ?>"><?= $t->e($p['program_simple_type'] ?? '') ?></p>
			<h2 class="nc-heading text-3xl md:text-4xl mb-4"><?= $t->e($p['academic_program']) ?></h2>
<?php if ($program_links): ?>
			<div class="mb-4 text-sm"><?= $fancy(array_map(static fn ($l) => ['text' => $l['link_text'] ?? '', 'href' => $l['href'] ?? '#'], $program_links)) ?></div>
<?php endif; ?>
			<div class="prose max-w-4xl"><?= $c['description'] ?? '' ?></div>
<?php if (!empty($c['learn_how'])): ?>
			<h3 class="nc-heading text-xl mt-8 mb-3"><?= $c['learn_how'] ?></h3>
			<div class="flex flex-wrap items-center gap-3">
<?php foreach ($learn_how_links as $i => $l): ?>
				<a class="nc-button"<?= $i === 0 ? ' data-variant="1"' : '' ?> href="<?= $t->e($l['href'] ?? '#') ?>"><span class="nc-button-text"><?= $t->e($l['link_text'] ?? '') ?></span></a>
<?php endforeach; ?>
			</div>
<?php endif; ?>
		</div>
<?php if (!empty($c['main_image_url'])): ?>
		<figure class="md:basis-2/5 shrink-0">
			<img class="w-full" src="<?= $t->e($c['main_image_url']) ?>" alt="<?= $t->e($c['main_image_alt'] ?? '') ?>">
<?php if (!empty($c['main_image_caption']) || !empty($c['main_image_credit'])): ?>
			<figcaption class="text-sm text-neutral-500 mt-2"><?= $c['main_image_caption'] ?? '' ?><?php if (!empty($c['main_image_credit'])): ?> <span class="italic"><?= $t->e($c['main_image_credit']) ?></span><?php endif; ?></figcaption>
<?php endif; ?>
		</figure>
<?php endif; ?>
	</div>
</section>

<?php
$cards = [];
if (!$is_certificate && !empty($c['wildcard_headline'])) {
    $cards[] = $card((string) $c['wildcard_headline'], (string) ($c['wildcard_text'] ?? ''), $c['wildcard_link_text'] ?? null, $c['wildcard_link_url'] ?? null);
}
if (!empty($c['admissions_headline'])) {
    $cards[] = $card((string) $c['admissions_headline'], (string) ($c['admissions_text'] ?? ''), $c['admissions_link_text'] ?? null, $c['admissions_link_url'] ?? null);
}
if (!empty($c['curriculum_text']) || $degree_maps) {
    $cards[] = $card('Curriculum', (string) ($c['curriculum_text'] ?? ''), $c['curriculum_link_text'] ?? null, $c['curriculum_link_url'] ?? null, $mapsHtml);
}
if (!$is_certificate && !empty($c['careers_headline'])) {
    $cards[] = $card((string) $c['careers_headline'], (string) ($c['careers_text'] ?? ''), $c['careers_link_text'] ?? null, $c['careers_link_url'] ?? null);
}
?>
<?php if ($cards): ?>
<section class="vertical-rhythm-standard">
	<div class="grid gap-6 md:grid-cols-2">
<?= implode("\n", $cards) ?>
	</div>
</section>
<?php endif; ?>

<?php if (!empty($c['inside_the_program_headline'])): ?>
<section class="vertical-rhythm-standard majors-band">
	<div class="flex flex-col gap-8 md:flex-row md:items-start">
<?php if (!empty($c['inside_the_program_image_url'])): ?>
		<img class="w-full md:basis-1/3 shrink-0" src="<?= $t->e($c['inside_the_program_image_url']) ?>" alt="<?= $t->e($c['inside_the_program_image_alt'] ?? '') ?>">
<?php endif; ?>
		<div class="grow">
			<p class="<?= $t->cls('headline.super') ?>">Inside the Program</p>
			<h2 class="nc-heading text-2xl mb-3"><?= $t->e($c['inside_the_program_headline']) ?></h2>
			<div class="prose max-w-4xl"><?= $c['inside_the_program_text'] ?? '' ?></div>
<?php if (!empty($c['inside_the_program_link_url'])): ?>
			<div class="mt-4"><?= $fancy([['text' => $c['inside_the_program_link_text'] ?? 'Learn more', 'href' => $c['inside_the_program_link_url']]]) ?></div>
<?php endif; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ($similar): ?>
<section class="vertical-rhythm-standard">
	<h2 class="nc-heading text-2xl mb-6">Similar Programs</h2>
	<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
<?php foreach ($similar as $s): ?>
		<a class="majors-card majors-card--link" href="<?= $t->e($program_url . (int) $s['id']) ?>">
<?php if (!empty($s['main_image_url'])): ?>
			<img class="w-full aspect-[3/2] object-cover" src="<?= $t->e($s['main_image_url']) ?>" alt="">
<?php endif; ?>
			<span class="block p-4 font-bold"><?= $t->e($s['academic_program']) ?> <span class="font-normal text-neutral-500">(<?= $t->e($s['program_simple_type'] ?? $s['program_type']) ?>)</span></span>
		</a>
<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>
