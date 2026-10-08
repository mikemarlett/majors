<?php
/**
 * Letter index for an A–Z listing: the design system's AlphaNav organism
 * (theme/components/Organism/AlphaNav), letter jump links in a neutral slab.
 *
 * Variables: $links (letter => anchor id, only the letters that have a section;
 *            '#' for a numeric section), optional $label
 * @var \Majors\View\Layout $t
 */
$label    = $label ?? 'Jump to a letter';
$linkBase = 'rounded-full absolute top-1/2 left-1/2 -translate-y-1/2 -translate-x-1/2 w-full h-full bg-theme-button-bg-color text-theme-button-text-color font-semibold flex justify-center items-center';
?>
	<nav data-nc-component="alpha-nav" data-tw-theme="neutral-200" aria-label="<?= $t->e($label) ?>" class="generic-slab [--vertical-space-padding:1.75rem] mb-8 noprint">
		<div class="generic-slab-inner theme-not-default:py-vertical-space-padding">
			<div class="generic-slab-content-outer-wrapper relative conditional-container theme-not-default:container">
				<div class="generic-slab-content-inner-wrapper flex flex-col gap-8">
					<div class="generic-slab-component-wrapper">
						<ul role="list" class="flex flex-wrap gap-2 text-lg/0">
<?php foreach (array_merge(range('A', 'Z'), ['#']) as $letter): ?>
							<li class="relative p-[calc(18rem/16)]">
<?php if (isset($links[$letter])): ?>
								<a href="#<?= $t->e($links[$letter]) ?>" class="<?= $linkBase ?> hocus:bg-theme-button-2-bg-color hocus:text-theme-button-2-text-color"<?= $letter === '#' ? ' aria-label="Numeric"' : '' ?>><?= $letter ?></a>
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
