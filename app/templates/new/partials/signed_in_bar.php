<?php
/**
 * "Signed in as" strip on admin pages (new design). Layout is Tailwind
 * utilities (this app's templates are in the theme's content scan); only the
 * chip/tag looks live in degree-map.css.
 * Variables: $user (\Majors\Auth\User)
 * @var \Majors\View\Layout $t
 */
$here = (string) ($_SERVER['REQUEST_URI'] ?? '');
$link = static function (string $path, string $label) use ($t, $here): string {
    $url     = $t->url($path);
    $current = str_starts_with($here, $url) || (basename($path) === 'maps.php' && str_starts_with($here, dirname($url) . '/') && !preg_match('~/admin/(help|manage_users)\.php~', $here));
    return '<a class="nc-chip-link' . ($current ? ' is-current' : '') . '" href="' . $t->e($url) . '"' . ($current ? ' aria-current="page"' : '') . '>' . $t->e($label) . '</a>';
};
?>
<div class="majors-admin-bar noprint">
	<div class="container flex flex-wrap items-center gap-x-4 gap-y-2 py-2 text-sm majors-admin-bar__inner">
		<span class="mr-auto majors-admin-bar__who">Signed in as <strong><?= $t->e($user->name()) ?></strong> <span class="majors-admin-bar__role"><?= $t->e(str_replace('_', ' ', $user->role)) ?></span></span>
		<nav class="flex flex-wrap items-center gap-2 majors-admin-bar__nav" aria-label="Admin">
<?php if ($user->canEditDegreeMaps()): ?>
			<?= $link('degree_maps/admin/maps.php', 'Degree Maps') ?>

<?php endif; ?>
<?php if ($user->canEditMajors()): ?>
			<?= $link('_admin/index.php', 'Majors') ?>

<?php endif; ?>
<?php if ($user->isSuperAdmin()): ?>
			<?= $link('degree_maps/admin/manage_users.php', 'Users') ?>

<?php endif; ?>
<?php if ($user->canEditDegreeMaps()): ?>
			<?= $link('degree_maps/admin/help.php', 'Help') ?>

<?php endif; ?>
			<a class="nc-chip-link majors-admin-bar__out" href="<?= $t->e($t->url('auth/logout.php')) ?>">Sign out</a>
		</nav>
	</div>
</div>
