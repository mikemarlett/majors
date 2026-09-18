<?php
/**
 * Sign-in diagnostics (auth/login.php?diag=1). Variables: $checks = [[label, value, 'ok'|'PROBLEM'], ...]
 */
?>
<div class="majors-diag">
	<p>What a sign-in on this server would do, without attempting one. Rows marked <strong>PROBLEM</strong> are the place to look.</p>
	<div class="<?= $t->cls('table_wrap') ?>">
	<table class="<?= $t->cls('table') ?>">
		<thead><tr><th>Check</th><th>Value</th><th>Status</th></tr></thead>
		<tbody>
		<?php foreach ($checks as [$label, $value, $status]): ?>
			<tr>
				<th scope="row"><?= $t->e($label) ?></th>
				<td style="word-break:break-all"><?= $t->e($value) ?></td>
				<td><?= $status === 'ok' ? 'ok' : '<strong>PROBLEM</strong>' ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<p><a href="<?= $t->e($t->url('auth/login.php')) ?>">Try signing in</a></p>
</div>
