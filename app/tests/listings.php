<?php

/** Listing entries: parsing the CMS list assets, merging them into entries, defaults, grouping. */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Majors\Majors\ListingImporter;
use Majors\Majors\Listings;
use Majors\Majors\ProgramRenderer;

$az = <<<'HTML'
<section class="taxonomy-filters"><h2 class="heading4">Filter Programs By:</h2></section>
<div class="alpha-list"><div class="alpha-list__items"><header><h2>A</h2></header><ul>
<li><a class="" href="{{f:1}}">Accountancy</a> — Master of Accountancy</li>
<li><a class="" href="{{f:2}}">Aging Studies</a> — Master of Arts <em>(Concentrations available)</em> (Online only)</li>
</ul><header><h2>C</h2></header><ul>
<li><a class="" href="{{f:3}}">Counseling - Addiction</a> — Master of Education</li>
<li><a class="" href="{{f:3}}">Counseling - School</a> — Master of Education</li>
</ul><header><h2>P</h2></header><ul>
<li><a class="" href="{{f:4}}">Public Health Practice, Advanced</a> &mdash; Undergraduate Certificate</li>
<!-- <li><a href="{{f:9}}">Commented out</a> — gone</li> -->
</ul></div></div>
HTML;
$college = <<<'HTML'
<h2 class="heading5">Select View:</h2>
<div class="alpha-list"><div class="alpha-list__items"><header><h2 class="heading4"><a id="business"></a>Barton School of Business</h2></header><ul>
<li><a class="" href="{{f:1}}">Accountancy</a> — Master of Accountancy</li>
</ul><header><h2 class="heading4">College of Health Professions</h2></header><ul>
<li><a class="" href="{{f:2}}">Aging Studies</a> — Master of Arts <em>(Concentrations available)</em> (Online only)</li>
</ul><header><h2 class="heading4">College of Education</h2></header><ul>
<li><a class="" href="{{f:3}}">Counseling - Addiction</a> — Master of Education</li>
<li><a class="" href="{{f:3}}">Counseling - School</a> — Master of Education</li>
<li><a class="" href="{{f:5}}">Special Music Education</a> — Graduate Certificate</li>
</ul></div></div>
HTML;
$certs = <<<'HTML'
<h2 id="Grad" class="heading3">Graduate Certificates</h2>
<table class="ou-snippet-universal-callout"><caption><img src="{{f:7}}" alt="" />Snippet: Callout</caption><tbody>
<tr><th>Style</th><td data-select-list="callout">callout--tertiary</td></tr>
<tr><th>Header</th><td> <h3 class="heading4"> What's a certificate? </h3></td></tr>
<tr><th>Content</th><td> <p> Certificates are a group of courses. </p> <ul> <li>Stackable.</li> </ul></td></tr>
</tbody></table>
<div id="graduate">
<div class="cert_group"><h3 class="heading4"> Education </h3><ul>
<li><a href="{{f:6}}">Kodaly Method</a> — Graduate Certificate</li></ul></div>
<div class="cert_group"><h3 class="heading4">Arts and Humanities</h3><ul>
<li><a href="{{f:6}}">Kodaly Method</a> — Graduate Certificate</li></ul></div>
</div>
<table class="ou-snippet-universal-callout"><caption>Snippet: Callout</caption><tbody>
<tr><th>Header</th><td><h3 class="heading4">What's a certificate?</h3></td></tr>
<tr><th>Content</th><td><p>Similar to a minor.</p></td></tr></tbody></table>
<div id="undergraduate">
<div class="cert_group"><h3 class="heading4">Health</h3><ul>
<li><a href="{{f:4}}">Advanced Public Health Practice</a> — Undergraduate Certificate</li></ul></div>
</div>
HTML;

echo "[parse]\n";
$a = ListingImporter::parseList($az);
check(count($a) === 5, 'A–Z list: five lines, the commented-out one ignored');
check($a[1]['detail'] === 'Master of Arts <em>(Concentrations available)</em> (Online only)', 'detail keeps <em> and notes, loses the dash');
check($a[4]['detail'] === 'Undergraduate Certificate' && $a[4]['group'] === 'P', 'an &mdash; dash is stripped too; letter headings become the group');
$c = ListingImporter::parseList($college);
check(count($c) === 5 && $c[0]['group'] === 'Barton School of Business' && $c[4]['group'] === 'College of Education', 'by-college list: headings with nested anchors, "Select View:" ignored');
$ce = ListingImporter::parseCertificates($certs);
check(count($ce) === 3 && $ce[0]['section'] === 'graduate' && $ce[0]['topic'] === 'Education' && $ce[1]['topic'] === 'Arts and Humanities' && $ce[2]['section'] === 'undergraduate', 'certificates: halves and topics');
$in = ListingImporter::parseCertificateIntros($certs);
check(($in['graduate']['headline'] ?? '') === "What's a certificate?" && ($in['graduate']['body'] ?? '') === '<p>Certificates are a group of courses.</p><ul><li>Stackable.</li></ul>', 'graduate intro: header and cleaned content');
check(($in['undergraduate']['body'] ?? '') === '<p>Similar to a minor.</p>', 'undergraduate intro');

echo "[plan]\n";
$base = ['{{f:1}}' => 'accountancy', '{{f:2}}' => 'aging', '{{f:3}}' => 'counseling', '{{f:4}}' => 'aphp', '{{f:5}}' => 'sme', '{{f:6}}' => 'kodaly'];
$tag = static function (array $lines) use ($base): array {
    return array_map(static fn ($l) => $l + ['basename' => $base[$l['href']]], $lines);
};
$online = [['href' => '{{f:2}}', 'name' => 'Aging Studies', 'detail' => 'Master of Arts <em>(Concentrations available)</em> (Online only)', 'group' => 'A']];
$plan = ListingImporter::plan(
    ['all' => ['az' => $tag($a), 'college' => $tag($c)], 'online' => ['az' => $tag($online), 'college' => $tag($online)]],
    $tag($ce),
    ['accountancy' => 'Accountancy', 'aging' => 'Aging Studies', 'counseling' => 'Counseling', 'aphp' => 'Advanced Public Health Practice', 'sme' => 'Special Music Education', 'kodaly' => 'Kodaly Method']
);
check($plan['accountancy'] === [['name' => null, 'detail' => 'Master of Accountancy', 'lists' => ['all'], 'shown_in' => 'both', 'cert_section' => null, 'cert_topics' => null]], 'a line in both views is one entry shown in both; the page name is stored as "same as the page"');
check(count($plan['counseling']) === 2 && $plan['counseling'][1]['name'] === 'Counseling - School', 'concentrations stay separate lines, in page order');
check($plan['aging'][0]['lists'] === ['all', 'online'], 'identical lines on two lists become one entry on both');
check($plan['aphp'][0]['shown_in'] === 'az' && $plan['aphp'][0]['name'] === 'Public Health Practice, Advanced', 'a line only in the A–Z view is A–Z only');
check($plan['sme'][0]['shown_in'] === 'college', 'a line only in the by-college view is by-college only');
$aphpCert = array_values(array_filter($plan['aphp'], static fn ($e) => in_array('certificates', $e['lists'], true)));
check(count($aphpCert) === 1 && $aphpCert[0]['name'] === null && $aphpCert[0]['cert_section'] === 'undergraduate' && $aphpCert[0]['cert_topics'] === 'Health', 'a Certificates line with no matching entry becomes its own');
check($plan['kodaly'][0]['cert_topics'] === 'Education|Arts and Humanities' && count($plan['kodaly']) === 1, 'a certificate under two topics is one entry with both');

echo "[defaults]\n";
check(Listings::defaults(['credential' => 'Major']) === ['lists' => ['all', 'undergrad'], 'cert_section' => null], 'Major: All + Undergrad');
check(Listings::defaults(['credential' => "Bachelor's to Master's", 'graduate' => 1])['lists'] === ['all', 'undergrad'], "Bachelor's to Master's: Undergrad, as the CMS lists do");
check(Listings::defaults(['credential' => "Master's", 'online_learning' => 1])['lists'] === ['all', 'graduate', 'online'], "Master's available online: All + Graduate + Online");
check(Listings::defaults(['credential' => 'Graduate Certificate']) === ['lists' => ['all', 'certificates'], 'cert_section' => 'graduate'], 'Graduate Certificate: Certificates only, graduate half');
check(Listings::defaults(['credential' => 'Undergraduate Certificate'])['cert_section'] === 'undergraduate', 'Undergraduate Certificate: undergraduate half');
check(Listings::defaults(['credential' => 'Endorsement'])['lists'] === ['all', 'certificates'], 'Endorsement: Certificates');
check(Listings::degreeName(['program_type' => 'MS', 'credential' => "Master's"]) === 'Master of Science' && Listings::degreeName(['program_type' => 'Minor']) === 'Minor'
    && Listings::degreeName(['program_type' => 'XYZ', 'credential' => 'Field Major']) === 'Field Major', 'degree written out from the type, else the credential');
check(Listings::detailHtml(['detail' => ''], ['program_type' => 'BS']) === 'Bachelor of Science' && Listings::detailHtml(['detail' => '<em>x</em>'], []) === '<em>x</em>', 'detail: own HTML or the degree name');
check(Listings::url('/academics/majors', 'graduate', 'college') === '/academics/majors/graduate_by_college.php' && Listings::url('/academics/majors', 'certificates', 'college') === '/academics/majors/certificates.php'
    && Listings::url('/academics/majors', 'badges') === Listings::BADGES_URL, 'list addresses match the CMS pages');

echo "[grouping]\n";
$rows = [
    ['list_name' => 'Public Health Practice, Advanced', 'college' => 'CHP', 'cert_section' => 'undergraduate', 'cert_topics' => 'Health', 'graduate' => 0],
    ['list_name' => 'Kodaly Method', 'college' => 'CFA', 'cert_section' => 'graduate', 'cert_topics' => 'Education|Arts and Humanities', 'graduate' => 1],
    ['list_name' => 'Advanced Composite Materials', 'college' => 'CoE', 'cert_section' => 'graduate', 'cert_topics' => 'Science and Engineering', 'graduate' => 1],
];
$g = ProgramRenderer::group($rows, 'alpha');
check(array_column($g, 'label') === ['P', 'K', 'A'], 'A–Z groups by the line name (rows arrive sorted from the query)');
$cs = ProgramRenderer::groupCertificates($rows);
check(array_column($cs, 'key') === ['graduate', 'undergraduate'], 'certificates: graduate half first');
check(array_column($cs[0]['topics'], 'label') === ['Education', 'Science and Engineering', 'Arts and Humanities'], 'topics in the Certificates page order');
check($cs[0]['topics'][0]['items'][0]['list_name'] === 'Kodaly Method' && $cs[0]['topics'][2]['items'][0]['list_name'] === 'Kodaly Method', 'a certificate under two topics shows in both');

finish();
