<?php
/** New program form. Variables: $csrf @var \Majors\View\Layout $t */ ?>
<section class="<?= $t->cls('section') ?>">
	<form id="newProgramForm" class="ma-user-form" method="post">
		<p>Give the program a name and its credential; everything else is filled in on the editor page that opens next.</p>
		<div class="ma-row">
			<div class="ma-col-6"><label for="np_name">Program name <span class="ma-required">*</span></label><input type="text" id="np_name" name="academic_program" required maxlength="255"></div>
			<div class="ma-col-3"><label for="np_cred">Credential</label>
				<select id="np_cred" name="credential"><option value="">—</option><?php foreach (['Major', 'Minor', "Master's", 'Doctorate', 'Graduate Certificate', 'Undergraduate Certificate', "Bachelor's to Master's", 'Badge', 'Field Major', 'Postbaccalaureate'] as $c): ?><option><?= $t->e($c) ?></option><?php endforeach; ?></select></div>
			<div class="ma-col-3"><label for="np_type">Type (BS, MA, MACC…)</label><input type="text" id="np_type" name="program_type" maxlength="40" placeholder="e.g. MS"></div>
		</div>
		<p><label><input type="checkbox" name="graduate" value="1"> Graduate program</label></p>
		<div class="ma-actions"><button type="submit" class="<?= $t->cls('button.accent') ?>">Create program</button> <a class="<?= $t->cls('button.subtle') ?>" href="<?= $t->e($t->url('_admin/index.php')) ?>">Cancel</a></div>
	</form>
</section>
