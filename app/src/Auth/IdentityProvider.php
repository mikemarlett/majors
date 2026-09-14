<?php

declare(strict_types=1);

namespace Majors\Auth;

/**
 * A way of proving who the visitor is. CAS today; Azure/Entra later is one
 * more class implementing this and a config switch — nothing else changes.
 */
interface IdentityProvider
{
    public function name(): string;

    /**
     * Establish the visitor's identity. May redirect the browser to the
     * identity service and exit; when it returns, the identity is known.
     *
     * @param string $returnUrl absolute URL to come back to after sign-in
     */
    public function authenticate(string $returnUrl): Identity;

    /** End the provider-side session too, then send the browser to $returnUrl. */
    public function logout(string $returnUrl): never;
}
