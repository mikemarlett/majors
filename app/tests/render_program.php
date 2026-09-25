<?php

/** Program marketing page + listing under both designs, from fixtures. */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Majors\Majors\ProgramRenderer;

$program = json_decode((string) file_get_contents(__DIR__ . '/fixtures/program.json'), true, 512, JSON_THROW_ON_ERROR);
$maps    = [['id' => 922, 'major' => 'Aerospace Engineering', 'degree_type' => 'BS', 'academic_year' => 2026], ['id' => 800, 'major' => 'Aerospace Engineering', 'degree_type' => 'BS', 'academic_year' => 2025]];

foreach (['old', 'new'] as $design) {
    echo "[$design]\n";
    $layout = test_layout($design);
    $r      = new ProgramRenderer($layout);
    $html   = $r->page($program, $maps);
    check(str_contains($html, 'Aerospace Engineering'), 'title present');
    check(str_contains($html, 'Design, build and test'), 'description (trusted HTML) present');
    check(str_contains($html, 'Request info') && str_contains($html, 'Visit'), 'learn-how links decoded from JSON');
    check(str_contains($html, 'degree_maps/maps.php?degree_map_id=922') && str_contains($html, '’25-’26'), 'degree map links with short year label');
    check(str_contains($html, 'Wind tunnels and flight labs'), 'inside the program');
    check(str_contains($html, 'Careers in Aerospace Engineering'), 'careers');
    check(str_contains($html, 'Mechanical Engineering') && str_contains($html, '?program=mechanical_engineering_bs_42') && str_contains($html, '?id=43'), 'similar programs linked by basename, id only when there is none');
    check(isset($r->sectionNav($program)['Degrees from College of Engineering']), 'section nav gets college entry');
    check(!str_contains($html, 'STUB SITE HEADER'), 'no chrome in body');

    $groups = ProgramRenderer::group([$program, ['id' => 9, 'academic_program' => 'Biology', 'program_type' => 'BA', 'college' => 'Fairmount College']], 'alpha');
    check(count($groups) === 2 && $groups[0]['label'] === 'A' && $groups[1]['label'] === 'B', 'alpha grouping');
    $list = $layout->render('majors/listing', ['groups' => $groups, 'headline' => 'All Degrees', 'order' => 'alpha',
        'results_url' => '/academics/majors/index.php?filter=online', 'all_url' => '/academics/majors/index.php']);
    check(str_contains($list, 'Link to These Results') && str_contains($list, '?program=aerospace_engineering_bs_41') && str_contains($list, '?id=9'), 'listing header and links by basename');
}

echo "[editing markers]\n";
foreach (['old', 'new'] as $design) {
    $r    = new ProgramRenderer(test_layout($design));
    $pub  = $r->page($program, $maps);
    $edit = $r->page($program, $maps, true, [7 => 268]);
    check(!str_contains($pub, 'data-ma-') && !str_contains($pub, 'ma-ph'), "$design: public output carries no editing markers");
    check(substr_count($edit, 'data-ma-part="card"') === 1 && substr_count($edit, 'data-ma-part="similar"') === 1 && substr_count($edit, 'data-ma-part="content"') >= 2, "$design: card, content roots and similar band are marked as parts");
    check(str_contains($edit, 'data-ma-scope="program"') && str_contains($edit, 'data-ma-text="academic_program"') && str_contains($edit, 'data-ma-html="description"') && str_contains($edit, 'data-ma-text="learn_how"'), "$design: program card fields are editable");
    check(str_contains($edit, 'data-ma-form="image"') && str_contains($edit, 'data-ma-form="buttons"') && str_contains($edit, 'data-ma-form="identity"') && str_contains($edit, 'data-ma-form="crumbs"'), "$design: card popover forms");
    check(str_contains($edit, 'data-ma-form="facts"') && str_contains($edit, 'data-ma-form="coordinator"') && substr_count($edit, 'data-ma-ph') === 2, "$design: empty facts and coordinator show placeholders (and only those)");
    check(str_contains($edit, 'data-section="2" data-ma-kind="teaser" data-ma-scope="section"') && str_contains($edit, 'data-section="1" data-shared="1" data-ma-kind="teaser" data-ma-scope="block" data-block="7" data-ma-uses="268"'), "$design: own and shared sections carry their scope");
    check(str_contains($edit, 'Shared text · 268 pages') && substr_count($edit, 'data-ma-tools') === 5, "$design: a tool strip per stored section, with the shared badge");
    check(str_contains($edit, 'data-ma-form="section-image"') && str_contains($edit, 'data-ma-text="label"') && substr_count($edit, 'data-ma-form="links"') === 5, "$design: feature photo/label and per-section links are editable");
    check(str_contains($edit, 'data-ma-add-bar') && str_contains($edit, 'data-ma-similar-add') && substr_count($edit, 'data-ma-similar-remove=') === 2, "$design: add bar, similar add tile and remove buttons");
    check(str_contains($edit, 'data-ma-static'), "$design: degree-map links are marked static");
    check(str_contains($edit, '?program=mechanical_engineering_bs_42'), "$design: similar cards still link by basename when editing");
}
$blank = ['id' => 5, 'academic_program' => 'Blank', 'basename' => 'blank', 'content' => [], 'sections' => [], 'similar_programs' => []];
foreach (['old', 'new'] as $design) {
    $h = (new ProgramRenderer(test_layout($design)))->page($blank, [], true);
    check(str_contains($h, 'Click to write the description.') && str_contains($h, 'data-ma-empty="1"') && str_contains($h, 'Add a photo') && str_contains($h, 'This page has no sections yet') && str_contains($h, 'data-ma-part="content"') && str_contains($h, 'data-ma-part="similar"'), "$design: an empty program shows placeholders and still has every part");
    $hp = (new ProgramRenderer(test_layout($design)))->page($blank, []);
    check(!str_contains($hp, 'Click to write') && !str_contains($hp, 'Add a photo') && !str_contains($hp, 'Similar Programs'), "$design: the empty program's public page shows no placeholders and no empty similar band");
}

check(ProgramRenderer::headline('online', 'College of Engineering', null, 'robot') === '"robot" in Online Degrees in College of Engineering', 'headline composition');
check(ProgramRenderer::title($program) === 'Aerospace Engineering, Major', 'page title');
$lay = test_layout('new', ['image_base' => 'https://www.wichita.edu']);
check($lay->img('/academics/majors/_images/x.jpg') === 'https://www.wichita.edu/academics/majors/_images/x.jpg' && $lay->img('https://cdn/x.jpg') === 'https://cdn/x.jpg', 'image_base prefixes relative content images only');
check(str_contains((new ProgramRenderer($lay))->page($program, $maps), 'src="https://www.wichita.edu/academics/majors/_images/Aerospace_airbus.jpg"'), 'program page uses image_base');
$grad = $program + ['degree_title' => 'PhD', 'credit_hours' => '84', 'modality' => 'On Campus', 'is_stem' => 1, 'coordinator_name' => 'Deana Beek', 'coordinator_email' => 'deana.beek@wichita.edu', 'coordinator_phone' => '316-978-3961'];
foreach (['old', 'new'] as $design) {
    $h = (new ProgramRenderer(test_layout($design)))->page($grad, $maps);
    check(str_contains($h, 'Credit Hours') && str_contains($h, '>84<') && str_contains($h, 'On Campus') && !str_contains($h, 'Entry Term'), "$design: details box shows only the filled facts");
    check(str_contains($h, 'STEM Program') && str_contains($h, 'mailto:deana.beek@wichita.edu') && str_contains($h, '316-978-3961'), "$design: STEM tag and coordinator strip");
}

finish();
