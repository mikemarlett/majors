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
                $default = $config->get('auth.dev_default');
                return new DevProvider(is_string($default) ? $default : null);
            default:
                throw new RuntimeException("Unknown auth.provider '{$name}'.");
        }
    }
}
