<?php

declare(strict_types=1);

namespace Majors\Http;

use Majors\Support\Request;
use Throwable;

/**
 * auth/login.php  — hand off to the identity provider, then look the person up
 *                   on the approved list and seat them in the session.
 * auth/logout.php — clear our session and the provider's.
 *
 * Nobody is created here: an authenticated person who is not in majors_users
 * (or is inactive / role 'none') gets the "not on the access list" page.
 */
final class AuthController extends Controller
{
    public function handle(Request $r): void
    {
        $this->app->session();
        $action = basename($r->path(), '.php');
        if ($action === 'logout') {
            $this->logout();
        }
        $this->login($r);
    }

    private function login(Request $r): never
    {
        $layout = $this->app->layout();
        $return = $this->safeReturn($r->str('return'));
        $guard  = $this->app->guard();

        if ($guard->user() !== null) {
            $this->redirect($return);
        }

        // Keep the return path across the provider round-trip.
        if ($r->str('return') !== '') {
            $_SESSION['auth_return'] = $return;
        } else {
            $return = $this->safeReturn((string) ($_SESSION['auth_return'] ?? ''));
        }

        $provider = $this->app->identityProvider();
        try {
            // The provider must come back to exactly this URL (CAS validates the service URL).
            $identity = $provider->authenticate($this->absolute($layout->url('auth/login.php')));
        } catch (Throwable $e) {
            error_log('[majors] sign-in failed: ' . $e->getMessage());
            http_response_code(400);
            $this->page($layout->render('auth/denied', ['reason' => 'provider', 'detail' => $this->app->isDev() ? $e->getMessage() : '']), ['title' => 'Sign-in problem']);
            exit;
        }

        $user = $this->app->users()->findByNetidOrEmail($identity->netid, $identity->email);
        if ($user === null || $user->role === 'none') {
            http_response_code(403);
            $this->page($layout->render('auth/denied', ['reason' => 'not_listed', 'identity' => $identity]), ['title' => 'Access denied']);
            exit;
        }

        $this->app->users()->touchLogin($user->id, $identity);
        $guard->login($this->app->users()->find($user->id) ?? $user);
        unset($_SESSION['auth_return']);
        $this->redirect($return);
    }

    private function logout(): never
    {
        $layout = $this->app->layout();
        $this->app->guard()->logout();
        $home = $this->absolute($layout->url('degree_maps/maps.php'));
        try {
            $this->app->identityProvider()->logout($home);
        } catch (Throwable $e) {
            $this->redirect($home);
        }
    }

    /** Only allow returning to pages inside this app. */
    private function safeReturn(string $candidate): string
    {
        $base = $this->app->layout()->url('');
        if ($candidate !== '' && str_starts_with($candidate, $base) && !str_contains($candidate, '//') && !str_contains($candidate, "\n")) {
            return $candidate;
        }
        return $this->app->layout()->url('degree_maps/admin/maps.php');
    }

    private function absolute(string $path): string
    {
        $https = (($_SERVER['HTTPS'] ?? 'off') !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host  = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return ($https ? 'https' : 'http') . '://' . $host . $path;
    }
}
