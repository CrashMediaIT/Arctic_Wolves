<?php
/**
 * PWA Device Detection Helper
 *
 * Detects whether the user-agent is a mobile phone, tablet, or desktop
 * to route them to the appropriate view:
 *   - Phone  → PWA (mobile-optimized)
 *   - Tablet → Desktop (full dashboard)
 *   - Desktop → Desktop (full dashboard)
 *
 * Also provides an opt-out: if the user explicitly sets ?view=desktop or
 * ?view=pwa, that preference is stored in the session and honored until
 * the session ends or is reset with ?view=auto.
 */

/**
 * Determine the preferred view for the current request.
 *
 * @return string 'pwa' | 'desktop'
 */
function getPwaViewPreference(): string {
    // 1. Honour explicit override in the query string
    if (isset($_GET['view'])) {
        $v = strtolower(trim($_GET['view']));
        if ($v === 'pwa' || $v === 'desktop') {
            $_SESSION['pwa_view_override'] = $v;
            return $v;
        }
        if ($v === 'auto') {
            unset($_SESSION['pwa_view_override']);
        }
    }

    // 2. Honour a session-stored override
    if (!empty($_SESSION['pwa_view_override'])) {
        return $_SESSION['pwa_view_override'];
    }

    // 3. Auto-detect from User-Agent
    return isMobilePhone() ? 'pwa' : 'desktop';
}

/**
 * Returns true only for mobile *phones* (not tablets).
 */
function isMobilePhone(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (empty($ua)) {
        return false;
    }

    // iPad and Android tablets: treat as desktop
    if (preg_match('/iPad|Android(?!.*Mobile)/i', $ua)) {
        return false;
    }

    // Common mobile phone indicators
    if (preg_match('/Mobile|iPhone|iPod|Android.*Mobile|webOS|BlackBerry|Opera Mini|Opera Mobi|IEMobile|Windows Phone|Symbian/i', $ua)) {
        return true;
    }

    return false;
}

/**
 * Redirect to the PWA if the current view preference is 'pwa'
 * and we are not already on a PWA page. Call from index.php/login.php/dashboard.php.
 *
 * @param string $pwaTarget The PWA URL to redirect to (default: pwa.php)
 */
function redirectToPwaIfMobile(string $pwaTarget = 'pwa.php'): void {
    // Don't redirect API, AJAX, or process_ requests
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (
        strpos($uri, '/api/') !== false ||
        strpos($uri, 'process_') !== false ||
        !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    ) {
        return;
    }

    // Don't redirect if already on a PWA page
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script, 'pwa') === 0) {
        return;
    }

    if (getPwaViewPreference() === 'pwa') {
        header("Location: $pwaTarget");
        exit();
    }
}
