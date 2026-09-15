<?php
/**
 * Access denied / not on the list / provider failure.
 * Variables: $reason ('not_listed'|'role'|'provider'), optional $identity, $user, $roles, $detail
 * @var \Majors\View\Layout $t
 */
$reason  = $reason ?? 'role';
$contact = $t->site('contact');
?>
<section class="<?= $t->cls('section') ?>">
<?php if ($reason === 'not_listed'): ?>
	<p class="<?= $t->cls('heading4') ?>">You signed in successfully, but this account is not on the access list for the Degree Maps and Majors editors.</p>
<?php if (!empty($identity)): ?>
	<p>Signed in as <strong><?= $t->e($identity->email ?: $identity->netid) ?></strong>.</p>
<?php endif; ?>
	<p>Advisors and marketing staff are added by request. Please contact <a href="mailto:<?= $t->e($contact) ?>"><?= $t->e($contact) ?></a> and include your myWSU ID.</p>
<?php elseif ($reason === 'provider'): ?>
	<p class="<?= $t->cls('heading4') ?>">Sign-in could not be completed. Please try again.</p>
<?php if (!empty($detail)): ?>
	<pre><?= $t->e($detail) ?></pre>
<?php endif; ?>
<?php else: ?>
	<p class="<?= $t->cls('heading4') ?>">You don't have access to that area.</p>
<?php if (!empty($user)): ?>
	<p>You are signed in as <strong><?= $t->e($user->name()) ?></strong> with the role <strong><?= $t->e($user->role) ?></strong>.
	<?php if (!empty($roles)): ?>This page needs: <?= $t->e(implode(' or ', $roles)) ?>.<?php endif; ?></p>
<?php endif; ?>
	<p>If you need this access, contact <a href="mailto:<?= $t->e($contact) ?>"><?= $t->e($contact) ?></a>.</p>
<?php endif; ?>
	<p><a href="<?= $t->e($t->url('degree_maps/maps.php')) ?>">Back to Degree Maps</a> · <a href="<?= $t->e($t->url('auth/logout.php')) ?>">Sign out</a></p>
</section>
