<?php

declare(strict_types=1);

namespace Majors\Auth;

use Majors\Support\Config;
use RuntimeException;

final class ProviderFactory
{
    public static function make(Config $config, bool $isDev): IdentityProvider
    {
        $name = $config->string('auth.provider', 'cas');
        switch ($name) {
            case 'cas':
                return new CasProvider($config->string('auth.cas_config', '/data/www/config/phpCAS/config.php'));
            case 'dev':
                if (!$isDev) {
                    throw new RuntimeException('The dev sign-in provider is only allowed when env=dev.');
                }
                if (!self::isPrivateHost((string) ($_SERVER['HTTP_HOST'] ?? ''))) {
                    // A sandbox app.local.php copied onto a server must not turn the admin into an open door.
                    throw new RuntimeException('The dev sign-in provider only answers on localhost or a private-network address.');
                }
                $default = $config->get('auth.dev_default');
                return new DevProvider(is_string($default) ? $default : null);
            default:
                throw new RuntimeException("Unknown auth.provider '{$name}'.");
        }
    }

    /** localhost, loopback, or an RFC 1918 / link-local address (with or without a port). CLI (no host) counts as private. */
    public static function isPrivateHost(string $host): bool
    {
        if ($host === '') {
            return PHP_SAPI === 'cli';
        }
        $h = strtolower(trim($host));
        if ($h[0] === '[') {                       // [::1]:8080
            $h = substr($h, 1, (int) strpos($h, ']') - 1);
        } elseif (substr_count($h, ':') === 1) {   // host:port
            $h = explode(':', $h)[0];
        }
        if ($h === 'localhost' || $h === '::1') {
            return true;
        }
        if (filter_var($h, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return filter_var($h, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }
        return false;
    }
}
