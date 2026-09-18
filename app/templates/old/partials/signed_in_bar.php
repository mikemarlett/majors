<?php
/**
 * Small "signed in as" strip shown on admin pages.
 * Variables: $user (\Majors\Auth\User)
 * @var \Majors\View\Layout $t
 */
?>
<div class="majors-admin-bar noprint">
	<div class="<?= $t->cls('row') ?> majors-admin-bar__inner">
	<span>Signed in as <strong><?= $t->e($user->name()) ?></strong> (<?= $t->e($user->role) ?>)</span>
	<nav aria-label="Admin">
<?php if ($user->canEditDegreeMaps()): ?>
		<a href="<?= $t->e($t->url('degree_maps/admin/maps.php')) ?>">Degree Maps</a>
<?php endif; ?>
<?php if ($user->canEditMajors()): ?>
		<a href="<?= $t->e($t->url('_admin/index.php')) ?>">Majors</a>
<?php endif; ?>
<?php if ($user->isSuperAdmin()): ?>
		<a href="<?= $t->e($t->url('degree_maps/admin/manage_users.php')) ?>">Users</a>
<?php endif; ?>
		<a href="<?= $t->e($t->url('auth/logout.php')) ?>">Sign out</a>
	</nav>
	</div>
</div>
