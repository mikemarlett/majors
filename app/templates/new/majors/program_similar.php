<?php
/**
 * New design: Similar Programs as the Image Card Grid organism (full width
 * after the sidebar grid, neutral-900, centred heading, wide cards).
 * Variables: $similar
 * @var \Majors\View\Layout $t
 */
if (!$similar) {
    return;
}
?>
<section data-nc-component="image-card-grid" data-tw-theme="neutral-900" data-small-container="true" data-centered-small-container="true" data-centered-heading="true" class="generic-slab">
	<div data-fade-me="true" data-shift-me-up="true" class="generic-slab-inner vertical-rhythm-standard theme-not-default:my-0 with-sidebar-up:theme-not-default:my-vertical-space with-sidebar-up-first:theme-not-default:mt-0 with-sidebar-up-last:theme-not-default:mb-0 theme-not-default:py-vertical-space-padding">
		<div aria-hidden="true" class="top-arrowhead-spacer tw-hidden relative h-[--arrowhead-h] pointer-events-none"></div>
		<div class="generic-slab-content-outer-wrapper relative conditional-container theme-not-default:container">
			<div class="generic-slab-content-inner-wrapper flex flex-col gap-8">
				<div class="generic-slab-extras-wrapper flex flex-col gap-6">
					<div class="generic-slab-heading-wrapper max-w-4xl"><h2 data-style-level="2" class="nc-heading text-[length:--text-size] sm:text-[length:--text-size-sm] md:text-[length:--text-size-md]">Similar Programs</h2></div>
				</div>
				<div class="generic-slab-component-wrapper">
					<ul data-card-style="wide" role="list" class="group/image-cards grid grid-cols-1 gap-8 md:grid-cols-2 lg:gap-11 lg-xs:data-[card-style=tall]:grid-cols-2 md:data-[card-style=tall]:grid-cols-3 lg:data-[card-style=tall]:grid-cols-4">
<?php foreach ($similar as $s): ?>
						<li>
							<a href="<?= $t->e($t->programUrl($s)) ?>" class="nc-image-card group/fancy-link-outer-link flex flex-col xs:flex-row xs:items-stretch xs:group-data-[card-style=tall]/image-cards:flex-col h-full">
								<div class="xs:w-1/3 xs:self-stretch xs:group-data-[card-style=tall]/image-cards:w-full shrink-0 descendants:size-full [&_img]:object-cover overflow-hidden">
									<div class="will-change-transform transition-transform group-hocus/fancy-link-outer-link:scale-110">
<?php if (!empty($s['main_image_url'])): ?>
										<picture><img src="<?= $t->e($t->img($s['main_image_url'])) ?>" alt="" loading="lazy"></picture>
<?php else: ?>
										<div class="bg-neutral-700 aspect-[3/2]"></div>
<?php endif; ?>
									</div>
								</div>
								<div class="px-5 py-4 w-full bg-neutral-800 flex items-center h-full leading-tight">
									<div class="nc-fancy-link-wrapper"><div class="nc-fancy-link uppercase"><?= $t->e(trim($s['academic_program'] . ' (' . ($s['credential'] ?? $s['program_simple_type'] ?? $s['program_type']) . ')')) ?></div></div>
								</div>
							</a>
						</li>
<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
		<div aria-hidden="true" class="bottom-arrowhead-spacer tw-hidden relative h-[--arrowhead-h] pointer-events-none"></div>
	</div>
</section>
