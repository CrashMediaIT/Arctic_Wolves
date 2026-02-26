<?php
/**
 * Site Branding Helper
 * Loads logo URL and favicon URL from theme_settings with fallback defaults.
 * Include this file in any page that needs to display the site logo or favicon.
 */

define('DEFAULT_LOGO_URL', 'https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png');

/**
 * Get the site logo URL from theme_settings, with a fallback to the default logo.
 *
 * @param PDO|null $pdo Database connection
 * @return string Logo URL
 */
function getSiteLogoUrl($pdo) {
    if (!$pdo) {
        return DEFAULT_LOGO_URL;
    }
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM theme_settings WHERE setting_name = 'logo_url' AND setting_value IS NOT NULL AND setting_value != '' LIMIT 1");
        $stmt->execute();
        $url = $stmt->fetchColumn();
        return !empty($url) ? $url : DEFAULT_LOGO_URL;
    } catch (\Throwable $e) {
        return DEFAULT_LOGO_URL;
    }
}

/**
 * Get the site favicon URL. Uses the logo if use_logo_as_favicon is enabled,
 * or a dedicated favicon_url if set, otherwise falls back to the logo URL.
 *
 * @param PDO|null $pdo Database connection
 * @return string Favicon URL
 */
function getSiteFaviconUrl($pdo) {
    if (!$pdo) {
        return DEFAULT_LOGO_URL;
    }
    try {
        $stmt = $pdo->prepare("SELECT setting_name, setting_value FROM theme_settings WHERE setting_name IN ('logo_url', 'favicon_url', 'use_logo_as_favicon')");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
        $favicon = $settings['favicon_url'] ?? '';
        $logo = $settings['logo_url'] ?? '';
        if (!empty($favicon)) {
            return $favicon;
        }
        return !empty($logo) ? $logo : DEFAULT_LOGO_URL;
    } catch (\Throwable $e) {
        return DEFAULT_LOGO_URL;
    }
}
