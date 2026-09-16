<?php

/**
 * Add or update an approved user from the command line (for the first
 * super admin, before Manage Users is reachable).
 *
 *   php bin/add-user.php mike.marlett@wichita.edu super_admin "Mike" "Marlett" [netid] [college_id,college_id]
 */

declare(strict_types=1);

[$_, $email, $role, $first, $last] = $argv + [null, null, null, null, null];
$netid    = $argv[5] ?? null;
$colleges = isset($argv[6]) ? array_map('intval', explode(',', $argv[6])) : [];

$app = require dirname(__DIR__) . '/bootstrap.php';

if (!$email || !$role || !in_array($role, \Majors\Auth\User::ROLES, true)) {
    fwrite(STDERR, "usage: add-user.php <email> <advisor|advisor_admin|marketing|super_admin|none> <first> <last> [netid] [college ids]\n");
    exit(1);
}

$users = $app->users();
$existing = $users->findByNetidOrEmail($netid, $email);

$id = $users->save([
    'id'         => $existing?->id,
    'first_name' => $first ?? ($existing?->firstName ?? ''),
    'last_name'  => $last ?? ($existing?->lastName ?? ''),
    'email'      => $email,
    'netid'      => $netid ?? $existing?->netid,
    'role'       => $role,
    'is_active'  => 1,
    'colleges'   => $colleges !== [] ? $colleges : ($existing?->colleges ?? []),
]);
echo ($existing ? 'updated' : 'created') . " user #{$id} {$email} as {$role}\n";
