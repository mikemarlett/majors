<?php

/**
 * Quinn's round: full-width sections, the feature wrap, the Similar Programs photo, more than one
 * department, card labels, and the CMS parser for the generic section and the title-row photo.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Majors\Majors\CmsPageParser;
use Majors\Majors\ProgramEditor;
use Majors\Majors\ProgramRenderer;
use Majors\Majors\ProgramRepository;

$program = json_decode((string) file_get_contents(__DIR__ . '/fixtures/program.json'), true, 512, JSON_THROW_ON_ERROR);
$maps    = [['id' => 922, 'major' => 'Aerospace Engineering', 'degree_type' => 'BS', 'academic_year' => 2026]];

echo "[parser]\n";
$load = static fn (string $b): array => CmsPageParser::parse((string) file_get_contents(__DIR__ . "/fixtures/cms/$b.pcf"), $b);
$n = $load('nursing_bsn_179');
$bands = array_values(array_filter($n['sections'], static fn ($s) => $s['kind'] === 'band'));
check(count($bands) === 1 && $bands[0]['headline'] === 'Wichita State and Kansas State Pathway to Nursing Program' && $bands[0]['theme'] === 'yellow', 'the generic section becomes a yellow full-width section');
check(str_contains($bands[0]['body'], 'Kansas State') && !str_contains($bands[0]['body'], '<table') && !str_contains($bands[0]['body'], '<caption'), 'its body is the content, without the snippet table');
check(array_column($n['sections'], 'kind') === ['teaser', 'teaser', 'feature', 'teaser', 'teaser', 'band', 'similar'], 'the band keeps its place in the page order');
check(($n['similar_bg'] ?? null) === '{{f:22198459}}', 'the Similar Programs photo comes from the title row (tag left for the resolver)');
$a = $load('aerospace_engineering_bs_100');
check(array_filter($a['sections'], static fn ($s) => $s['kind'] === 'band') === [] && ($a['similar_bg'] ?? null) === '{{f:22198539}}', 'an empty generic section adds nothing; the photo is still read');

echo "[bands]\n";
$withBands = $program;
$withBands['sections'][] = ['id' => 60, 'kind' => 'band', 'theme' => 'yellow', 'label' => '', 'headline' => 'Pathway to Nursing', 'body' => '<p>Two universities, one path.</p>', 'links' => [['text' => 'Pathway details', 'href' => '/pathway/']], 'image' => null, 'block_id' => null, 'shared' => false];
$withBands['sections'][] = ['id' => 61, 'kind' => 'band', 'theme' => 'white', 'label' => '', 'headline' => 'Plain band', 'body' => '<p>White.</p>', 'links' => [], 'image' => null, 'block_id' => null, 'shared' => false];
$withBands['sections'][] = ['id' => 62, 'kind' => 'band', 'theme' => 'dark', 'label' => '', 'headline' => 'Dark band', 'body' => '<p>Dark.</p>', 'links' => [], 'image' => null, 'block_id' => null, 'shared' => false];
$new = (new ProgramRenderer(test_layout('new')))->page($withBands, $maps);
check(str_contains($new, 'data-tw-theme="yellow" class="generic-slab majors-section majors-band" data-section="60"'), 'new: a yellow band is a Generic Slab with the yellow theme');
check(str_contains($new, 'class="generic-slab majors-section majors-band" data-section="61"') && !preg_match('/data-tw-theme="[^"]*" class="generic-slab majors-section majors-band" data-section="61"/', $new), 'new: a white band has no theme attribute');
check(str_contains($new, 'data-tw-theme="neutral-900" class="generic-slab majors-section majors-band" data-section="62"'), 'new: a dark band uses neutral-900');
check(str_contains($new, 'Pathway to Nursing') && str_contains($new, 'Two universities, one path.') && str_contains($new, 'href="/pathway/"'), 'new: band headline, body and link');
$old = (new ProgramRenderer(test_layout('old')))->page($withBands, $maps);
check(str_contains($old, 'section-wrap section-wrap--wheat majors-section majors-band" data-section="60"') && str_contains($old, 'section-wrap--none majors-section majors-band" data-section="61"') && str_contains($old, 'section-wrap--shade-dark majors-section majors-band" data-section="62"'), 'old: yellow, white and dark bands use wheat, none and shade-dark');
check(str_contains($old, 'Two universities, one path.') && str_contains($old, 'majors-band__body'), 'old: band body');
foreach (['old', 'new'] as $design) {
    $ed = (new ProgramRenderer(test_layout($design)))->page($withBands, $maps, true);
    check(str_contains($ed, 'data-section="60" data-ma-kind="band" data-ma-theme="yellow"') && str_contains($ed, 'data-ma-theme="white"'), "$design: editing marks a band with its background");
    check(str_contains($ed, 'Full width') && str_contains($ed, '+ Full-width section'), "$design: band tool strip and the add button");
}

$noHead = $withBands;
$noHead['sections'][] = ['id' => 63, 'kind' => 'band', 'theme' => 'light', 'label' => '', 'headline' => '', 'body' => '<p>Text only.</p>', 'links' => [], 'image' => null, 'block_id' => null, 'shared' => false];
foreach (['old', 'new'] as $design) {
    $h = (new ProgramRenderer(test_layout($design)))->page($noHead, $maps);
    preg_match('/data-section="63".*?Text only\./s', $h, $m);
    check(isset($m[0]) && !str_contains($m[0], '<h2'), "$design: a band with no headline prints no empty heading");
}
$dark = (new ProgramRenderer(test_layout('old')))->page($withBands, $maps);
preg_match('/data-section="62".*?<\/section>/s', $dark, $m62);
preg_match('/data-section="60".*?<\/section>/s', $dark, $m60);
$dark62 = $withBands;
$dark62['sections'][count($dark62['sections']) - 1]['links'] = [['text' => 'Apply', 'href' => '/apply/']];
preg_match('/data-section="62".*?<\/section>/s', (new ProgramRenderer(test_layout('old')))->page($dark62, $maps), $m62l);
check(isset($m62l[0]) && str_contains($m62l[0], 'class="link--rich link--ondark"') && isset($m60[0]) && str_contains($m60[0], 'class="link--rich"'), 'old: links on a dark band use the light-on-dark style, others do not');
$ed62 = (new ProgramRenderer(test_layout('old')))->page($withBands, $maps, true);
preg_match('/data-section="62".*?<\/section>/s', $ed62, $e62);
check(isset($e62[0]) && str_contains($e62[0], 'ma-ph--on-dark'), 'old: the link placeholder on a dark band is the light one');

echo "[parts the editor can swap]\n";
// After a save the editor replaces the page's parts with the re-rendered ones, taking only the
// top-level elements of each part: every top-level element must be a part, and none may be nested.
foreach (['old', 'new'] as $design) {
    foreach (['cards last' => $program, 'band last' => $withBands] as $case => $prog) {
        $parts = (new ProgramRenderer(test_layout($design)))->parts($prog, $maps, true);
        $bad = [];
        foreach ($parts as $slot => $html) {
            if (trim($html) === '') {
                continue;
            }
            $doc = new DOMDocument();
            @$doc->loadHTML('<?xml encoding="utf-8"?><div id="root">' . $html . '</div>', LIBXML_NOERROR);
            $root = $doc->getElementById('root');
            foreach ($root->childNodes as $n) {
                if (!$n instanceof DOMElement) {
                    continue;
                }
                if (!$n->hasAttribute('data-ma-part')) {
                    $bad[] = "$slot: top-level <{$n->tagName} class=\"" . $n->getAttribute('class') . '"> is not a part';
                }
                foreach ((new DOMXPath($doc))->query('.//*[@data-ma-part]', $n) as $inner) {
                    $bad[] = "$slot: a part nested in <{$n->tagName}>";
                }
            }
        }
        check($bad === [], "$design ($case): every top-level element is a part, none nested" . ($bad ? ' — ' . implode('; ', array_slice($bad, 0, 3)) : ''));
    }
}

echo "[feature wrap and headings]\n";
$new = (new ProgramRenderer(test_layout('new')))->page($program, $maps);
check(preg_match('/<div class="majors-feature"><div class="majors-feature__media">.*?<img[^>]+Wind|<div class="majors-feature"><div class="majors-feature__media">/s', $new) === 1 && str_contains($new, 'class="prose max-w-none majors-feature__text"'), 'new: the feature photo floats inside the text wrap');
check(strpos($new, 'majors-feature__media') < strpos($new, 'majors-feature__label') && strpos($new, 'majors-feature__label') < strpos($new, 'majors-feature__text'), 'new: photo first, then the label and the text that wraps around it');
check(str_contains($new, 'nc-heading text-lg sm:text-xl mb-3 majors-maps-heading'), 'new: the Degree Maps subheading is a size up');
$old = (new ProgramRenderer(test_layout('old')))->page($program, $maps);
check(str_contains($old, 'section-wrap section-wrap--dots majors-section'), 'old: Inside the Program sits on the gray dots');

echo "[similar programs photo]\n";
$bg = $program + ['similar_bg_url' => '/academics/majors/_images/similar_bg.jpg'];
$old = (new ProgramRenderer(test_layout('old')))->page($bg, $maps);
check(str_contains($old, 'src="/academics/majors/_images/similar_bg.jpg"'), 'old: the chosen photo is behind the Similar Programs band');
$old = (new ProgramRenderer(test_layout('old')))->page($program, $maps);
check(str_contains($old, 'section-wrap__image"><img src="/academics/majors/_images/Aerospace_airbus.jpg"'), 'old: with none chosen, the program photo stands in');
$ed = (new ProgramRenderer(test_layout('old')))->page($bg, $maps, true);
check(str_contains($ed, 'ma-similar-bg-btn') && str_contains($ed, 'data-ma-form="similar-bg"'), 'old: a Background photo button when editing');
check(!str_contains((new ProgramRenderer(test_layout('old')))->page($bg, $maps), 'ma-similar-bg-btn'), 'old: no button on the public page');

echo "[departments]\n";
$joint = ['department' => 'Chemistry and Biochemistry', 'department_url' => '/academics/chemistry/', 'more_departments' => '[{"text":"Biological Sciences","href":"/academics/biology/"},{"text":"Chemistry and Biochemistry","href":"/dup/"}]'];
check(ProgramRepository::departmentsOf($joint) === [['text' => 'Chemistry and Biochemistry', 'href' => '/academics/chemistry/'], ['text' => 'Biological Sciences', 'href' => '/academics/biology/']], 'departmentsOf: the main one first, then the rest, without repeats');
check(ProgramRepository::departmentsOf(['department' => '', 'more_departments' => 'not json']) === [], 'departmentsOf: nothing when there is nothing');
check(ProgramEditor::departmentRows(['text' => ['Chemistry', ' ', 'Biology', 'Chemistry'], 'href' => ['/chem/', '', 'https://example.edu/bio', '/x/']]) === [['text' => 'Chemistry', 'href' => '/chem/'], ['text' => 'Biology', 'href' => 'https://example.edu/bio']], 'departmentRows: parallel form arrays, blanks and repeats dropped');
check(ProgramEditor::departmentRows('[{"text":"<b>Physics</b>","href":""}]') === [['text' => 'Physics', 'href' => '']], 'departmentRows: JSON in, tags stripped');
$threw = false;
try { ProgramEditor::departmentRows([['text' => 'Bad', 'href' => 'javascript:alert(1)']]); } catch (\RuntimeException) { $threw = true; }
check($threw, 'departmentRows: an unsafe link is refused');
$jp = $program + $joint;
$jp['department'] = $joint['department'];
$jp['department_url'] = $joint['department_url'];
foreach (['old', 'new'] as $design) {
    $r = new ProgramRenderer(test_layout($design));
    $h = $r->page($jp, $maps);
    check(str_contains($h, 'href="/academics/chemistry/"') && str_contains($h, 'href="/academics/biology/"'), "$design: crumbs link every department");
    $nav = $r->sectionNav($jp);
    check(str_ends_with($nav['Degrees from Biological Sciences'] ?? '', '?department=Biological%20Sciences') && isset($nav['Degrees from Chemistry and Biochemistry']), "$design: the section menu lists every department's programs");
}

echo "[card labels]\n";
check(ProgramRenderer::cardLabel(['academic_program' => 'Pre-Medicine', 'credential' => '', 'program_simple_type' => '', 'program_type' => '']) === 'Pre-Medicine', 'no credential: the name alone, no empty brackets');
check(ProgramRenderer::cardLabel(['academic_program' => 'Biology', 'credential' => '', 'program_type' => 'BS']) === 'Biology (BS)', 'falls back to the program type');
check(ProgramRenderer::cardLabel(['academic_program' => 'Biology', 'credential' => 'Major', 'program_type' => 'BS']) === 'Biology (Major)', 'credential first');

finish();
