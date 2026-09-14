<?php
/**
 * Course modal (new or existing).
 * Variables: $course, $map, $headline, $years, $semesters, $sge (code=>label), $orders, $footnotes (id=>label), $selected_footnotes
 * @var \Majors\View\Layout $t
 */
use Majors\Support\Html;
$isNew = empty($course['id']);
?>
<div id="editCourseModal" title="<?= $isNew ? 'New Course' : 'Edit ' . $t->e($headline) ?>">
	<form id="editCourseForm">
		<input type="hidden" name="course_id" id="course_id" value="<?= (int) ($course['id'] ?? 0) ?>">
		<input type="hidden" name="degree_map_id" id="degree_map_id_course" value="<?= (int) $map['id'] ?>">
		<label for="course_info">Course Info</label>
		<input type="text" name="course_info" id="course_info" class="form-control" value="<?= $t->e($course['course_info']) ?>" autocomplete="off">
		<span class="help-block">Start typing a title or course number like "ENGL 101" for suggestions; hours fill in automatically. Both can be edited.</span>
		<input type="hidden" name="scbcrse_crse_numb" id="scbcrse_crse_numb" value="<?= $t->e($course['scbcrse_crse_numb']) ?>">
		<input type="hidden" name="scbcrse_subj_code" id="scbcrse_subj_code" value="<?= $t->e($course['scbcrse_subj_code']) ?>">
		<div class="ma-row">
			<div class="ma-col-2">
				<label for="hours">Credit Hours</label>
				<input type="text" name="hours" id="hours" class="form-control" value="<?= $t->e($course['hours']) ?>" inputmode="decimal">
			</div>
			<div class="ma-col-6">
				<label for="footnotes">Footnotes</label>
				<select id="footnotes" name="footnotes[]" multiple class="select2" data-placeholder="Select footnotes">
<?php foreach ($footnotes as $id => $label): ?>
					<option value="<?= (int) $id ?>"<?= in_array((int) $id, $selected_footnotes, true) ? ' selected' : '' ?>><?= $t->e($label) ?></option>
<?php endforeach; ?>
				</select>
			</div>
			<div class="ma-col-4">
				<label for="sge">SGE Code</label>
				<?= Html::select('sge', $sge, $course['sge'] ?? '', ['id' => 'sge', 'class' => 'form-control']) ?>
			</div>
		</div>
		<div id="advanced">
			<h3>Placement</h3>
			<div>
				<div class="ma-row">
					<div class="ma-col-4"><label for="year">Year</label><?= Html::select('year', $years, $course['year'], ['id' => 'year', 'class' => 'form-control']) ?></div>
					<div class="ma-col-5"><label for="semester">Semester</label><?= Html::select('semester', $semesters, $course['semester'], ['id' => 'semester', 'class' => 'form-control']) ?></div>
					<div class="ma-col-3"><label for="order">Order</label><?= Html::select('order', $orders, $course['order'], ['id' => 'order', 'class' => 'form-control']) ?></div>
				</div>
				<div class="ma-row">
					<div class="ma-col-12">
						<label for="extra">Extra Note</label>
						<textarea name="extra" id="extra" rows="3"><?= $t->e($course['extra']) ?></textarea>
						<span class="help-block">Prints in small type under this one course. Use footnotes for anything shared.</span>
					</div>
				</div>
			</div>
		</div>
		<div class="modal-buttons">
			<button type="button" id="saveCourseBtn" class="ui-button-primary <?= $t->cls('button') ?>">Save Changes</button>
			<button type="button" id="cancelCourseBtn" class="<?= $t->cls('button') ?> cancelBtn" data-modal="editCourseModal">Cancel</button>
<?php if (!$isNew): ?>
			<button type="button" id="deleteCourseBtn" class="ui-button-danger <?= $t->cls('button') ?>">Delete</button>
<?php endif; ?>
		</div>
	</form>
</div>
