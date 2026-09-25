<?php
/**
 * New design: the Program Card organism (Organism/ProgramCard) as the page
 * demo uses it — a neutral-200 Generic Slab with a dots background and a
 * bottom arrowhead, full width under the page title. Markup follows
 * ProgramCard.twig / GenericSlab.twig / Figure.twig / ButtonLinkList.twig.
 *
 * When $editing, $ed (EditMarks) adds the in-place editor's markers and
 * placeholders; the public output is unchanged.
 *
 * Variables: $p, $kind, $crumbs, $description, $learn_how, $buttons, $image, $facts (label/value), $is_stem, $coordinator (name/email/phone|null), $editing, $ed
 * @var \Majors\View\Layout $t
 */
$identity = $ed->form('identity', ['credential' => (string) ($p['credential'] ?? ''), 'program_type' => (string) ($p['program_type'] ?? ''), 'graduate' => (int) !empty($p['graduate']), 'is_stem' => (int) $is_stem]);
$crumbsForm = $ed->form('crumbs', ['college' => (string) ($p['college'] ?? ''), 'college_url' => (string) ($p['college_url'] ?? ''), 'department' => (string) ($p['department'] ?? ''), 'department_url' => (string) ($p['department_url'] ?? '')]);
$imageForm = $ed->form('image', ['image_url' => (string) ($p['image_url'] ?? ''), 'image_alt' => (string) ($p['image_alt'] ?? ''), 'image_caption' => (string) ($p['image_caption'] ?? ''), 'image_credit' => (string) ($p['image_credit'] ?? '')]);
$factsForm = $ed->form('facts', ['degree_title' => (string) ($p['degree_title'] ?? ''), 'modality' => (string) ($p['modality'] ?? ''), 'credit_hours' => (string) ($p['credit_hours'] ?? ''), 'entry_terms' => (string) ($p['entry_terms'] ?? '')]);
$coordForm = $ed->form('coordinator', ['coordinator_name' => (string) ($p['coordinator_name'] ?? ''), 'coordinator_email' => (string) ($p['coordinator_email'] ?? ''), 'coordinator_phone' => (string) ($p['coordinator_phone'] ?? '')]);
$buttonsForm = $ed->form('buttons', ['buttons' => $buttons]);
$withMedia = $image || $editing;
?>
<section data-nc-component="program-card" data-tw-theme="neutral-200" data-arrowhead-bottom="true" class="generic-slab"<?= $editing ? ' data-ma-part="card" data-ma-scope="program"' : '' ?>>
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
					<div<?= $withMedia ? ' data-with-media="true"' : '' ?> class="group/program-card grid grid-cols-1 gap-8 lg:grid-cols-11 lg:gap-12">
						<div class="lg:col-span-full max-w-3xl lg:group-data-[with-media]/program-card:col-span-5 self-center">
							<div class="flex flex-col gap-6">
								<div>
									<h2 class="nc-heading text-[length:--text-size] sm:text-[length:--text-size-sm] md:text-[length:--text-size-md]"<?= $ed->text('academic_program') ?>><?= $t->e($p['academic_program']) ?></h2>
								</div>
<?php if ($kind !== '' || $crumbs || $editing): ?>
								<div class="order-first flex flex-col gap-4">
<?php if ($kind !== ''): ?>
									<div class="flex flex-wrap items-center gap-x-4 gap-y-2 font-bold uppercase text-theme-heading-color text-lg/tight sm:text-size-xl md:text-2xl"<?= $identity ?>><span><span class="sr-only">Program type: </span><?= $t->e($kind) ?></span><?php if ($is_stem): ?><span class="text-sm border-2 border-theme-text-color px-3 py-1 tracking-wide">STEM Program</span><?php endif; ?></div>
<?php else: ?>
<?= $ed->placeholder('Set the program type (credential, degree, STEM)', $identity) ?>
<?php endif; ?>
<?php if ($crumbs): ?>
									<ul role="list" class="flex flex-wrap gap-x-6 sm:gap-x-4 gap-y-2.5 leading-none"<?= $crumbsForm ?>>
<?php foreach ($crumbs as $l): ?>
										<li class="relative sm:pr-4 sm:last:pr-0 after:hidden sm:after:inline-block sm:last:after:hidden after:relative after:w-px after:-ml-px after:h-4 after:bg-theme-text-color/50 after:left-4 after:top-0.5"><a href="<?= $t->e($l['href']) ?>" class="underline text-theme-link-color hocus:text-theme-link-hocus-color text-base/tight"><?= $t->e(trim($l['text'])) ?></a></li>
<?php endforeach; ?>
									</ul>
<?php else: ?>
<?= $ed->placeholder('Add the college and department links', $crumbsForm) ?>
<?php endif; ?>
								</div>
<?php endif; ?>
<?php if ($description !== '' || $editing): ?>
								<div><div class="prose max-w-4xl"<?= $ed->html('description', $description) ?>><?= $ed->showHtml($description, 'Click to write the description.') ?></div></div>
<?php endif; ?>
<?php if ($learn_how !== '' || $editing): ?>
								<p class="font-semibold text-lg/tight text-theme-heading-color"<?= $ed->text('learn_how', $learn_how) ?>><?= $ed->show($learn_how, 'Add the "Learn how…" line') ?></p>
<?php endif; ?>
<?php if ($facts): ?>
								<dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 border-2 border-theme-text-color p-4 majors-facts"<?= $factsForm ?>>
<?php foreach ($facts as $f): ?>
									<div><dt class="text-xs font-bold uppercase tracking-wide text-theme-text-color/80"><?= $t->e($f['label']) ?></dt><dd class="font-display font-bold text-2xl leading-tight text-theme-heading-color"><?= $t->e($f['value']) ?></dd></div>
<?php endforeach; ?>
								</dl>
<?php elseif (!empty($p['graduate'])): ?>
<?= $ed->placeholder('Add program details (degree, modality, credit hours, entry term)', $factsForm) ?>
<?php endif; ?>
<?php if ($buttons): ?>
								<div<?= $buttonsForm ?>><div><ul role="list" class="flex flex-wrap flex-col gap-5 [&_.nc-button]:w-full md-xs:flex-row">
<?php foreach ($buttons as $i => $b): ?>
									<li><a<?= $i > 0 ? ' data-variant="2"' : '' ?> href="<?= $t->e($b['href'] ?? '#') ?>" class="nc-button"><span class="nc-button-text"><?= $t->e(trim((string) ($b['text'] ?? ''))) ?></span></a></li>
<?php endforeach; ?>
								</ul></div></div>
<?php else: ?>
<?= $ed->placeholder('+ Add a button (Request info, Apply, Visit…)', $buttonsForm) ?>
<?php endif; ?>
<?php if ($coordinator): ?>
								<p class="text-base majors-coordinator"<?= $coordForm ?>>Questions? Contact <?= $coordinator['name'] !== '' ? 'Program Coordinator ' . $t->e($coordinator['name']) : 'the program' ?><?php if ($coordinator['email'] !== ''): ?> at <a class="underline text-theme-link-color hocus:text-theme-link-hocus-color" href="mailto:<?= $t->e($coordinator['email']) ?>"><?= $t->e($coordinator['email']) ?></a><?php endif; ?><?php if ($coordinator['phone'] !== ''): ?> or call <a class="underline text-theme-link-color hocus:text-theme-link-hocus-color" href="tel:<?= $t->e(preg_replace('/[^0-9+]/', '', $coordinator['phone'])) ?>"><?= $t->e($coordinator['phone']) ?></a><?php endif; ?>.</p>
<?php elseif (!empty($p['graduate'])): ?>
<?= $ed->placeholder('Add a program coordinator (name, email, phone)', $coordForm) ?>
<?php endif; ?>
							</div>
						</div>
<?php if ($image): ?>
						<div class="lg:col-span-6"<?= $imageForm ?>>
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
<?php elseif ($editing): ?>
						<div class="lg:col-span-6">
<?= $ed->placeholder('Add a photo', $imageForm, 'ma-ph--photo') ?>
						</div>
<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<div aria-hidden="true" class="bottom-arrowhead-spacer tw-hidden relative h-[--arrowhead-h] pointer-events-none"></div>
	</div>
</section>
