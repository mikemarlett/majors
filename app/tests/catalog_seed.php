<?php

/** CatalogSeeder::extract against a trimmed real catalog page and the multi-total cases seen live. */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Majors\Majors\CatalogSeeder;

$x = CatalogSeeder::extract((string) file_get_contents(__DIR__ . '/fixtures/catalog-phd-applied-math.html'));
check($x['title'] === 'PhD in Applied Mathematics', 'catalog title without the site suffix');
check($x['degree_title'] === 'PhD', 'degree from "X in Y"');
check($x['credit_hours'] === '84' && count($x['totals']) === 1, 'single total');
check(str_starts_with($x['admission'], 'Admission to the PhD program'), 'admission tab text without markup');

$page = static fn (string $req, string $adm = ''): string => '<title>MACC - Master of Accountancy &lt; Catalog</title>'
    . ($adm !== '' ? '<div id="admissiontextcontainer" class="x">' . $adm . '</div>' : '')
    . '<div id="requirementstextcontainer" class="x">' . $req . '</div><footer></footer>';
$row = static fn (string $h): string => '<tr class="listsum"><td colspan="2">Total Credit Hours</td><td class="hourscol">' . $h . '</td></tr>';
check(CatalogSeeder::extract($page('<h2>Program Requirements</h2>' . $row('73') . '<h3>Master of Accountancy Curriculum</h3>' . $row('30')))['credit_hours'] === '30', 'a Curriculum heading beats the foundation list under Program Requirements');
check(CatalogSeeder::extract('<title>Doctor of Nursing Practice &lt; Catalog</title>')['degree_title'] === 'Doctor of Nursing Practice', 'short title without "in" is the degree');
check(CatalogSeeder::extract($page('<h2>Program Requirements</h2>' . $row('36') . '<h3>Track 1</h3>' . $row('4') . '<h3>Track 2</h3>' . $row('4')))['credit_hours'] === '36', 'Program Requirements total wins over track totals');
check(CatalogSeeder::extract($page($row('88-91'), $row('9') . ' prerequisites'))['credit_hours'] === '88-91', 'admission-tab totals are ignored; ranges kept');
check(CatalogSeeder::extract($page($row('30') . $row('30')))['credit_hours'] === '30', 'repeated equal totals');
check(CatalogSeeder::extract($page($row('12') . $row('18')))['credit_hours'] === '18', 'no helpful heading → the largest');
check(CatalogSeeder::extract($page(''))['credit_hours'] === '', 'no total → empty, not 0');
check(CatalogSeeder::extract('<title>MACC - Master of Accountancy</title>')['degree_title'] === 'MACC', 'degree from "ABBR - Name" titles');
check(CatalogSeeder::extract('<title>Certificate in Clinical Mental Health</title>')['degree_title'] === 'Certificate', 'certificate title');

finish();
