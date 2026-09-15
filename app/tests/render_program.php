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
    check(str_contains($html, 'Mechanical Engineering') && str_contains($html, '?id=42'), 'similar programs linked');
    check(isset($r->sectionNav($program)['Degrees from College of Engineering']), 'section nav gets college entry');
    check(!str_contains($html, 'STUB SITE HEADER'), 'no chrome in body');

    $groups = ProgramRenderer::group([$program, ['id' => 9, 'academic_program' => 'Biology', 'program_type' => 'BA', 'college' => 'Fairmount College']], 'alpha');
    check(count($groups) === 2 && $groups[0]['label'] === 'A' && $groups[1]['label'] === 'B', 'alpha grouping');
    $list = $layout->render('majors/listing', ['groups' => $groups, 'headline' => 'All Degrees', 'order' => 'alpha',
        'link_base' => '/academics/majors/index.php?id=', 'results_url' => '/academics/majors/index.php?filter=online', 'all_url' => '/academics/majors/index.php']);
    check(str_contains($list, 'Link to These Results') && str_contains($list, '?id=41'), 'listing header and links');
}

check(ProgramRenderer::headline('online', 'College of Engineering', null, 'robot') === '"robot" in Online Degrees in College of Engineering', 'headline composition');
check(ProgramRenderer::title($program) === 'Aerospace Engineering, Major', 'page title');

finish();
