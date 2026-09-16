<?php
/**
 * Manage Users page body. The table is filled by manage-users.js (get_users).
 * @var \Majors\View\Layout $t
 */
?>
<section class="<?= $t->cls('section') ?>">
	<p>People on this list can sign in with their myWSU ID (the ID is what sign-in matches on, so every row needs one). <strong>Advisors</strong> edit degree maps for their colleges, <strong>marketing</strong> staff edit the majors pages, and <strong>super admins</strong> can do both and manage this list.</p>
	<p><button id="add-user-btn" class="<?= $t->cls('button.accent') ?>" type="button">Add New User</button></p>
	<div id="user-list" class="<?= $t->cls('table_wrap') ?>" aria-live="polite" data-table-class="<?= $t->e($t->cls('table')) ?>">Loading…</div>
	<div id="user-form-modal" hidden></div>
</section>
