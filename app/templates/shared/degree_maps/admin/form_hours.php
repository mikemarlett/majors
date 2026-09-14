<?php
/**
 * Hours modal: manual semester/year totals with the calculated sums beside them.
 * Variables: $map, $calc[y][s|'total_hours'], $years (1..4 => First..), $semesters (1..3 => Fall..)
 * @var \Majors\View\Layout $t
 */
$span = static function (string $calc, mixed $manual) use ($t): string {
    if ($calc === '0' && ((string) $manual === '' || (string) $manual === '0')) {
        return '';
    }
    $warn = (string) $calc !== (string) $manual ? ' warning' : '';
    return '<span class="calculated_hours' . $warn . '">' . $t->e($calc) . '</span>';
};
?>
<div id="editHoursModal" title="Edit Degree Map Hours">
	<form method="post" id="map_hours_form" name="map_hours_form">
		<fieldset>
			<input type="hidden" name="degree_map_id" value="<?= (int) $map['id'] ?>">
			<legend class="sr-only">Course hours</legend>
			<p class="help-block">Enter the hours to print for each semester and year. The gray number is the sum of the courses listed; it turns orange when the two disagree.</p>
			<table class="course_hours_table">
				<thead><tr><th scope="col">Semester</th><?php foreach ($years as $y => $name): ?><th scope="col"><?= $t->e($name) ?> Year</th><?php endforeach; ?></tr></thead>
				<tbody>
<?php foreach ($semesters as $s => $sname): ?>
					<tr><th scope="row"><?= $t->e($sname) ?></th>
<?php foreach ($years as $y => $yname): ?>
						<td>
							<label class="sr-only" for="hours_<?= $y ?>_<?= $s ?>"><?= $t->e($sname) ?> <?= $t->e($yname) ?> Year Hours</label>
							<input type="text" name="degree_map[hours][<?= $y ?>][<?= $s ?>]" id="hours_<?= $y ?>_<?= $s ?>" value="<?= $t->e($map['hours'][$y][$s]['hours'] ?? '') ?>" inputmode="decimal">
							<?= $span($calc[$y][$s], $map['hours'][$y][$s]['hours'] ?? '') ?>
						</td>
<?php endforeach; ?>
					</tr>
<?php endforeach; ?>
					<tr><th scope="row">Total</th>
<?php foreach ($years as $y => $yname): ?>
						<td>
							<label class="sr-only" for="hours_<?= $y ?>_total">Total <?= $t->e($yname) ?> Year Hours</label>
							<input type="text" name="degree_map[hours][<?= $y ?>][total_hours]" id="hours_<?= $y ?>_total" value="<?= $t->e($map['hours'][$y]['total_hours'] ?? '') ?>" inputmode="decimal">
							<?= $span($calc[$y]['total_hours'], $map['hours'][$y]['total_hours'] ?? '') ?>
						</td>
<?php endforeach; ?>
					</tr>
				</tbody>
			</table>
			<label for="hours_to_graduate" class="ma-inline-label">Total Hours to Graduate:</label>
			<input type="text" name="hours_to_graduate" id="hours_to_graduate" class="ma-inline-input" value="<?= $t->e($map['hours_to_graduate']) ?>" inputmode="decimal">
		</fieldset>
		<div class="modal-buttons">
			<button type="button" id="SaveHoursBtn" class="ui-button-primary <?= $t->cls('button') ?>">Save Changes</button>
			<button type="button" id="CancelHoursBtn" class="<?= $t->cls('button') ?> cancelBtn" data-modal="editHoursModal">Cancel</button>
		</div>
	</form>
</div>
