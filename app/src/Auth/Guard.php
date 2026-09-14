<?php

declare(strict_types=1);

namespace Majors\Auth;

use Majors\Kernel;
use Majors\Support\Json;

/**
 * Access control for the admin pages and ajax actions.
 *
 *   $user = $app->guard()->require('advisor');          // page: redirect to login / 403 page
 *   $user = $app->guard()->requireAjax('advisor', true); // ajax: 401/403 JSON, CSRF check
 */
final class Guard
{
    private const SESSION_KEY = 'majors_user';

    public function __construct(
        private readonly Kernel $app,
        private readonly UserRepository $users,
        private readonly Csrf $csrf,
    ) {
    }

    public function user(): ?User
    {
        $s = $_SESSION[self::SESSION_KEY] ?? null;
        return is_array($s) ? User::fromArray($s) : null;
    }

    /** Seat a freshly authenticated, approved user in the session. */
    public function login(User $user): void
    {
        session_regenerate_id(true);
        $this->csrf->rotate();
        $_SESSION[self::SESSION_KEY] = $user->toArray();
    }

    public function logout(): void
    {
        Session::destroy();
    }

    /**
     * Page guard. Anonymous → redirect to the sign-in page with a return path;
     * signed in without the role → 403 page. super_admin passes every check.
     */
    public function require(string ...$roles): User
    {
        $user = $this->user();
        if ($user === null) {
            $return = $this->app->request()->uri();
            header('Location: ' . $this->app->layout()->url('auth/login.php') . '?return=' . rawurlencode($return), true, 302);
            exit;
        }
        if ($roles !== [] && !$user->hasRole(...$roles)) {
            $this->forbidPage($user, $roles);
        }
        return $user;
    }

    /** Ajax guard: JSON errors instead of redirects; optional CSRF enforcement. */
    public function requireAjax(array $roles, bool $csrf): User
    {
        $user = $this->user();
        if ($user === null) {
            Json::fail('Your session has expired. Please sign in again.', 401, ['login' => $this->app->layout()->url('auth/login.php')]);
        }
        if ($roles !== [] && !$user->hasRole(...$roles)) {
            Json::fail('You do not have permission to do that.', 403);
        }
        if ($csrf && !$this->csrf->valid($this->csrfCandidate())) {
            Json::fail('Security token missing or expired. Reload the page and try again.', 419);
        }
        return $user;
    }

    /** Refresh the session copy of the user (after Manage Users edits, say). */
    public function refresh(): ?User
    {
        $u = $this->user();
        if ($u === null) {
            return null;
        }
        $fresh = $this->users->find($u->id);
        if ($fresh === null || $fresh->role === 'none') {
            $this->logout();
            return null;
        }
        $_SESSION[self::SESSION_KEY] = $fresh->toArray();
        return $fresh;
    }

    public function csrfToken(): string
    {
        return $this->csrf->token();
    }

    private function csrfCandidate(): ?string
    {
        $r = $this->app->request();
        return $r->header('X-CSRF-Token') ?? $r->strOrNull('_csrf');
    }

    private function forbidPage(User $user, array $roles): never
    {
        http_response_code(403);
        $layout = $this->app->layout();
        echo $layout->page(
            $layout->render('auth/denied', ['user' => $user, 'roles' => $roles, 'reason' => 'role']),
            ['title' => 'Access denied', 'user' => $user]
        );
        exit;
    }
}
