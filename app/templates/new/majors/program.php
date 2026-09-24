<?php
/**
 * New design: the main column of a program page, after the Program Detail
 * page demo — Teaser Grids for runs of teaser sections and a dark Split
 * Feature (wheat background, arrowheads) for Inside the Program. The Program
 * Card and Similar Programs are their own full-width slabs (program_card.php,
 * program_similar.php) unless $split is false.
 *
 * Variables: $p, $kind, $crumbs, $description, $learn_how, $buttons, $image, $sections, $degree_maps, $similar,
 *            $program_url, $split (bool: card/similar rendered elsewhere)
 * @var \Majors\View\Layout $t
 */
$fancy = static function (array $links) use ($t): string {
    if ($links === []) {
        return '';
    }
    $out = '<ul role="list" class="nc-fancy-link-list">';
    foreach ($links as $l) {
        $out .= '<li><span class="nc-fancy-link-wrapper"><a class="nc-fancy-link" href="' . $t->e($l['href']) . '">' . $t->e(trim($l['text'])) . '</a></span></li>';
    }
    return $out . '</ul>';
};
$mapLinks = array_map(static fn (array $m) => ['text' => $m['label'], 'href' => $m['url']], $degree_maps);
$teaser = static function (array $s, array $extraLinks = [], string $extraHeading = '') use ($t, $fancy): string {
    $out = '<li class="group/li"><section class="nc-teaser majors-section" data-section="' . (int) $s['id'] . '"' . ($s['shared'] ? ' data-shared="1"' : '') . '><div class="flex flex-col gap-4"><div class="flex flex-col gap-4">'
        . '<h2 class="font-display [&_*]:!font-display font-semibold [&_*]:!font-semibold text-xl sm:text-2xl/[1.85rem] md:group-data-[max-cols=2]/teaser-grid:text-size-3xl md:group-has-[>:nth-child(2):last-child]/teaser-grid:text-size-3xl md:group-has-[>:only-child]/teaser-grid:text-size-3xl">' . $t->e($s['headline']) . '</h2>';
    if ($s['body'] !== '') {
        $out .= '<div><div class="prose max-w-4xl">' . $s['body'] . '</div></div>';
    }
    if ($s['links']) {
        $out .= '<div>' . $fancy($s['links']) . '</div>';
    }
    if ($extraLinks) {
        $out .= '<div class="pt-2"><h3 class="nc-heading text-base mb-2">' . $t->e($extraHeading) . '</h3>' . $fancy($extraLinks) . '</div>';
    }
    return $out . '</div></div></section></li>';
};
$grid = static fn (array $items): string => '<section data-nc-component="teaser-grid" class="generic-slab"><div data-fade-me="true" data-shift-me-up="true" class="generic-slab-inner vertical-rhythm-standard theme-not-default:my-0 with-sidebar-up:theme-not-default:my-vertical-space with-sidebar-up-first:theme-not-default:mt-0 with-sidebar-up-last:theme-not-default:mb-0 theme-not-default:py-vertical-space-padding">'
    . '<div class="generic-slab-content-outer-wrapper relative conditional-container theme-not-default:container"><div class="generic-slab-content-inner-wrapper flex flex-col gap-8"><div class="generic-slab-component-wrapper"><div class="flex flex-col gap-10">'
    . '<ul data-max-cols="2" style="--max-cols: 2;" role="list" class="group/teaser-grid [--item-min-w:10rem] [--gap-x:1.1rem] [--fr:1fr] xs:[--item-min-w:14rem] xs:[--gap-x:1.5rem] md:[--gap-x:2rem] md:[--fr:.5fr] lg:[--gap-x:2.5rem] [--max-gap-x-count:calc(var(--max-cols,3)-1)] [--max-total-gap-x-width:calc(var(--max-gap-x-count)*var(--gap-x))] [--item-max-w:calc((100%-var(--max-total-gap-x-width))/var(--max-cols,3))] grid gap-x-[--gap-x] gap-y-10 grid-cols-[repeat(auto-fit,minmax(max(var(--item-max-w),var(--item-min-w)),var(--fr)))]">'
    . implode('', $items) . '</ul></div></div></div></div></div></section>';
$feature = static function (array $s) use ($t, $fancy): string {
    $label = $s['label'] !== '' ? $s['label'] : 'Inside the Program';
    $media = $s['image'] ? '<picture><img src="' . $t->e($t->img($s['image']['url'])) . '" alt="' . $t->e($s['image']['alt']) . '" class="w-full"></picture>' : '';
    return '<section data-nc-component="split-feature" data-tw-theme="neutral-900" data-arrowhead-top="true" data-arrowhead-bottom="true" class="generic-slab majors-section" data-section="' . (int) $s['id'] . '">'
        . '<div data-fade-me="true" data-shift-me-up="true" class="generic-slab-inner vertical-rhythm-standard theme-not-default:my-0 with-sidebar-up:theme-not-default:my-vertical-space with-sidebar-up-first:theme-not-default:mt-0 with-sidebar-up-last:theme-not-default:mb-0 theme-not-default:py-vertical-space-padding">'
        . '<div aria-hidden="true" class="top-arrowhead-spacer tw-hidden relative h-[--arrowhead-h] pointer-events-none"></div>'
        . '<div aria-hidden="true" class="absolute inset-0 pointer-events-none theme-default:tw-hidden"><div data-bg-pattern-parallax-wrapper="true" aria-hidden="true" class="relative size-full overflow-hidden"><div data-bg-wheat="true" style="--wheat-tile: url(\'/_resources/_theme/images/wheat.svg\'); --wheat-tile-width: 1440px; --wheat-tile-height: 1388px; --parallax-scale: 1.3; --wheat-tile-parallax-size: calc(var(--wheat-tile-width) / var(--parallax-scale)) calc(var(--wheat-tile-height) / var(--parallax-scale));" class="absolute inset-0 bg-[image:--wheat-tile] opacity-[--theme-wheat-opacity] [.simple-parallax-initialized>&]:bg-[length:--wheat-tile-parallax-size]"></div></div></div>'
        . '<div class="generic-slab-content-outer-wrapper relative conditional-container theme-not-default:container"><div class="generic-slab-content-inner-wrapper flex flex-col gap-8"><div class="generic-slab-component-wrapper">'
        . '<div class="group/split-feature grid grid-cols-1 gap-8 md:grid-cols-10 md:gap-12"><div class="md:col-span-5 lg:group-data-[sixty-forty]/split-feature:col-span-4 self-center"><div class="flex flex-col gap-8"><div class="flex flex-col gap-5">'
        . '<div><h2 data-style-level="2" class="nc-heading text-[length:--text-size] sm:text-[length:--text-size-sm] md:text-[length:--text-size-md]">' . $t->e($label) . '</h2></div>'
        . '<div><div class="prose max-w-4xl"><h3>' . $t->e($s['headline']) . '</h3>' . $s['body'] . '</div></div>'
        . ($s['links'] ? '<div>' . $fancy($s['links']) . '</div>' : '')
        . '</div></div></div>'
        . '<div class="order-first md:order-last md:group-data-[flipped]/split-feature:order-first md:col-span-5 lg:group-data-[sixty-forty]/split-feature:col-span-6">' . $media . '</div>'
        . '</div></div></div></div>'
        . '<div aria-hidden="true" class="bottom-arrowhead-spacer tw-hidden relative h-[--arrowhead-h] pointer-events-none"></div></div></section>';
};
$groups = [];
$mapsPlaced = false;
foreach ($sections as $s) {
    if ($s['kind'] === 'teaser') {
        $extra = [];
        if (!$mapsPlaced && $s['headline'] === 'Curriculum' && $mapLinks !== []) {
            $extra = $mapLinks;
            $mapsPlaced = true;
        }
        if ($groups === [] || end($groups)['type'] !== 'grid') {
            $groups[] = ['type' => 'grid', 'items' => []];
        }
        $groups[count($groups) - 1]['items'][] = $teaser($s, $extra, count($mapLinks) > 1 ? 'Degree Maps' : 'Degree Map');
    } elseif ($s['kind'] === 'feature') {
        $groups[] = ['type' => 'feature', 's' => $s];
    }
}
if (!$mapsPlaced && $mapLinks !== []) {
    $groups[] = ['type' => 'grid', 'items' => [$teaser(['id' => 0, 'shared' => false, 'headline' => count($mapLinks) > 1 ? 'Degree Maps' : 'Degree Map', 'body' => '', 'links' => $mapLinks])]];
}
?>
<?php if (empty($split)): ?>
<?= $t->render('majors/program_card', get_defined_vars()) ?>
<?php endif; ?>
<?php foreach ($groups as $g): ?>
<?= $g['type'] === 'grid' ? $grid($g['items']) : $feature($g['s']) ?>

<?php endforeach; ?>
<?php if (empty($split)): ?>
<?= $t->render('majors/program_similar', get_defined_vars()) ?>
<?php endif; ?>
