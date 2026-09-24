<?php

/** CmsPageParser + Importer helpers against real CMS page sources (tests/fixtures/cms). */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Majors\Majors\CmsPageParser;
use Majors\Majors\Importer;

$load = static fn (string $b): array => CmsPageParser::parse((string) file_get_contents(__DIR__ . "/fixtures/cms/$b.pcf"), $b);

$a = $load('aerospace_engineering_bs_100');
check($a['name'] === 'Aerospace Engineering' && $a['credential'] === 'Major' && $a['college_code'] === 'ENG', 'title split into name / credential / college code');
check($a['kind'] === 'Major' && $a['catalog_number'] === 100, 'program card kind + catalog number from the slug');
check(array_column($a['breadcrumb'], 'text') === ['All Programs', 'College of Engineering', 'Aerospace Engineering'], 'breadcrumb links');
check(str_starts_with(strip_tags($a['description']), 'The Bachelor of Science (BS) in aerospace engineering'), 'description paragraph');
check($a['learn_how'] === 'Learn how aerospace engineering is the right fit for you.' && count($a['buttons']) === 2 && $a['buttons'][0]['text'] === 'Request Info', 'learn-how heading + buttons');
check($a['image'] !== null && $a['image']['url'] === '{{f:75212535}}', 'hero image (tag left for the resolver)');
check(array_map(static fn ($s) => $s['kind'] . ':' . $s['headline'], $a['sections']) === [
    'teaser:Applied learning at Wichita State', 'teaser:Admission to the program', 'feature:Successful NASA career started at Wichita State',
    'teaser:Curriculum', 'teaser:Careers', 'similar:Similar Programs',
], 'sections in page order');
check($a['sections'][2]['label'] === 'Inside the Program' && $a['sections'][2]['image'] !== null, 'feature carries its label and image');
check(count($a['sections'][5]['links']) === 5 && str_contains($a['sections'][5]['links'][0]['text'], 'Engineering'), 'similar programs list');
check(str_contains($a['sections'][3]['body'], '<ul>') && $a['sections'][3]['links'][0]['href'] !== '', 'curriculum body keeps its HTML and link');

$c = $load('supply_chain_analytics_certificate_graduate');
check($c['credential'] === 'Graduate Certificate' && $c['catalog_number'] === null && count($c['buttons']) === 1 && str_starts_with($c['buttons'][0]['href'], 'mailto:'), 'certificate page: no catalog number, mailto button');
check(count($c['sections']) === 2, 'certificate page: two teasers');

$m = $load('accountancy_macc_20');
check($m['credential'] === "Master's" && $m['sections'][0]['headline'] === 'Making your graduate education affordable' && count($m['sections'][0]['links']) === 2, 'graduate page: two-link teaser');

check(Importer::programType("Master's", ['Accountancy', 'MACC', "Master's", 'Graduate'], 'accountancy_macc_20') === 'MACC', 'program type from keywords');
check(Importer::programType('Undergraduate Certificate', ['X', 'Certificate - Undergraduate'], 'x_certificate_undergraduate') === 'Certificate - Undergraduate', 'certificate type keeps its level');
check(Importer::programType('Minor', ['Accounting', 'Minor'], 'accounting_minor_2') === 'Minor', 'minor type');
check(Importer::slug('Applied learning at Wichita State') === 'applied-learning-at-wichita-state', 'block slug');
check(Importer::sectionKey(['headline' => 'A', 'body' => '<p>x  y</p>', 'links' => []]) === Importer::sectionKey(['headline' => 'a', 'body' => '<p>x y</p>', 'links' => []]), 'section key ignores case and spacing');
check(CmsPageParser::splitTitle('Details: Government and Social Studies (Secondary), BAEd, LAS') === ['Government and Social Studies (Secondary)', 'BAEd', 'LAS'], 'title with a comma-free name and code');

finish();
