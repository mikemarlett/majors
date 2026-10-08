<?php
/**
 * New design: A–Z / by-college list of maps as the design system's
 * AlphaNav (partials/alpha_nav; letter jump links, alphabetical order only)
 * + AlphaListing (dotted-rule sections) with a ColumnedLinkList in each
 * section. Markup mirrors theme/components/Organism/{AlphaListing,ColumnedLinkList}.
 *
 * Variables: $groups (Listing::byAlpha/byCollege), $link_base, optional $alpha_nav (bool),
 *            $flags (map id => label; admin: duplicates, not approved)
 * @var \Majors\View\Layout $t
 */
/* $flags: map id => label ("duplicate", "not approved", "not approved, duplicate"); $flag_ids + $flag_text is the older one-label form. */
$flags = $flags ?? [];
foreach (($flag_ids ?? []) as $fid) {
    $flags[(int) $fid] = (string) ($flag_text ?? '');
}
$flagTitles = [
    'duplicate'    => 'Another map with the same name, degree type and college exists for this catalog year',
    'not approved' => 'Not on the public site until an advisor admin or a super admin approves it',
];
$flagTitle = static fn (string $label): string => implode('. ', array_filter(array_map(static fn (string $part): string => $flagTitles[trim($part)] ?? '', explode(',', $label))));
$alpha_nav = !empty($alpha_nav);
$anchor    = static fn (string $key): string => 'dm-' . (preg_match('/^[A-Za-z]$/', $key) ? strtoupper($key) : (ctype_digit($key) ? 'num' : 'other'));
?>
<div class="dm-listing">
<?php if ($groups === []): ?>
	<p class="nc-heading text-2xl">No degree maps found.</p>
<?php endif; ?>
<?php if ($alpha_nav && $groups !== []): ?>
<?php
$links = [];
foreach ($groups as $g) {
    $key = (string) $g['key'];
    $letter = preg_match('/^[A-Za-z]$/', $key) ? strtoupper($key) : (ctype_digit($key) ? '#' : null);
    if ($letter !== null) {
        $links[$letter] ??= $anchor($key);
    }
}
echo $t->partial('partials/alpha_nav', ['links' => $links]);
?>
<?php endif; ?>
<?php if ($groups !== []): ?>
	<div data-nc-component="alpha-listing" class="grid grid-cols-1 gap-10">
<?php foreach ($groups as $group): ?>
		<section id="<?= $anchor((string) $group['key']) ?>" class="grid grid-cols-1 gap-4 scroll-mt-6">
			<h2 data-style-level="3" class="nc-heading text-[length:--text-size] sm:text-[length:--text-size-sm] md:text-[length:--text-size-md]"><?= $t->e($group['label']) ?></h2>
			<div class="pt-8 border-t-4 border-dotted border-theme-border-color">
				<div class="prose md:prose-lg max-w-none">
					<ul role="list" data-max-cols="3" class="leading-snug lg-xs:columns-2 gap-8 sm:gap-12 lg:columns-3 xl:data-[max-cols=4]:columns-4 xl:gap-16 descendants:break-inside-avoid children:pb-5 -mb-5 children:!my-0 !list-none !pl-0 children:!pl-0">
<?php foreach ($group['items'] as $m): ?>
						<li><a href="<?= $t->e($link_base . (int) $m['id']) ?>"><?= $t->e($m['major']) ?></a> — <?= $t->e($m['degree_type']) ?><?php if (isset($flags[(int) $m['id']])): ?> <span class="dm-flag" title="<?= $t->e($flagTitle($flags[(int) $m['id']])) ?> (id <?= (int) $m['id'] ?>)"><?= $t->e($flags[(int) $m['id']]) ?></span><?php endif; ?></li>
<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</section>
<?php endforeach; ?>
	</div>
<?php endif; ?>
</div>
