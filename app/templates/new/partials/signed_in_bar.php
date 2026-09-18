<?php
/**
 * "Signed in as" strip on admin pages (new design). The compiled theme CSS
 * only ships the utilities its own templates use, so the layout of this bar
 * comes from .majors-admin-bar rules in degree-map.css, not from utilities.
 * Variables: $user (\Majors\Auth\User)
 * @var \Majors\View\Layout $t
 */
$here = (string) ($_SERVER['REQUEST_URI'] ?? '');
$link = static function (string $path, string $label) use ($t, $here): string {
    $url     = $t->url($path);
    $current = str_starts_with($here, dirname($url) . '/');
    return '<a class="nc-chip-link' . ($current ? ' is-current' : '') . '" href="' . $t->e($url) . '"' . ($current ? ' aria-current="page"' : '') . '>' . $t->e($label) . '</a>';
};
?>
<div class="majors-admin-bar noprint">
	<div class="container majors-admin-bar__inner">
		<span class="majors-admin-bar__who">Signed in as <strong><?= $t->e($user->name()) ?></strong> <span class="majors-admin-bar__role"><?= $t->e(str_replace('_', ' ', $user->role)) ?></span></span>
		<nav class="majors-admin-bar__nav" aria-label="Admin">
<?php if ($user->canEditDegreeMaps()): ?>
			<?= $link('degree_maps/admin/maps.php', 'Degree Maps') ?>

<?php endif; ?>
<?php if ($user->canEditMajors()): ?>
			<?= $link('_admin/index.php', 'Majors') ?>

<?php endif; ?>
<?php if ($user->isSuperAdmin()): ?>
			<?= $link('degree_maps/admin/manage_users.php', 'Users') ?>

<?php endif; ?>
			<a class="nc-chip-link majors-admin-bar__out" href="<?= $t->e($t->url('auth/logout.php')) ?>">Sign out</a>
		</nav>
	</div>
</div>
