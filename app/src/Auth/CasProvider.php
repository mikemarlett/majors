<?php

declare(strict_types=1);

namespace Majors\Auth;

use RuntimeException;

/**
 * WSU single sign-on: Apereo CAS (SAML 1.1 attribute release) through phpCAS.
 *
 * The central config at /data/www/config/phpCAS/config.php sets $phpcas_path,
 * $cas_host, $cas_port and $cas_context; we include it inside a closure so
 * those stay out of the global scope, then initialize the client once.
 * phpCAS drives its own redirect + service-ticket validation inside
 * forceAuthentication(), so authenticate() simply returns when the ticket is
 * good. Attributes released: sAMAccountName / UDC_IDENTIFIER (netid), mail,
 * givenName, sn. Same shape as the Calendar project's CasProvider.
 */
final class CasProvider implements IdentityProvider
{
    private bool $booted = false;

    public function __construct(private readonly string $configPath)
    {
    }

    public function name(): string
    {
        return 'cas';
    }

    public function authenticate(string $returnUrl): Identity
    {
        $this->boot();
        \phpCAS::setFixedServiceURL($returnUrl);
        \phpCAS::forceAuthentication();
        $attributes = \phpCAS::getAttributes();
        $principal  = (string) \phpCAS::getUser();
        return self::identityFromAttributes(is_array($attributes) ? $attributes : [], $principal);
    }

    public function logout(string $returnUrl): never
    {
        $this->boot();
        \phpCAS::logoutWithRedirectService($returnUrl);
        exit; // phpCAS exits itself; this satisfies the `never` return type
    }

    /**
     * Map a CAS attribute bag to an Identity. Static and phpCAS-free so it can
     * be unit-tested. $principal (phpCAS::getUser()) is the fallback when the
     * bag is empty; at WSU it is the netid.
     */
    public static function identityFromAttributes(array $a, string $principal = ''): Identity
    {
        $first = static function (array $bag, string ...$keys): string {
            foreach ($keys as $k) {
                if (isset($bag[$k])) {
                    $v = is_array($bag[$k]) ? reset($bag[$k]) : $bag[$k];
                    $v = trim((string) $v);
                    if ($v !== '') {
                        return $v;
                    }
                }
            }
            return '';
        };

        $netid = strtolower($first($a, 'sAMAccountName', 'UDC_IDENTIFIER', 'uid'));
        $email = strtolower($first($a, 'mail', 'email'));
        if ($netid === '' && $principal !== '') {
            $netid = strtolower(trim($principal));
            if (str_contains($netid, '@')) { // some CAS setups release the UPN as principal
                [$netid, $domain] = explode('@', $netid, 2);
                $email = $email !== '' ? $email : $netid . '@' . $domain;
            }
        }
        if ($netid === '' && $email === '') {
            throw new RuntimeException('CAS returned no identifying attributes.');
        }
        if (!preg_match('/^[a-z][a-z0-9]{2,15}$/', $netid)) {
            $netid = ''; // not a netid-shaped principal; rely on email
        }

        return new Identity(
            $netid !== '' ? $netid : null,
            $email,
            $first($a, 'givenName'),
            $first($a, 'sn', 'surname'),
        );
    }

    private function boot(): void
    {
        if ($this->booted) {
            return;
        }
        if (!is_file($this->configPath)) {
            throw new RuntimeException('phpCAS config not found: ' . $this->configPath);
        }
        $cfg = (static function (string $file): array {
            require $file;
            return [
                'path'    => $phpcas_path ?? null,
                'host'    => $cas_host ?? null,
                'port'    => $cas_port ?? null,
                'context' => $cas_context ?? null,
                'ca'      => $cas_server_ca_cert_path ?? null,
            ];
        })($this->configPath);

        foreach (['path', 'host', 'port', 'context'] as $k) {
            if (empty($cfg[$k])) {
                throw new RuntimeException("phpCAS config is incomplete (missing {$k}).");
            }
        }
        require_once rtrim((string) $cfg['path'], '/') . '/CAS.php';

        if (!\phpCAS::isInitialized()) {
            \phpCAS::client(SAML_VERSION_1_1, (string) $cfg['host'], (int) $cfg['port'], (string) $cfg['context'], false);
            if (!empty($cfg['ca']) && is_file((string) $cfg['ca'])) {
                \phpCAS::setCasServerCACert((string) $cfg['ca']);
            } else {
                // Matches the existing WSU deployments (Calendar, legacy admin).
                \phpCAS::setNoCasServerValidation();
            }
        }
        $this->booted = true;
    }
}
