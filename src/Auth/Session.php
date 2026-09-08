<?php
declare(strict_types=1);

namespace AtomTool\Auth;

use AtomTool\Config;
use AtomTool\Http\Request;
use AtomTool\Http\Response;

/**
 * HMAC-signed cookie session. Stateless: the cookie carries the username and
 * an expiry timestamp; the signature proves the engine issued it. No server
 * storage, so sessions survive container redeploys.
 */
final class Session
{
    private const COOKIE_NAME = 'atomtool_session';
    private const TTL_SECONDS = 60 * 60 * 8; // 8 hours

    private ?string $username = null;

    public function __construct(
        private readonly Config $config,
        private readonly Request $request,
    ) {
        $this->username = $this->readCookie();
    }

    public function isAuthenticated(): bool
    {
        return $this->username !== null;
    }

    public function username(): ?string
    {
        return $this->username;
    }

    /**
     * Verify credentials against Config. Returns true on success and marks
     * the session as authenticated in memory; the caller must attach the
     * issued cookie to the outgoing Response via issueCookie().
     */
    public function attemptLogin(string $username, string $password): bool
    {
        if ($this->config->adminPasswordHash === '' || $this->config->sessionSecret === '') {
            return false;
        }
        if (!hash_equals($this->config->adminUser, $username)) {
            return false;
        }
        if (!password_verify($password, $this->config->adminPasswordHash)) {
            return false;
        }
        $this->username = $username;
        return true;
    }

    /**
     * Attach the signed session cookie to the given response.
     */
    public function issueCookie(Response $response): Response
    {
        if ($this->username === null) {
            return $response;
        }
        $expiry = time() + self::TTL_SECONDS;
        $payload = $this->username . '|' . $expiry;
        $sig = $this->sign($payload);
        $value = $payload . '|' . $sig;

        return $response->cookie(self::COOKIE_NAME, $value, [
            'expires'  => $expiry,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $this->isHttps(),
        ]);
    }

    /**
     * Attach a cookie-clearing header to the given response.
     */
    public function clearCookie(Response $response): Response
    {
        $this->username = null;
        return $response->cookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $this->isHttps(),
        ]);
    }

    private function readCookie(): ?string
    {
        $raw = $this->request->cookie(self::COOKIE_NAME);
        if ($raw === null || $raw === '') {
            return null;
        }
        $parts = explode('|', $raw);
        if (count($parts) !== 3) {
            return null;
        }
        [$user, $expiry, $sig] = $parts;
        if (!ctype_digit($expiry) || (int) $expiry < time()) {
            return null;
        }
        $expected = $this->sign($user . '|' . $expiry);
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        return $user;
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->config->sessionSecret);
    }

    private function isHttps(): bool
    {
        $https = $this->request->server['HTTPS'] ?? '';
        return $https !== '' && strtolower((string) $https) !== 'off';
    }
}