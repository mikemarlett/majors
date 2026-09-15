<?php
/**
 * New-design page header: the design system's SimplePageHeader band, markup
 * copied from the pages www-dev publishes (Organism/PageHeader/SimplePageHeader).
 * Variables: $title
 * @var \Majors\View\Layout $t
 */
?>
	<header data-nc-component="simple-page-header">
		<div data-tw-theme="neutral-900">
			<div class="py-4 bg-neutral-600 text-theme-text-color">
				<div class="container">
					<hgroup class="max-w-4xl flex flex-col gap-3">
						<h1 data-style-level="3" class="nc-heading text-[length:--text-size] sm:text-[length:--text-size-sm] md:text-[length:--text-size-md]"><?= $t->e($title) ?></h1>
					</hgroup>
				</div>
			</div>
		</div>
	</header>
