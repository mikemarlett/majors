<?php

declare(strict_types=1);

namespace Majors\Http\Ajax;

use Majors\Auth\User;
use Majors\Support\Request;

/** Manage Users (super admins). */
final class UserActions extends BaseAction
{
    public function list(Request $r, User $user): array
    {
        $colleges    = $this->lookups->collegeNames();
        $departments = $this->lookups->departments();
        $users       = [];
        foreach ($this->app->users()->all() as $u) {
            $users[] = [
                'id'          => (int) $u['id'],
                'first_name'  => (string) ($u['first_name'] ?? ''),
                'last_name'   => (string) ($u['last_name'] ?? ''),
                'email'       => (string) ($u['email'] ?? ''),
                'netid'       => (string) ($u['netid'] ?? ''),
                'role'        => (string) ($u['role'] ?? 'none'),
                'is_active'   => (int) ($u['is_active'] ?? 1),
                'last_login'  => (string) ($u['last_login_at'] ?? ''),
                'colleges'    => array_values(array_filter(array_map(static fn (int $id) => $colleges[$id] ?? null, $u['colleges']))),
                'department'  => (string) ($departments[(int) ($u['default_department_id'] ?? 0)]['department'] ?? ''),
            ];
        }
        return ['status' => 'success', 'success' => true, 'users' => $users];
    }

    public function form(Request $r, User $user): string
    {
        $id  = $r->id('user_id');
        $row = null;
        if ($id !== null) {
            $found = $this->app->users()->find($id);
            if ($found === null) {
                throw new ActionException('User not found.', 404);
            }
            $row = [
                'id' => $found->id, 'first_name' => $found->firstName, 'last_name' => $found->lastName,
                'email' => $found->email, 'netid' => (string) $found->netid, 'role' => $found->role,
                'colleges' => $found->colleges, 'default_department_id' => $found->defaultDepartmentId, 'is_active' => 1,
            ];
        }
        return $this->app->layout()->render('degree_maps/admin/form_user', [
            'u'           => $row ?? ['id' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'netid' => '', 'role' => 'advisor', 'colleges' => [], 'default_department_id' => null, 'is_active' => 1],
            'colleges'    => $this->lookups->collegeNames(),
            'departments' => array_map(static fn (array $d) => (string) $d['department'], $this->lookups->departments()),
            'roles'       => ['advisor' => 'Advisor (degree maps)', 'marketing' => 'Marketing (majors pages)', 'super_admin' => 'Super admin (everything)', 'none' => 'Disabled'],
            'csrf'        => $this->app->guard()->csrfToken(),
        ]);
    }

    public function save(Request $r, User $user): array
    {
        $email = strtolower($r->str('email'));
        if ($r->str('first_name') === '' || $r->str('last_name') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ActionException('First name, last name and a valid email are required.');
        }
        $role = $r->enum('role', User::ROLES, 'none');
        $netid = strtolower($r->str('netid'));
        if ($netid !== '' && !preg_match('/^[a-z][a-z0-9]{2,7}$/', $netid)) {
            throw new ActionException('The myWSU ID should look like a123b456.');
        }
        $id = $r->id('user_id');
        if ($id !== null && $id === $user->id && $role !== 'super_admin') {
            throw new ActionException('You cannot remove your own super admin role.');
        }
        $colleges = array_values(array_filter(array_map('intval', $r->arr('colleges')), static fn (int $c) => $c > 0));
        $saved = $this->app->users()->save([
            'id'                    => $id,
            'first_name'            => $r->str('first_name'),
            'last_name'             => $r->str('last_name'),
            'email'                 => $email,
            'netid'                 => $netid !== '' ? $netid : null,
            'role'                  => $role,
            'default_college_id'    => $colleges[0] ?? null,
            'default_department_id' => $r->id('department'),
            'is_active'             => $r->int('is_active', 1) ? 1 : 0,
            'colleges'              => $colleges,
        ]);
        return ['status' => 'success', 'success' => true, 'message' => 'User saved.', 'user_id' => $saved];
    }

    public function delete(Request $r, User $user): array
    {
        $id = $r->id('user_id') ?? throw new ActionException('Invalid user ID.');
        if ($id === $user->id) {
            throw new ActionException('You cannot delete your own account.');
        }
        $this->app->users()->delete($id);
        return ['status' => 'success', 'success' => true, 'message' => 'User deleted.'];
    }
}
