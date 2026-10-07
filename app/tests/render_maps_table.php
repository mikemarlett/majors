<?php

/** The Degree Maps admin listing (table + bulk approval) and the approval helpers, rendered without a database. */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Majors\Auth\User;
use Majors\DegreeMaps\MapRepository;

$rows = [
    ['id' => 11, 'major' => 'Accounting', 'degree_type' => 'BBA', 'college' => 'W. Frank Barton School of Business', 'department' => 'School of Accountancy', 'note' => null,
        'academic_year' => 2028, 'approved' => 1, 'approved_by' => 'Aaron Admin', 'approved_at' => '2026-10-01 09:00:00', 'timestamp' => '2026-10-01 09:00:00', 'can_edit' => true, 'duplicate' => false],
    ['id' => 12, 'major' => 'Aerospace Engineering', 'degree_type' => 'BS', 'college' => 'College of Engineering', 'department' => null, 'note' => 'Transfer students see the note.',
        'academic_year' => 2028, 'approved' => null, 'approved_by' => null, 'approved_at' => null, 'timestamp' => '2026-10-05 12:00:00', 'can_edit' => false, 'duplicate' => true],
    ['id' => 13, 'major' => 'Art Education', 'degree_type' => 'BFA', 'college' => 'College of Fine Arts', 'department' => null, 'note' => null,
        'academic_year' => 2028, 'approved' => 1, 'approved_by' => 'Aaron Admin', 'approved_at' => '2026-10-01 09:00:00', 'timestamp' => '2026-10-06 08:30:00', 'can_edit' => true, 'duplicate' => false],
    ['id' => 14, 'major' => 'Biology', 'degree_type' => 'BA', 'college' => 'Fairmount College of Liberal Arts and Sciences', 'department' => null, 'note' => null,
        'academic_year' => 2028, 'approved' => 0, 'approved_by' => 'Mike Marlett', 'approved_at' => '2026-10-02 10:00:00', 'timestamp' => '2026-10-02 10:00:00', 'can_edit' => true, 'duplicate' => false],
];

echo "[helpers]\n";
check(MapRepository::isApproved($rows[0]) && !MapRepository::isApproved($rows[1]) && !MapRepository::isApproved($rows[3]), 'approved = 1 only; NULL and 0 are not approved');
check(!MapRepository::changedSinceApproval($rows[0]), 'saved at approval time: not changed since');
check(MapRepository::changedSinceApproval($rows[2]), 'saved after approval: changed since');
check(!MapRepository::changedSinceApproval($rows[1]) && !MapRepository::changedSinceApproval($rows[3]), 'unapproved maps are never "changed since approval"');

$vars = static fn (User $u): array => [
    'rows' => $rows, 'user' => $u, 'year' => 2028, 'can_approve' => $u->isDegreeMapsAdmin(), 'college' => null, 'status' => 'all',
    'view_base' => '/academics/majors/degree_maps/admin/maps.php?degree_map_id=', 'public_base' => '/academics/majors/degree_maps/maps.php?degree_map_id=',
];
$advisor = new User(2, 'ada.advisor@wichita.edu', 'a123b456', 'Ada', 'Advisor', 'advisor', [3]);
$admin   = new User(5, 'aaron.admin@wichita.edu', 'a999a999', 'Aaron', 'Admin', 'advisor_admin');

foreach (['old', 'new'] as $design) {
    echo "[table: {$design} design, advisor]\n";
    $html = test_layout($design)->render('degree_maps/admin/table', $vars($advisor));
    check(substr_count($html, '<tr data-id="') === 4, 'one row per map');
    check(!str_contains($html, 'ma-row-check') && !str_contains($html, 'id="bulkApprove"') && !str_contains($html, 'id="maps_select_all"'), 'advisors get no tick boxes or bulk buttons');
    check(str_contains($html, '4 maps for 2027-2028, 2 approved'), 'count line');
    check(str_contains($html, 'ma-state--approved">Approved</span><span class="ma-sub">by Aaron Admin, Oct 1, 2026</span>'), 'approved state with who and when');
    check(str_contains($html, 'ma-state--pending">Not approved</span></td>'), 'never-approved state has no meta');
    check(str_contains($html, 'withdrawn by Mike Marlett, Oct 2, 2026'), 'withdrawn state names who withdrew it');
    check(str_contains($html, 'edited since approval'), 'a map saved after approval is marked');
    check(substr_count($html, 'edited since approval') === 1, 'only that map is marked');
    check(str_contains($html, 'data-status="approved"') && str_contains($html, 'data-status="pending"'), 'status data attributes for the filter');
    check(str_contains($html, '>duplicate</span>'), 'duplicate flag');
    check(substr_count($html, '&amp;editMap=Edit') === 3, 'Edit link only where can_edit');
    check(str_contains($html, '>Preview</a>') && str_contains($html, '>Public page</a>'), 'public link reads Preview for unapproved maps');
    check(str_contains($html, 'data-search="aerospace engineering bs college of engineering transfer students see the note."'), 'search haystack is lower-case text of the row');

    echo "[table: {$design} design, advisor admin]\n";
    $html = test_layout($design)->render('degree_maps/admin/table', $vars($admin));
    check(substr_count($html, 'class="ma-row-check"') === 4 && str_contains($html, 'id="maps_select_all"'), 'advisor admins get a tick box per row plus select-all');
    check(str_contains($html, 'id="bulkApprove"') && str_contains($html, 'id="bulkWithdraw"'), 'bulk buttons');
    check(str_contains($html, 'data-can-approve="1"'), 'panel says approvals are allowed');
}

echo "[toolbar]\n";
$tb = static fn (User $u, ?array $map, string $mode = 'view'): string => test_layout('old')->render('degree_maps/admin/toolbar', [
    'user' => $u, 'mode' => $mode, 'map' => $map, 'order' => 'alpha', 'year' => 2028, 'years' => [2028, 2027], 'colleges' => ['College of Engineering'], 'college' => null,
    'status' => 'all', 'can_edit' => true, 'can_clone' => false, 'can_approve' => $u->isDegreeMapsAdmin(), 'editable_year' => true,
    'self_url' => '/academics/majors/degree_maps/admin/maps.php', 'search_url' => '/academics/majors/degree_maps/search.php', 'csrf' => 'x',
]);
$html = $tb($admin, $rows[1]);
check(str_contains($html, 'id="approveMap"') && str_contains($html, 'data-approved="0"') && str_contains($html, '>Approve for public site<'), 'admin sees Approve on an unapproved map');
check(str_contains($html, '>Preview public page<'), 'public link reads Preview');
check(str_contains($html, '<strong>Not approved</strong>: students cannot see this map. Approve it when it is ready.'), 'status line for an admin');
$html = $tb($admin, $rows[2]);
check(str_contains($html, '>Withdraw from public site<') && str_contains($html, 'data-approved="1"'), 'admin sees Withdraw on an approved map');
check(str_contains($html, '<strong>Approved</strong> by Aaron Admin on Oct 1, 2026: this map is on the public site.') && str_contains($html, 'It has been saved again since'), 'status line notes a later save');
$html = $tb($advisor, $rows[1]);
check(!str_contains($html, 'id="approveMap"'), 'advisors get no Approve button');
check(str_contains($html, 'An advisor admin or a super admin approves it when it is ready'), 'advisors are told who approves');
$html = $tb($advisor, null, 'list');
check(str_contains($html, 'id="selected_status"'), 'list mode has the approval filter');
check(!str_contains($tb($advisor, $rows[1], 'view'), 'id="selected_status"'), 'a map page does not');

echo "[listing flags]\n";
$groups = [['key' => 'A', 'label' => 'A', 'items' => [$rows[0], $rows[1]]]];
foreach (['old', 'new'] as $design) {
    $html = test_layout($design)->render('degree_maps/listing', ['groups' => $groups, 'link_base' => '/x?id=', 'flags' => [12 => 'not approved, duplicate']]);
    check(str_contains($html, '>not approved, duplicate</span>') && substr_count($html, 'dm-flag') === 1, "{$design}: flags map labels one row");
    check(str_contains($html, 'Not on the public site until an advisor admin'), "{$design}: flag title explains");
    $html = test_layout($design)->render('degree_maps/listing', ['groups' => $groups, 'link_base' => '/x?id=', 'flag_ids' => [11], 'flag_text' => 'duplicate']);
    check(str_contains($html, '>duplicate</span>'), "{$design}: the older flag_ids form still works");
}

echo "[preview notice]\n";
$html = test_layout('old')->render('degree_maps/preview_notice', ['map' => $rows[1], 'admin_url' => '/admin?degree_map_id=12']);
check(str_contains($html, 'Not approved.') && str_contains($html, 'href="/admin?degree_map_id=12"'), 'preview notice links to the admin');

finish();
