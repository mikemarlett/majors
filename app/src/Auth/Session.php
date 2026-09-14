<?php

declare(strict_types=1);

namespace Majors\Auth;

use Majors\Support\Config;
use Majors\Support\Request;

/**
 * PHP session with a fixed cookie name (never derived from the host), scoped
 * to the app's URL prefix so one login covers auth/, degree_maps/admin/ and
 * _admin/. Public pages never start a session.
 */
final class Session
{
    public static function start(Config $config, Request $request): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = $config->get('session.secure');
        if ($secure === null) {
            $secure = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? 'off') !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        }
        session_name($config->string('session.name', 'wsumajors'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $config->string('session.path', '/academics/majors'),
            'secure'   => (bool) $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) (60 * (int) $config->get('session.lifetime_minutes', 480)));
        session_start();

        // Idle timeout.
        $now  = time();
        $max  = 60 * (int) $config->get('session.lifetime_minutes', 480);
        $last = (int) ($_SESSION['_last_seen'] ?? $now);
        if ($now - $last > $max) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_last_seen'] = $now;
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'],
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_destroy();
    }
}
