<?php
/**
 * Shown above a map on the public page when it is not approved and the visitor
 * is a signed-in advisor: students cannot open this address yet.
 * Variables: $map (header), $admin_url
 * @var \Majors\View\Layout $t
 */
?>
<div class="dm-preview-notice noprint" role="status">
	<strong>Not approved.</strong> Students cannot see this map yet. You can because you are signed in.
	<a href="<?= $t->e($admin_url) ?>">Open it in the admin</a>.
</div>
