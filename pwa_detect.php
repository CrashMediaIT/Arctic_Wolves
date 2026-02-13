<?php
/**
 * PWA Device Detection Helper
 *
 * Detects whether the user-agent is a mobile phone, tablet, or desktop
 * to route them to the appropriate view:
 *   - Phone   → PWA mobile (bottom-tab navigation)
 *   - Tablet  → PWA tablet (collapsible sidebar + touch-friendly)
 *   - Desktop → Desktop (full dashboard)
 *
 * Also provides an opt-out: if the user explicitly sets ?view=desktop,
 * ?view=pwa, or ?view=pwa_tablet, that preference is stored in the
 * session and honored until the session ends or is reset with ?view=auto.
 */

/**
 * Determine the preferred view for the current request.
 *
 * @return string 'pwa' | 'pwa_tablet' | 'desktop'
 */
function getPwaViewPreference(): string {
    // 1. Honor explicit override in the query string
    if (isset($_GET['view'])) {
        $v = strtolower(trim($_GET['view']));
        if (in_array($v, ['pwa', 'pwa_tablet', 'desktop'], true)) {
            $_SESSION['pwa_view_override'] = $v;
            return $v;
        }
        if ($v === 'auto') {
            unset($_SESSION['pwa_view_override']);
        }
    }

    // 2. Honor a session-stored override
    if (!empty($_SESSION['pwa_view_override'])) {
        return $_SESSION['pwa_view_override'];
    }

    // 3. Auto-detect from User-Agent
    if (isMobilePhone()) {
        return 'pwa';
    }
    if (isTablet()) {
        return 'pwa_tablet';
    }
    return 'desktop';
}

/**
 * Returns true only for mobile *phones* (not tablets).
 */
function isMobilePhone(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (empty($ua)) {
        return false;
    }

    // iPad and Android tablets: treat as tablet, not phone
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
 * Returns true for tablets (iPad, Android tablets, Windows tablets).
 */
function isTablet(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (empty($ua)) {
        return false;
    }

    // iPad (including iPadOS 13+ which reports as Mac)
    if (preg_match('/iPad/i', $ua)) {
        return true;
    }

    // iPadOS 13+ uses Mac user-agent but has touch support
    // Detected via Macintosh + touch hint (not reliable via UA alone,
    // but we include the common pattern)
    if (preg_match('/Macintosh.*Safari/i', $ua) && preg_match('/Mobile/i', $ua)) {
        return true;
    }

    // Android tablets: "Android" without "Mobile"
    if (preg_match('/Android/i', $ua) && !preg_match('/Mobile/i', $ua)) {
        return true;
    }

    // Windows tablets with touch
    if (preg_match('/Windows.*Touch/i', $ua) || preg_match('/Tablet PC/i', $ua)) {
        return true;
    }

    // Amazon Kindle/Fire tablets
    if (preg_match('/Kindle|Silk|KFAPWI|KFOT|KFJWI|KFJWA|KFSOWI|KFTHWI/i', $ua)) {
        return true;
    }

    return false;
}

/**
 * Check if the current request is rendered inside the PWA shell (pwa.php or pwa_tablet.php).
 * Views can call this to render mobile-optimised content instead of their desktop layout.
 *
 * @return bool
 */
function isPwaMode(): bool {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return in_array($script, ['pwa.php', 'pwa_tablet.php'], true);
}

/**
 * Redirect to the PWA if the current view preference is not 'desktop'
 * and we are not already on a PWA page. Call from index.php/login.php/dashboard.php.
 *
 * @param string $phonePwaTarget  URL for phone users (default: pwa.php)
 * @param string $tabletPwaTarget URL for tablet users (default: pwa_tablet.php)
 */
function redirectToPwaIfMobile(string $phonePwaTarget = 'pwa.php', string $tabletPwaTarget = 'pwa_tablet.php'): void {
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

    $pref = getPwaViewPreference();
    if ($pref === 'pwa') {
        header("Location: $phonePwaTarget");
        exit();
    }
    if ($pref === 'pwa_tablet') {
        header("Location: $tabletPwaTarget");
        exit();
    }
}
