<?php
/**
 * Manage Users: add/edit form (HTML fragment injected into a dialog).
 * Variables: $u (array), $colleges (id=>name), $departments (id=>name), $roles (role=>label), $csrf
 * @var \Majors\View\Layout $t
 */
use Majors\Support\Html;
?>
<form id="user-form" class="ma-user-form">
	<input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
	<div class="ma-row">
		<div class="ma-col-6"><label for="uf_first">First name</label><input type="text" name="first_name" id="uf_first" required value="<?= $t->e($u['first_name']) ?>"></div>
		<div class="ma-col-6"><label for="uf_last">Last name</label><input type="text" name="last_name" id="uf_last" required value="<?= $t->e($u['last_name']) ?>"></div>
	</div>
	<div class="ma-row">
		<div class="ma-col-8"><label for="uf_email">Email (wichita.edu)</label><input type="email" name="email" id="uf_email" required value="<?= $t->e($u['email']) ?>">
			<span class="help-block">Must match the address released by myWSU sign-in.</span></div>
		<div class="ma-col-4"><label for="uf_netid">myWSU ID</label><input type="text" name="netid" id="uf_netid" value="<?= $t->e($u['netid']) ?>" placeholder="a123b456" pattern="[A-Za-z][A-Za-z0-9]{2,7}">
			<span class="help-block">Optional; filled in automatically at first sign-in.</span></div>
	</div>
	<div class="ma-row">
		<div class="ma-col-6"><label for="uf_role">Role</label><?= Html::select('role', $roles, $u['role'], ['id' => 'uf_role']) ?></div>
		<div class="ma-col-6"><label for="uf_active">Status</label><?= Html::select('is_active', [1 => 'Active', 0 => 'Inactive (cannot sign in)'], (int) $u['is_active'], ['id' => 'uf_active']) ?></div>
	</div>
	<div class="ma-row">
		<div class="ma-col-6">
			<label for="uf_colleges">Colleges (advisors edit only these)</label>
			<select name="colleges[]" id="uf_colleges" multiple size="6">
<?php foreach ($colleges as $id => $name): ?>
				<option value="<?= (int) $id ?>"<?= in_array((int) $id, $u['colleges'], true) ? ' selected' : '' ?>><?= $t->e($name) ?></option>
<?php endforeach; ?>
			</select>
			<span class="help-block">Hold Ctrl (Windows) or Cmd (Mac) to pick more than one. Super admins ignore this.</span>
		</div>
		<div class="ma-col-6"><label for="uf_dept">Department (optional)</label>
			<select name="department" id="uf_dept"><option value="">None</option><?= Html::options($departments, $u['default_department_id']) ?></select></div>
	</div>
	<div class="modal-buttons">
		<button type="submit" class="ui-button-primary <?= $t->cls('button') ?>">Save User</button>
		<button type="button" id="cancel-user-btn" class="<?= $t->cls('button') ?>">Cancel</button>
	</div>
</form>
