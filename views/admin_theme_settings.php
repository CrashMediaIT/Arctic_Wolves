<?php
/**
 * Admin Theme Settings
 * Comprehensive theming portal with tabs for Colors, Logo & Branding, Hero Section, and Training Programs
 */

require_once __DIR__ . '/../security.php';

// Check if user is admin
if ($user_role !== 'admin') {
    header('Location: dashboard.php?page=home');
    exit;
}

// Get current theme settings
try {
    $stmt = $pdo->query("SELECT setting_name, setting_value FROM theme_settings");
    $theme_colors = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $theme_colors = [];
}

// Get training programs
try {
    $programs_stmt = $pdo->query("SELECT * FROM training_programs ORDER BY display_order ASC, id ASC");
    $training_programs = $programs_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $training_programs = [];
}

// Default colors
$defaults = [
    'primary_color' => '#7000a4',
    'secondary_color' => '#c0c0c0',
    'background_color' => '#06080b',
    'card_background_color' => '#0d1117',
    'text_color' => '#ffffff',
    'text_muted_color' => '#94a3b8',
    'border_color' => '#1e293b',
    'sidebar_color' => '#020305',
    'button_hover_color' => '#a78bfa',
    'success_color' => '#22c55e',
    'error_color' => '#ef4444',
    'warning_color' => '#f59e0b'
];

$colors = array_merge($defaults, $theme_colors);

// Get branding settings
$logo_url = $theme_colors['logo_url'] ?? '';
$hero_image_url = $theme_colors['hero_image_url'] ?? '';
$hero_title = $theme_colors['hero_title'] ?? 'Crash Hockey Development';
$hero_subtitle = $theme_colors['hero_subtitle'] ?? 'Specialized on-ice and off-ice training protocols designed for competitive athletes seeking elite performance levels.';
?>

<style>
    :root {
        --primary: #7000a4;
    }
    
    .theme-header {
        margin-bottom: 30px;
    }
    
    .theme-header h1 {
        font-size: 32px;
        font-weight: 900;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .theme-header h1 i {
        color: var(--primary);
    }
    
    .theme-header p {
        color: #94a3b8;
        font-size: 14px;
    }
    
    /* Tab Navigation */
    .tabs-container {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        border-bottom: 2px solid #1e293b;
        overflow-x: auto;
    }
    
    .tab-btn {
        padding: 12px 24px;
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .tab-btn:hover {
        color: #fff;
    }
    
    .tab-btn.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    /* Theme Container */
    .theme-container {
        display: grid;
        grid-template-columns: 1fr 450px;
        gap: 30px;
        margin-top: 30px;
    }
    
    .color-settings-panel,
    .form-panel {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 12px;
        padding: 30px;
    }
    
    .full-width-panel {
        grid-column: 1 / -1;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 12px;
        padding: 30px;
    }
    
    .settings-section {
        margin-bottom: 35px;
    }
    
    .settings-section:last-child {
        margin-bottom: 0;
    }
    
    .section-title {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 20px;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-title i {
        color: var(--primary);
        font-size: 14px;
    }
    
    .color-input-group {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    
    .color-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .color-field label {
        font-size: 12px;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .color-picker-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 8px 12px;
        transition: all 0.2s;
    }
    
    .color-picker-wrapper:hover,
    .color-picker-wrapper:focus-within {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(112, 0, 164, 0.1);
    }
    
    .color-swatch {
        width: 40px;
        height: 40px;
        border-radius: 6px;
        border: 2px solid #1e293b;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }
    
    .color-swatch:hover {
        transform: scale(1.05);
        border-color: var(--primary);
    }
    
    .color-hex-input {
        flex: 1;
        background: transparent;
        border: none;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        font-family: 'Courier New', monospace;
        outline: none;
    }
    
    .color-picker {
        position: absolute;
        opacity: 0;
        pointer-events: none;
        width: 0;
        height: 0;
    }
    
    /* Preview Panel */
    .preview-panel {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 12px;
        padding: 30px;
        position: sticky;
        top: 20px;
        max-height: calc(100vh - 140px);
        overflow-y: auto;
    }
    
    .preview-title {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 20px;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .preview-title i {
        color: var(--primary);
    }
    
    .preview-container {
        background: var(--bg, #06080b);
        border: 1px solid var(--border, #1e293b);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .preview-card {
        background: var(--card-bg, #0d1117);
        border: 1px solid var(--border, #1e293b);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
    }
    
    .preview-card h3 {
        color: var(--text-color, #fff);
        font-size: 18px;
        margin-bottom: 8px;
    }
    
    .preview-card p {
        color: var(--text-muted, #94a3b8);
        font-size: 14px;
        line-height: 1.6;
    }
    
    .preview-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .preview-btn {
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .preview-btn-primary {
        background: var(--primary, #7000a4);
        color: #fff;
    }
    
    .preview-btn-primary:hover {
        background: var(--button-hover, #a78bfa);
        transform: translateY(-2px);
    }
    
    .preview-btn-secondary {
        background: transparent;
        color: var(--primary, #7000a4);
        border: 1px solid var(--border, #1e293b);
    }
    
    .preview-sidebar {
        background: var(--sidebar, #020305);
        border: 1px solid var(--border, #1e293b);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .preview-nav-item {
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 13px;
        color: var(--text-muted, #94a3b8);
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .preview-nav-item.active {
        background: rgba(112, 0, 164, 0.1);
        color: var(--primary, #7000a4);
    }
    
    .preview-status {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }
    
    .status-badge {
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    
    .status-success {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success, #22c55e);
        border: 1px solid var(--success, #22c55e);
    }
    
    .status-error {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error, #ef4444);
        border: 1px solid var(--error, #ef4444);
    }
    
    .status-warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning, #f59e0b);
        border: 1px solid var(--warning, #f59e0b);
    }
    
    /* Form Elements */
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #94a3b8;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .form-control {
        width: 100%;
        padding: 12px 16px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 8px;
        color: #fff;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(112, 0, 164, 0.1);
    }
    
    textarea.form-control {
        resize: vertical;
        min-height: 100px;
    }
    
    .radio-group {
        display: flex;
        gap: 20px;
        margin-bottom: 15px;
    }
    
    .radio-option {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    
    .radio-option input[type="radio"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary);
        cursor: pointer;
    }
    
    .radio-option label {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
        color: #fff;
        cursor: pointer;
        text-transform: none;
        letter-spacing: normal;
    }
    
    .image-preview {
        margin-top: 15px;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 15px;
        background: #06080b;
    }
    
    .image-preview img {
        max-width: 100%;
        max-height: 200px;
        border-radius: 6px;
        display: block;
    }
    
    .image-preview-empty {
        color: #94a3b8;
        font-size: 14px;
        text-align: center;
        padding: 40px 20px;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 12px;
        margin-top: 30px;
        padding-top: 30px;
        border-top: 1px solid #1e293b;
    }
    
    .btn {
        padding: 14px 28px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 700;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-save {
        background: var(--primary);
        color: #fff;
        flex: 1;
    }
    
    .btn-save:hover {
        background: #a78bfa;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(112, 0, 164, 0.3);
    }
    
    .btn-reset {
        background: transparent;
        color: #ef4444;
        border: 1px solid #ef4444;
    }
    
    .btn-reset:hover {
        background: rgba(239, 68, 68, 0.1);
    }
    
    .btn-primary {
        background: var(--primary);
        color: #fff;
    }
    
    .btn-primary:hover {
        background: #a78bfa;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(112, 0, 164, 0.3);
    }
    
    .btn-secondary {
        background: transparent;
        color: #94a3b8;
        border: 1px solid #1e293b;
    }
    
    .btn-secondary:hover {
        color: #fff;
        border-color: #94a3b8;
    }
    
    .btn-danger {
        background: transparent;
        color: #ef4444;
        border: 1px solid #ef4444;
        padding: 8px 16px;
        font-size: 13px;
    }
    
    .btn-danger:hover {
        background: rgba(239, 68, 68, 0.1);
    }
    
    /* Alert */
    .alert {
        padding: 14px 18px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
        display: none;
        align-items: center;
        gap: 10px;
    }
    
    .alert.show {
        display: flex;
    }
    
    .alert-success {
        background: rgba(34, 197, 94, 0.1);
        color: #22c55e;
        border: 1px solid #22c55e;
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid #ef4444;
    }
    
    /* Training Programs Table */
    .programs-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    
    .programs-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .programs-table thead {
        background: #06080b;
        border-bottom: 2px solid #1e293b;
    }
    
    .programs-table th {
        padding: 15px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .programs-table td {
        padding: 15px;
        border-bottom: 1px solid #1e293b;
        color: #fff;
        font-size: 14px;
    }
    
    .programs-table tbody tr:hover {
        background: rgba(112, 0, 164, 0.05);
    }
    
    .program-image-thumb {
        width: 60px;
        height: 40px;
        object-fit: cover;
        border-radius: 4px;
    }
    
    .program-tags {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    
    .program-tag {
        background: rgba(112, 0, 164, 0.1);
        color: var(--primary);
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .action-btns {
        display: flex;
        gap: 8px;
    }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .modal.show {
        display: flex;
    }
    
    .modal-content {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 12px;
        padding: 30px;
        max-width: 600px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    
    .modal-header h2 {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
    }
    
    .modal-close {
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.2s;
    }
    
    .modal-close:hover {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }
    
    @media (max-width: 1200px) {
        .theme-container {
            grid-template-columns: 1fr;
        }
        
        .preview-panel {
            position: relative;
            max-height: none;
        }
    }
    
    @media (max-width: 768px) {
        .tabs-container {
            overflow-x: scroll;
        }
        
        .color-input-group {
            grid-template-columns: 1fr;
        }
        
        .programs-table {
            display: block;
            overflow-x: auto;
        }
    }
</style>

<div class="theme-header">
    <h1><i class="fas fa-palette"></i> Theme Settings</h1>
    <p>Customize the look and feel of your application across all pages and features.</p>
</div>

<div id="alertContainer"></div>

<!-- Tab Navigation -->
<div class="tabs-container">
    <button class="tab-btn active" onclick="switchTab('colors')">
        <i class="fas fa-palette"></i> Colors
    </button>
    <button class="tab-btn" onclick="switchTab('branding')">
        <i class="fas fa-image"></i> Logo & Branding
    </button>
    <button class="tab-btn" onclick="switchTab('hero')">
        <i class="fas fa-photo-video"></i> Hero Section
    </button>
    <button class="tab-btn" onclick="switchTab('programs')">
        <i class="fas fa-graduation-cap"></i> Training Programs
    </button>
</div>

<!-- TAB 1: COLORS -->
<div id="tab-colors" class="tab-content active">
    <div class="theme-container">
        <div class="color-settings-panel">
            <form id="colorsForm">
                <?= csrfTokenInput() ?>
                
                <!-- Brand Colors -->
                <div class="settings-section">
                    <h3 class="section-title">
                        <i class="fas fa-paint-brush"></i>
                        Brand Colors
                    </h3>
                    <div class="color-input-group">
                        <div class="color-field">
                            <label>Primary Color</label>
                            <div class="color-picker-wrapper">
                                <div class="color-swatch" style="background: <?= htmlspecialchars($colors['primary_color']) ?>;" onclick="document.getElementById('primary_color_picker').click()"></div>
                                <input type="text" class="color-hex-input" name="primary_color" value="<?= htmlspecialchars($colors['primary_color']) ?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                                <input type="color" id="primary_color_picker" class="color-picker" value="<?= htmlspecialchars($colors['primary_color']) ?>">
                            </div>
                        </div>
                        
                        <div class="color-field">
                            <label>Secondary/Accent</label>
                            <div class="color-picker-wrapper">
                                <div class="color-swatch" style="background: <?= htmlspecialchars($colors['secondary_color']) ?>;" onclick="document.getElementById('secondary_color_picker').click()"></div>
                                <input type="text" class="color-hex-input" name="secondary_color" value="<?= htmlspecialchars($colors['secondary_color']) ?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                                <input type="color" id="secondary_color_picker" class="color-picker" value="<?= htmlspecialchars($colors['secondary_color']) ?>">
                            </div>
                        </div>
                        
                        <div class="color-field">
                            <label>Button Hover</label>
                            <div class="color-picker-wrapper">
                                <div class="color-swatch" style="background: <?= htmlspecialchars($colors['button_hover_color']) ?>;" onclick="document.getElementById('button_hover_color_picker').click()"></div>
                                <input type="text" class="color-hex-input" name="button_hover_color" value="<?= htmlspecialchars($colors['button_hover_color']) ?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                                <input type="color" id="button_hover_color_picker" class="color-picker" value="<?= htmlspecialchars($colors['button_hover_color']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Background Colors -->
                <div class="settings-section">
                    <h3 class="section-title">
                        <i class="fas fa-fill-drip"></i>
                        Background Colors
                    </h3>
                    <div class="color-input-group">
                        <div class="color-field">
                            <label>Main Background</label>
                            <div class="color-picker-wrapper">
                                <div class="color-swatch" style="background: <?= htmlspecialchars($colors['background_color']) ?>;" onclick="document.getElementById('background_color_picker').click()"></div>
                                <input type="text" class="color-hex-input" name="background_color" value="<?= htmlspecialchars($colors['background_color']) ?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                                <input type="color" id="background_color_picker" class="color-picker" value="<?= htmlspecialchars($colors['background_color']) ?>">
                            </div>
                        </div>
                        
                        <div class="color-field">
                            <label>Card Background</label>
                            <div class="color-picker-wrapper">
                                <div class="color-swatch" style="background: <?= htmlspecialchars($colors['card_background_color']) ?>;" onclick="document.getElementById('card_background_color_picker').click()"></div>
                                <input type="text" class="color-hex-input" name="card_background_color" value="<?= htmlspecialchars($colors['card_background_color']) ?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                                <input type="color" id="card_background_color_picker" class="color-picker" value="<?= htmlspecialchars($colors['card_background_color']) ?>">
                            </div>
                        </div>
                        
                        <div class="color-field">
                            <label>Sidebar Background</label>
                            <div class="color-picker-wrapper">
                                <div class="color-swatch" style="background: <?= htmlspecialchars($colors['sidebar_color']) ?>;" onclick="document.getElementById('sidebar_color_picker').click()"></div>
                                <input type="text" class="color-hex-input" name="sidebar_color" value="<?= htmlspecialchars($colors['sidebar_color']) ?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                                <input type="color" id="sidebar_color_picker" class="color-picker" value="<?= htmlspecialchars($colors['sidebar_color']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Text Colors -->
                <div class="settings-section">
                    <h3 class="section-title">
                        <i class="fas fa-font"></i>
                        Text Colors
                    </h3>
                    <div class="color-input-group">
                        <div class="color-field">
                            <label>Primary Text</label>
                            <div class="color-picker-wrapper">
                                <div class="color-swatch" style="background: <?= htmlspecialchars($colors['text_color']) ?>;" onclick="document.getElementById('text_color_picker').click()"></div>
                                <input type="text" class="color-hex-input" name="text_color" value="<?= htmlspecialchars($colors['text_color']) ?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                                <input type="color" id="text_color_picker" class="color-picker" value="<?= htmlspecialchars($colors['text_color']) ?>">
                            </div>
                        </div>
                        
                        <div class="color-field">
                            <label>Muted Text</label>
                            <div class="color-picker-wrapper">
                                <div class="color-swatch" style="background: <?= htmlspecialchars($colors['text_muted_color']) ?>;" onclick="document.getElementById('text_muted_color_picker').click()"></div>
                                <input type="text" class="color-hex-input" name="text_muted_color" value="<?= htmlspecialchars($colors['text_muted_color']) ?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                                <input type="color" id="text_muted_color_picker" class="color-picker" value="<?= htmlspecialchars($colors['text_muted_color']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- UI Colors -->
                <div class="settings-section">
                    <h3 class="section-title">
                        <i class="fas fa-border-style"></i>
                        UI Colors
                    </h3>
                    <div class="color-input-group">
                        <div class="color-field">
                            <label>Border Color</label>
                            <div class="color-picker-wrapper">
                                <div class="color-swatch" style="background: <?= htmlspecialchars($colors['border_color']) ?>;" onclick="document.getElementById('border_color_picker').click()"></div>
                                <input type="text" class="color-hex-input" name="border_color" value="<?= htmlspecialchars($colors['border_color']) ?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                                <input type="color" id="border_color_picker" class="color-picker" value="<?= htmlspecialchars($colors['border_color']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Status Colors -->
                <div class="settings-section">
                    <h3 class="section-title">
                        <i class="fas fa-check-circle"></i>
                        Status Colors
                    </h3>
                    <div class="color-input-group">
                        <div class="color-field">
                            <label>Success</label>
                            <div class="color-picker-wrapper">
                                <div class="color-swatch" style="background: <?= htmlspecialchars($colors['success_color']) ?>;" onclick="document.getElementById('success_color_picker').click()"></div>
                                <input type="text" class="color-hex-input" name="success_color" value="<?= htmlspecialchars($colors['success_color']) ?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                                <input type="color" id="success_color_picker" class="color-picker" value="<?= htmlspecialchars($colors['success_color']) ?>">
                            </div>
                        </div>
                        
                        <div class="color-field">
                            <label>Error</label>
                            <div class="color-picker-wrapper">
                                <div class="color-swatch" style="background: <?= htmlspecialchars($colors['error_color']) ?>;" onclick="document.getElementById('error_color_picker').click()"></div>
                                <input type="text" class="color-hex-input" name="error_color" value="<?= htmlspecialchars($colors['error_color']) ?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                                <input type="color" id="error_color_picker" class="color-picker" value="<?= htmlspecialchars($colors['error_color']) ?>">
                            </div>
                        </div>
                        
                        <div class="color-field">
                            <label>Warning</label>
                            <div class="color-picker-wrapper">
                                <div class="color-swatch" style="background: <?= htmlspecialchars($colors['warning_color']) ?>;" onclick="document.getElementById('warning_color_picker').click()"></div>
                                <input type="text" class="color-hex-input" name="warning_color" value="<?= htmlspecialchars($colors['warning_color']) ?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                                <input type="color" id="warning_color_picker" class="color-picker" value="<?= htmlspecialchars($colors['warning_color']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="btn btn-save">
                        <i class="fas fa-save"></i> Save Colors
                    </button>
                    <button type="button" class="btn btn-reset" onclick="resetTheme()">
                        <i class="fas fa-undo"></i> Reset to Defaults
                    </button>
                </div>
            </form>
        </div>
        
        <div class="preview-panel">
            <h3 class="preview-title">
                <i class="fas fa-eye"></i>
                Live Preview
            </h3>
            
            <div class="preview-container" id="previewContainer">
                <div class="preview-sidebar">
                    <div class="preview-nav-item active">
                        <i class="fas fa-home"></i> Home
                    </div>
                    <div class="preview-nav-item">
                        <i class="fas fa-chart-line"></i> Stats
                    </div>
                    <div class="preview-nav-item">
                        <i class="fas fa-calendar"></i> Schedule
                    </div>
                </div>
                
                <div class="preview-card">
                    <h3>Sample Card</h3>
                    <p>This preview shows how your theme colors will look across the application. Update any color to see changes instantly.</p>
                </div>
                
                <div class="preview-buttons">
                    <button class="preview-btn preview-btn-primary">Primary Button</button>
                    <button class="preview-btn preview-btn-secondary">Secondary Button</button>
                </div>
                
                <div class="preview-status">
                    <span class="status-badge status-success">Success</span>
                    <span class="status-badge status-error">Error</span>
                    <span class="status-badge status-warning">Warning</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TAB 2: LOGO & BRANDING -->
<div id="tab-branding" class="tab-content">
    <div class="full-width-panel">
        <form id="brandingForm">
            <?= csrfTokenInput() ?>
            
            <h3 class="section-title">
                <i class="fas fa-image"></i>
                Logo Settings
            </h3>
            
            <div class="form-group">
                <label>Upload Method</label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="logo_upload" name="logo_method" value="upload" checked>
                        <label for="logo_upload">Upload File</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="logo_url" name="logo_method" value="url">
                        <label for="logo_url">Enter URL</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group" id="logo_upload_field">
                <label>Logo File (PNG, max 2MB)</label>
                <input type="file" class="form-control" name="logo" accept=".png" onchange="previewImage(this, 'logo_preview')">
                <small style="color: #94a3b8; display: block; margin-top: 8px;">Recommended size: 200x60px</small>
            </div>
            
            <div class="form-group" id="logo_url_field" style="display: none;">
                <label>Logo URL</label>
                <input type="text" class="form-control" name="logo_url_input" placeholder="https://example.com/logo.png">
            </div>
            
            <div class="image-preview" id="logo_preview">
                <?php if ($logo_url): ?>
                    <img src="<?= htmlspecialchars($logo_url) ?>" alt="Current Logo">
                <?php else: ?>
                    <div class="image-preview-empty">
                        <i class="fas fa-image" style="font-size: 48px; margin-bottom: 10px; opacity: 0.3;"></i>
                        <p>No logo uploaded</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save"></i> Save Logo
                </button>
            </div>
        </form>
    </div>
</div>

<!-- TAB 3: HERO SECTION -->
<div id="tab-hero" class="tab-content">
    <div class="full-width-panel">
        <form id="heroForm">
            <?= csrfTokenInput() ?>
            
            <h3 class="section-title">
                <i class="fas fa-photo-video"></i>
                Hero Section Settings
            </h3>
            
            <div class="form-group">
                <label>Upload Method</label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="hero_upload" name="hero_method" value="upload" checked>
                        <label for="hero_upload">Upload File</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="hero_url" name="hero_method" value="url">
                        <label for="hero_url">Enter URL</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group" id="hero_upload_field">
                <label>Hero Image (PNG/JPG, max 5MB)</label>
                <input type="file" class="form-control" name="hero_image" accept=".png,.jpg,.jpeg" onchange="previewImage(this, 'hero_preview')">
                <small style="color: #94a3b8; display: block; margin-top: 8px;">Recommended size: 1920x600px</small>
            </div>
            
            <div class="form-group" id="hero_url_field" style="display: none;">
                <label>Hero Image URL</label>
                <input type="text" class="form-control" name="hero_url_input" placeholder="https://example.com/hero.jpg">
            </div>
            
            <div class="image-preview" id="hero_preview">
                <?php if ($hero_image_url): ?>
                    <img src="<?= htmlspecialchars($hero_image_url) ?>" alt="Current Hero Image">
                <?php else: ?>
                    <div class="image-preview-empty">
                        <i class="fas fa-image" style="font-size: 48px; margin-bottom: 10px; opacity: 0.3;"></i>
                        <p>No hero image uploaded</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Hero Title</label>
                <input type="text" class="form-control" name="hero_title" value="<?= htmlspecialchars($hero_title) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Hero Subtitle</label>
                <textarea class="form-control" name="hero_subtitle" required><?= htmlspecialchars($hero_subtitle) ?></textarea>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save"></i> Save Hero Section
                </button>
            </div>
        </form>
    </div>
</div>

<!-- TAB 4: TRAINING PROGRAMS -->
<div id="tab-programs" class="tab-content">
    <div class="full-width-panel">
        <div class="programs-header">
            <h3 class="section-title">
                <i class="fas fa-graduation-cap"></i>
                Training Programs
            </h3>
            <button type="button" class="btn btn-primary" onclick="openProgramModal()">
                <i class="fas fa-plus"></i> Add New Program
            </button>
        </div>
        
        <table class="programs-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Tags</th>
                    <th>Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="programsTableBody">
                <?php if (empty($training_programs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #94a3b8; padding: 40px;">
                            No training programs found. Add your first program to get started.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($training_programs as $program): ?>
                        <tr>
                            <td>
                                <?php if (!empty($program['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($program['image_url']) ?>" alt="" class="program-image-thumb">
                                <?php else: ?>
                                    <i class="fas fa-image" style="font-size: 24px; color: #1e293b;"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($program['title']) ?></td>
                            <td><?= htmlspecialchars(substr($program['description'], 0, 80)) ?><?= strlen($program['description']) > 80 ? '...' : '' ?></td>
                            <td>
                                <div class="program-tags">
                                    <?php 
                                    $tags = explode(',', $program['tags']);
                                    foreach ($tags as $tag): 
                                    ?>
                                        <span class="program-tag"><?= htmlspecialchars(trim($tag)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($program['display_order']) ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-secondary" onclick='editProgram(<?= json_encode($program) ?>)'>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger" onclick="deleteProgram(<?= $program['id'] ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Program Modal -->
<div id="programModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add Training Program</h2>
            <button class="modal-close" onclick="closeProgramModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="programForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="program_id" id="program_id">
            
            <div class="form-group">
                <label>Upload Method</label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="program_upload" name="program_method" value="upload" checked>
                        <label for="program_upload">Upload File</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="program_url" name="program_method" value="url">
                        <label for="program_url">Enter URL</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group" id="program_upload_field">
                <label>Program Image (PNG/JPG, max 5MB)</label>
                <input type="file" class="form-control" name="program_image" accept=".png,.jpg,.jpeg" onchange="previewImage(this, 'program_preview')">
            </div>
            
            <div class="form-group" id="program_url_field" style="display: none;">
                <label>Program Image URL</label>
                <input type="text" class="form-control" name="program_url_input" placeholder="https://example.com/program.jpg">
            </div>
            
            <div class="image-preview" id="program_preview">
                <div class="image-preview-empty">
                    <i class="fas fa-image" style="font-size: 48px; margin-bottom: 10px; opacity: 0.3;"></i>
                    <p>No image selected</p>
                </div>
            </div>
            
            <div class="form-group">
                <label>Program Title</label>
                <input type="text" class="form-control" name="program_title" id="program_title" required>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea class="form-control" name="program_description" id="program_description" required></textarea>
            </div>
            
            <div class="form-group">
                <label>Tags (comma-separated)</label>
                <input type="text" class="form-control" name="program_tags" id="program_tags" placeholder="skating, shooting, conditioning">
            </div>
            
            <div class="form-group">
                <label>Display Order</label>
                <input type="number" class="form-control" name="program_order" id="program_order" value="0" min="0">
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save"></i> Save Program
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeProgramModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Tab switching
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // Mark button as active
    event.target.closest('.tab-btn').classList.add('active');
}

// Color picker sync
document.querySelectorAll('.color-picker').forEach(picker => {
    const name = picker.id.replace('_picker', '');
    const textInput = document.querySelector(`input[name="${name}"]`);
    const swatch = picker.previousElementSibling.previousElementSibling;
    
    picker.addEventListener('input', function() {
        textInput.value = this.value;
        swatch.style.background = this.value;
        updatePreview();
    });
    
    textInput.addEventListener('input', function() {
        if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
            picker.value = this.value;
            swatch.style.background = this.value;
            updatePreview();
        }
    });
});

// Update live preview
function updatePreview() {
    const formData = new FormData(document.getElementById('colorsForm'));
    const container = document.getElementById('previewContainer');
    
    container.style.setProperty('--primary', formData.get('primary_color'));
    container.style.setProperty('--bg', formData.get('background_color'));
    container.style.setProperty('--card-bg', formData.get('card_background_color'));
    container.style.setProperty('--sidebar', formData.get('sidebar_color'));
    container.style.setProperty('--text-color', formData.get('text_color'));
    container.style.setProperty('--text-muted', formData.get('text_muted_color'));
    container.style.setProperty('--border', formData.get('border_color'));
    container.style.setProperty('--button-hover', formData.get('button_hover_color'));
    container.style.setProperty('--success', formData.get('success_color'));
    container.style.setProperty('--error', formData.get('error_color'));
    container.style.setProperty('--warning', formData.get('warning_color'));
}

// Save colors
document.getElementById('colorsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'save');
    
    try {
        const response = await fetch('process_theme_settings.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('error', data.message);
        }
    } catch (error) {
        showAlert('error', 'An error occurred while saving colors.');
    }
});

// Reset theme
async function resetTheme() {
    if (!confirm('Are you sure you want to reset all colors to their default values?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'reset');
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    try {
        const response = await fetch('process_theme_settings.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('error', data.message);
        }
    } catch (error) {
        showAlert('error', 'An error occurred while resetting the theme.');
    }
}

// Radio button handlers
document.querySelectorAll('input[name="logo_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('logo_upload_field').style.display = this.value === 'upload' ? 'block' : 'none';
        document.getElementById('logo_url_field').style.display = this.value === 'url' ? 'block' : 'none';
    });
});

document.querySelectorAll('input[name="hero_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('hero_upload_field').style.display = this.value === 'upload' ? 'block' : 'none';
        document.getElementById('hero_url_field').style.display = this.value === 'url' ? 'block' : 'none';
    });
});

document.querySelectorAll('input[name="program_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('program_upload_field').style.display = this.value === 'upload' ? 'block' : 'none';
        document.getElementById('program_url_field').style.display = this.value === 'url' ? 'block' : 'none';
    });
});

// Image preview
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Save branding
document.getElementById('brandingForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'save_logo');
    
    try {
        const response = await fetch('process_theme_settings.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('error', data.message);
        }
    } catch (error) {
        showAlert('error', 'An error occurred while saving branding.');
    }
});

// Save hero section
document.getElementById('heroForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'save_hero');
    
    try {
        const response = await fetch('process_theme_settings.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('error', data.message);
        }
    } catch (error) {
        showAlert('error', 'An error occurred while saving hero section.');
    }
});

// Program modal
function openProgramModal(program = null) {
    document.getElementById('modalTitle').textContent = program ? 'Edit Training Program' : 'Add Training Program';
    document.getElementById('programForm').reset();
    
    if (program) {
        document.getElementById('program_id').value = program.id;
        document.getElementById('program_title').value = program.title;
        document.getElementById('program_description').value = program.description;
        document.getElementById('program_tags').value = program.tags;
        document.getElementById('program_order').value = program.display_order;
        
        if (program.image_url) {
            document.getElementById('program_preview').innerHTML = `<img src="${program.image_url}" alt="Preview">`;
        }
    } else {
        document.getElementById('program_id').value = '';
    }
    
    document.getElementById('programModal').classList.add('show');
}

function closeProgramModal() {
    document.getElementById('programModal').classList.remove('show');
}

function editProgram(program) {
    openProgramModal(program);
}

// Save program
document.getElementById('programForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'save_program');
    
    try {
        const response = await fetch('process_theme_settings.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('success', data.message);
            closeProgramModal();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('error', data.message);
        }
    } catch (error) {
        showAlert('error', 'An error occurred while saving program.');
    }
});

// Delete program
async function deleteProgram(programId) {
    if (!confirm('Are you sure you want to delete this training program?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_program');
    formData.append('program_id', programId);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    try {
        const response = await fetch('process_theme_settings.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('error', data.message);
        }
    } catch (error) {
        showAlert('error', 'An error occurred while deleting program.');
    }
}

// Show alert
function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} show`;
    alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    container.appendChild(alert);
    
    setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}

// Close modal on outside click
document.getElementById('programModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeProgramModal();
    }
});

// Initialize preview
updatePreview();
</script>
