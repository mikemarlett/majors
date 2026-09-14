<?php
/**
 * One program's marketing page — new design (Tailwind utilities, to be refined
 * against the live design system on www-dev). Same variables as old/majors/program.php.
 * @var \Majors\View\Layout $t
 */
$nav_html = '';
foreach ($nav_items as $label => $href) {
    $nav_html .= '<li><a class="underline" href="' . $t->e($href) . '">' . $t->e($label) . '</a></li>' . "\n";
}
$card = function (string $headline, string $text, ?string $linkText, ?string $linkUrl, string $extra = '') use ($t): string {
    $out = '<div class="rounded border border-neutral-200 p-6"><h3 class="nc-heading text-xl mb-3">' . $t->e($headline) . '</h3><div class="prose">' . $text . '</div>';
    if ($linkText && $linkUrl) {
        $out .= '<p class="mt-4"><a class="' . $t->cls('link.rich') . '" href="' . $t->e($linkUrl) . '">' . $t->e($linkText) . '</a></p>';
    }
    return $out . $extra . '</div>';
};
$mapsHtml = '';
if ($degree_maps !== []) {
    $mapsHtml = '<h4 class="nc-heading text-base mt-6 mb-2">' . (count($degree_maps) > 1 ? 'Degree Maps' : 'Degree Map') . '</h4><ul class="list-disc pl-5">';
    foreach ($degree_maps as $m) {
        $mapsHtml .= '<li><a class="underline" href="' . $t->e($m['url']) . '">' . $t->e($m['label']) . '</a></li>';
    }
    $mapsHtml .= '</ul>';
}
?>
<?= $t->partial('partials/page_header', ['title' => $title, 'nav_html' => $nav_html, 'noprint' => false]) ?>

<section class="container my-10 grid gap-10 md:grid-cols-2">
	<div>
		<p class="<?= $t->cls('headline.super') ?>"><?= $t->e($p['program_simple_type'] ?? '') ?></p>
		<h2 class="nc-heading text-3xl mb-4"><?= $t->e($p['academic_program']) ?></h2>
<?php if ($program_links): ?>
		<ul class="flex flex-wrap gap-x-4 gap-y-1 text-sm mb-4">
<?php foreach ($program_links as $l): ?>
			<li><a class="underline" href="<?= $t->e($l['href'] ?? '#') ?>"><?= $t->e($l['link_text'] ?? '') ?></a></li>
<?php endforeach; ?>
		</ul>
<?php endif; ?>
		<div class="prose"><?= $c['description'] ?? '' ?></div>
<?php if (!empty($c['learn_how'])): ?>
		<h3 class="nc-heading text-xl mt-8 mb-3"><?= $c['learn_how'] ?></h3>
		<div class="<?= $t->cls('button_collection') ?>">
<?php foreach ($learn_how_links as $i => $l): ?>
			<a href="<?= $t->e($l['href'] ?? '#') ?>" class="<?= $t->cls($i === 0 ? 'button.accent' : 'button') ?>"><?= $t->e($l['link_text'] ?? '') ?></a>
<?php endforeach; ?>
		</div>
<?php endif; ?>
	</div>
<?php if (!empty($c['main_image_url'])): ?>
	<figure>
		<img class="w-full rounded" src="<?= $t->e($c['main_image_url']) ?>" alt="<?= $t->e($c['main_image_alt'] ?? '') ?>">
<?php if (!empty($c['main_image_caption']) || !empty($c['main_image_credit'])): ?>
		<figcaption class="text-sm text-neutral-600 mt-2"><?= $c['main_image_caption'] ?? '' ?><?php if (!empty($c['main_image_credit'])): ?> <span class="italic"><?= $t->e($c['main_image_credit']) ?></span><?php endif; ?></figcaption>
<?php endif; ?>
	</figure>
<?php endif; ?>
</section>

<section class="container my-10 grid gap-6 md:grid-cols-2">
<?php if (!$is_certificate && !empty($c['wildcard_headline'])): ?>
	<?= $card((string) $c['wildcard_headline'], (string) ($c['wildcard_text'] ?? ''), $c['wildcard_link_text'] ?? null, $c['wildcard_link_url'] ?? null) ?>
<?php endif; ?>
<?php if (!empty($c['admissions_headline'])): ?>
	<?= $card((string) $c['admissions_headline'], (string) ($c['admissions_text'] ?? ''), $c['admissions_link_text'] ?? null, $c['admissions_link_url'] ?? null) ?>
<?php endif; ?>
<?php if (!empty($c['curriculum_text']) || $degree_maps): ?>
	<?= $card('Curriculum', (string) ($c['curriculum_text'] ?? ''), $c['curriculum_link_text'] ?? null, $c['curriculum_link_url'] ?? null, $mapsHtml) ?>
<?php endif; ?>
<?php if (!$is_certificate && !empty($c['careers_headline'])): ?>
	<?= $card((string) $c['careers_headline'], (string) ($c['careers_text'] ?? ''), $c['careers_link_text'] ?? null, $c['careers_link_url'] ?? null) ?>
<?php endif; ?>
</section>

<?php if (!empty($c['inside_the_program_headline'])): ?>
<section class="bg-neutral-100 py-10"><div class="container grid gap-8 md:grid-cols-3">
<?php if (!empty($c['inside_the_program_image_url'])): ?>
	<img class="w-full rounded" src="<?= $t->e($c['inside_the_program_image_url']) ?>" alt="<?= $t->e($c['inside_the_program_image_alt'] ?? '') ?>">
<?php endif; ?>
	<div class="md:col-span-2">
		<p class="<?= $t->cls('headline.super') ?>">Inside the Program</p>
		<h2 class="nc-heading text-2xl mb-3"><?= $t->e($c['inside_the_program_headline']) ?></h2>
		<div class="prose"><?= $c['inside_the_program_text'] ?? '' ?></div>
<?php if (!empty($c['inside_the_program_link_url'])): ?>
		<p class="mt-4"><a class="<?= $t->cls('link.rich') ?>" href="<?= $t->e($c['inside_the_program_link_url']) ?>"><?= $t->e($c['inside_the_program_link_text'] ?? 'Learn more') ?></a></p>
<?php endif; ?>
	</div>
</div></section>
<?php endif; ?>

<?php if ($similar): ?>
<section class="container my-10">
	<h2 class="nc-heading text-2xl mb-6">Similar Programs</h2>
	<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
<?php foreach ($similar as $s): ?>
		<a class="block rounded border border-neutral-200 overflow-hidden hover:shadow" href="<?= $t->e($program_url . (int) $s['id']) ?>">
<?php if (!empty($s['main_image_url'])): ?>
			<img class="w-full aspect-[3/2] object-cover" src="<?= $t->e($s['main_image_url']) ?>" alt="">
<?php endif; ?>
			<span class="block p-4 font-semibold"><?= $t->e($s['academic_program']) ?> <span class="font-normal text-neutral-600">(<?= $t->e($s['program_simple_type'] ?? $s['program_type']) ?>)</span></span>
		</a>
<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>
