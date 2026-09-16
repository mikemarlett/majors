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
        if ($r->int('diag') === 1) {
            $this->diagnostics($r);
        }
        $this->login($r);
    }

    /**
     * auth/login.php?diag=1 — what the sign-in would do, without doing it.
     * Only facts a visitor could learn anyway (scheme, host, whether files
     * exist, whether the schema is migrated) plus the last sign-in failure
     * from this browser's session, so a broken login can be diagnosed on a
     * server whose error log is out of reach.
     */
    private function diagnostics(Request $r): never
    {
        $layout = $this->app->layout();
        $cfg    = $this->app->config;
        $checks = [];
        $ok     = static fn (bool $b): string => $b ? 'ok' : 'PROBLEM';

        $checks[] = ['Site / env / design', sprintf('%s / %s / %s', $this->app->site(), $cfg->string('env', 'prod'), $layout->design()), 'ok'];
        $https = (($_SERVER['HTTPS'] ?? 'off') !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $checks[] = ['Request scheme seen by PHP', ($https ? 'https' : 'http') . ' (HTTPS=' . ($_SERVER['HTTPS'] ?? 'unset') . ', X-Forwarded-Proto=' . ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'unset') . ')', $ok($https)];
        $service = $this->absolute($layout->url('auth/login.php'));
        $checks[] = ['CAS service URL we register', $service . ($cfg->string('auth.service_host', '') === '' ? '  (host taken from the request; set auth.service_host to pin it)' : ''), $ok(str_starts_with($service, 'https://'))];
        $checks[] = ['Host header on this request', (string) ($_SERVER['HTTP_HOST'] ?? ''), 'ok'];

        $provider = $cfg->string('auth.provider', 'cas');
        $checks[] = ['auth.provider', $provider, $ok($provider === 'cas')];
        $casCfg = $cfg->string('auth.cas_config', '/data/www/config/phpCAS/config.php');
        $checks[] = ['phpCAS config file', $casCfg, $ok(is_file($casCfg))];
        if (is_file($casCfg)) {
            $c = (static function (string $f): array { require $f; return ['path' => $phpcas_path ?? '', 'host' => $cas_host ?? '', 'ctx' => $cas_context ?? '']; })($casCfg);
            $casPhp = rtrim((string) $c['path'], '/') . '/CAS.php';
            $checks[] = ['phpCAS library', $casPhp . (is_file($casPhp) ? '' : ' (missing)'), $ok(is_file($casPhp))];
            $checks[] = ['CAS server', $c['host'] . $c['ctx'], $ok($c['host'] !== '')];
        }

        $p = session_get_cookie_params();
        $checks[] = ['Session cookie', sprintf('%s path=%s secure=%s samesite=%s', session_name(), $p['path'], $p['secure'] ? 'yes' : 'no', $p['samesite']), $ok(!$https || $p['secure'])];

        try {
            $db   = $this->app->db();
            $cols = array_column($db->query('SHOW COLUMNS FROM `majors_users`')->fetch_all(MYSQLI_ASSOC), 'Field');
            $need = ['netid', 'role', 'is_active', 'last_login_at'];
            $miss = array_diff($need, $cols);
            $checks[] = ['majors_users migrated', $miss === [] ? 'columns present' : 'missing: ' . implode(', ', $miss) . ' (run sql/003_migration_plain.sql)', $ok($miss === [])];
            if ($miss === []) {
                $n = (int) $db->query("SELECT COUNT(*) FROM `majors_users` WHERE `is_active` = 1 AND `role` <> 'none'")->fetch_row()[0];
                $checks[] = ['Active users with a role', (string) $n, $ok($n > 0)];
            }
            $hasColleges = $db->query("SHOW TABLES LIKE 'majors_user_colleges'")->num_rows === 1;
            $checks[] = ['majors_user_colleges table', $hasColleges ? 'present' : 'missing', $ok($hasColleges)];
        } catch (Throwable $e) {
            $checks[] = ['Database', 'connection or query failed: ' . get_class($e), 'PROBLEM'];
        }

        $last = $_SESSION['auth_last_error'] ?? null;
        $checks[] = ['Last sign-in failure (this browser)', is_array($last) ? sprintf('%s — %s', $last['at'], $last['message']) : 'none recorded', is_array($last) ? 'PROBLEM' : 'ok'];

        http_response_code(200);
        $this->page($layout->render('auth/diag', ['checks' => $checks]), ['title' => 'Sign-in diagnostics', 'page_header' => 'Sign-in diagnostics', 'header_print' => true]);
        exit;
    }

    /** Remember why sign-in failed so ?diag=1 can show it. Database errors are not echoed (schema details). */
    private function rememberFailure(Throwable $e): void
    {
        $safe = $e instanceof \mysqli_sql_exception ? 'database error (see server log)' : $e->getMessage();
        $_SESSION['auth_last_error'] = ['at' => date('Y-m-d H:i:s'), 'message' => get_class($e) . ': ' . mb_substr($safe, 0, 600)];
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
            $this->rememberFailure($e);
            http_response_code(400);
            $this->page($layout->render('auth/denied', ['reason' => 'provider', 'detail' => $this->app->isDev() ? $e->getMessage() : '']), ['title' => 'Sign-in problem', 'page_header' => 'Sign-in problem', 'header_print' => true]);
            exit;
        }

        try {
            $user = $this->app->users()->findByNetidOrEmail($identity->netid, $identity->email);
        } catch (Throwable $e) {
            // Usually an unmigrated majors_users table; keep the reason for ?diag=1.
            error_log('[majors] sign-in lookup failed: ' . $e->getMessage());
            $this->rememberFailure($e);
            http_response_code(500);
            $this->page($layout->render('auth/denied', ['reason' => 'provider', 'detail' => $this->app->isDev() ? $e->getMessage() : '']), ['title' => 'Sign-in problem', 'page_header' => 'Sign-in problem', 'header_print' => true]);
            exit;
        }
        if ($user === null || $user->role === 'none') {
            error_log(sprintf('[majors] sign-in refused: %s / %s is not on the access list', $identity->netid ?? '-', $identity->email ?? '-'));
            $_SESSION['auth_last_error'] = ['at' => date('Y-m-d H:i:s'), 'message' => sprintf('CAS identified netid "%s", email "%s" — no active majors_users row with a role matches', $identity->netid ?? '', $identity->email ?? '')];
            http_response_code(403);
            $this->page($layout->render('auth/denied', ['reason' => 'not_listed', 'identity' => $identity]), ['title' => 'Access denied', 'page_header' => 'Access denied', 'header_print' => true]);
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

    /**
     * Absolute URL for the CAS service. CAS authorizes by this exact URL, so it
     * must not wobble with how someone reached the page: on a server the scheme
     * is always https and the host comes from auth.service_host when set
     * (an internal server name or a plain http:// visit would otherwise
     * produce an unregistered service).
     */
    private function absolute(string $path): string
    {
        $https = !$this->app->isDev()
            || (($_SERVER['HTTPS'] ?? 'off') !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host  = $this->app->config->string('auth.service_host', '');
        if ($host === '') {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        return ($https ? 'https' : 'http') . '://' . $host . $path;
    }
}
