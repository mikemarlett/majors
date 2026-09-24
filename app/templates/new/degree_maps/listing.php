<?php
/**
 * New design: A–Z / by-college list of maps as the design system's
 * AlphaNav (letter jump links, alphabetical order only) + AlphaListing
 * (dotted-rule sections) with a ColumnedLinkList in each section. Markup
 * mirrors theme/components/Organism/{AlphaNav,AlphaListing,ColumnedLinkList}.
 *
 * Variables: $groups (Listing::byAlpha/byCollege), $link_base, optional $alpha_nav (bool),
 *            $flag_ids (ids to tag) + $flag_text (admin: duplicates)
 * @var \Majors\View\Layout $t
 */
$flag_ids  = $flag_ids ?? [];
$flag_text = $flag_text ?? '';
$alpha_nav = !empty($alpha_nav);
$anchor    = static fn (string $key): string => 'dm-' . (preg_match('/^[A-Za-z]$/', $key) ? strtoupper($key) : (ctype_digit($key) ? 'num' : 'other'));
$present   = [];
foreach ($groups as $g) {
    $present[$anchor((string) $g['key'])] = true;
}
$linkBase = 'rounded-full absolute top-1/2 left-1/2 -translate-y-1/2 -translate-x-1/2 w-full h-full bg-theme-button-bg-color text-theme-button-text-color font-semibold flex justify-center items-center';
?>
<div class="dm-listing">
<?php if ($groups === []): ?>
	<p class="nc-heading text-2xl">No degree maps found.</p>
<?php endif; ?>
<?php if ($alpha_nav && $groups !== []): ?>
	<nav data-nc-component="alpha-nav" data-tw-theme="neutral-200" aria-label="Jump to a letter" class="generic-slab mb-8 noprint">
		<div class="generic-slab-inner !py-7">
			<div class="generic-slab-content-outer-wrapper relative conditional-container theme-not-default:container">
				<div class="generic-slab-content-inner-wrapper flex flex-col gap-8">
					<div class="generic-slab-component-wrapper">
						<ul role="list" class="flex flex-wrap gap-2 text-lg/0">
<?php foreach (array_merge(range('A', 'Z'), ['#']) as $letter): ?>
<?php $id = $anchor($letter === '#' ? '0' : $letter); ?>
							<li class="relative p-[1.125rem]">
<?php if (isset($present[$id])): ?>
								<a href="#<?= $id ?>" class="<?= $linkBase ?> hocus:bg-theme-button-2-bg-color hocus:text-theme-button-2-text-color"<?= $letter === '#' ? ' aria-label="Numeric"' : '' ?>><?= $letter ?></a>
<?php elseif ($letter !== '#'): ?>
								<span role="link" aria-disabled="true" class="<?= $linkBase ?> opacity-50"><?= $letter ?></span>
<?php endif; ?>
							</li>
<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</nav>
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
						<li><a href="<?= $t->e($link_base . (int) $m['id']) ?>"><?= $t->e($m['major']) ?></a> — <?= $t->e($m['degree_type']) ?><?php if (in_array((int) $m['id'], $flag_ids, true)): ?> <span class="dm-flag" title="Another map with the same name, degree type and college exists for this catalog year (id <?= (int) $m['id'] ?>)"><?= $t->e($flag_text) ?></span><?php endif; ?></li>
<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</section>
<?php endforeach; ?>
	</div>
<?php endif; ?>
</div>
