<?php
/** New program form. Variables: $csrf, $public_base @var \Majors\View\Layout $t */ ?>
<section class="<?= $t->cls('section') ?>">
	<form id="newProgramForm" class="ma-user-form ma-new-program" method="post" data-ma-new-program>
		<p>Give the program a name and its credential. Everything else is written on the page itself, which opens next.</p>
		<div class="ma-grid-2">
			<div class="ma-field ma-field--span"><label for="np_name">Program name <span class="ma-required">*</span></label><input type="text" id="np_name" name="academic_program" required maxlength="255" autocomplete="off"></div>
			<div class="ma-field"><label for="np_cred">Credential</label>
				<select id="np_cred" name="credential"><option value="">—</option><?php foreach (['Major', 'Minor', "Master's", 'Doctorate', 'Graduate Certificate', 'Undergraduate Certificate', "Bachelor's to Master's", 'Badge', 'Field Major', 'Postbaccalaureate'] as $c): ?><option><?= $t->e($c) ?></option><?php endforeach; ?></select></div>
			<div class="ma-field"><label for="np_type">Type (BS, MA, MACC…)</label><input type="text" id="np_type" name="program_type" maxlength="40" placeholder="e.g. MS" autocomplete="off"></div>
			<label class="ma-field ma-field--check ma-field--span"><input type="hidden" name="graduate" value="0"><input type="checkbox" name="graduate" value="1"> <span>Graduate program</span></label>
			<div class="ma-field ma-field--span"><label for="np_basename">Page name (the public address)</label><input type="text" id="np_basename" name="basename" maxlength="120" pattern="[a-z0-9_]*" placeholder="filled in from the name and type" autocomplete="off"><div class="ma-help"><?= $t->e($public_base) ?><span data-ma-basename-echo>…</span> — computed from the name and type; change it here if you want a different one. It must be unique.</div></div>
		</div>
		<div class="ma-btn-row"><button type="submit" class="ma-btn ma-btn--accent">Create program</button> <a class="ma-btn ma-btn--ghost" href="<?= $t->e($t->url('_admin/index.php')) ?>">Cancel</a> <span class="ma-error" data-ma-new-error></span></div>
	</form>
</section>
