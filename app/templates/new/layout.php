<?php
/**
 * Page chrome for the new (NewCity / Tailwind) wichita.edu design, matched to
 * the pages Modern Campus publishes on www-dev:
 *
 *   <body>
 *     header.inc  → opens <div class="flex flex-col min-h-screen"> … <header id="site-header">…</header>
 *     <main id="main" class="relative pb-px -mb-px grow flex flex-col" [data-page-has-section-nav]>
 *       <div class="relative"> simple-page-header band </div>
 *       mobile-section-nav
 *       <div class="with-sidebar …grid…">  sidebar: desktop-section-nav  |  main column: our content
 *     </main>
 *     footer.inc  → <footer …>…</footer> and closes the min-h-screen wrapper
 *
 * The include fragments must therefore NOT be wrapped in anything.
 *
 * Variables: $content, $top (full-width strip under the title band), $title, $description, $head[], $foot[], $body_class, $user, $csrf,
 *            $page_header, $nav_html, $section_nav (array{desktop,mobile}|null), $header_print, $chrome[]
 * @var \Majors\View\Layout $t
 */
$hasNav = $section_nav !== null || $nav_html !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= $chrome['headcode'] ?>
	<title><?= $t->e($title) ?> · Wichita State University</title>
<?php if ($description !== ''): ?>
	<meta name="description" content="<?= $t->e($description) ?>">
<?php endif; ?>
<?php if ($csrf): ?>
	<meta name="csrf-token" content="<?= $t->e($csrf) ?>">
<?php endif; ?>
<?php foreach ($head as $h): ?>
	<?= $h ?>

<?php endforeach; ?>
</head>
<body class="majors-new<?= $body_class ? ' ' . $t->e($body_class) : '' ?>">
<?= $chrome['header'] ?>
<main id="main" class="relative pb-px -mb-px grow flex flex-col"<?= $hasNav ? ' data-page-has-section-nav=""' : '' ?>>
<?php if ($user): ?>
<?= $t->partial('partials/signed_in_bar', ['user' => $user]) ?>
<?php endif; ?>
<?php if ($page_header !== null): ?>
	<div class="relative<?= $header_print ? '' : ' noprint' ?>">
<?= $t->partial('partials/page_header', ['title' => $page_header]) ?>
	</div>
<?php endif; ?>
<?php if ($top !== ''): ?>
	<div class="majors-top">
<?= $top ?>
	</div>
<?php endif; ?>
<?php if ($hasNav): ?>
<?php if ($section_nav !== null): ?>
<?= $section_nav['mobile'] ?>
<?php else: ?>
<?= $t->partial('partials/section_nav_fallback', ['nav_html' => $nav_html, 'mobile' => true]) ?>
<?php endif; ?>
	<div class="with-sidebar sidebar-up:my-vertical-space relative sidebar-up:container sidebar-up:grid sidebar-up:grid-cols-[repeat(19,minmax(0,1fr))] sidebar-up:gap-x-12 xl:!gap-x-16">
		<div class="with-sidebar__sidebar sidebar-up:col-span-6 noprint">
			<div class="with-sidebar__sidebar-inner-wrapper px-4 pb-10 sidebar-up:px-0">
<?php if ($section_nav !== null): ?>
<?= $section_nav['desktop'] ?>
<?php else: ?>
<?= $t->partial('partials/section_nav_fallback', ['nav_html' => $nav_html, 'mobile' => false]) ?>
<?php endif; ?>
			</div>
		</div>
		<div class="with-sidebar__main sidebar-up:col-[span_13_/_span_13] sidebar-up:order-first">
			<div class="with-sidebar__main-inner-wrapper majors-content px-4 sidebar-up:px-0 pt-1 pb-12 space-y-8">
<?= $content ?>
			</div>
		</div>
	</div>
<?php else: ?>
	<div class="container my-vertical-space majors-content space-y-8">
<?= $content ?>
	</div>
<?php endif; ?>
</main>
<?= $chrome['footer'] ?>
<?= $chrome['footcode'] ?>
<?php foreach ($foot as $f): ?>
<?= $f ?>

<?php endforeach; ?>
</body>
</html>
