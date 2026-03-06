<?php
/**
 * Dynamic Theme Variables CSS Generator
 * Generates CSS variables from database theme settings
 */

// Set content type to CSS
header('Content-Type: text/css');

// Cache for 1 hour but revalidate
header('Cache-Control: public, max-age=3600, must-revalidate');

require_once __DIR__ . '/../db_config.php';

try {
    // Fetch theme settings from database
    $stmt = $pdo->query("SELECT setting_name, setting_value FROM theme_settings");
    $theme_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Default fallback colors
    $defaults = [
        'primary_color' => '#6B46C1',
        'secondary_color' => '#c0c0c0',
        'button_hover_color' => '#a78bfa',
        'success_color' => '#22c55e',
        'error_color' => '#ef4444',
        'warning_color' => '#f59e0b'
    ];
    
    // Merge with defaults
    $colors = array_merge($defaults, $theme_settings);
    
} catch (Exception $e) {
    // If database error, use defaults
    error_log("Theme CSS Generator Error: " . $e->getMessage());
    $colors = $defaults;
}

// Generate CSS
?>
:root {
    /* Primary Theme Colors */
    --primary: <?= htmlspecialchars($colors['primary_color'], ENT_QUOTES, 'UTF-8') ?>;
    --neon: <?= htmlspecialchars($colors['primary_color'], ENT_QUOTES, 'UTF-8') ?>;
    --secondary: <?= htmlspecialchars($colors['secondary_color'], ENT_QUOTES, 'UTF-8') ?>;
    --accent: <?= htmlspecialchars($colors['secondary_color'], ENT_QUOTES, 'UTF-8') ?>;
    
    /* UI Colors */
    --button-hover: <?= htmlspecialchars($colors['button_hover_color'], ENT_QUOTES, 'UTF-8') ?>;
    
    /* Status Colors */
    --success: <?= htmlspecialchars($colors['success_color'], ENT_QUOTES, 'UTF-8') ?>;
    --error: <?= htmlspecialchars($colors['error_color'], ENT_QUOTES, 'UTF-8') ?>;
    --warning: <?= htmlspecialchars($colors['warning_color'], ENT_QUOTES, 'UTF-8') ?>;
    
    /* Utility */
    --container-padding: 30px;
}

/* Override brand colors with theme variables */
.btn-primary,
.nav-btn {
    background: var(--primary) !important;
}

.btn-primary:hover,
.nav-btn:hover {
    background: var(--button-hover) !important;
}

.highlight,
.brand span,
.logo-text span {
    color: var(--primary) !important;
}

.status-indicator {
    color: var(--primary) !important;
    border-color: var(--primary) !important;
}

.dot {
    background: var(--primary) !important;
    box-shadow: 0 0 8px var(--primary) !important;
}

.nav-link:hover,
.nav-link.active {
    color: var(--primary) !important;
}

.avatar {
    background: var(--primary) !important;
}

.mobile-menu-toggle {
    background: var(--primary) !important;
}

.game-card:hover {
    border-color: var(--primary) !important;
}

.eyebrow {
    color: var(--primary) !important;
}

.form-footer a {
    color: var(--primary) !important;
}

.footer-col h4 {
    color: var(--primary) !important;
}

.input-wrapper:focus-within {
    border-color: var(--primary) !important;
}
