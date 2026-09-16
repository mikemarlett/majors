<?php

/**
 * Add or update an approved user from the command line (for the first
 * super admin, before Manage Users is reachable).
 *
 *   php bin/add-user.php mike.marlett@wichita.edu super_admin "Mike" "Marlett" q262t958 [college_id,college_id]
 *
 * The netid is required: CAS identifies people by it, not by email.
 */

declare(strict_types=1);

[$_, $email, $role, $first, $last] = $argv + [null, null, null, null, null];
$netid    = strtolower((string) ($argv[5] ?? ''));
$colleges = isset($argv[6]) ? array_map('intval', explode(',', $argv[6])) : [];

$app = require dirname(__DIR__) . '/bootstrap.php';

if (!$email || !$role || !in_array($role, \Majors\Auth\User::ROLES, true) || !preg_match('/^[a-z][a-z0-9]{2,7}$/', $netid)) {
    fwrite(STDERR, "usage: add-user.php <email> <advisor|advisor_admin|marketing|super_admin|none> <first> <last> <netid> [college ids]\n"
        . "The netid (myWSU ID, e.g. a123b456) is required: sign-in matches people by it.\n");
    exit(1);
}

$users = $app->users();
$existing = $users->findByNetidOrEmail($netid, $email);

$id = $users->save([
    'id'         => $existing?->id,
    'first_name' => $first ?? ($existing?->firstName ?? ''),
    'last_name'  => $last ?? ($existing?->lastName ?? ''),
    'email'      => $email,
    'netid'      => $netid,
    'role'       => $role,
    'is_active'  => 1,
    'colleges'   => $colleges !== [] ? $colleges : ($existing?->colleges ?? []),
]);
echo ($existing ? 'updated' : 'created') . " user #{$id} {$email} as {$role}\n";
