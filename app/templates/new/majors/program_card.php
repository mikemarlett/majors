<?php
/**
 * New design: the Program Card organism (Organism/ProgramCard) as the page
 * demo uses it — a neutral-200 Generic Slab with a dots background and a
 * bottom arrowhead, full width under the page title. Markup follows
 * ProgramCard.twig / GenericSlab.twig / Figure.twig / ButtonLinkList.twig.
 *
 * Variables: $p, $kind, $crumbs, $description, $learn_how, $buttons, $image, $facts (label/value), $is_stem, $coordinator (name/email/phone|null)
 * @var \Majors\View\Layout $t
 */
?>
<section data-nc-component="program-card" data-tw-theme="neutral-200" data-arrowhead-bottom="true" class="generic-slab">
	<div data-fade-me="true" data-shift-me-up="true" class="generic-slab-inner vertical-rhythm-standard theme-not-default:my-0 with-sidebar-up:theme-not-default:my-vertical-space with-sidebar-up-first:theme-not-default:mt-0 with-sidebar-up-last:theme-not-default:mb-0 theme-not-default:py-vertical-space-padding">
		<div aria-hidden="true" class="top-arrowhead-spacer tw-hidden relative h-[--arrowhead-h] pointer-events-none"></div>
		<div aria-hidden="true" class="absolute inset-0 pointer-events-none theme-default:tw-hidden">
			<div data-bg-pattern-parallax-wrapper="true" aria-hidden="true" class="relative size-full overflow-hidden">
				<div data-bg-dots="true" style="--parallax-scale: 1.3; --dots-tile-parallax-size: calc(42px / var(--parallax-scale)) calc(42px / var(--parallax-scale));" class="absolute inset-0 mask-dots mask-repeat bg-theme-dots-color/[--theme-dots-opacity] [.simple-parallax-initialized>&]:[mask-size:--dots-tile-parallax-size]"></div>
			</div>
		</div>
		<div class="generic-slab-content-outer-wrapper relative conditional-container theme-not-default:container">
			<div class="generic-slab-content-inner-wrapper flex flex-col gap-8">
				<div class="generic-slab-component-wrapper">
					<div<?= $image ? ' data-with-media="true"' : '' ?> class="group/program-card grid grid-cols-1 gap-8 lg:grid-cols-11 lg:gap-12">
						<div class="lg:col-span-full max-w-3xl lg:group-data-[with-media]/program-card:col-span-5 self-center">
							<div class="flex flex-col gap-6">
								<div>
									<h2 class="nc-heading text-[length:--text-size] sm:text-[length:--text-size-sm] md:text-[length:--text-size-md]"><?= $t->e($p['academic_program']) ?></h2>
								</div>
<?php if ($kind !== '' || $crumbs): ?>
								<div class="order-first flex flex-col gap-4">
<?php if ($kind !== ''): ?>
									<div class="flex flex-wrap items-center gap-x-4 gap-y-2 font-bold uppercase text-theme-heading-color text-lg/tight sm:text-size-xl md:text-2xl"><span><span class="sr-only">Program type: </span><?= $t->e($kind) ?></span><?php if ($is_stem): ?><span class="text-sm border-2 border-theme-text-color px-3 py-1 tracking-wide">STEM Program</span><?php endif; ?></div>
<?php endif; ?>
<?php if ($crumbs): ?>
									<ul role="list" class="flex flex-wrap gap-x-6 sm:gap-x-4 gap-y-2.5 leading-none">
<?php foreach ($crumbs as $l): ?>
										<li class="relative sm:pr-4 sm:last:pr-0 after:hidden sm:after:inline-block sm:last:after:hidden after:relative after:w-px after:-ml-px after:h-4 after:bg-theme-text-color/50 after:left-4 after:top-0.5"><a href="<?= $t->e($l['href']) ?>" class="underline text-theme-link-color hocus:text-theme-link-hocus-color text-base/tight"><?= $t->e(trim($l['text'])) ?></a></li>
<?php endforeach; ?>
									</ul>
<?php endif; ?>
								</div>
<?php endif; ?>
<?php if ($description !== ''): ?>
								<div><div class="prose max-w-4xl"><?= $description ?></div></div>
<?php endif; ?>
<?php if ($learn_how !== ''): ?>
								<p class="font-semibold text-lg/tight text-theme-heading-color"><?= $t->e($learn_how) ?></p>
<?php endif; ?>
<?php if ($facts): ?>
								<dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 border-2 border-theme-text-color p-4 majors-facts">
<?php foreach ($facts as $f): ?>
									<div><dt class="text-xs font-bold uppercase tracking-wide text-theme-text-color/80"><?= $t->e($f['label']) ?></dt><dd class="font-display font-bold text-2xl leading-tight text-theme-heading-color"><?= $t->e($f['value']) ?></dd></div>
<?php endforeach; ?>
								</dl>
<?php endif; ?>
<?php if ($buttons): ?>
								<div><div><ul role="list" class="flex flex-wrap flex-col gap-5 [&_.nc-button]:w-full md-xs:flex-row">
<?php foreach ($buttons as $i => $b): ?>
									<li><a<?= $i > 0 ? ' data-variant="2"' : '' ?> href="<?= $t->e($b['href'] ?? '#') ?>" class="nc-button"><span class="nc-button-text"><?= $t->e(trim((string) ($b['text'] ?? ''))) ?></span></a></li>
<?php endforeach; ?>
								</ul></div></div>
<?php endif; ?>
<?php if ($coordinator): ?>
								<p class="text-base majors-coordinator">Questions? Contact <?= $coordinator['name'] !== '' ? 'Program Coordinator ' . $t->e($coordinator['name']) : 'the program' ?><?php if ($coordinator['email'] !== ''): ?> at <a class="underline text-theme-link-color hocus:text-theme-link-hocus-color" href="mailto:<?= $t->e($coordinator['email']) ?>"><?= $t->e($coordinator['email']) ?></a><?php endif; ?><?php if ($coordinator['phone'] !== ''): ?> or call <a class="underline text-theme-link-color hocus:text-theme-link-hocus-color" href="tel:<?= $t->e(preg_replace('/[^0-9+]/', '', $coordinator['phone'])) ?>"><?= $t->e($coordinator['phone']) ?></a><?php endif; ?>.</p>
<?php endif; ?>
							</div>
						</div>
<?php if ($image): ?>
						<div class="lg:col-span-6">
							<figure class="<?= ($image['caption'] !== '' || $image['credit'] !== '') ? 'table ' : '' ?>space-y-2.5">
								<picture><img src="<?= $t->e($t->img($image['url'])) ?>" alt="<?= $t->e($image['alt']) ?>" class="w-full"></picture>
<?php if ($image['caption'] !== '' || $image['credit'] !== ''): ?>
								<figcaption class="table-caption caption-bottom text-sm text-theme-text-color">
									<div class="flex flex-col space-y-2 max-w-3xl [&_a]:underline [&_a]:text-theme-link-color hocus:[&_a]:text-theme-link-hocus-color">
<?php if ($image['caption'] !== ''): ?>
										<span><?= $t->e($image['caption']) ?></span>
<?php endif; ?>
<?php if ($image['credit'] !== ''): ?>
										<span class="italic font-semibold"><?= $t->e($image['credit']) ?></span>
<?php endif; ?>
									</div>
								</figcaption>
<?php endif; ?>
							</figure>
						</div>
<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<div aria-hidden="true" class="bottom-arrowhead-spacer tw-hidden relative h-[--arrowhead-h] pointer-events-none"></div>
	</div>
</section>
