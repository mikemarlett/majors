<?php
/**
 * Page chrome for the new (NewCity / Tailwind) wichita.edu design on www-dev.
 * The header/footer fragments come from _resources/_theme/includes; the
 * fragment files there carry their own <html>-level metadata, so this
 * layout only adds what the page itself needs.
 *
 * Variables: $content, $title, $description, $head[], $foot[], $body_class, $user, $csrf, $chrome[]
 * @var \Majors\View\Layout $t
 */
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
<body class="majors-app<?= $body_class ? ' ' . $t->e($body_class) : '' ?>">
<div class="site-chrome site-chrome--header">
<?= $chrome['header'] ?>
</div>
<main id="main" class="<?= $t->cls('main') ?>">
<?php if ($user): ?>
<?= $t->partial('partials/signed_in_bar', ['user' => $user]) ?>
<?php endif; ?>
<?= $content ?>
</main>
<div class="site-chrome site-chrome--footer">
<?= $chrome['footer'] ?>
</div>
<?= $chrome['footcode'] ?>
<?php foreach ($foot as $f): ?>
<?= $f ?>

<?php endforeach; ?>
</body>
</html>
