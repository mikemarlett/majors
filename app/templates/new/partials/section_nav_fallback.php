<?php
/**
 * Section menu when the site's own renderer (_resources/php/section-nav.php)
 * is not available — the sandbox, mostly. Shaped like the design system's
 * desktop-section-nav (yellow crumb bar + neutral list) but without its
 * accordion behaviour. $nav_html is a string of <li><a>…</a></li> items.
 * Variables: $nav_html, $mobile (bool)
 * @var \Majors\View\Layout $t
 */
?>
<?php if ($mobile): ?>
	<nav data-nc-component="mobile-section-nav" aria-label="Section" class="sidebar-up:hidden noprint">
		<div class="relative bg-neutral-600 text-neutral-300 py-4">
			<div class="relative container">
				<details class="group/details">
					<summary class="font-bold text-white cursor-pointer">Section menu</summary>
					<ul role="list" class="pt-4 flex flex-col gap-3 text-[calc(17rem/16)]/tight [&_a]:text-neutral-300 [&_a:hover]:text-white">
<?= $nav_html ?>
					</ul>
				</details>
			</div>
		</div>
	</nav>
<?php else: ?>
	<nav data-nc-component="desktop-section-nav" aria-label="Section" class="tw-hidden sidebar-up:flex flex-col gap-1">
		<div data-tw-theme="yellow">
			<ol role="list" class="bg-theme-bg-color text-theme-text-color py-4 px-6 flex gap-3 text-base/tight">
				<li class="shrink-0"><a href="/" aria-label="Home" class="relative inline-block size-4 mt-0.5 after:inline-block after:absolute after:inset-0 after:mask-home after:mask-contain after:bg-theme-text-color hocus:after:bg-theme-link-hocus-color after:transition-colors"></a></li>
				<li class="pt-0.5 shrink border-l border-black/30 pl-3 font-extrabold"><span class="uppercase">Academics</span></li>
			</ol>
		</div>
		<div data-tw-theme="neutral-200">
			<div class="relative bg-theme-bg-color text-theme-text-color">
				<ul role="list" class="relative text-base/tight py-2 [&_li]:border-b [&_li]:border-black/10 [&_li:last-child]:border-0 [&_a]:block [&_a]:px-6 [&_a]:py-3 [&_a:hover]:text-theme-link-hocus-color">
<?= $nav_html ?>
				</ul>
			</div>
		</div>
	</nav>
<?php endif; ?>
