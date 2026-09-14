<?php

declare(strict_types=1);

namespace Majors\Auth;

use RuntimeException;

/**
 * Local-development sign-in: ?as=someone@wichita.edu (or the configured
 * default). Only constructed by ProviderFactory when env=dev; never on a
 * server. The email must still be on the approved list to get a role.
 */
final class DevProvider implements IdentityProvider
{
    public function __construct(private readonly ?string $default)
    {
    }

    public function name(): string
    {
        return 'dev';
    }

    public function authenticate(string $returnUrl): Identity
    {
        $email = strtolower(trim((string) ($_GET['as'] ?? $this->default ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Dev sign-in needs ?as=<email> or auth.dev_default.');
        }
        $local = strstr($email, '@', true) ?: $email;
        $parts = explode('.', $local, 2);
        return new Identity(null, $email, ucfirst($parts[0]), ucfirst($parts[1] ?? ''));
    }

    public function logout(string $returnUrl): never
    {
        header('Location: ' . $returnUrl);
        exit;
    }
}
