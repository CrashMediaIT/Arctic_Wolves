<?php
/**
 * Session Configuration - Cross-Subdomain Cookie Sharing
 *
 * Configures the PHP session cookie domain so that the session is accessible
 * across subdomains (e.g. arcticwolves.ca ↔ review.arcticwolves.ca).
 *
 * Include this file BEFORE calling session_start().
 */
if (session_status() === PHP_SESSION_NONE) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host) {
        // Strip port number if present (e.g. localhost:8080)
        $hostOnly = explode(':', $host)[0];
        $parts = explode('.', $hostOnly);

        // Derive parent domain for subdomains (e.g. ".arcticwolves.ca")
        // Skip for localhost / single-label hosts
        if (count($parts) >= 2) {
            $cookieDomain = '.' . implode('.', array_slice($parts, -2));

            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => $cookieDomain,
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly'  => true,
                'samesite'  => 'Lax',
            ]);
        }
    }
}
