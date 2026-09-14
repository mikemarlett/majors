<?php

/** Renders the admin editor and its modal forms from the fixture map (no database). */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Majors\Auth\User;
use Majors\DegreeMaps\Forms;
use Majors\DegreeMaps\HoursCalculator;
use Majors\DegreeMaps\Lookups;
use Majors\DegreeMaps\MapRepository;
use Majors\DegreeMaps\SgeValidator;

$map    = json_decode((string) file_get_contents(__DIR__ . '/fixtures/degree_map.json'), true, 512, JSON_THROW_ON_ERROR);
$layout = test_layout('old');
$db     = mysqli_init(); // unconnected handle: the forms below never touch the database
$forms  = new Forms($layout, new MapRepository($db), new Lookups($db));
$user   = new User(1, 'advisor@wichita.edu', 'a123b456', 'Ada', 'Advisor', 'advisor', [3]);

echo "[hours]\n";
check(HoursCalculator::sum([['hours' => '3'], ['hours' => '2-4'], ['hours' => 'x']]) === '5-7', 'sum handles numbers, ranges and junk');
check(HoursCalculator::semester($map['courses'], 1, 1) === '11', 'semester sum 3+5+3');
check(HoursCalculator::year($map['courses'], 1) === '21-22', 'year sum with a 4-5 range');

echo "[sge]\n";
$w = SgeValidator::warnings($map['courses']);
check(count($w) > 0 && str_contains(implode(' ', $w), 'Social and Behavioral Science'), 'missing SGE categories are reported');
check(!str_contains(implode(' ', $w), 'Natural and Physical Science (040) has 4-5'), 'a single 4-5 science course satisfies 040');

echo "[editor]\n";
$html = $forms->editor($map, $user);
check(substr_count($html, '<ul class="semester-list"') === 12, 'twelve semester drop lists (4 years x 3)');
check(str_contains($html, 'data-course-id="2"') && str_contains($html, 'MATH 242'), 'courses listed with ids');
check(str_contains($html, 'id="edit_map_details"') && str_contains($html, 'id="edit_map_hours"') && str_contains($html, 'id="edit_map_footnotes"'), 'header edit buttons');
check(str_contains($html, 'ma-sge-warning'), 'SGE warning block shown');
check(str_contains($html, '?latest=922'), 'permanent link shown to advisors');
check(str_contains($html, 'calculated_hours warning'), 'calculated vs manual hours mismatch flagged');

echo "[forms]\n";
$hours = $forms->hoursForm($map);
check(str_contains($hours, 'name="degree_map[hours][1][3]"') && str_contains($hours, 'value="3"'), 'hours form has the summer cell');
check(str_contains($hours, 'name="hours_to_graduate"') && str_contains($hours, 'value="128"'), 'hours to graduate prefilled');

$fn = $forms->footnotesForm($map);
check(substr_count($fn, 'class="footnote-container"') === 3, 'three footnotes listed');
check(str_contains($fn, 'name="footnotes[11][note]"'), 'footnote textarea names keyed by id');

$course = $forms->courseForm($map['courses'][1][1][2], $map, 1, 1);
check(str_contains($course, 'value="MATH 242 Calculus I&lt;sup&gt;1&lt;/sup&gt;"'), 'course info escaped into the input');
check(str_contains($course, '<option value="11" selected>'), 'attached footnote preselected');
check(str_contains($course, 'id="deleteCourseBtn"'), 'existing course has delete');
check(str_contains($course, '<option value="030" selected>'), 'SGE preselected');

$new = $forms->courseForm($forms->blankCourse(922, 2, 3), $map, 2, 3);
check(!str_contains($new, 'id="deleteCourseBtn"'), 'new course has no delete');
check(str_contains($new, '<option value="3" selected>Summer</option>'), 'new course lands in the chosen semester');

echo "[user]\n";
check($user->canEditCollege(3) && !$user->canEditCollege(4) && !$user->canEditCollege(null), 'advisor college scope');
check($user->hasRole('advisor') && !$user->hasRole('marketing'), 'advisor role checks');
$sa = new User(2, 'sa@wichita.edu', null, 'Sam', 'Admin', 'super_admin');
check($sa->hasRole('marketing') && $sa->canEditCollege(99), 'super admin passes every check');
check(User::fromArray($user->toArray())->colleges === [3], 'session round trip keeps colleges');
$aa = new User(3, 'aa@wichita.edu', null, 'Aaron', 'Admin', 'advisor_admin');
check($aa->hasRole('advisor') && !$aa->hasRole('marketing') && !$aa->hasRole('super_admin'), 'advisor_admin implies advisor only');
check($aa->canEditCollege(4) && $aa->canEditCollege(99) && !$aa->isSuperAdmin(), 'advisor_admin edits any college but is not super admin');
check(!$user->hasRole('advisor_admin'), 'plain advisor is not advisor_admin');

finish();
