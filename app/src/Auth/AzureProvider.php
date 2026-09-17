<?php

declare(strict_types=1);

namespace Majors\Auth;

use RuntimeException;

/**
 * WSU Azure / Entra ID sign-in (OAuth 2.0 authorization code, Microsoft
 * identity platform v2.0) through league/oauth2-client's GenericProvider,
 * the same library and app registration the existing
 * /_resources/authorization/azure.php uses.
 *
 * Settings come from config 'auth.azure' and fall back to the constants
 * defined by /data/www/config/phpAzure/loader.php (WSU_OAUTH2_CLIENT_ID,
 * WSU_OAUTH2_SECRET — the name the existing azure.php uses; WSU_OAUTH2_CLIENT_SECRET
 * is accepted too — and WSU_OAUTH2_TENANT). The redirect URI is our own
 * auth/login.php (absolute), so that URL must be on the app registration's
 * redirect-URI list — Azure refuses anything not listed, exactly as CAS does.
 *
 * Flow: no ?code → send the browser to Azure (state kept in the session);
 * ?code&state → exchange it, read the profile from Graph /me and map it to
 * an Identity. The netid comes from onPremisesSamAccountName when the tenant
 * releases it, else from the UPN's local part when that is netid-shaped.
 */
final class AzureProvider implements IdentityProvider
{
    private const GRAPH_ME = 'https://graph.microsoft.com/v1.0/me?$select=id,mail,userPrincipalName,givenName,surname,onPremisesSamAccountName';

    /** @param array<string,mixed> $settings keys: loader, client_id, client_secret, tenant, scopes, netid_claim */
    public function __construct(private readonly array $settings)
    {
    }

    public function name(): string
    {
        return 'azure';
    }

    public function authenticate(string $returnUrl): Identity
    {
        $provider = $this->provider($returnUrl);
        $code     = (string) ($_GET['code'] ?? '');

        if ($code === '') {
            if (isset($_GET['error'])) {
                throw new RuntimeException('Azure sign-in refused: ' . (string) $_GET['error'] . ' ' . (string) ($_GET['error_description'] ?? ''));
            }
            $url = $provider->getAuthorizationUrl();
            $_SESSION['azure_oauth2_state'] = $provider->getState();
            header('Location: ' . $url, true, 302);
            exit;
        }

        $expected = (string) ($_SESSION['azure_oauth2_state'] ?? '');
        unset($_SESSION['azure_oauth2_state']);
        if ($expected === '' || !hash_equals($expected, (string) ($_GET['state'] ?? ''))) {
            throw new RuntimeException('Azure sign-in state mismatch (stale or reused sign-in link). Please try again.');
        }

        $token  = $provider->getAccessToken('authorization_code', ['code' => $code]);
        $claims = $provider->getResourceOwner($token)->toArray();
        return self::identityFromClaims(is_array($claims) ? $claims : [], (string) ($this->settings['netid_claim'] ?? 'onPremisesSamAccountName'));
    }

    /** Ends our knowledge of the sign-in; the Azure SSO session itself is left alone (a Microsoft sign-out would log them out of everything). */
    public function logout(string $returnUrl): never
    {
        unset($_SESSION['azure_oauth2_state']);
        header('Location: ' . $returnUrl, true, 302);
        exit;
    }

    /**
     * Map Graph /me (or ID-token) claims to an Identity. Static and library-free
     * so it can be unit-tested. Claim names are matched case-insensitively.
     *
     * @param array<string,mixed> $c
     */
    public static function identityFromClaims(array $c, string $netidClaim = 'onPremisesSamAccountName'): Identity
    {
        $lc = [];
        foreach ($c as $k => $v) {
            $lc[strtolower((string) $k)] = is_scalar($v) ? trim((string) $v) : '';
        }
        $get = static fn (string ...$keys): string => (static function () use ($lc, $keys): string {
            foreach ($keys as $k) {
                if (($lc[strtolower($k)] ?? '') !== '') {
                    return $lc[strtolower($k)];
                }
            }
            return '';
        })();

        $upn   = strtolower($get('userPrincipalName', 'upn', 'preferred_username'));
        $email = strtolower($get('mail', 'email'));
        if ($email === '' && str_contains($upn, '@')) {
            $email = $upn;
        }

        $netid = '';
        foreach ([$get($netidClaim), $get('onPremisesSamAccountName'), strstr($upn, '@', true) ?: ''] as $cand) {
            $cand = strtolower($cand);
            if (preg_match('/^[a-z][a-z0-9]{2,15}$/', $cand)) {
                $netid = $cand;
                break;
            }
        }
        if ($netid === '' && $email === '') {
            throw new RuntimeException('Azure returned no identifying claims.');
        }

        return new Identity($netid !== '' ? $netid : null, $email, $get('givenName', 'given_name'), $get('surname', 'family_name', 'sn'));
    }

    /** @return object league GenericProvider (typed loosely: the library is loaded from outside the app) */
    private function provider(string $redirectUri): object
    {
        $loader = (string) ($this->settings['loader'] ?? '/data/www/config/phpAzure/loader.php');
        if (is_file($loader)) {
            require_once $loader;
        }
        $class = '\\League\\OAuth2\\Client\\Provider\\GenericProvider';
        if (!class_exists($class)) {
            throw new RuntimeException("league/oauth2-client is not available (expected from {$loader}).");
        }

        $get = static function (array $s, string $key, string ...$consts): string {
            $v = $s[$key] ?? null;
            if (is_string($v) && $v !== '') {
                return $v;
            }
            foreach ($consts as $const) {
                if (defined($const) && (string) constant($const) !== '') {
                    return (string) constant($const);
                }
            }
            return '';
        };
        $clientId = $get($this->settings, 'client_id', 'WSU_OAUTH2_CLIENT_ID');
        $secret   = $get($this->settings, 'client_secret', 'WSU_OAUTH2_SECRET', 'WSU_OAUTH2_CLIENT_SECRET');
        $tenant   = $get($this->settings, 'tenant', 'WSU_OAUTH2_TENANT');
        foreach (['client id' => $clientId, 'client secret' => $secret, 'tenant' => $tenant] as $what => $v) {
            if ($v === '') {
                throw new RuntimeException("Azure sign-in is not configured: missing {$what} (auth.azure.* or the WSU_OAUTH2_* constants from {$loader}).");
            }
        }
        $scopes = (string) ($this->settings['scopes'] ?? 'openid profile email User.Read');

        return new $class([
            'clientId'                => $clientId,
            'clientSecret'            => $secret,
            'redirectUri'             => $redirectUri,
            'urlAuthorize'            => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize",
            'urlAccessToken'          => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            'urlResourceOwnerDetails' => self::GRAPH_ME,
            'scopes'                  => $scopes,
            'scopeSeparator'          => ' ',
        ]);
    }
}
