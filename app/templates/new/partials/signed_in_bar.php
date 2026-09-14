<?php
/** @var \Majors\View\Layout $t */ ?>
<div class="majors-admin-bar noprint border-b border-neutral-200 bg-neutral-50">
	<div class="container flex flex-wrap items-center gap-x-4 gap-y-1 py-2 text-sm">
		<span class="mr-auto text-neutral-600">Signed in as <strong><?= $t->e($user->name()) ?></strong> (<?= $t->e($user->role) ?>)</span>
<?php if ($user->canEditDegreeMaps()): ?>
		<a class="underline" href="<?= $t->e($t->url('degree_maps/admin/maps.php')) ?>">Degree Maps</a>
<?php endif; ?>
<?php if ($user->canEditMajors()): ?>
		<a class="underline" href="<?= $t->e($t->url('_admin/index.php')) ?>">Majors</a>
<?php endif; ?>
<?php if ($user->isSuperAdmin()): ?>
		<a class="underline" href="<?= $t->e($t->url('degree_maps/admin/manage_users.php')) ?>">Users</a>
<?php endif; ?>
		<a class="underline" href="<?= $t->e($t->url('auth/logout.php')) ?>">Sign out</a>
	</div>
</div>
