<?php
/**
 * Page chrome for the current (2018) wichita.edu design. Mirrors the legacy
 * pages exactly: headcode.inc in <head>, header.inc right after <body>,
 * <main class="main main--slab"> with alert.php, then footer.inc, footcode.inc,
 * analytics.inc. The include fragments are NOT wrapped in extra elements —
 * they may open and close shared wrappers between them.
 *
 * Variables: $content, $top, $title, $description, $head[], $foot[], $body_class, $user, $csrf,
 *            $page_header, $nav_html, $section_nav (unused here), $header_print, $chrome[]
 * @var \Majors\View\Layout $t
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<!-- OU Search Ignore Start Here -->
<?= $chrome['headcode'] ?>
<!-- OU Search Ignore End Here -->
	<title><?= $t->e($title) ?></title>
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
<body class="majors-old<?= $body_class ? ' ' . $t->e($body_class) : '' ?>">
<!-- OU Search Ignore Start Here -->
<?= $chrome['header'] ?>
<!-- OU Search Ignore End Here -->
<main class="<?= $t->cls('main') ?>">
<?= $chrome['alert'] ?>
<?php if ($user): ?>
<?= $t->partial('partials/signed_in_bar', ['user' => $user]) ?>
<?php endif; ?>
<?php if ($page_header !== null): ?>
<?= $t->partial('partials/page_header', ['title' => $page_header, 'nav_html' => $nav_html, 'noprint' => !$header_print]) ?>
<?php endif; ?>
<?= $top ?>
<?= $content ?>
</main>
<!-- OU Search Ignore Start Here -->
<?= $chrome['footer'] ?>
<?= $chrome['footcode'] ?>
<?= $chrome['analytics'] ?>
<?php foreach ($foot as $f): ?>
<?= $f ?>

<?php endforeach; ?>
<!-- OU Search Ignore End Here -->
</body>
</html>
