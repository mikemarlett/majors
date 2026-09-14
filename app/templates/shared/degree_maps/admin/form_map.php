<?php
/**
 * Map details modal (new or existing map).
 * Variables: $map, $colleges (name=>name), $departments (name=>name), $years (year=>label), $programs (id=>label), $is_new
 * @var \Majors\View\Layout $t
 */
use Majors\Support\Html;
?>
<div id="editMapModal" title="<?= $is_new ? 'Create New Map' : 'Edit Degree Map Details' ?>">
	<form method="post" id="degree_map_form" name="degree_map_form" novalidate>
		<input type="hidden" name="degree_map_id" value="<?= (int) ($map['id'] ?? 0) ?>">
		<input type="hidden" name="academic_year_hidden" value="<?= (int) $map['academic_year'] ?>">
		<div class="ma-row">
			<div class="ma-col-9">
				<label class="control-label" for="major">Degree Name<span class="required">*</span></label>
				<input type="text" name="major" id="major" class="form-control" required value="<?= $t->e($map['major']) ?>">
				<span class="help-block">The name of the degree, e.g. "Aerospace Engineering".</span>
			</div>
			<div class="ma-col-3">
				<label class="control-label" for="degree_type">Degree Type<span class="required">*</span></label>
				<input type="text" name="degree_type" id="degree_type" class="form-control" required value="<?= $t->e($map['degree_type']) ?>">
				<span class="help-block">e.g. "BFA", "BS", "BA"</span>
			</div>
		</div>
		<div class="ma-row">
			<div class="ma-col-12">
				<label class="control-label" for="note">Note</label>
				<textarea class="form-control" name="note" id="note" rows="2"><?= $t->e($map['note']) ?></textarea>
				<span class="help-block">Publicly visible note about the whole degree (single courses use footnotes).</span>
			</div>
		</div>
		<div class="ma-row">
			<div class="ma-col-6">
				<label class="control-label" for="college">College<span class="required">*</span></label>
				<?= Html::select('college', $colleges, $map['college'] ?: null, ['id' => 'college', 'class' => 'form-control', 'required' => true], 'Choose one') ?>
			</div>
			<div class="ma-col-6">
				<label class="control-label" for="department">Department</label>
				<select class="form-control" name="department" id="department">
					<option value="">None selected</option>
					<?= Html::options($departments, $map['department']) ?>
				</select>
				<span class="help-block">Not displayed; used to match the map to its program page.</span>
			</div>
		</div>
		<div class="ma-row">
			<div class="ma-col-3">
				<label class="control-label" for="academic_year">Catalog Year</label>
				<?= Html::select('academic_year', $years, (int) $map['academic_year'], ['id' => 'academic_year', 'class' => 'form-control', 'disabled' => !$is_new]) ?>
<?php if (!$is_new): ?>
				<span class="help-block">Fixed once created. Use Clone to make next year's version.</span>
<?php endif; ?>
			</div>
			<div class="ma-col-9">
				<label class="control-label" for="program_id">Program page</label>
				<select class="form-control" name="program_id" id="program_id">
					<option value="">None selected</option>
					<?= Html::options($programs, $map['program_id'] ?: null) ?>
				</select>
				<span class="help-block">Links this map to a Majors marketing page.</span>
			</div>
		</div>
		<div class="modal-buttons">
			<button type="button" id="SaveMapBtn" class="ui-button-primary <?= $t->cls('button') ?>">Save Changes</button>
			<button type="button" id="cancelMapBtn" class="<?= $t->cls('button') ?> cancelBtn" data-modal="editMapModal">Cancel</button>
		</div>
	</form>
</div>
