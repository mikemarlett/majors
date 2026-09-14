<?php
/**
 * New-design page header: the design system's SimplePageHeader band
 * (gray title bar) plus an optional section menu rendered as a plain list.
 * Variables: $title, $nav_html (li items), $noprint (bool)
 * @var \Majors\View\Layout $t
 */
$nav_html = $nav_html ?? '';
$noprint  = $noprint ?? true;
?>
<header data-nc-component="simple-page-header" class="<?= $noprint ? 'noprint' : '' ?>">
	<div data-tw-theme="neutral-900">
		<div class="py-4 bg-neutral-600 text-theme-text-color">
			<div class="container">
				<hgroup class="max-w-4xl flex flex-col gap-3">
					<h1 data-style-level="3" class="nc-heading text-[length:--text-size] sm:text-[length:--text-size-sm] md:text-[length:--text-size-md]"><?= $t->e($title) ?></h1>
				</hgroup>
			</div>
		</div>
	</div>
<?php if ($nav_html !== ''): ?>
	<nav class="container py-3" aria-label="Section">
		<ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
<?= $nav_html ?>
		</ul>
	</nav>
<?php endif; ?>
</header>
