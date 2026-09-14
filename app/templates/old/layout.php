<?php
/**
 * Page chrome for the current (2018) wichita.edu design.
 * Variables: $content, $title, $description, $head[], $foot[], $body_class, $user, $csrf, $chrome[]
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
<body<?= $body_class ? ' class="' . $t->e($body_class) . '"' : '' ?>>
<!-- OU Search Ignore Start Here -->
<div class="site-chrome site-chrome--header">
<?= $chrome['header'] ?>
</div>
<!-- OU Search Ignore End Here -->
<main class="<?= $t->cls('main') ?>">
<?= $chrome['alert'] ?>
<?php if ($user): ?>
<?= $t->partial('partials/signed_in_bar', ['user' => $user]) ?>
<?php endif; ?>
<?= $content ?>
</main>
<!-- OU Search Ignore Start Here -->
<div class="site-chrome site-chrome--footer">
<?= $chrome['footer'] ?>
</div>
<?= $chrome['footcode'] ?>
<?= $chrome['analytics'] ?>
<?php foreach ($foot as $f): ?>
<?= $f ?>

<?php endforeach; ?>
<!-- OU Search Ignore End Here -->
</body>
</html>
