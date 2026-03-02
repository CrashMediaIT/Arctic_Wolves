<!-- Admin System Tools View -->
<?php
$activeTab = $_GET['tab'] ?? 'settings';
require_once __DIR__ . '/../lib/image_helper.php';

// Fetch system settings from database
try {
    $settings_query = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = [];
    while ($row = $settings_query->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Set defaults for missing settings
    $defaults = [
        'site_title' => 'Arctic Wolves',
        'site_email' => 'info@arcticwolves.ca',
        'contact_phone' => '',
        'org_address' => '',
        'timezone' => 'America/New_York',
        'date_format' => 'MM/DD/YYYY',
        'session_duration' => 60,
        'notifications_enabled' => '1',
        'maintenance_mode' => '0',
        'province' => 'ON',
        'booking_window_days' => '30',
        'cancellation_window_hours' => '24',
        'auto_confirm_bookings' => '1',
        'send_booking_confirmations' => '1',
        'mileage_rate_per_km' => '0.70',
        'mileage_rate_after_5000_per_km' => '0.64',
        'mileage_rate_per_mile' => '1.10',
        'mileage_unit' => 'km',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_from_name' => 'Arctic Wolves'
    ];
    
    foreach ($defaults as $key => $value) {
        if (!isset($settings[$key])) {
            $settings[$key] = $value;
        }
    }
    
    // Decrypt encrypted credential settings so they display correctly in form fields
    // and don't get double-encrypted on re-save
    $encrypted_setting_keys = getEncryptedSettingKeys();
    foreach ($encrypted_setting_keys as $esk) {
        if (!empty($settings[$esk])) {
            $settings[$esk] = decryptCredential($settings[$esk]);
        }
    }
} catch (PDOException $e) {
    error_log("Settings fetch error: " . $e->getMessage());
    $settings = [];
}

// Fetch theme settings from theme_settings table
$theme_settings = [];
try {
    $theme_stmt = $pdo->prepare("SELECT setting_name, setting_value FROM theme_settings");
    $theme_stmt->execute();
    while ($row = $theme_stmt->fetch(PDO::FETCH_ASSOC)) {
        $theme_settings[$row['setting_name']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Theme settings fetch error: " . $e->getMessage());
}

// Theme defaults
$theme_defaults = [
    'primary_color' => '#6B46C1',
    'secondary_color' => '#c0c0c0',
    'background_color' => '#06080b',
    'logo_url' => '',
    'logo_method' => 'url',
    'use_logo_as_favicon' => '0',
    'center_ice_logo_url' => '',
    'center_ice_logo_method' => 'upload',
    'business_card_front_bg_url' => '',
    'business_card_back_bg_url' => ''
];
foreach ($theme_defaults as $key => $value) {
    if (!isset($theme_settings[$key])) {
        $theme_settings[$key] = $value;
    }
}

// Helper for favicon checkbox
$is_favicon_enabled = !empty($theme_settings['use_logo_as_favicon']) && $theme_settings['use_logo_as_favicon'] !== '0';

// Resolve RustFS URLs through the media proxy so the browser can load them
$url_keys = ['logo_url', 'center_ice_logo_url', 'business_card_front_bg_url', 'business_card_back_bg_url'];
foreach ($url_keys as $uk) {
    if (!empty($theme_settings[$uk])) {
        $theme_settings[$uk] = resolveRustfsUrl($pdo, $theme_settings[$uk]);
    }
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-cog"></i> System Tools</h1>
        <p class="page-description">Configure system settings, integrations, database, and maintenance</p>
    </div>
</div>

<!-- System Tools Tabs - Primary Row -->
<div class="page-tabs" style="flex-wrap: wrap;">
    <a href="?page=system_tools&tab=settings" class="page-tab <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
        <i class="fas fa-sliders-h"></i> Settings
    </a>
    <a href="?page=system_tools&tab=mileage" class="page-tab <?php echo $activeTab === 'mileage' ? 'active' : ''; ?>">
        <i class="fas fa-car"></i> Mileage
    </a>
    <a href="?page=system_tools&tab=smtp" class="page-tab <?php echo $activeTab === 'smtp' ? 'active' : ''; ?>">
        <i class="fas fa-envelope"></i> SMTP
    </a>
    <a href="?page=system_tools&tab=rustfs" class="page-tab <?php echo $activeTab === 'rustfs' ? 'active' : ''; ?>">
        <i class="fas fa-box-open"></i> RustFS Storage
    </a>
    <a href="?page=system_tools&tab=docuseal" class="page-tab <?php echo $activeTab === 'docuseal' ? 'active' : ''; ?>">
        <i class="fas fa-file-signature"></i> DocuSeal
    </a>
    <a href="?page=system_tools&tab=payments" class="page-tab <?php echo $activeTab === 'payments' ? 'active' : ''; ?>">
        <i class="fas fa-credit-card"></i> Payments
    </a>
    <a href="?page=system_tools&tab=theme" class="page-tab <?php echo $activeTab === 'theme' ? 'active' : ''; ?>">
        <i class="fas fa-palette"></i> Theme
    </a>
    <a href="?page=system_tools&tab=stallion" class="page-tab <?php echo $activeTab === 'stallion' ? 'active' : ''; ?>">
        <i class="fas fa-shipping-fast"></i> Stallion Express
    </a>
    <a href="?page=system_tools&tab=paperless" class="page-tab <?php echo $activeTab === 'paperless' ? 'active' : ''; ?>">
        <i class="fas fa-file-invoice"></i> Paperless-NGX
    </a>
    <a href="?page=system_tools&tab=database" class="page-tab <?php echo $activeTab === 'database' ? 'active' : ''; ?>">
        <i class="fas fa-database"></i> Database
    </a>
</div>

<!-- System Tools Tabs - Secondary Row (System Status) -->
<div class="page-tabs page-tabs-secondary" style="flex-wrap: wrap; margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border);">
    <a href="?page=system_tools&tab=encryption" class="page-tab <?php echo $activeTab === 'encryption' ? 'active' : ''; ?>">
        <i class="fas fa-lock"></i> Encryption
    </a>
    <a href="?page=system_tools&tab=landing" class="page-tab <?php echo $activeTab === 'landing' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i> Landing Page
    </a>
    <a href="system_health_validator.php" class="page-tab">
        <i class="fas fa-heartbeat"></i> Health
    </a>
    <a href="?page=system_tools&tab=updates" class="page-tab <?php echo $activeTab === 'updates' ? 'active' : ''; ?>">
        <i class="fas fa-download"></i> Updates
    </a>
    <a href="?page=system_tools&tab=api_keys" class="page-tab <?php echo $activeTab === 'api_keys' ? 'active' : ''; ?>">
        <i class="fas fa-key"></i> API Keys
    </a>
    <a href="?page=system_tools&tab=ndi_cameras" class="page-tab <?php echo $activeTab === 'ndi_cameras' ? 'active' : ''; ?>">
        <i class="fas fa-video"></i> NDI Cameras
    </a>
    <a href="?page=system_tools&tab=gameplan" class="page-tab <?php echo $activeTab === 'gameplan' ? 'active' : ''; ?>">
        <i class="fas fa-chess-board"></i> Game Plan
    </a>
</div>

<div class="page-tab-content">
    <!-- Success/Error Messages -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="margin-bottom: 24px;">
        <i class="fas fa-check-circle"></i>
        <span>Settings saved successfully!</span>
        <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error" style="margin-bottom: 24px;">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($_GET['error']); ?></span>
        <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Settings Tab -->
    <div class="tab-content <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" id="settings-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-sliders-h"></i> System Settings</h3>
            </div>
            <div class="card-body">
                <form id="settings-form" method="POST" action="process_settings.php" data-form-type="settings">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_settings">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Site Title</h4>
                                <p>The name of your site</p>
                            </div>
                            <input type="text" name="site_title" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['site_title'] ?? 'Arctic Wolves'); ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Site Email</h4>
                                <p>Primary contact email</p>
                            </div>
                            <input type="email" name="site_email" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['site_email'] ?? 'info@arcticwolves.ca'); ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Session Duration</h4>
                                <p>Default session length in minutes</p>
                            </div>
                            <input type="number" name="session_duration" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['session_duration'] ?? 60); ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Enable Notifications</h4>
                                <p>Send email notifications to users</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="notifications_enabled" 
                                       <?php echo !empty($settings['notifications_enabled']) ? 'checked' : ''; ?>
                                       data-action="toggle-setting">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Maintenance Mode</h4>
                                <p>Put site in maintenance mode</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="maintenance_mode" 
                                       <?php echo !empty($settings['maintenance_mode']) ? 'checked' : ''; ?>
                                       data-action="toggle-setting">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Province</h4>
                                <p>Set your province to ensure correct tax rates are applied</p>
                            </div>
                            <select name="province" class="form-input" style="width: auto; min-width: 200px;">
                                <?php
                                $provinces = [
                                    'AB' => 'Alberta',
                                    'BC' => 'British Columbia',
                                    'MB' => 'Manitoba',
                                    'NB' => 'New Brunswick',
                                    'NL' => 'Newfoundland and Labrador',
                                    'NS' => 'Nova Scotia',
                                    'NT' => 'Northwest Territories',
                                    'NU' => 'Nunavut',
                                    'ON' => 'Ontario',
                                    'PE' => 'Prince Edward Island',
                                    'QC' => 'Quebec',
                                    'SK' => 'Saskatchewan',
                                    'YT' => 'Yukon'
                                ];
                                $selected_province = $settings['province'] ?? 'ON';
                                foreach ($provinces as $code => $name):
                                ?>
                                <option value="<?php echo $code; ?>" <?php echo $selected_province === $code ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Contact Phone</h4>
                                <p>Organization phone number</p>
                            </div>
                            <input type="tel" name="contact_phone" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>"
                                   placeholder="(555) 123-4567">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Organization Address</h4>
                                <p>Full mailing address</p>
                            </div>
                            <input type="text" name="org_address" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['org_address'] ?? ''); ?>"
                                   placeholder="123 Main St, City, Province">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Timezone</h4>
                                <p>Default timezone for scheduling</p>
                            </div>
                            <select name="timezone" class="form-input" style="width: auto; min-width: 200px;">
                                <?php
                                $timezones = [
                                    'America/St_Johns' => 'Newfoundland (NST)',
                                    'America/Halifax' => 'Atlantic (AST)',
                                    'America/New_York' => 'Eastern (EST)',
                                    'America/Chicago' => 'Central (CST)',
                                    'America/Denver' => 'Mountain (MST)',
                                    'America/Los_Angeles' => 'Pacific (PST)'
                                ];
                                $selected_tz = $settings['timezone'] ?? 'America/New_York';
                                foreach ($timezones as $tz_val => $tz_label):
                                ?>
                                <option value="<?php echo $tz_val; ?>" <?php echo $selected_tz === $tz_val ? 'selected' : ''; ?>><?php echo $tz_label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Date Format</h4>
                                <p>How dates are displayed throughout the system</p>
                            </div>
                            <select name="date_format" class="form-input" style="width: auto; min-width: 200px;">
                                <?php
                                $formats = ['MM/DD/YYYY', 'DD/MM/YYYY', 'YYYY-MM-DD'];
                                $selected_fmt = $settings['date_format'] ?? 'MM/DD/YYYY';
                                foreach ($formats as $fmt):
                                ?>
                                <option value="<?php echo $fmt; ?>" <?php echo $selected_fmt === $fmt ? 'selected' : ''; ?>><?php echo $fmt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Booking Window (days)</h4>
                                <p>How far in advance clients can book sessions</p>
                            </div>
                            <input type="number" name="booking_window_days" class="form-input" min="1"
                                   value="<?php echo htmlspecialchars($settings['booking_window_days'] ?? '30'); ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Cancellation Window (hours)</h4>
                                <p>Minimum notice required for cancellation</p>
                            </div>
                            <input type="number" name="cancellation_window_hours" class="form-input" min="1"
                                   value="<?php echo htmlspecialchars($settings['cancellation_window_hours'] ?? '24'); ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Auto-Confirm Bookings</h4>
                                <p>Automatically confirm session bookings without manual approval</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="auto_confirm_bookings" 
                                       <?php echo !empty($settings['auto_confirm_bookings']) ? 'checked' : ''; ?>
                                       data-action="toggle-setting">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Send Booking Confirmations</h4>
                                <p>Send email confirmations when sessions are booked</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="send_booking_confirmations" 
                                       <?php echo !empty($settings['send_booking_confirmations']) ? 'checked' : ''; ?>
                                       data-action="toggle-setting">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mileage Rates Tab -->
    <div class="tab-content <?php echo $activeTab === 'mileage' ? 'active' : ''; ?>" id="mileage-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-car"></i> Mileage Reimbursement Rates</h3>
            </div>
            <div class="card-body">
                <form id="mileage-rates-form" method="POST" action="process_settings.php" data-form-type="mileage">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_mileage_rates">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Default Mileage Unit</h4>
                                <p>Select the default unit for displaying distances in travel logs</p>
                            </div>
                            <select name="mileage_unit" class="form-input" style="width: auto; min-width: 200px;">
                                <option value="km" <?php echo ($settings['mileage_unit'] ?? 'km') === 'km' ? 'selected' : ''; ?>>Kilometers (km)</option>
                                <option value="miles" <?php echo ($settings['mileage_unit'] ?? 'km') === 'miles' ? 'selected' : ''; ?>>Miles (mi)</option>
                            </select>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Rate per Kilometer (First 5,000 km)</h4>
                                <p>CRA reimbursement rate for the first 5,000 km driven per year (CAD)</p>
                            </div>
                            <div class="rate-input-group">
                                <span class="currency-symbol">$</span>
                                <input type="number" name="mileage_rate_per_km" class="form-input" step="0.01" min="0"
                                       value="<?php echo htmlspecialchars($settings['mileage_rate_per_km'] ?? '0.70'); ?>"
                                       placeholder="0.70">
                                <span class="rate-unit">/km</span>
                            </div>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Rate per Kilometer (After 5,000 km)</h4>
                                <p>CRA reimbursement rate for every km after the first 5,000 km per year (CAD)</p>
                            </div>
                            <div class="rate-input-group">
                                <span class="currency-symbol">$</span>
                                <input type="number" name="mileage_rate_after_5000_per_km" class="form-input" step="0.01" min="0"
                                       value="<?php echo htmlspecialchars($settings['mileage_rate_after_5000_per_km'] ?? '0.64'); ?>"
                                       placeholder="0.64">
                                <span class="rate-unit">/km</span>
                            </div>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Rate per Mile</h4>
                                <p>Reimbursement rate for travel in miles (CAD)</p>
                            </div>
                            <div class="rate-input-group">
                                <span class="currency-symbol">$</span>
                                <input type="number" name="mileage_rate_per_mile" class="form-input" step="0.01" min="0"
                                       value="<?php echo htmlspecialchars($settings['mileage_rate_per_mile'] ?? '1.10'); ?>"
                                       placeholder="1.10">
                                <span class="rate-unit">/mi</span>
                            </div>
                        </div>
                    </div>
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <p>These rates follow CRA (Canada Revenue Agency) guidelines. The standard CRA rate for 2024 is $0.70/km for the first 5,000 km and $0.64/km thereafter. Rates can be verified at <a href="https://www.canada.ca/en/revenue-agency/services/tax/businesses/topics/payroll/benefits-allowances/automobile/automobile-motor-vehicle-allowances/automobile-allowance-rates.html" target="_blank" style="color: var(--primary-light);">CRA Automobile Allowance Rates</a>.</p>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save Mileage Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Google Maps API Configuration -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h3><i class="fas fa-map-marker-alt"></i> Google Maps API</h3>
            </div>
            <div class="card-body">
                <form id="google-maps-form" method="POST" action="process_settings.php" data-form-type="google-maps">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_google_maps">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Google Maps API Key</h4>
                                <p>Used for location autocomplete and distance calculations in travel logs</p>
                            </div>
                            <input type="text" name="google_maps_api_key" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['google_maps_api_key'] ?? ''); ?>"
                                   placeholder="Enter your Google Maps API key" style="min-width: 300px;">
                        </div>
                    </div>
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <p>To obtain a Google Maps API key, visit the <a href="https://console.cloud.google.com/google/maps-apis" target="_blank" style="color: #8B5CF6;">Google Cloud Console</a>. Enable the Maps JavaScript API and Places API for location autocomplete and distance calculations.</p>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="testGoogleMapsAPI()">
                            <i class="fas fa-vial"></i> Test API Key
                        </button>
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save API Key
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SMTP Settings Tab -->
    <div class="tab-content <?php echo $activeTab === 'smtp' ? 'active' : ''; ?>" id="smtp-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-envelope"></i> SMTP Email Settings</h3>
            </div>
            <div class="card-body">
                <form id="smtp-form" method="POST" action="process_settings.php" data-form-type="smtp">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_smtp">
                    <input type="hidden" name="redirect_page" value="system_tools">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>SMTP Host</h4>
                                <p>Mail server hostname (e.g., smtp.gmail.com)</p>
                            </div>
                            <input type="text" name="smtp_host" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"
                                   placeholder="smtp.example.com">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>SMTP Port</h4>
                                <p>Port number (typically 587 for TLS, 465 for SSL)</p>
                            </div>
                            <input type="number" name="smtp_port" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>"
                                   placeholder="587">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>SMTP Username</h4>
                                <p>Email account username or address</p>
                            </div>
                            <input type="text" name="smtp_user" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>"
                                   placeholder="user@example.com">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>SMTP Password</h4>
                                <p>Email account password or app password<?php echo !empty($settings['smtp_pass']) ? ' (currently set)' : ''; ?></p>
                            </div>
                            <input type="password" name="smtp_pass" class="form-input" 
                                   placeholder="<?php echo !empty($settings['smtp_pass']) ? 'Leave blank to keep current password' : 'Enter password'; ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Encryption</h4>
                                <p>Connection security protocol</p>
                            </div>
                            <select name="smtp_encryption" class="form-input">
                                <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>From Name</h4>
                                <p>Sender name displayed in emails</p>
                            </div>
                            <input type="text" name="smtp_from_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? 'Arctic Wolves'); ?>"
                                   placeholder="Arctic Wolves">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>From Email</h4>
                                <p>Sender email address</p>
                            </div>
                            <input type="email" name="smtp_from_email" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>"
                                   placeholder="noreply@example.com">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="testSmtpConnection()">
                            <i class="fas fa-vial"></i> Test Connection
                        </button>
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save SMTP Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- RustFS S3 Storage Tab -->
    <div class="tab-content <?php echo $activeTab === 'rustfs' ? 'active' : ''; ?>" id="rustfs-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-box-open"></i> RustFS S3 Storage</h3>
                <span class="badge badge-primary">Object Storage</span>
            </div>
            <div class="card-body">
                <div class="integration-status <?php echo !empty($settings['rustfs_endpoint']) ? 'connected' : 'disconnected'; ?>">
                    <div class="status-icon">
                        <i class="fas <?php echo !empty($settings['rustfs_endpoint']) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    </div>
                    <div class="status-info">
                        <h4><?php echo !empty($settings['rustfs_endpoint']) ? 'RustFS Configured' : 'Not Configured'; ?></h4>
                        <p><?php echo !empty($settings['rustfs_endpoint']) ? htmlspecialchars($settings['rustfs_endpoint']) : 'Configure RustFS S3 settings for persistent file storage'; ?></p>
                    </div>
                </div>

                <div class="info-box" style="margin-bottom: 24px;">
                    <div class="info-box-content">
                        <p><strong>About RustFS S3 Storage:</strong></p>
                        <p>RustFS is an S3-compatible object storage server. All uploaded files (images, videos, documents) are stored directly in RustFS. No files are saved locally on the web server.</p>
                        <p style="color: var(--text-dim);">Ensure your RustFS server is running and the bucket exists before saving settings.</p>
                    </div>
                </div>

                <form id="rustfs-form" method="POST" action="process_settings.php" data-form-type="rustfs">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_rustfs">
                    <input type="hidden" name="redirect_page" value="system_tools">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>RustFS Endpoint URL</h4>
                                <p>Your RustFS server address (e.g., https://rustfs.example.com). SSL on port 443 is recommended with HAProxy.</p>
                            </div>
                            <input type="text" name="rustfs_endpoint" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['rustfs_endpoint'] ?? ''); ?>"
                                   placeholder="https://rustfs.example.com">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Public Endpoint URL</h4>
                                <p>Browser-accessible URL for direct uploads via reverse proxy (e.g., https://tnode1.example.com). If empty, the internal endpoint above is used.</p>
                            </div>
                            <input type="text" name="rustfs_public_endpoint" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['rustfs_public_endpoint'] ?? ''); ?>"
                                   placeholder="https://tnode1.example.com">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Access Key</h4>
                                <p>S3-compatible access key ID</p>
                            </div>
                            <input type="text" name="rustfs_access_key" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['rustfs_access_key'] ?? ''); ?>"
                                   placeholder="Enter access key">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Secret Key</h4>
                                <p>S3-compatible secret key<?php echo !empty($settings['rustfs_secret_key']) ? ' (currently set)' : ''; ?></p>
                            </div>
                            <input type="password" name="rustfs_secret_key" class="form-input" 
                                   placeholder="<?php echo !empty($settings['rustfs_secret_key']) ? 'Leave blank to keep current key' : 'Enter secret key'; ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Bucket Name</h4>
                                <p>The S3 bucket where all files will be stored</p>
                            </div>
                            <input type="text" name="rustfs_bucket" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['rustfs_bucket'] ?? ''); ?>"
                                   placeholder="arctic-wolves">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Region</h4>
                                <p>S3 region (default: us-east-1)</p>
                            </div>
                            <input type="text" name="rustfs_region" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['rustfs_region'] ?? 'us-east-1'); ?>"
                                   placeholder="us-east-1">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Use SSL</h4>
                                <p>Enable HTTPS for RustFS connections</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="rustfs_use_ssl" 
                                       <?php echo ($settings['rustfs_use_ssl'] ?? '1') === '1' ? 'checked' : ''; ?>
                                       value="1">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Path Style Access</h4>
                                <p>Use path-style URLs (recommended for self-hosted RustFS). Disable for virtual-hosted style.</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="rustfs_path_style" 
                                       <?php echo ($settings['rustfs_path_style'] ?? '1') === '1' ? 'checked' : ''; ?>
                                       value="1">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 24px;">
                        <button type="button" class="btn btn-secondary" onclick="testRustFSConnection()">
                            <i class="fas fa-plug"></i> Test Connection
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save RustFS Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Paperless-NGX Tab -->
    <div class="tab-content <?php echo $activeTab === 'paperless' ? 'active' : ''; ?>" id="paperless-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-file-invoice"></i> Paperless-NGX OCR Integration</h3>
                <span class="badge <?php echo !empty($settings['paperless_url']) ? 'badge-primary' : 'badge-secondary'; ?>">
                    <?php echo !empty($settings['paperless_url']) ? 'Configured' : 'Not Configured'; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="integration-status <?php echo !empty($settings['paperless_url']) ? 'connected' : 'disconnected'; ?>">
                    <div class="status-icon">
                        <i class="fas <?php echo !empty($settings['paperless_url']) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    </div>
                    <div class="status-info">
                        <h4><?php echo !empty($settings['paperless_url']) ? 'Paperless-NGX Configured' : 'Not Connected'; ?></h4>
                        <p><?php echo !empty($settings['paperless_url']) ? htmlspecialchars($settings['paperless_url']) . ' — use Test Connection to verify' : 'Configure Paperless-NGX to enable OCR processing'; ?></p>
                    </div>
                </div>

                <form id="paperless-form" method="POST" action="process_settings.php" data-form-type="paperless">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_paperless">
                    <input type="hidden" name="redirect_page" value="system_tools">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Paperless-NGX URL</h4>
                                <p>Your Paperless-NGX server address (e.g. https://paperless.arcticwolves.ca)</p>
                            </div>
                            <input type="url" name="paperless_url" class="form-input"
                                   value="<?php echo htmlspecialchars($settings['paperless_url'] ?? ''); ?>"
                                   placeholder="https://paperless.arcticwolves.ca">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>API Token</h4>
                                <p>API authentication token from Paperless-NGX (Settings &rarr; API Tokens)<?php echo !empty($settings['paperless_api_token']) ? ' (currently set)' : ''; ?></p>
                            </div>
                            <input type="password" name="paperless_api_token" class="form-input"
                                   placeholder="<?php echo !empty($settings['paperless_api_token']) ? 'Leave blank to keep current token' : 'Enter API token'; ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Use for OCR</h4>
                                <p>Enable Paperless-NGX for receipt OCR processing</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="paperless_ocr_enabled"
                                       <?php echo !empty($settings['paperless_ocr_enabled']) ? 'checked' : ''; ?>
                                       data-action="toggle-setting">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Default Correspondent</h4>
                                <p>Paperless-NGX correspondent name for uploaded documents (optional)</p>
                            </div>
                            <input type="text" name="paperless_correspondent" class="form-input"
                                   value="<?php echo htmlspecialchars($settings['paperless_correspondent'] ?? ''); ?>"
                                   placeholder="Arctic Wolves">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Default Document Type</h4>
                                <p>Paperless-NGX document type for uploaded receipts (optional)</p>
                            </div>
                            <input type="text" name="paperless_document_type" class="form-input"
                                   value="<?php echo htmlspecialchars($settings['paperless_document_type'] ?? ''); ?>"
                                   placeholder="Receipt">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="testPaperlessConnection()">
                            <i class="fas fa-vial"></i> Test Connection
                        </button>
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- DocuSeal Tab -->
    <div class="tab-content <?php echo $activeTab === 'docuseal' ? 'active' : ''; ?>" id="docuseal-tab">
        <form id="docuseal-form" method="POST" action="process_settings.php" data-form-type="docuseal">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_docuseal">
            <input type="hidden" name="redirect_page" value="system_tools">
            
            <!-- DocuSeal Setup Guide Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-book"></i> DocuSeal Setup Guide</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info" style="margin-bottom: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <span>DocuSeal is an open-source document e-signature platform. Follow these steps to set up DocuSeal for employee contract workflows.</span>
                    </div>
                    
                    <div class="setup-instructions" style="background: var(--bg-main); border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid var(--border);">
                        <h4 style="color: var(--text-primary); margin-bottom: 15px;"><i class="fas fa-server" style="color: var(--primary-light); margin-right: 8px;"></i> Option 1: Self-Hosted Installation (Recommended)</h4>
                        <ol style="color: var(--text-secondary); line-height: 1.8; padding-left: 20px;">
                            <li><strong>Install Docker</strong> on your server if not already installed. Visit <a href="https://docs.docker.com/get-docker/" target="_blank" style="color: var(--primary-light);">Docker's official documentation</a> for platform-specific installation instructions.</li>
                            <li><strong>Run DocuSeal container:</strong>
                                <code style="display: block; background: var(--bg-card); padding: 10px; border-radius: 4px; margin: 8px 0; font-size: 12px; overflow-x: auto; border: 1px solid var(--border);">docker run -d --name docuseal -p 3000:3000 -v docuseal_data:/data docuseal/docuseal</code>
                            </li>
                            <li><strong>Access DocuSeal</strong> at <code style="background: var(--bg-card); padding: 4px 8px; border-radius: 4px;">http://your-server-ip:3000</code> and create your admin account</li>
                            <li><strong>Generate an API key:</strong> Go to Settings → API → Create new API key</li>
                            <li><strong>Set up webhook URL:</strong> In DocuSeal settings, add webhook URL: <code style="background: var(--bg-card); padding: 4px 8px; border-radius: 4px;"><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'your-domain.com'); ?>/webhook_docuseal.php</code></li>
                            <li><strong>Create contract templates</strong> in DocuSeal with signature fields</li>
                        </ol>
                        
                        <h4 style="color: var(--text-primary); margin: 20px 0 15px 0;"><i class="fas fa-cloud" style="color: var(--primary-light); margin-right: 8px;"></i> Option 2: DocuSeal Cloud</h4>
                        <ol style="color: var(--text-secondary); line-height: 1.8; padding-left: 20px;">
                            <li>Sign up at <a href="https://docuseal.co" target="_blank" style="color: var(--primary-light);">docuseal.co</a></li>
                            <li>Navigate to Settings → API and generate an API key</li>
                            <li>Use <code style="background: var(--bg-card); padding: 4px 8px; border-radius: 4px;">https://api.docuseal.co</code> as your DocuSeal URL</li>
                            <li>Configure webhook URL in your DocuSeal account settings</li>
                        </ol>
                        
                        <h4 style="color: var(--text-primary); margin: 20px 0 15px 0;"><i class="fas fa-cog" style="color: var(--primary-light); margin-right: 8px;"></i> Template Setup</h4>
                        <ol style="color: var(--text-secondary); line-height: 1.8; padding-left: 20px;">
                            <li>Upload your contract PDF template to DocuSeal</li>
                            <li>Add signature fields, date fields, and text fields as needed</li>
                            <li>Configure submitter roles (e.g., "Employee", "Manager")</li>
                            <li>Note the Template ID for use in Arctic Wolves HR module</li>
                        </ol>
                        
                        <div class="alert alert-warning" style="margin-top: 15px;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span><strong>Important:</strong> Ensure your webhook URL is accessible from DocuSeal. For self-hosted installations, make sure port 3000 is open and your webhook endpoint is publicly accessible.</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- DocuSeal Configuration Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-file-signature"></i> DocuSeal Configuration</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info" style="margin-bottom: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <span>DocuSeal is used for e-signature workflows on employee contracts. Create templates in DocuSeal and link them here. <a href="https://www.docuseal.co/docs/api" target="_blank" style="color: inherit;">View API Documentation</a></span>
                    </div>
                    
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Enable DocuSeal</h4>
                                <p>Enable e-signature features for employee contracts</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="docuseal_enabled" 
                                       <?php echo !empty($settings['docuseal_enabled']) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>DocuSeal URL</h4>
                                <p>The base URL of your DocuSeal instance (e.g., https://docuseal.example.com or https://api.docuseal.co)</p>
                            </div>
                            <input type="url" name="docuseal_url" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['docuseal_url'] ?? ''); ?>"
                                   placeholder="https://api.docuseal.co">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>API Key</h4>
                                <p>Your DocuSeal API authentication key (found in Settings > API)</p>
                            </div>
                            <input type="password" name="docuseal_api_key" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['docuseal_api_key'] ?? ''); ?>"
                                   placeholder="Enter your DocuSeal API key">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Webhook Secret (Optional)</h4>
                                <p>Secret key for verifying webhook callbacks from DocuSeal</p>
                            </div>
                            <input type="password" name="docuseal_webhook_secret" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['docuseal_webhook_secret'] ?? ''); ?>"
                                   placeholder="Webhook secret for signature verification">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Verify SSL Certificate</h4>
                                <p>Enable SSL certificate verification for secure connections (recommended for production)</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="docuseal_verify_ssl" 
                                       <?php echo ($settings['docuseal_verify_ssl'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Test Connection & Save -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-plug"></i> Connection Test</h3>
                </div>
                <div class="card-body">
                    <div class="flex-row" style="display: flex; gap: 12px; align-items: center;">
                        <button type="button" id="test-docuseal" class="btn-secondary">
                            <i class="fas fa-plug"></i> Test Connection
                        </button>
                        <span id="docuseal-status"></span>
                    </div>
                </div>
            </div>
            
            <!-- E-Signature Settings Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-signature"></i> E-Signature Settings</h3>
                </div>
                <div class="card-body">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Auto-send Confirmation Email</h4>
                                <p>Send confirmation email from Arctic Wolves when contract is signed (in addition to DocuSeal notifications)</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="docuseal_auto_confirm" 
                                       <?php echo ($settings['docuseal_auto_confirm'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save DocuSeal Settings</button>
            </div>
        </form>
    </div>

    <!-- Stallion Express Shipping Tab -->
    <div class="tab-content <?php echo $activeTab === 'stallion' ? 'active' : ''; ?>" id="stallion-tab">
        <form id="stallion-form" method="POST" action="process_settings.php" data-form-type="stallion">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_stallion">
            <input type="hidden" name="redirect_page" value="system_tools">
            
            <!-- Stallion Express Setup Guide -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-book"></i> Stallion Express Setup Guide</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info" style="margin-bottom: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <span>Stallion Express is a shipping fulfillment service that aggregates rates from multiple carriers (Canada Post, UPS, FedEx, DHL, etc.) to find the best shipping rates. Integrate with Stallion to automatically create shipping labels for online orders.</span>
                    </div>
                    
                    <div class="setup-instructions" style="background: var(--bg-main); border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid var(--border);">
                        <h4 style="color: var(--text-primary); margin-bottom: 15px;"><i class="fas fa-rocket" style="color: var(--primary-light); margin-right: 8px;"></i> Getting Started</h4>
                        <ol style="color: var(--text-secondary); line-height: 1.8; padding-left: 20px;">
                            <li><strong>Create an account</strong> at <a href="https://www.stallionexpress.ca" target="_blank" style="color: var(--primary-light);">stallionexpress.ca</a></li>
                            <li><strong>Navigate to API Settings</strong> in your Stallion Express dashboard</li>
                            <li><strong>Generate an API Key</strong> and copy it below. Refer to <a href="https://stallionexpress.redocly.app/stallionexpress-v4" target="_blank" style="color: var(--primary-light);">API v4 Documentation</a> for details</li>
                            <li><strong>Configure your sender address</strong> — this will be the return address on all shipping labels</li>
                            <li><strong>Set default package dimensions</strong> — these will be used if no overrides are provided per-order</li>
                        </ol>
                        
                        <div class="alert alert-info" style="margin-top: 15px;">
                            <i class="fas fa-info-circle"></i>
                            <span>Stallion Express will automatically compare rates across multiple carriers (Canada Post, UPS, FedEx, DHL, etc.) and select the best option for each shipment.</span>
                        </div>
                        
                        <div class="alert alert-warning" style="margin-top: 15px;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span><strong>Note:</strong> You can create shipping labels and print them from the POS Online Orders page or the Shop Orders management page. Athletes can also pick up orders at sessions — use the "Pickup at Session" fulfillment option.</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- API Configuration -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-key"></i> API Configuration</h3>
                </div>
                <div class="card-body">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Enable Stallion Express</h4>
                                <p>Enable Stallion Express shipping integration for online orders</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="stallion_enabled" 
                                       <?php echo !empty($settings['stallion_enabled']) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>API URL</h4>
                                <p>Stallion Express API base URL</p>
                            </div>
                            <input type="url" name="stallion_api_url" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['stallion_api_url'] ?? 'https://ship.stallionexpress.ca/api/v4'); ?>"
                                   placeholder="https://ship.stallionexpress.ca/api/v4">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>API Key</h4>
                                <p>Your Stallion Express API authentication key</p>
                            </div>
                            <input type="password" name="stallion_api_key" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['stallion_api_key'] ?? ''); ?>"
                                   placeholder="Enter your Stallion Express API key">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>API Secret (Optional)</h4>
                                <p>API secret key if required by your Stallion Express plan</p>
                            </div>
                            <input type="password" name="stallion_api_secret" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['stallion_api_secret'] ?? ''); ?>"
                                   placeholder="Enter API secret (if applicable)">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sender Address -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-map-marker-alt"></i> Sender Address (Return Address)</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info" style="margin-bottom: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <span>This address will appear as the return/sender address on all shipping labels created through Stallion Express.</span>
                    </div>
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Contact Name</h4>
                                <p>Name of the sender contact person</p>
                            </div>
                            <input type="text" name="stallion_sender_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['stallion_sender_name'] ?? ''); ?>"
                                   placeholder="e.g., Arctic Wolves Shipping">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Company Name</h4>
                                <p>Business or organization name</p>
                            </div>
                            <input type="text" name="stallion_sender_company" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['stallion_sender_company'] ?? ''); ?>"
                                   placeholder="e.g., Arctic Wolves Hockey">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Street Address</h4>
                                <p>Sender street address</p>
                            </div>
                            <input type="text" name="stallion_sender_address" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['stallion_sender_address'] ?? ''); ?>"
                                   placeholder="e.g., 123 Arena Drive">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>City</h4>
                                <p>Sender city</p>
                            </div>
                            <input type="text" name="stallion_sender_city" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['stallion_sender_city'] ?? ''); ?>"
                                   placeholder="e.g., Toronto">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Province</h4>
                                <p>Sender province code</p>
                            </div>
                            <select name="stallion_sender_province" class="form-input">
                                <option value="">-- Select Province --</option>
                                <?php
                                $provinces = ['AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba', 'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador', 'NS' => 'Nova Scotia', 'NT' => 'Northwest Territories', 'NU' => 'Nunavut', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island', 'QC' => 'Quebec', 'SK' => 'Saskatchewan', 'YT' => 'Yukon'];
                                foreach ($provinces as $code => $name): ?>
                                    <option value="<?php echo $code; ?>" <?php echo ($settings['stallion_sender_province'] ?? '') === $code ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Postal Code</h4>
                                <p>Sender postal code (e.g., M5V 2T6)</p>
                            </div>
                            <input type="text" name="stallion_sender_postal_code" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['stallion_sender_postal_code'] ?? ''); ?>"
                                   placeholder="e.g., M5V 2T6">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Phone Number</h4>
                                <p>Sender contact phone number</p>
                            </div>
                            <input type="tel" name="stallion_sender_phone" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['stallion_sender_phone'] ?? ''); ?>"
                                   placeholder="e.g., 416-555-1234">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Default Package Dimensions -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-box"></i> Default Package Dimensions</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info" style="margin-bottom: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <span>Set default package dimensions used when creating shipping labels. These can be overridden per-order when fulfilling.</span>
                    </div>
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Weight (kg)</h4>
                                <p>Default package weight in kilograms</p>
                            </div>
                            <input type="number" name="stallion_default_weight" class="form-input" step="0.01" min="0.01"
                                   value="<?php echo htmlspecialchars($settings['stallion_default_weight'] ?? '0.5'); ?>"
                                   placeholder="0.5">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Length (cm)</h4>
                                <p>Default package length in centimeters</p>
                            </div>
                            <input type="number" name="stallion_default_length" class="form-input" step="0.1" min="1"
                                   value="<?php echo htmlspecialchars($settings['stallion_default_length'] ?? '25'); ?>"
                                   placeholder="25">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Width (cm)</h4>
                                <p>Default package width in centimeters</p>
                            </div>
                            <input type="number" name="stallion_default_width" class="form-input" step="0.1" min="1"
                                   value="<?php echo htmlspecialchars($settings['stallion_default_width'] ?? '20'); ?>"
                                   placeholder="20">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Height (cm)</h4>
                                <p>Default package height in centimeters</p>
                            </div>
                            <input type="number" name="stallion_default_height" class="form-input" step="0.1" min="1"
                                   value="<?php echo htmlspecialchars($settings['stallion_default_height'] ?? '10'); ?>"
                                   placeholder="10">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Connection Test & Save -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-plug"></i> Connection Test</h3>
                </div>
                <div class="card-body">
                    <div class="flex-row" style="display: flex; gap: 12px; align-items: center;">
                        <button type="button" id="test-stallion" class="btn-secondary">
                            <i class="fas fa-plug"></i> Test Connection
                        </button>
                        <span id="stallion-status"></span>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Stallion Express Settings</button>
            </div>
        </form>
    </div>

    <!-- Payments Tab -->
    <div class="tab-content <?php echo $activeTab === 'payments' ? 'active' : ''; ?>" id="payments-tab">
        <form id="payments-form" method="POST" action="process_settings.php" data-form-type="payments">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_payments">
            <input type="hidden" name="redirect_page" value="system_tools">
            
            <!-- Stripe Payment Configuration Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-credit-card"></i> Stripe Payment Configuration</h3>
                </div>
                <div class="card-body">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Stripe Publishable Key</h4>
                                <p>Public key for frontend payment processing (starts with pk_)</p>
                            </div>
                            <input type="text" name="stripe_publishable_key" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['stripe_publishable_key'] ?? ''); ?>"
                                   placeholder="pk_test_..." style="min-width: 300px;">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Stripe Secret Key</h4>
                                <p>Secret key for backend processing (starts with sk_)<?php echo !empty($settings['stripe_secret_key']) ? ' (currently set)' : ''; ?></p>
                            </div>
                            <input type="password" name="stripe_secret_key" class="form-input" 
                                   placeholder="<?php echo !empty($settings['stripe_secret_key']) ? 'Leave blank to keep current key' : 'sk_test_...'; ?>" style="min-width: 300px;">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Currency</h4>
                                <p>Default currency for all transactions</p>
                            </div>
                            <select name="currency" class="form-input" style="width: auto; min-width: 200px;">
                                <?php
                                $currencies = ['CAD' => 'Canadian Dollar (CAD)', 'USD' => 'US Dollar (USD)', 'EUR' => 'Euro (EUR)', 'GBP' => 'British Pound (GBP)'];
                                $current_currency = $settings['currency'] ?? 'CAD';
                                foreach ($currencies as $code => $name) {
                                    $selected = ($code === $current_currency) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($code) . "\" $selected>" . htmlspecialchars($name) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <p>To obtain Stripe API keys, visit the <a href="https://dashboard.stripe.com/apikeys" target="_blank" style="color: #8B5CF6;">Stripe Dashboard → API Keys</a>. Use test keys (pk_test_/sk_test_) for development and live keys (pk_live_/sk_live_) for production.</p>
                    </div>
                </div>
            </div>
            
            <!-- Tax Settings Card -->
            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h3><i class="fas fa-percentage"></i> Tax Settings</h3>
                </div>
                <div class="card-body">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Tax Name</h4>
                                <p>Label for tax on invoices (e.g., HST, GST, VAT)</p>
                            </div>
                            <input type="text" name="tax_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['tax_name'] ?? 'HST'); ?>"
                                   placeholder="HST" style="width: auto; min-width: 150px;">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Tax Rate (%)</h4>
                                <p>Percentage rate for tax calculations</p>
                            </div>
                            <input type="number" name="tax_rate" class="form-input" step="0.01" min="0" max="100"
                                   value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '13.00'); ?>"
                                   placeholder="13.00" style="width: auto; min-width: 120px;">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save Payment & Tax Settings
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Theme Tab -->
    <div class="tab-content <?php echo $activeTab === 'theme' ? 'active' : ''; ?>" id="theme-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-palette"></i> Theme Customization</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($_GET['upload_warning'])): ?>
                <div class="alert alert-error" style="margin-bottom: 16px; padding: 12px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444;">
                    <i class="fas fa-exclamation-triangle"></i> Upload Warning: <?php echo htmlspecialchars($_GET['upload_warning']); ?>
                </div>
                <?php endif; ?>
                <form id="theme-form" method="POST" action="process_theme.php" enctype="multipart/form-data">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="save_theme">
                    <input type="hidden" name="redirect_page" value="system_tools">
                    
                    <!-- Color Scheme Section -->
                    <div class="sync-options" style="margin-bottom: 24px;">
                        <h4><i class="fas fa-swatchbook"></i> Color Scheme</h4>
                        <p class="help-text" style="margin-bottom: 16px;">Customize the application's color palette</p>
                        <div class="theme-colors" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                            <div class="color-picker-item">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dim);">Primary Color</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input type="color" name="primary_color" id="theme_primary_color" 
                                           value="<?php echo htmlspecialchars($theme_settings['primary_color']); ?>"
                                           style="width: 50px; height: 40px; border: none; border-radius: 6px; cursor: pointer;">
                                    <input type="text" class="form-input" id="theme_primary_color_text" 
                                           value="<?php echo htmlspecialchars($theme_settings['primary_color']); ?>" 
                                           style="width: 100px; font-family: monospace;" readonly>
                                </div>
                            </div>
                            <div class="color-picker-item">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dim);">Secondary Color</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input type="color" name="secondary_color" id="theme_secondary_color" 
                                           value="<?php echo htmlspecialchars($theme_settings['secondary_color']); ?>"
                                           style="width: 50px; height: 40px; border: none; border-radius: 6px; cursor: pointer;">
                                    <input type="text" class="form-input" id="theme_secondary_color_text" 
                                           value="<?php echo htmlspecialchars($theme_settings['secondary_color']); ?>" 
                                           style="width: 100px; font-family: monospace;" readonly>
                                </div>
                            </div>
                            <div class="color-picker-item">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dim);">Background Color</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input type="color" name="background_color" id="theme_background_color" 
                                           value="<?php echo htmlspecialchars($theme_settings['background_color']); ?>"
                                           style="width: 50px; height: 40px; border: none; border-radius: 6px; cursor: pointer;">
                                    <input type="text" class="form-input" id="theme_background_color_text" 
                                           value="<?php echo htmlspecialchars($theme_settings['background_color']); ?>" 
                                           style="width: 100px; font-family: monospace;" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Logo & Branding Section -->
                    <div class="sync-options" style="margin-bottom: 24px;">
                        <h4><i class="fas fa-image"></i> Logo & Branding</h4>
                        <p class="help-text" style="margin-bottom: 16px;">Upload a logo or provide a URL</p>
                        
                        <div class="settings-list">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Logo Source</h4>
                                    <p>Choose how to provide your logo</p>
                                </div>
                                <div style="display: flex; gap: 20px;">
                                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                        <input type="radio" name="logo_method" value="upload" 
                                               <?php echo ($theme_settings['logo_method'] === 'upload') ? 'checked' : ''; ?> 
                                               onchange="toggleThemeLogoInput()">
                                        <span>Upload File</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                        <input type="radio" name="logo_method" value="url" 
                                               <?php echo ($theme_settings['logo_method'] === 'url') ? 'checked' : ''; ?> 
                                               onchange="toggleThemeLogoInput()">
                                        <span>Use URL</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="setting-item" id="theme-logo-upload-row" style="<?php echo ($theme_settings['logo_method'] === 'url') ? 'display: none;' : ''; ?>">
                                <div class="setting-info">
                                    <h4>Upload Logo</h4>
                                    <p>PNG, JPG, GIF, SVG (max 5MB)</p>
                                </div>
                                <input type="file" name="logo" class="form-input" accept="image/*" style="max-width: 300px;">
                            </div>
                            
                            <div class="setting-item" id="theme-logo-url-row" style="<?php echo ($theme_settings['logo_method'] === 'upload') ? 'display: none;' : ''; ?>">
                                <div class="setting-info">
                                    <h4>Logo URL</h4>
                                    <p>Direct URL to your logo image</p>
                                </div>
                                <input type="text" name="logo_url" class="form-input" 
                                       value="<?php echo htmlspecialchars($theme_settings['logo_url']); ?>"
                                       placeholder="https://example.com/logo.png" style="min-width: 300px;">
                            </div>
                            
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Use Logo as Favicon</h4>
                                    <p>Use the logo as the browser tab icon</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="use_logo_as_favicon" 
                                           <?php echo $is_favicon_enabled ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <?php if (!empty($theme_settings['logo_url'])): ?>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Current Logo</h4>
                                    <p>Preview of the current logo</p>
                                </div>
                                <div style="background: #0A0A0F; padding: 16px; border-radius: 8px; border: 1px solid var(--border);">
                                    <img src="<?php echo htmlspecialchars($theme_settings['logo_url']); ?>" alt="Current Logo" 
                                         style="max-height: 60px; max-width: 200px;" onerror="this.style.display='none'">
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Center Ice Logo Section -->
                    <div class="sync-options" style="margin-bottom: 24px;">
                        <h4><i class="fas fa-hockey-puck"></i> Center Ice Logo</h4>
                        <p class="help-text" style="margin-bottom: 16px;">This logo appears at center ice in the drill designer as a subtle watermark. Preview shown at 50% opacity; actual display uses 12% opacity.</p>
                        
                        <div class="settings-list">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Logo Source</h4>
                                    <p>Choose how to provide your center ice logo</p>
                                </div>
                                <div style="display: flex; gap: 20px;">
                                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                        <input type="radio" name="center_ice_logo_method" value="upload" 
                                               <?php echo ($theme_settings['center_ice_logo_method'] === 'upload') ? 'checked' : ''; ?> 
                                               onchange="toggleCenterIceLogoInput()">
                                        <span>Upload File</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                        <input type="radio" name="center_ice_logo_method" value="url" 
                                               <?php echo ($theme_settings['center_ice_logo_method'] === 'url') ? 'checked' : ''; ?> 
                                               onchange="toggleCenterIceLogoInput()">
                                        <span>Use URL</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="setting-item" id="center-ice-logo-upload-row" style="<?php echo ($theme_settings['center_ice_logo_method'] === 'url') ? 'display: none;' : ''; ?>">
                                <div class="setting-info">
                                    <h4>Upload Center Ice Logo</h4>
                                    <p>PNG, JPG, WEBP (max 2MB, 400x400px recommended)</p>
                                </div>
                                <input type="file" name="center_ice_logo" class="form-input" accept=".png,.jpg,.jpeg,.webp" style="max-width: 300px;">
                            </div>
                            
                            <div class="setting-item" id="center-ice-logo-url-row" style="<?php echo ($theme_settings['center_ice_logo_method'] === 'upload') ? 'display: none;' : ''; ?>">
                                <div class="setting-info">
                                    <h4>Center Ice Logo URL</h4>
                                    <p>Direct URL to your center ice logo image</p>
                                </div>
                                <input type="text" name="center_ice_logo_url_input" class="form-input" 
                                       value="<?php echo htmlspecialchars($theme_settings['center_ice_logo_url']); ?>"
                                       placeholder="https://example.com/center-ice-logo.png" style="min-width: 300px;">
                            </div>
                            
                            <?php if (!empty($theme_settings['center_ice_logo_url'])): ?>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Current Center Ice Logo</h4>
                                    <p>Preview of the center ice logo</p>
                                </div>
                                <div style="background: #0A0A0F; padding: 16px; border-radius: 8px; border: 1px solid var(--border); display: flex; align-items: center; gap: 16px;">
                                    <img src="<?php echo htmlspecialchars($theme_settings['center_ice_logo_url']); ?>" alt="Current Center Ice Logo" 
                                         style="max-height: 100px; max-width: 100px; opacity: 0.5;" onerror="this.style.display='none'">
                                    <button type="button" class="btn btn-secondary" onclick="removeCenterIceLogo()" style="font-size: 12px; padding: 6px 12px;">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>No Center Ice Logo Set</h4>
                                    <p>Default text branding will be used in drill designer</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Business Card Backgrounds Section (inside main theme form) -->
                    <div class="sync-options" style="margin-bottom: 24px; margin-top: 24px; border-top: 1px solid var(--border); padding-top: 24px;">
                        <h4><i class="fas fa-id-card"></i> Business Card Backgrounds</h4>
                        <p class="help-text" style="margin-bottom: 16px;">Upload background images for the front and back of business cards. The logo above is automatically used on cards. Recommended: 1050×600px (3.5" × 2" at 300 DPI).</p>
                        
                        <?php
                        $st_bc_front_bg = $theme_settings['business_card_front_bg_url'] ?? '';
                        $st_bc_back_bg = $theme_settings['business_card_back_bg_url'] ?? '';
                        ?>
                        <div class="settings-list">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Front Card Background</h4>
                                    <p>PNG, JPG, WEBP (max 5MB)</p>
                                </div>
                                <input type="file" name="bc_front_bg" class="form-input" accept=".png,.jpg,.jpeg,.webp" style="max-width: 300px;" onchange="previewBcBackground(this, 'bc-front-bg-preview')">
                            </div>
                            
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Current Front Background</h4>
                                    <p>Preview of the current front card background</p>
                                </div>
                                <div id="bc-front-bg-preview" style="background: #0A0A0F; padding: 12px; border-radius: 8px; border: 1px solid var(--border);">
                                    <?php if (!empty($st_bc_front_bg)): ?>
                                        <img src="<?php echo htmlspecialchars($st_bc_front_bg); ?>" alt="Front Card Background" 
                                             style="max-height: 80px; max-width: 200px;" onerror="this.parentNode.innerHTML='<span style=\'color:#64748b;font-size:13px;\'>Image not found — please re-upload</span>'">
                                    <?php else: ?>
                                        <span style="color: #64748b; font-size: 13px;">No front background uploaded</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Back Card Background</h4>
                                    <p>PNG, JPG, WEBP (max 5MB)</p>
                                </div>
                                <input type="file" name="bc_back_bg" class="form-input" accept=".png,.jpg,.jpeg,.webp" style="max-width: 300px;" onchange="previewBcBackground(this, 'bc-back-bg-preview')">
                            </div>
                            
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Current Back Background</h4>
                                    <p>Preview of the current back card background</p>
                                </div>
                                <div id="bc-back-bg-preview" style="background: #0A0A0F; padding: 12px; border-radius: 8px; border: 1px solid var(--border);">
                                    <?php if (!empty($st_bc_back_bg)): ?>
                                        <img src="<?php echo htmlspecialchars($st_bc_back_bg); ?>" alt="Back Card Background" 
                                             style="max-height: 80px; max-width: 200px;" onerror="this.parentNode.innerHTML='<span style=\'color:#64748b;font-size:13px;\'>Image not found — please re-upload</span>'">
                                    <?php else: ?>
                                        <span style="color: #64748b; font-size: 13px;">No back background uploaded</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="resetThemeColors()">
                            <i class="fas fa-undo"></i> Reset Colors
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save All Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Database Tab -->
    <div class="tab-content <?php echo $activeTab === 'database' ? 'active' : ''; ?>" id="database-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-database"></i> Database Tools</h3>
            </div>
            <div class="card-body">
                <!-- Database Status -->
                <div id="db-status-message" style="display: none; margin-bottom: 20px;"></div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <!-- Backup Database -->
                    <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; padding: 24px; text-align: center;">
                        <div style="font-size: 36px; color: var(--primary); margin-bottom: 12px;">
                            <i class="fas fa-download"></i>
                        </div>
                        <h4 style="margin-bottom: 8px; color: var(--text-white);">Backup Database</h4>
                        <p style="color: var(--text-dim); font-size: 13px; margin-bottom: 16px;">Create a full backup of all database tables</p>
                        <button type="button" class="btn btn-primary" id="btn-backup-db" onclick="runDatabaseBackup(this)">
                            <i class="fas fa-download"></i> Backup Now
                        </button>
                    </div>
                    
                    <!-- Repair & Optimize -->
                    <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; padding: 24px; text-align: center;">
                        <div style="font-size: 36px; color: #10b981; margin-bottom: 12px;">
                            <i class="fas fa-wrench"></i>
                        </div>
                        <h4 style="margin-bottom: 8px; color: var(--text-white);">Repair & Optimize</h4>
                        <p style="color: var(--text-dim); font-size: 13px; margin-bottom: 16px;">Check, repair and optimize all database tables</p>
                        <button type="button" class="btn btn-secondary" id="btn-optimize-db" onclick="runDatabaseOptimize(this)">
                            <i class="fas fa-wrench"></i> Optimize
                        </button>
                    </div>
                    
                    <!-- Clear Cache -->
                    <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; padding: 24px; text-align: center;">
                        <div style="font-size: 36px; color: #f59e0b; margin-bottom: 12px;">
                            <i class="fas fa-broom"></i>
                        </div>
                        <h4 style="margin-bottom: 8px; color: var(--text-white);">Clear Cache</h4>
                        <p style="color: var(--text-dim); font-size: 13px; margin-bottom: 16px;">Clear temporary files and cached data</p>
                        <button type="button" class="btn btn-secondary" id="btn-clear-cache" onclick="runClearCache(this)">
                            <i class="fas fa-broom"></i> Clear Cache
                        </button>
                    </div>
                    
                    <!-- Restore Database -->
                    <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; padding: 24px; text-align: center;">
                        <div style="font-size: 36px; color: #ef4444; margin-bottom: 12px;">
                            <i class="fas fa-upload"></i>
                        </div>
                        <h4 style="margin-bottom: 8px; color: var(--text-white);">Restore Database</h4>
                        <p style="color: var(--text-dim); font-size: 13px; margin-bottom: 16px;">Restore from a previous backup file</p>
                        <a href="dashboard.php?page=admin_database_restore" class="btn btn-secondary">
                            <i class="fas fa-upload"></i> Restore
                        </a>
                    </div>
                </div>
                
                <!-- Info Box -->
                <div class="info-box" style="margin-top: 24px;">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <p><strong>Database Maintenance</strong></p>
                        <ul style="margin: 8px 0; padding-left: 20px; color: var(--text-dim); font-size: 13px;">
                            <li><strong>Backup</strong> - Creates a SQL dump of all tables saved to the backups folder</li>
                            <li><strong>Optimize</strong> - Runs CHECK, REPAIR, OPTIMIZE and ANALYZE on all tables</li>
                            <li><strong>Clear Cache</strong> - Removes temporary files from cache and tmp directories</li>
                        </ul>
                    </div>
                </div>

                <!-- ===== Cluster Management ===== -->
                <div style="margin-top: 32px;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                        <h4 style="font-size: 16px; font-weight: 700; color: var(--text-white); margin: 0;">
                            <i class="fas fa-circle-nodes" style="color: var(--primary); margin-right: 8px;"></i>Galera Cluster Management
                        </h4>
                        <?php $db_mode_current = $_ENV['DB_MODE'] ?? 'single'; ?>
                        <span id="cluster-mode-badge" style="padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
                              background: <?= $db_mode_current === 'cluster' ? 'rgba(0,255,136,0.15)' : 'rgba(148,163,184,0.15)' ?>;
                              color: <?= $db_mode_current === 'cluster' ? '#00ff88' : '#94a3b8' ?>;
                              border: 1px solid <?= $db_mode_current === 'cluster' ? '#00ff88' : '#94a3b8' ?>;">
                            <?= $db_mode_current === 'cluster' ? 'CLUSTER MODE' : 'SINGLE DB' ?>
                        </span>
                    </div>

                    <!-- Mode + cluster settings form -->
                    <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 20px;">
                        <h5 style="color: var(--text-white); margin-bottom: 16px; font-size: 14px;">Database Mode Configuration</h5>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div>
                                <label style="font-size: 13px; color: var(--text-dim); display: block; margin-bottom: 6px;">Database Mode</label>
                                <select id="cfg-db-mode" style="width: 100%; padding: 8px 12px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; color: var(--text-white); font-size: 13px;" onchange="toggleClusterConfigFields()">
                                    <option value="single" <?= $db_mode_current !== 'cluster' ? 'selected' : '' ?>>Single Database</option>
                                    <option value="cluster" <?= $db_mode_current === 'cluster' ? 'selected' : '' ?>>Galera Cluster</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size: 13px; color: var(--text-dim); display: block; margin-bottom: 6px;">Cluster Name</label>
                                <input type="text" id="cfg-cluster-name" value="<?= htmlspecialchars($_ENV['DB_CLUSTER_NAME'] ?? 'arctic_wolves_cluster') ?>"
                                       style="width: 100%; padding: 8px 12px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; color: var(--text-white); font-size: 13px;">
                            </div>
                        </div>
                        <div id="cfg-cluster-nodes-row">
                            <label style="font-size: 13px; color: var(--text-dim); display: block; margin-bottom: 6px;">Cluster Nodes (comma-separated host or host:port)</label>
                            <input type="text" id="cfg-cluster-nodes" value="<?= htmlspecialchars($_ENV['DB_CLUSTER_NODES'] ?? '') ?>"
                                   placeholder="node1,node2,node3 or node1:3306,node2:3306,node3:3306"
                                   style="width: 100%; padding: 8px 12px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; color: var(--text-white); font-size: 13px;">
                        </div>
                        <div style="margin-top: 14px;">
                            <button type="button" class="btn btn-primary" onclick="saveClusterSettings()" style="font-size: 13px;">
                                <i class="fas fa-save"></i> Save Cluster Settings
                            </button>
                        </div>
                        <div id="cluster-settings-result" style="margin-top: 10px; font-size: 13px;"></div>
                    </div>

                    <!-- Live cluster status -->
                    <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 20px;" id="cluster-status-card">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                            <h5 style="color: var(--text-white); margin: 0; font-size: 14px;">Live Cluster Status</h5>
                            <button type="button" class="btn btn-secondary" onclick="loadClusterStatus()" style="font-size: 12px; padding: 6px 12px;">
                                <i class="fas fa-refresh"></i> Refresh
                            </button>
                        </div>
                        <div id="cluster-status-content" style="color: var(--text-dim); font-size: 13px;">
                            <span style="color: #94a3b8;"><i class="fas fa-circle-info"></i> Click Refresh to load cluster status.</span>
                        </div>
                    </div>

                    <!-- Node list and add node -->
                    <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
                        <h5 style="color: var(--text-white); margin-bottom: 16px; font-size: 14px;">Cluster Nodes</h5>
                        <div id="node-list" style="margin-bottom: 20px;">
                            <?php
                            $cfg_nodes = array_filter(array_map('trim', explode(',', $_ENV['DB_CLUSTER_NODES'] ?? '')));
                            if (empty($cfg_nodes)): ?>
                                <p style="color: #94a3b8; font-size: 13px; margin: 0;">No nodes configured. Switch to Cluster mode and save settings, or add nodes below.</p>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                <?php foreach ($cfg_nodes as $node): ?>
                                    <div class="cluster-node-row" data-node="<?= htmlspecialchars($node) ?>" style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px;">
                                        <i class="fas fa-server" style="color: var(--primary);"></i>
                                        <span style="flex: 1; font-size: 13px; color: var(--text-white); font-family: monospace;"><?= htmlspecialchars($node) ?></span>
                                        <button type="button" onclick="testNode('<?= htmlspecialchars($node, ENT_QUOTES) ?>')" class="btn btn-secondary" style="font-size: 11px; padding: 4px 10px;">
                                            <i class="fas fa-plug"></i> Test
                                        </button>
                                        <button type="button" onclick="removeNode('<?= htmlspecialchars($node, ENT_QUOTES) ?>')" class="btn" style="font-size: 11px; padding: 4px 10px; background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid #ef4444;">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Add Node Form -->
                        <div style="border-top: 1px solid var(--border); padding-top: 20px;">
                            <h6 style="color: var(--text-white); margin-bottom: 12px; font-size: 13px; font-weight: 600;">
                                <i class="fas fa-plus-circle" style="color: #00ff88; margin-right: 6px;"></i>Add New Node &amp; Join Cluster
                            </h6>
                            <div style="display: flex; gap: 10px; align-items: flex-end;">
                                <div style="flex: 1;">
                                    <label style="font-size: 12px; color: var(--text-dim); display: block; margin-bottom: 5px;">Node Address (host or host:port)</label>
                                    <input type="text" id="new-node-address" placeholder="192.168.1.100 or mariadb-node4:3306"
                                           style="width: 100%; padding: 8px 12px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; color: var(--text-white); font-size: 13px;">
                                </div>
                                <button type="button" class="btn btn-primary" onclick="addClusterNode()" style="white-space: nowrap; font-size: 13px; height: 36px;">
                                    <i class="fas fa-plus"></i> Add &amp; Generate Join Command
                                </button>
                            </div>
                            <div id="add-node-result" style="margin-top: 12px; font-size: 13px;"></div>
                        </div>
                    </div>
                </div>
                <!-- ===== End Cluster Management ===== -->

            </div>
        </div>
    </div>
    
    <!-- Cron Jobs Tab -->
    <div class="tab-content <?php echo $activeTab === 'cron' ? 'active' : ''; ?>" id="cron-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Scheduled Tasks</h3>
                <button class="btn btn-secondary" data-action="run-all">
                    <i class="fas fa-play"></i> Run All
                </button>
            </div>
            <div class="card-body">
                <p class="placeholder-text">Cron job monitoring and management</p>
            </div>
        </div>
    </div>
    
    <!-- Updates Tab -->
    <div class="tab-content <?php echo $activeTab === 'updates' ? 'active' : ''; ?>" id="updates-tab">
        <!-- System Updates Card - GitHub Updater -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fab fa-github"></i> System Updates</h3>
            </div>
            <div class="card-body">
                <div class="info-box" style="margin-bottom: 24px;">
                    <i class="fas fa-info-circle"></i>
                    <p>Update your system directly from the official GitHub repository. Click <strong>Check for Updates</strong> to see if a new version is available, then <strong>Update Now</strong> to apply it. Your configuration, uploads, and encryption keys are preserved automatically.</p>
                </div>
                
                <!-- Current Version Status -->
                <div id="githubUpdateStatus" style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; padding: 24px; margin-bottom: 24px;">
                    <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                        <div style="font-size: 40px; color: var(--primary);">
                            <i class="fab fa-github"></i>
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <div style="font-size: 16px; font-weight: 700; color: var(--text-white); margin-bottom: 4px;">
                                CrashMediaIT/Arctic_Wolves
                            </div>
                            <div id="githubCurrentVersion" style="font-size: 13px; color: var(--text-dim);">
                                Click "Check for Updates" to check current status
                            </div>
                        </div>
                        <div id="githubUpdateBadge" style="display: none; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700;"></div>
                    </div>
                </div>
                
                <!-- Update Details (shown after check) -->
                <div id="githubUpdateDetails" style="display: none; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                    <h4 style="color: var(--text-white); margin-bottom: 12px;"><i class="fas fa-code-branch"></i> Latest Commit</h4>
                    <div style="font-size: 13px;">
                        <div style="margin-bottom: 8px;">
                            <span style="color: var(--text-dim);">SHA:</span>
                            <code id="githubLatestSha" style="color: #10b981; background: var(--bg-dark); padding: 2px 8px; border-radius: 4px; margin-left: 8px;"></code>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <span style="color: var(--text-dim);">Message:</span>
                            <span id="githubLatestMessage" style="color: var(--text-white); margin-left: 8px;"></span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <span style="color: var(--text-dim);">Author:</span>
                            <span id="githubLatestAuthor" style="color: var(--text-white); margin-left: 8px;"></span>
                        </div>
                        <div>
                            <span style="color: var(--text-dim);">Date:</span>
                            <span id="githubLatestDate" style="color: var(--text-white); margin-left: 8px;"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="form-actions" style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" id="githubCheckBtn" onclick="githubCheckForUpdates()">
                        <i class="fas fa-sync"></i> Check for Updates
                    </button>
                    <button type="button" class="btn btn-primary" id="githubUpdateBtn" onclick="githubApplyUpdate()" disabled>
                        <i class="fas fa-download"></i> Update Now
                    </button>
                </div>
                
                <!-- Result Banner -->
                <div id="githubResultBanner" style="display: none; border-radius: 8px; padding: 20px; margin-top: 20px;"></div>
                
                <!-- Progress Section -->
                <div id="githubProgressSection" style="display: none; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; padding: 24px; margin-top: 24px;">
                    <div id="githubProgressTitle" style="font-size: 16px; font-weight: 700; color: var(--text-white); margin-bottom: 12px;">
                        <i class="fas fa-spinner fa-spin"></i> Applying Update...
                    </div>
                    <div style="background: var(--bg-dark); border-radius: 6px; height: 8px; overflow: hidden; margin-bottom: 20px;">
                        <div id="githubProgressBar" style="background: var(--primary); height: 100%; width: 0; transition: width 0.3s;"></div>
                    </div>
                    <div id="githubLogContainer" style="background: var(--bg-dark); border: 1px solid var(--border); border-radius: 6px; padding: 16px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 13px;"></div>
                </div>
            </div>
        </div>
        
        <?php
        // Load installed feature versions with error handling
        $installed_versions = [];
        try {
            $feature_importer_file = __DIR__ . '/../admin/feature_importer.php';
            if (file_exists($feature_importer_file)) {
                require_once $feature_importer_file;
                $feature_importer = new FeatureImporter($pdo, __DIR__ . '/..');
                $installed_versions = $feature_importer->getInstalledVersions();
            }
        } catch (Exception $e) {
            error_log("Feature importer error: " . $e->getMessage());
        }
        ?>
        
        <?php if (!empty($installed_versions)): ?>
        <!-- Installed Feature Versions (historical reference) -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Installed Feature Versions</h3>
            </div>
            <div class="card-body">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <tr style="border-bottom: 1px solid var(--border); color: var(--text-dim);">
                        <th style="padding: 8px; text-align: left;">Feature</th>
                        <th style="padding: 8px; text-align: left;">Version</th>
                        <th style="padding: 8px; text-align: left;">Installed</th>
                    </tr>
                    <?php 
                    $grouped = [];
                    foreach ($installed_versions as $v) {
                        if (!isset($grouped[$v['feature_name']])) {
                            $grouped[$v['feature_name']] = $v;
                        }
                    }
                    foreach ($grouped as $feature_name => $version): 
                    $install_date = $version['applied_at'] ?? $version['created_at'] ?? null;
                    ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 8px; color: var(--text-white);"><?php echo htmlspecialchars($feature_name); ?></td>
                        <td style="padding: 8px; color: #10b981;"><?php echo htmlspecialchars($version['version']); ?></td>
                        <td style="padding: 8px; color: var(--text-dim);"><?php echo $install_date ? date('M d, Y', strtotime($install_date)) : 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Stripe PHP Library Updates -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h3><i class="fab fa-stripe-s"></i> Stripe PHP Library</h3>
            </div>
            <div class="card-body">
                <div class="info-box" style="margin-bottom: 24px;">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <p>Update the Stripe PHP library from the official repository: <a href="https://github.com/stripe/stripe-php" target="_blank" style="color: #8B5CF6;">stripe/stripe-php</a></p>
                        <p style="margin-top: 8px; font-size: 13px; color: var(--text-dim);">The Stripe PHP library is located in the <code>/stripe-php</code> directory at the root of this application.</p>
                    </div>
                </div>
                
                <?php
                // Get current Stripe version if available - validate path is within expected directory
                $stripe_base_path = realpath(__DIR__ . '/../stripe-php');
                $current_stripe_version = 'Unknown';
                if ($stripe_base_path && is_dir($stripe_base_path)) {
                    $stripe_version_file = $stripe_base_path . '/VERSION';
                    if (file_exists($stripe_version_file)) {
                        $current_stripe_version = trim(file_get_contents($stripe_version_file));
                    }
                }
                ?>
                
                <div class="integration-status connected" style="margin-bottom: 24px;">
                    <div class="status-icon">
                        <i class="fab fa-stripe-s"></i>
                    </div>
                    <div class="status-info">
                        <h4>Current Version: <?php echo htmlspecialchars($current_stripe_version); ?></h4>
                        <p>Stripe PHP Library installed in /stripe-php</p>
                    </div>
                </div>
                
                <div id="stripe-update-status" style="display: none; margin-bottom: 24px;"></div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="checkStripeUpdates()">
                        <i class="fas fa-sync"></i> Check for Updates
                    </button>
                    <button type="button" class="btn btn-primary" onclick="updateStripeLibrary()" id="update-stripe-btn" disabled>
                        <i class="fas fa-download"></i> Update Stripe Library
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Landing Page Settings Tab -->
    <div class="tab-content <?php echo $activeTab === 'landing' ? 'active' : ''; ?>" id="landing-tab">
        <form method="POST" action="process_settings.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_landing">
            <input type="hidden" name="redirect_page" value="system_tools">
            
            <!-- Programs Section -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-columns"></i> Programs Section</h3>
                </div>
                <div class="card-body">
                    <div class="info-box" style="margin-bottom: 24px;">
                        <i class="fas fa-info-circle"></i>
                        <p>Edit the training programs displayed on the public landing page. Leave fields empty to use default values.</p>
                    </div>
                    
                    <?php 
                    $default_programs = [
                        ['title' => 'Player Dev', 'tags' => 'Power Skating, Shooting', 'description' => 'Forwards & Defense: Explosive edgework and shot mechanics.'],
                        ['title' => 'Goalie Elite', 'tags' => 'Positioning, Tracking', 'description' => 'Crease management, angle control, and rebound psychology.'],
                        ['title' => 'Conditioning', 'tags' => 'Strength, Power', 'description' => 'Dryland training for endurance and explosive 60-minute power.'],
                        ['title' => 'Nutrition', 'tags' => 'Protein, Recovery', 'description' => 'Meal planning to fuel muscle growth and accelerate recovery.']
                    ];
                    for ($i = 1; $i <= 4; $i++): 
                        $program_prefix = "landing_program_{$i}_";
                    ?>
                    <div class="card" style="margin-bottom: 20px; border: 1px solid var(--border);">
                        <div class="card-header" style="padding: 12px 20px; background: var(--bg-main);">
                            <h4 style="font-size: 14px; margin: 0; color: var(--primary);"><i class="fas fa-cube"></i> Program <?php echo $i; ?></h4>
                        </div>
                        <div class="card-body" style="padding: 20px;">
                            <div class="settings-list">
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <h4>Title</h4>
                                        <p>Default: <?php echo htmlspecialchars($default_programs[$i-1]['title']); ?></p>
                                    </div>
                                    <input type="text" name="<?php echo $program_prefix; ?>title" class="form-input" 
                                           value="<?php echo htmlspecialchars($settings[$program_prefix . 'title'] ?? ''); ?>"
                                           placeholder="<?php echo htmlspecialchars($default_programs[$i-1]['title']); ?>">
                                </div>
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <h4>Image URL</h4>
                                        <p>Full URL to the program image</p>
                                    </div>
                                    <input type="url" name="<?php echo $program_prefix; ?>image" class="form-input" 
                                           value="<?php echo htmlspecialchars($settings[$program_prefix . 'image'] ?? ''); ?>"
                                           placeholder="https://...">
                                </div>
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <h4>Tags (comma-separated)</h4>
                                        <p>Default: <?php echo htmlspecialchars($default_programs[$i-1]['tags']); ?></p>
                                    </div>
                                    <input type="text" name="<?php echo $program_prefix; ?>tags" class="form-input" 
                                           value="<?php echo htmlspecialchars($settings[$program_prefix . 'tags'] ?? ''); ?>"
                                           placeholder="<?php echo htmlspecialchars($default_programs[$i-1]['tags']); ?>">
                                </div>
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <h4>Description</h4>
                                        <p>Default: <?php echo htmlspecialchars($default_programs[$i-1]['description']); ?></p>
                                    </div>
                                    <textarea name="<?php echo $program_prefix; ?>description" class="form-input" rows="2"
                                              placeholder="<?php echo htmlspecialchars($default_programs[$i-1]['description']); ?>"><?php echo htmlspecialchars($settings[$program_prefix . 'description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Standards Section -->
            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h3><i class="fas fa-medal"></i> Standards Section</h3>
                </div>
                <div class="card-body">
                    <div class="info-box" style="margin-bottom: 24px;">
                        <i class="fas fa-info-circle"></i>
                        <p>Edit the elite standards displayed on the landing page. Leave fields empty to use default values.</p>
                    </div>
                    
                    <?php 
                    $default_standards = [
                        ['label' => 'Ice Ratio', 'value' => '4:1 Player/Coach'],
                        ['label' => 'Technology', 'value' => 'Video Analysis'],
                        ['label' => 'Facility', 'value' => 'Pro-Grade Gym'],
                        ['label' => 'Methodology', 'value' => 'Periodization']
                    ];
                    for ($i = 1; $i <= 4; $i++): 
                        $standard_prefix = "landing_standard_{$i}_";
                    ?>
                    <div class="setting-item" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: center; padding: 15px; background: var(--bg-main); border-radius: 8px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-dim); margin-bottom: 8px;">Label <?php echo $i; ?> (Default: <?php echo htmlspecialchars($default_standards[$i-1]['label']); ?>)</label>
                            <input type="text" name="<?php echo $standard_prefix; ?>label" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings[$standard_prefix . 'label'] ?? ''); ?>"
                                   placeholder="<?php echo htmlspecialchars($default_standards[$i-1]['label']); ?>">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-dim); margin-bottom: 8px;">Value <?php echo $i; ?> (Default: <?php echo htmlspecialchars($default_standards[$i-1]['value']); ?>)</label>
                            <input type="text" name="<?php echo $standard_prefix; ?>value" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings[$standard_prefix . 'value'] ?? ''); ?>"
                                   placeholder="<?php echo htmlspecialchars($default_standards[$i-1]['value']); ?>">
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="form-actions" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary" data-action="save">
                    <i class="fas fa-save"></i> Save Landing Page Settings
                </button>
                <button type="button" class="btn btn-secondary" onclick="showConfirmModal('This will clear all custom landing page content. After clearing, click Save to apply the changes and use default values.').then(function(ok){ if(ok){ document.querySelectorAll('#landing-tab input, #landing-tab textarea').forEach(el => el.value = ''); } })">
                    <i class="fas fa-undo"></i> Reset to Defaults
                </button>
            </div>
        </form>
    </div>
</div>

<!-- API Keys Tab -->
<div class="tab-content <?php echo $activeTab === 'api_keys' ? 'active' : ''; ?>" id="api_keys-tab">
    <?php
    // Fetch existing API keys for the current admin
    $api_keys_list = [];
    try {
        $ak_stmt = $pdo->prepare("
            SELECT ak.id, ak.key_name, ak.permissions, ak.is_active, ak.created_at, ak.expires_at, ak.last_used,
                   LEFT(ak.api_key, 8) AS key_prefix
            FROM api_keys ak
            WHERE ak.user_id = ?
            ORDER BY ak.created_at DESC
        ");
        $ak_stmt->execute([$user_id]);
        $api_keys_list = $ak_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("API keys fetch error: " . $e->getMessage());
    }
    ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-key"></i> API Key Management</h3>
        </div>
        <div class="card-body">
            <p style="color: var(--text-dim); margin-bottom: 20px;">
                Generate and manage API keys for secure programmatic access to the system. API keys are tied to your admin account and inherit your permissions.
                Use server-side keys so end users never need to handle API credentials directly.
            </p>

            <!-- Generate New Key -->
            <div class="card" style="margin-bottom: 24px; border: 1px solid var(--border);">
                <div class="card-header" style="padding: 12px 20px; background: var(--bg-main);">
                    <h4 style="font-size: 14px; margin: 0; color: var(--primary);"><i class="fas fa-plus-circle"></i> Generate New API Key</h4>
                </div>
                <div class="card-body" style="padding: 20px;">
                    <form method="POST" action="process_settings.php" id="generate-api-key-form">
                        <?php echo csrfTokenInput(); ?>
                        <input type="hidden" name="action" value="generate_api_key">
                        <div class="settings-list">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Key Name</h4>
                                    <p>A descriptive label for this key (e.g. "Mobile App", "Kiosk")</p>
                                </div>
                                <input type="text" name="api_key_name" class="form-input" placeholder="My API Key" required maxlength="100">
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Expiration</h4>
                                    <p>How long the key remains valid</p>
                                </div>
                                <select name="api_key_expiry" class="form-input">
                                    <option value="30">30 Days</option>
                                    <option value="90">90 Days</option>
                                    <option value="180">180 Days</option>
                                    <option value="365">1 Year</option>
                                    <option value="0">No Expiration</option>
                                </select>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Permissions</h4>
                                    <p>Select the access level for this key</p>
                                </div>
                                <select name="api_key_permissions" class="form-input">
                                    <option value="full">Full Access (Admin)</option>
                                    <option value="read_only">Read Only</option>
                                    <option value="bookings">Bookings Only</option>
                                    <option value="sessions">Sessions &amp; Drills</option>
                                </select>
                            </div>
                        </div>
                        <div style="margin-top: 16px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Generate API Key</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Newly Generated Key Display (shown after generation) -->
            <?php
            $new_api_key = null;
            if (isset($_GET['key_generated']) && isset($_SESSION['new_api_key'])) {
                $new_api_key = $_SESSION['new_api_key'];
                unset($_SESSION['new_api_key']);
            }
            ?>
            <?php if ($new_api_key): ?>
            <div class="alert alert-success" style="margin-bottom: 24px; padding: 20px;">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <i class="fas fa-check-circle" style="font-size: 20px; margin-top: 2px;"></i>
                    <div style="flex: 1;">
                        <strong style="display: block; margin-bottom: 8px;">API Key Generated Successfully</strong>
                        <p style="margin-bottom: 12px; font-size: 13px;">Copy this key now — it will not be shown again.</p>
                        <div style="display: flex; align-items: center; gap: 8px; background: rgba(0,0,0,0.2); padding: 10px 14px; border-radius: 6px; font-family: monospace; font-size: 13px; word-break: break-all;">
                            <span id="new-api-key-value"><?php echo htmlspecialchars($new_api_key); ?></span>
                            <button type="button" class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px; white-space: nowrap;" onclick="copyApiKey()">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Existing Keys -->
            <div class="card" style="border: 1px solid var(--border);">
                <div class="card-header" style="padding: 12px 20px; background: var(--bg-main);">
                    <h4 style="font-size: 14px; margin: 0; color: var(--primary);"><i class="fas fa-list"></i> Your API Keys</h4>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($api_keys_list)): ?>
                    <div style="padding: 40px 20px; text-align: center; color: var(--text-dim);">
                        <i class="fas fa-key" style="font-size: 32px; opacity: 0.3; display: block; margin-bottom: 12px;"></i>
                        <p>No API keys generated yet. Create one above to get started.</p>
                    </div>
                    <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border); background: var(--bg-main);">
                                    <th style="padding: 10px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--text-dim); text-transform: uppercase;">Name</th>
                                    <th style="padding: 10px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--text-dim); text-transform: uppercase;">Key Prefix</th>
                                    <th style="padding: 10px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--text-dim); text-transform: uppercase;">Status</th>
                                    <th style="padding: 10px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--text-dim); text-transform: uppercase;">Created</th>
                                    <th style="padding: 10px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--text-dim); text-transform: uppercase;">Expires</th>
                                    <th style="padding: 10px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--text-dim); text-transform: uppercase;">Last Used</th>
                                    <th style="padding: 10px 16px; text-align: right; font-size: 12px; font-weight: 600; color: var(--text-dim); text-transform: uppercase;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($api_keys_list as $ak): 
                                    $is_expired = $ak['expires_at'] && strtotime($ak['expires_at']) < time();
                                    $status_class = $ak['is_active'] && !$is_expired ? 'badge-success' : 'badge-error';
                                    $status_text = !$ak['is_active'] ? 'Revoked' : ($is_expired ? 'Expired' : 'Active');
                                ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 12px 16px; font-size: 13px; color: var(--text-white); font-weight: 600;">
                                        <?php echo htmlspecialchars($ak['key_name'] ?? 'Unnamed'); ?>
                                    </td>
                                    <td style="padding: 12px 16px; font-size: 13px; font-family: monospace; color: var(--text-dim);">
                                        <?php echo htmlspecialchars($ak['key_prefix']); ?>...
                                    </td>
                                    <td style="padding: 12px 16px;">
                                        <span class="badge <?php echo $status_class; ?>" style="font-size: 11px;"><?php echo $status_text; ?></span>
                                    </td>
                                    <td style="padding: 12px 16px; font-size: 12px; color: var(--text-dim);">
                                        <?php echo date('M j, Y', strtotime($ak['created_at'])); ?>
                                    </td>
                                    <td style="padding: 12px 16px; font-size: 12px; color: var(--text-dim);">
                                        <?php echo $ak['expires_at'] ? date('M j, Y', strtotime($ak['expires_at'])) : 'Never'; ?>
                                    </td>
                                    <td style="padding: 12px 16px; font-size: 12px; color: var(--text-dim);">
                                        <?php echo $ak['last_used'] ? date('M j, Y g:i A', strtotime($ak['last_used'])) : 'Never'; ?>
                                    </td>
                                    <td style="padding: 12px 16px; text-align: right;">
                                        <?php if ($ak['is_active'] && !$is_expired): ?>
                                        <form method="POST" action="process_settings.php" style="display: inline;" data-confirm="Are you sure you want to revoke this API key? Any applications using it will lose access.">
                                            <?php echo csrfTokenInput(); ?>
                                            <input type="hidden" name="action" value="revoke_api_key">
                                            <input type="hidden" name="api_key_id" value="<?php echo (int)$ak['id']; ?>">
                                            <button type="submit" class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px;">
                                                <i class="fas fa-ban"></i> Revoke
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="POST" action="process_settings.php" style="display: inline;" data-confirm="Permanently delete this API key?">
                                            <?php echo csrfTokenInput(); ?>
                                            <input type="hidden" name="action" value="delete_api_key">
                                            <input type="hidden" name="api_key_id" value="<?php echo (int)$ak['id']; ?>">
                                            <button type="submit" class="btn btn-secondary" style="padding: 4px 10px; font-size: 12px; color: #ef4444;">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Usage Instructions -->
            <div class="card" style="margin-top: 24px; border: 1px solid var(--border);">
                <div class="card-header" style="padding: 12px 20px; background: var(--bg-main);">
                    <h4 style="font-size: 14px; margin: 0; color: var(--primary);"><i class="fas fa-book"></i> Usage Guide</h4>
                </div>
                <div class="card-body" style="padding: 20px;">
                    <p style="color: var(--text-dim); margin-bottom: 16px; font-size: 13px;">
                        Use these API keys on the server side to authenticate requests. This keeps keys secure and means end users never handle credentials directly.
                    </p>
                    <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                        <p style="font-size: 12px; font-weight: 600; color: var(--text-white); margin-bottom: 8px;">Authorization Header (Recommended)</p>
                        <code style="display: block; font-size: 12px; color: var(--primary); word-break: break-all;">Authorization: Bearer YOUR_API_KEY</code>
                    </div>
                    <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                        <p style="font-size: 12px; font-weight: 600; color: var(--text-white); margin-bottom: 8px;">X-API-Key Header</p>
                        <code style="display: block; font-size: 12px; color: var(--primary); word-break: break-all;">X-API-Key: YOUR_API_KEY</code>
                    </div>

                    <!-- Public App Security Pattern -->
                    <div class="card" style="margin-top: 16px; border: 1px solid var(--border);">
                        <div class="card-header" style="padding: 10px 16px; background: var(--bg-main);">
                            <h4 style="font-size: 13px; margin: 0; color: var(--text-white);"><i class="fas fa-mobile-alt" style="color: var(--primary); margin-right: 6px;"></i> Public App Access (ACWolvesApp)</h4>
                        </div>
                        <div class="card-body" style="padding: 16px;">
                            <p style="color: var(--text-dim); font-size: 13px; margin-bottom: 12px;">
                                For public applications such as the ACWolvesApp mobile app, do <strong>not</strong> embed API keys in the app source code. Instead, use the built-in per-user authentication flow:
                            </p>
                            <ol style="color: var(--text-dim); font-size: 13px; padding-left: 20px; margin-bottom: 12px;">
                                <li style="margin-bottom: 6px;">The user logs in with their email &amp; password via <code style="color: var(--primary);">POST /v1/auth/login</code>.</li>
                                <li style="margin-bottom: 6px;">The API returns a per-user Bearer token (no shared key required).</li>
                                <li style="margin-bottom: 6px;">The app stores the token securely on the device (e.g. Expo SecureStore).</li>
                                <li style="margin-bottom: 6px;">All subsequent requests include the token as <code style="color: var(--primary);">Authorization: Bearer &lt;token&gt;</code>.</li>
                            </ol>
                            <p style="color: var(--text-dim); font-size: 13px;">
                                This way, no secrets are stored in the public repository. Each user's token is unique, time-limited, and revocable from this panel.
                            </p>
                        </div>
                    </div>

                    <div class="info-box" style="margin-top: 16px;">
                        <i class="fas fa-shield-alt"></i>
                        <p style="font-size: 13px;">
                            <strong>Security Best Practice:</strong> Store API keys in server-side environment variables or config files — never embed them in client-side code.
                            For server-to-server integrations, use the admin-generated keys above. For public-facing apps, rely on per-user authentication.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Encryption Tab -->
<div class="tab-content <?php echo $activeTab === 'encryption' ? 'active' : ''; ?>" id="encryption-tab">
    <?php
    require_once __DIR__ . '/../lib/encryption.php';
    $encryption_configured = FieldEncryption::isConfigured();
    
    // Check encryption_enabled setting
    $encryption_enabled_setting = '1'; // default enabled
    try {
        $enc_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'pii_encryption_enabled'");
        $enc_stmt->execute();
        $enc_row = $enc_stmt->fetch(PDO::FETCH_ASSOC);
        if ($enc_row) {
            $encryption_enabled_setting = $enc_row['setting_value'];
        }
    } catch (PDOException $e) {
        // Setting may not exist yet
    }
    
    // Determine if the current user is the first admin (lowest ID with admin role)
    $is_first_admin = false;
    try {
        $first_admin_stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        $first_admin_stmt->execute();
        $first_admin_row = $first_admin_stmt->fetch(PDO::FETCH_ASSOC);
        if ($first_admin_row && (int)$first_admin_row['id'] === (int)$user_id) {
            $is_first_admin = true;
        }
    } catch (PDOException $e) {
        // If we can't determine, default to not allowing
    }
    ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-shield-halved"></i> PII Encryption at Rest</h3>
            <?php if ($encryption_configured && $encryption_enabled_setting === '1'): ?>
                <span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span>
            <?php elseif ($encryption_configured && $encryption_enabled_setting !== '1'): ?>
                <span class="badge badge-warning"><i class="fas fa-pause-circle"></i> Key Configured, Encryption Disabled</span>
            <?php else: ?>
                <span class="badge badge-error"><i class="fas fa-exclamation-triangle"></i> Not Configured</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <p style="color: var(--text-dim); margin-bottom: 20px;">
                Personal Identifiable Information (PII) such as names, phone numbers, addresses, and birthdates can be encrypted at rest in the database using AES-256-CBC encryption. 
                This ensures that even if the database is compromised, sensitive data remains protected.
            </p>

            <!-- Status Overview -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <div style="padding: 16px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; text-align: center;">
                    <i class="fas fa-key" style="font-size: 24px; color: <?= $encryption_configured ? '#10b981' : '#ef4444' ?>; margin-bottom: 8px; display: block;"></i>
                    <div style="font-size: 13px; font-weight: 700; color: var(--text-white);">Encryption Key</div>
                    <div style="font-size: 12px; color: var(--text-dim); margin-top: 4px;"><?= $encryption_configured ? 'Configured' : 'Not Set' ?></div>
                </div>
                <div style="padding: 16px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; text-align: center;">
                    <i class="fas fa-toggle-<?= $encryption_enabled_setting === '1' ? 'on' : 'off' ?>" style="font-size: 24px; color: <?= $encryption_enabled_setting === '1' ? '#10b981' : '#ef4444' ?>; margin-bottom: 8px; display: block;"></i>
                    <div style="font-size: 13px; font-weight: 700; color: var(--text-white);">Encryption Status</div>
                    <div style="font-size: 12px; color: var(--text-dim); margin-top: 4px;"><?= $encryption_enabled_setting === '1' ? 'Enabled' : 'Disabled' ?></div>
                </div>
                <div style="padding: 16px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; text-align: center;">
                    <i class="fas fa-shield-halved" style="font-size: 24px; color: var(--primary); margin-bottom: 8px; display: block;"></i>
                    <div style="font-size: 13px; font-weight: 700; color: var(--text-white);">Algorithm</div>
                    <div style="font-size: 12px; color: var(--text-dim); margin-top: 4px;">AES-256-CBC</div>
                </div>
            </div>

            <!-- Toggle Encryption -->
            <?php if ($encryption_configured): ?>
            <form method="POST" action="process_settings.php">
                <?php echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="toggle_pii_encryption">
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h3><i class="fas fa-toggle-on"></i> Enable / Disable PII Encryption</h3>
                    </div>
                    <div class="card-body">
                        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 12px; background: rgba(107, 70, 193, 0.05); border: 1px solid var(--border); border-radius: 8px;">
                            <input type="checkbox" name="pii_encryption_enabled" value="1" <?= $encryption_enabled_setting === '1' ? 'checked' : '' ?>>
                            <div>
                                <span style="color: var(--text-white); font-weight: 600;">Enable PII Encryption at Rest</span>
                                <p style="color: var(--text-dim); font-size: 12px; margin: 4px 0 0 0;">When enabled, all PII fields (names, phone numbers, addresses, birthdates) will be encrypted before being stored in the database.</p>
                            </div>
                        </label>
                        <div style="margin-top: 16px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Encryption Setting</button>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>

            <!-- Protected Fields -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h3><i class="fas fa-list-check"></i> Protected PII Fields</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div>
                            <h4 style="color: var(--text-white); margin-bottom: 12px; font-size: 14px;"><i class="fas fa-user" style="color: var(--primary); margin-right: 6px;"></i> User PII Fields</h4>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <li style="padding: 6px 0; color: var(--text-dim); font-size: 13px;"><i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i> First Name</li>
                                <li style="padding: 6px 0; color: var(--text-dim); font-size: 13px;"><i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i> Last Name</li>
                                <li style="padding: 6px 0; color: var(--text-dim); font-size: 13px;"><i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i> Phone Number</li>
                                <li style="padding: 6px 0; color: var(--text-dim); font-size: 13px;"><i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i> Birth Date</li>
                            </ul>
                        </div>
                        <div>
                            <h4 style="color: var(--text-white); margin-bottom: 12px; font-size: 14px;"><i class="fas fa-user-tie" style="color: var(--primary); margin-right: 6px;"></i> Employee PII Fields</h4>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <li style="padding: 6px 0; color: var(--text-dim); font-size: 13px;"><i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i> Name &amp; Email</li>
                                <li style="padding: 6px 0; color: var(--text-dim); font-size: 13px;"><i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i> Phone &amp; DOB</li>
                                <li style="padding: 6px 0; color: var(--text-dim); font-size: 13px;"><i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i> Address</li>
                                <li style="padding: 6px 0; color: var(--text-dim); font-size: 13px;"><i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i> Emergency Contact</li>
                            </ul>
                        </div>
                        <div>
                            <h4 style="color: var(--text-white); margin-bottom: 12px; font-size: 14px;"><i class="fas fa-shopping-cart" style="color: var(--primary); margin-right: 6px;"></i> Customer PII Fields</h4>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <li style="padding: 6px 0; color: var(--text-dim); font-size: 13px;"><i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i> Customer Name &amp; Email</li>
                                <li style="padding: 6px 0; color: var(--text-dim); font-size: 13px;"><i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i> Phone Number</li>
                                <li style="padding: 6px 0; color: var(--text-dim); font-size: 13px;"><i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i> Billing Address</li>
                                <li style="padding: 6px 0; color: var(--text-dim); font-size: 13px;"><i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i> Shipping Address</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Setup / Update Encryption Key -->
            <?php if (!$is_first_admin): ?>
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h3><i class="fas fa-lock"></i> Encryption Key Management</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" style="margin: 0;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Only the original administrator account (first created during setup) can configure or change the encryption key.</span>
                    </div>
                </div>
            </div>
            <?php elseif (!$encryption_configured): ?>
            <div class="card" style="border-color: #f59e0b; margin-bottom: 20px;">
                <div class="card-header" style="border-bottom-color: #f59e0b;">
                    <h3><i class="fas fa-wrench" style="color: #f59e0b;"></i> Setup Encryption Key</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" style="margin-bottom: 16px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Encryption key is not configured. Enter or generate a key below to enable PII encryption.</span>
                    </div>
                    <form method="POST" action="process_settings.php" onsubmit="return validateEncryptionKey()">
                        <?php echo csrfTokenInput(); ?>
                        <input type="hidden" name="action" value="save_encryption_key">
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label" style="color: var(--text-white); font-weight: 600;">Encryption Key (64-character hex string)</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" name="encryption_key" id="encryption-key-input" class="form-input" 
                                       placeholder="Enter or generate a 64-character hex key" 
                                       pattern="[a-fA-F0-9]{64}" maxlength="64" required
                                       style="font-family: monospace; flex: 1;">
                                <button type="button" class="btn btn-secondary" onclick="generateEncryptionKey()" title="Generate a random key">
                                    <i class="fas fa-random"></i> Generate
                                </button>
                            </div>
                            <p style="color: var(--text-dim); font-size: 11px; margin-top: 4px;">Must be exactly 64 hexadecimal characters (0-9, a-f). This key will be saved to your environment file.</p>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Encryption Key</button>
                        </div>
                    </form>
                    <div class="alert alert-error" style="margin-top: 16px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <strong>Important:</strong> Back up your encryption key securely. If the key is lost, encrypted data cannot be recovered. 
                            Store a copy in a secure password manager or offline backup.
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h3><i class="fas fa-key"></i> Update Encryption Key</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="process_settings.php" onsubmit="return validateEncryptionKeyUpdate()">
                        <?php echo csrfTokenInput(); ?>
                        <input type="hidden" name="action" value="save_encryption_key">
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label" style="color: var(--text-white); font-weight: 600;">Current Encryption Key</label>
                            <input type="text" name="current_encryption_key" id="current-encryption-key-input" class="form-input" 
                                   placeholder="Enter your current 64-character hex key to verify" 
                                   pattern="[a-fA-F0-9]{64}" maxlength="64" required
                                   style="font-family: monospace;">
                            <p style="color: var(--text-dim); font-size: 11px; margin-top: 4px;">You must enter the current encryption key before you can change it.</p>
                        </div>
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label" style="color: var(--text-white); font-weight: 600;">New Encryption Key (64-character hex string)</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" name="encryption_key" id="encryption-key-input" class="form-input" 
                                       placeholder="Enter a new 64-character hex key" 
                                       pattern="[a-fA-F0-9]{64}" maxlength="64" required
                                       style="font-family: monospace; flex: 1;">
                                <button type="button" class="btn btn-secondary" onclick="generateEncryptionKey()" title="Generate a random key">
                                    <i class="fas fa-random"></i> Generate
                                </button>
                            </div>
                            <p style="color: var(--text-dim); font-size: 11px; margin-top: 4px;">Must be exactly 64 hexadecimal characters (0-9, a-f).</p>
                        </div>
                        <div class="alert alert-error" style="margin-bottom: 16px;">
                            <i class="fas fa-exclamation-circle"></i>
                            <div>
                                <strong>Critical Warning:</strong> Changing the encryption key will make all previously encrypted data unreadable unless you decrypt it first with the current key. This action cannot be undone. Only change this if you know what you are doing.
                            </div>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update Encryption Key</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- reCAPTCHA Configuration -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h3><i class="fas fa-robot"></i> reCAPTCHA v3 Configuration</h3>
                    <?php if (!empty($settings['recaptcha_site_key']) && !empty($settings['recaptcha_secret_key'])): ?>
                        <span class="badge badge-success"><i class="fas fa-check-circle"></i> Configured</span>
                    <?php else: ?>
                        <span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Not Configured</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p style="color: var(--text-dim); margin-bottom: 16px;">
                        Google reCAPTCHA v3 provides invisible bot protection on the registration form. Obtain keys from 
                        <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener" style="color: var(--primary);">Google reCAPTCHA Admin Console</a>.
                        When configured, registration submissions are scored automatically — no user interaction required.
                    </p>
                    <form method="POST" action="process_settings.php">
                        <?php echo csrfTokenInput(); ?>
                        <input type="hidden" name="action" value="update_recaptcha">
                        <div class="settings-list">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>reCAPTCHA Site Key</h4>
                                    <p>Public site key used in the frontend (visible to users)</p>
                                </div>
                                <input type="text" name="recaptcha_site_key" class="form-input"
                                       value="<?php echo htmlspecialchars($settings['recaptcha_site_key'] ?? ''); ?>"
                                       placeholder="6Lxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" style="min-width: 300px; font-family: monospace;"
                                       maxlength="100" pattern="[a-zA-Z0-9_\-]{20,100}" title="Enter a valid reCAPTCHA site key">
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>reCAPTCHA Secret Key</h4>
                                    <p>Private secret key for server-side verification<?php echo !empty($settings['recaptcha_secret_key']) ? ' (currently set)' : ''; ?></p>
                                </div>
                                <input type="password" name="recaptcha_secret_key" class="form-input"
                                       placeholder="<?php echo !empty($settings['recaptcha_secret_key']) ? 'Leave blank to keep current key' : '6Lxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'; ?>"
                                       style="min-width: 300px; font-family: monospace;"
                                       maxlength="100">
                            </div>
                        </div>
                        <div style="margin-top: 16px; display: flex; gap: 12px; align-items: center;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save reCAPTCHA Settings</button>
                            <?php if (!empty($settings['recaptcha_site_key'])): ?>
                                <span style="color: var(--text-dim); font-size: 12px;"><i class="fas fa-info-circle"></i> Leave fields blank to keep current values. Clear both fields to disable reCAPTCHA.</span>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- NDI Cameras Tab -->
<div class="tab-content <?php echo $activeTab === 'ndi_cameras' ? 'active' : ''; ?>" id="ndi_cameras-tab">
    <?php
    // Fetch NDI cameras from database
    $ndi_cameras = [];
    try {
        $ndi_stmt = $pdo->query("SELECT * FROM ndi_cameras ORDER BY name ASC");
        $ndi_cameras = $ndi_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table may not exist yet
        error_log("NDI cameras fetch error: " . $e->getMessage());
    }
    ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-video"></i> NDI Camera Management</h3>
        </div>
        <div class="card-body">
            <p style="color: var(--text-dim); margin-bottom: 20px;">
                Add and manage NDI (Network Device Interface) cameras for video capture across your facility. NDI cameras are network-connected video sources that can be used for recording sessions, evaluations, and live streaming.
            </p>

            <!-- Add New NDI Camera -->
            <div class="card" style="margin-bottom: 24px; border: 1px solid var(--border);">
                <div class="card-header" style="padding: 12px 20px; background: var(--bg-main);">
                    <h4 style="font-size: 14px; margin: 0; color: var(--primary);"><i class="fas fa-plus-circle"></i> Add New NDI Camera</h4>
                </div>
                <div class="card-body" style="padding: 20px;">
                    <form method="POST" action="process_settings.php" id="add-ndi-camera-form">
                        <?php echo csrfTokenInput(); ?>
                        <input type="hidden" name="action" value="add_ndi_camera">
                        <div class="settings-list">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Camera Name</h4>
                                    <p>A descriptive label (e.g. "Rink 1 - Center Ice", "Goal Camera")</p>
                                </div>
                                <input type="text" name="ndi_camera_name" class="form-input" placeholder="Main Rink Camera" required maxlength="255">
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>IP Address / Hostname</h4>
                                    <p>The network address of the NDI source</p>
                                </div>
                                <input type="text" name="ndi_camera_ip" class="form-input" placeholder="192.168.1.100" required maxlength="255">
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Port</h4>
                                    <p>NDI port number (default: 5960)</p>
                                </div>
                                <input type="number" name="ndi_camera_port" class="form-input" value="5960" min="1" max="65535">
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>NDI Source Name</h4>
                                    <p>Optional NDI source identifier for discovery</p>
                                </div>
                                <input type="text" name="ndi_camera_ndi_name" class="form-input" placeholder="MYCAMERA (Source 1)" maxlength="255">
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Location</h4>
                                    <p>Physical location description</p>
                                </div>
                                <input type="text" name="ndi_camera_location" class="form-input" placeholder="Main Rink - South End" maxlength="255">
                            </div>
                        </div>
                        <div style="margin-top: 20px; text-align: right;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Camera
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Existing NDI Cameras -->
            <div class="card" style="border: 1px solid var(--border);">
                <div class="card-header" style="padding: 12px 20px; background: var(--bg-main);">
                    <h4 style="font-size: 14px; margin: 0; color: var(--primary);"><i class="fas fa-list"></i> Configured Cameras (<?php echo count($ndi_cameras); ?>)</h4>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($ndi_cameras)): ?>
                        <div style="padding: 40px; text-align: center; color: var(--text-dim);">
                            <i class="fas fa-video-slash" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.3;"></i>
                            <p>No NDI cameras configured yet. Add your first camera above.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border);">
                                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">Name</th>
                                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">IP Address</th>
                                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">Port</th>
                                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">NDI Name</th>
                                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">Location</th>
                                        <th style="padding: 12px 16px; text-align: center; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">Status</th>
                                        <th style="padding: 12px 16px; text-align: center; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ndi_cameras as $camera): ?>
                                    <tr id="ndi-camera-row-<?php echo (int)$camera['id']; ?>" style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 12px 16px; color: var(--text-white); font-weight: 600;">
                                            <i class="fas fa-video" style="color: var(--primary-light); margin-right: 8px;"></i>
                                            <?php echo htmlspecialchars($camera['name']); ?>
                                        </td>
                                        <td style="padding: 12px 16px; color: var(--text-secondary);">
                                            <code style="background: var(--bg-main); padding: 2px 8px; border-radius: 4px; font-size: 13px;"><?php echo htmlspecialchars($camera['ip_address']); ?></code>
                                        </td>
                                        <td style="padding: 12px 16px; color: var(--text-secondary);"><?php echo (int)$camera['port']; ?></td>
                                        <td style="padding: 12px 16px; color: var(--text-secondary);"><?php echo htmlspecialchars($camera['ndi_name'] ?? '—'); ?></td>
                                        <td style="padding: 12px 16px; color: var(--text-secondary);"><?php echo htmlspecialchars($camera['location'] ?? '—'); ?></td>
                                        <td style="padding: 12px 16px; text-align: center;">
                                            <?php if ($camera['is_active']): ?>
                                                <span class="badge badge-success" style="font-size: 11px;"><i class="fas fa-check-circle"></i> Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-error" style="font-size: 11px;"><i class="fas fa-times-circle"></i> Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 16px; text-align: center;">
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="toggleNdiCamera(<?php echo (int)$camera['id']; ?>, <?php echo $camera['is_active'] ? 0 : 1; ?>)" title="<?php echo $camera['is_active'] ? 'Disable' : 'Enable'; ?>">
                                                <i class="fas fa-<?php echo $camera['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="editNdiCamera(<?php echo (int)$camera['id']; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteNdiCamera(<?php echo (int)$camera['id']; ?>, <?php echo htmlspecialchars(json_encode($camera['name']), ENT_QUOTES, 'UTF-8'); ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Game Plan Settings Tab -->
<div class="tab-content <?php echo $activeTab === 'gameplan' ? 'active' : ''; ?>" id="gameplan-tab">
    <?php
    // Load current gameplan settings from database
    $gameplan_settings = [];
    try {
        $gp_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'gameplan_%'");
        $gp_stmt->execute();
        while ($gp_row = $gp_stmt->fetch()) {
            $gameplan_settings[$gp_row['setting_key']] = $gp_row['setting_value'];
        }
    } catch (PDOException $e) {
        // Table or column may not exist yet
    }

    $gp_companion_url = $gameplan_settings['gameplan_companion_url'] ?? '';
    $gp_companion_api_key = $gameplan_settings['gameplan_companion_api_key'] ?? '';
    $gp_gameplan_url = $gameplan_settings['gameplan_app_url'] ?? 'https://gameplan.arcticwolves.ca';
    ?>

    <!-- Companion Server Configuration -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-server"></i> Companion Server</h3>
            <span class="badge <?= $gp_companion_url ? 'badge-success' : 'badge-warning' ?>" id="gpCompanionStatus">
                <?= $gp_companion_url ? 'Configured' : 'Not Configured' ?>
            </span>
        </div>
        <div class="card-body">
            <p style="color:var(--text-secondary);font-size:13px;margin-bottom:20px;">
                The companion server handles hardware-accelerated video encoding, decoding, and clip extraction.
                It runs alongside the Game Plan app and needs access to the same video storage.
            </p>
            <form method="POST" action="process_gameplan_settings.php" data-form-type="gameplan_companion">
                <?php echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="save_companion">

                <div class="settings-list">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Companion Server URL</h4>
                            <p>The base URL of the companion server (e.g. http://companion:5100 for Docker)</p>
                        </div>
                        <input type="url" name="companion_url" class="form-input" style="width: auto; min-width: 300px;"
                               value="<?= htmlspecialchars($gp_companion_url) ?>"
                               placeholder="http://localhost:5100">
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>API Key</h4>
                            <p>Must match the API_KEY configured on the companion server</p>
                        </div>
                        <div style="position:relative;display:flex;align-items:center;">
                            <input type="password" name="companion_api_key" id="gpCompanionApiKey" class="form-input" style="width: auto; min-width: 300px; padding-right:40px;"
                                   value="<?= htmlspecialchars($gp_companion_api_key) ?>"
                                   placeholder="Shared secret key">
                            <button type="button" onclick="gpToggleVisibility('gpCompanionApiKey', this)" aria-label="Toggle visibility"
                                    style="position:absolute;right:10px;background:none;border:none;cursor:pointer;color:var(--text-muted);padding:5px;">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Game Plan App URL</h4>
                            <p>The URL of the Game Plan (Video Review) application</p>
                        </div>
                        <input type="url" name="gameplan_app_url" class="form-input" style="width: auto; min-width: 300px;"
                               value="<?= htmlspecialchars($gp_gameplan_url) ?>"
                               placeholder="https://gameplan.arcticwolves.ca">
                    </div>
                </div>

                <div class="form-actions" style="display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary" data-action="save"><i class="fas fa-save"></i> Save Companion Settings</button>
                    <button type="button" class="btn btn-secondary" id="gpTestCompanionBtn" onclick="gpTestCompanion()">
                        <i class="fas fa-plug"></i> Test Connection
                    </button>
                </div>
            </form>
        </div>
    </div>


</div>

<!-- NDI Camera Edit Modal -->
<div id="ndi-camera-edit-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-video"></i> Edit NDI Camera</h3>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeNdiEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="edit-ndi-camera-form">
                <input type="hidden" name="ndi_camera_id" id="edit-ndi-camera-id">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-white);">Camera Name</label>
                    <input type="text" id="edit-ndi-camera-name" class="form-input" style="width: 100%;" required maxlength="255">
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-white);">IP Address / Hostname</label>
                    <input type="text" id="edit-ndi-camera-ip" class="form-input" style="width: 100%;" required maxlength="255">
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-white);">Port</label>
                    <input type="number" id="edit-ndi-camera-port" class="form-input" style="width: 100%;" min="1" max="65535">
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-white);">NDI Source Name</label>
                    <input type="text" id="edit-ndi-camera-ndi-name" class="form-input" style="width: 100%;" maxlength="255">
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-white);">Location</label>
                    <input type="text" id="edit-ndi-camera-location" class="form-input" style="width: 100%;" maxlength="255">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeNdiEditModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveNdiCamera()">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>
<div id="smtp-test-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-envelope"></i> Test SMTP Connection</h3>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeSmtpTestModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Enter an email address to send a test message:</p>
            <div class="form-group" style="margin-top: 16px;">
                <input type="email" id="smtp-test-email" class="form-input" placeholder="email@example.com" style="width: 100%;">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeSmtpTestModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="sendSmtpTestEmail()">
                <i class="fas fa-paper-plane"></i> Send Test Email
            </button>
        </div>
    </div>
</div>

<script>
function switchToolTab(tabName) {
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
    
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    document.getElementById(tabName + '-tab').classList.add('active');
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
}

// Copy API key to clipboard
function copyApiKey() {
    const keyEl = document.getElementById('new-api-key-value');
    if (!keyEl) return;
    const key = keyEl.textContent.trim();
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(key).then(function() {
            showToast('API key copied to clipboard!', 'success');
        });
    } else {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = key;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('API key copied to clipboard!', 'success');
    }
}

// SMTP Test Modal Functions
function testSmtpConnection() {
    document.getElementById('smtp-test-modal').classList.add('active');
    document.getElementById('smtp-test-email').value = '';
    document.getElementById('smtp-test-email').focus();
}

function closeSmtpTestModal() {
    document.getElementById('smtp-test-modal').classList.remove('active');
}

function sendSmtpTestEmail() {
    const testEmail = document.getElementById('smtp-test-email').value.trim();
    if (!testEmail) {
        showToast('Please enter an email address.', 'error');
        return;
    }
    
    // Validate email format
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(testEmail)) {
        showToast('Please enter a valid email address.', 'error');
        return;
    }
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    
    // Get CSRF token
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    fetch('process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=test_smtp&test_email=${encodeURIComponent(testEmail)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        closeSmtpTestModal();
        
        if (data.success) {
            showToast('Success: Test email sent successfully! Check your inbox for the test email.', 'success');
        } else {
            showToast('Error: Failed to send test email: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        closeSmtpTestModal();
        showToast('Error: Failed to test SMTP connection', 'error');
        console.error('Error:', error);
    });
}

// Test Google Maps API Key
function testGoogleMapsAPI() {
    const apiKeyInput = document.querySelector('input[name="google_maps_api_key"]');
    const apiKey = apiKeyInput ? apiKeyInput.value.trim() : '';
    
    if (!apiKey) {
        showToast('Please enter a Google Maps API key first.', 'error');
        return;
    }
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    // Test via server-side endpoint to avoid CORS issues
    fetch('process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=test_google_maps&google_maps_api_key=${encodeURIComponent(apiKey)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.success) {
            showToast('Success: ' + data.message, 'success');
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        showToast('Error: Failed to test Google Maps API. Please try again.', 'error');
        console.error('Error:', error);
    });
}

// Theme functions
function toggleThemeLogoInput() {
    const method = document.querySelector('input[name="logo_method"]:checked');
    if (!method) return;
    
    const uploadRow = document.getElementById('theme-logo-upload-row');
    const urlRow = document.getElementById('theme-logo-url-row');
    
    if (uploadRow) uploadRow.style.display = method.value === 'upload' ? '' : 'none';
    if (urlRow) urlRow.style.display = method.value === 'url' ? '' : 'none';
}

function toggleCenterIceLogoInput() {
    const method = document.querySelector('input[name="center_ice_logo_method"]:checked');
    if (!method) return;
    
    const uploadRow = document.getElementById('center-ice-logo-upload-row');
    const urlRow = document.getElementById('center-ice-logo-url-row');
    
    if (uploadRow) uploadRow.style.display = method.value === 'upload' ? '' : 'none';
    if (urlRow) urlRow.style.display = method.value === 'url' ? '' : 'none';
}

async function removeCenterIceLogo() {
    if (!await showConfirmModal('Are you sure you want to remove the center ice logo?')) return;
    
    const formData = new FormData();
    formData.append('action', 'remove_center_ice_logo');
    formData.append('csrf_token', getCsrfToken());
    
    fetch('process_theme.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        // Check if redirect happened (success)
        if (response.redirected || response.status === 200) {
            location.reload();
        } else {
            throw new Error('Unexpected response');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to remove center ice logo', 'error');
    });
}

function previewBcBackground(input, previewId) {
    var preview = document.getElementById(previewId);
    if (!preview) return;
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="max-height: 80px; max-width: 200px;">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

async function resetThemeColors() {
    if (!await showConfirmModal('Reset all theme colors to default values?')) return;
    
    const defaults = {
        'theme_primary_color': '#6B46C1',
        'theme_secondary_color': '#c0c0c0',
        'theme_background_color': '#06080b'
    };
    
    for (const [id, value] of Object.entries(defaults)) {
        const colorInput = document.getElementById(id);
        const textInput = document.getElementById(id + '_text');
        if (colorInput) colorInput.value = value;
        if (textInput) textInput.value = value;
    }
}

// Sync color pickers with text inputs
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[type="color"]').forEach(input => {
        input.addEventListener('input', function() {
            const textId = this.id + '_text';
            const textInput = document.getElementById(textId);
            if (textInput) textInput.value = this.value;
        });
    });
});

// Database Tools functions
function getCsrfToken() {
    return document.querySelector('input[name="csrf_token"]')?.value || '';
}

function showDbStatus(message, type) {
    const statusDiv = document.getElementById('db-status-message');
    if (!statusDiv) return;
    
    const colors = {
        success: { bg: 'rgba(16, 185, 129, 0.1)', border: '#10b981', text: '#10b981' },
        error: { bg: 'rgba(239, 68, 68, 0.1)', border: '#ef4444', text: '#ef4444' },
        info: { bg: 'rgba(59, 130, 246, 0.1)', border: '#3b82f6', text: '#3b82f6' }
    };
    const c = colors[type] || colors.info;
    
    statusDiv.style.cssText = `display: block; background: ${c.bg}; border: 1px solid ${c.border}; color: ${c.text}; padding: 16px; border-radius: 8px;`;
    statusDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}"></i> ${message}`;
    
    if (type === 'success') {
        setTimeout(() => { statusDiv.style.display = 'none'; }, 5000);
    }
}

function runDatabaseBackup(btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Backing up...';
    
    fetch('process_database_backup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=manual_backup&csrf_token=${encodeURIComponent(getCsrfToken())}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Response was not JSON:', text);
            throw new Error('Invalid server response');
        }
    })
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        showDbStatus(data.message || (data.success ? 'Backup completed' : 'Backup failed'), data.success ? 'success' : 'error');
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        showDbStatus(error.message || 'Network error: Failed to create backup', 'error');
        console.error('Backup error:', error);
    });
}

async function runDatabaseOptimize(btn) {
    if (!await showConfirmModal('This will check, repair and optimize all database tables. Continue?')) return;
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Optimizing...';
    
    fetch('process_database_backup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=repair_optimize&csrf_token=${encodeURIComponent(getCsrfToken())}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Response was not JSON:', text);
            throw new Error('Invalid server response');
        }
    })
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        showDbStatus(data.message || (data.success ? 'Optimization completed' : 'Optimization failed'), data.success ? 'success' : 'error');
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        showDbStatus(error.message || 'Network error: Failed to optimize database', 'error');
        console.error('Optimize error:', error);
    });
}

async function runClearCache(btn) {
    if (!await showConfirmModal('Clear all cached data and temporary files?')) return;
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing...';
    
    fetch('process_database_backup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=clear_cache&csrf_token=${encodeURIComponent(getCsrfToken())}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Response was not JSON:', text);
            throw new Error('Invalid server response');
        }
    })
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        showDbStatus(data.message || (data.success ? 'Cache cleared' : 'Failed to clear cache'), data.success ? 'success' : 'error');
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        showDbStatus(error.message || 'Network error: Failed to clear cache', 'error');
        console.error('Clear cache error:', error);
    });
}



function testRustFSConnection() {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';

    const formData = new FormData(document.getElementById('rustfs-form'));
    formData.append('action', 'test_rustfs');

    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;

        if (data.success) {
            showToast('Success: RustFS Connection Successful! ' + (data.message || 'Connected successfully.'), 'success');
        } else {
            showToast('Error: RustFS Connection Failed. ' + (data.message || 'Could not connect to server'), 'error');
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        showToast('Error testing RustFS connection', 'error');
        console.error('Error:', error);
    });
}

// GitHub Updater functions
function githubAddLogEntry(message, type) {
    const container = document.getElementById('githubLogContainer');
    if (!container) return;
    const logEntry = document.createElement('div');
    logEntry.style.cssText = 'padding: 6px 0; display: flex; align-items: start; gap: 10px;';
    
    let color = 'var(--text-dim)';
    if (type === 'success') color = '#10b981';
    else if (type === 'warning') color = '#f59e0b';
    else if (type === 'error') color = '#ef4444';
    else if (type === 'info') color = '#60a5fa';
    
    logEntry.innerHTML = `
        <span style="color: var(--text-dim); flex-shrink: 0;">${new Date().toLocaleTimeString()}</span>
        <span style="flex: 1; color: ${color};">${message}</span>
    `;
    container.appendChild(logEntry);
    container.scrollTop = container.scrollHeight;
}

async function githubCheckForUpdates() {
    const checkBtn = document.getElementById('githubCheckBtn');
    const updateBtn = document.getElementById('githubUpdateBtn');
    const badge = document.getElementById('githubUpdateBadge');
    const details = document.getElementById('githubUpdateDetails');
    const versionInfo = document.getElementById('githubCurrentVersion');
    const resultBanner = document.getElementById('githubResultBanner');
    
    checkBtn.disabled = true;
    checkBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    resultBanner.style.display = 'none';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    try {
        const response = await fetch('process_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=check_updates&csrf_token=${encodeURIComponent(csrfToken)}`
        });
        
        if (!response.ok) {
            throw new Error(`Server error (HTTP ${response.status})`);
        }
        
        let data;
        try {
            data = await response.json();
        } catch (parseError) {
            throw new Error('Invalid response from server');
        }
        
        if (data.success) {
            const currentSha = data.current_sha ? data.current_sha.substring(0, 7) : 'Not set';
            const latestSha = data.latest_commit ? data.latest_commit.sha.substring(0, 7) : 'Unknown';
            
            versionInfo.innerHTML = `Current: <code style="color: #10b981; background: var(--bg-dark); padding: 2px 8px; border-radius: 4px;">${currentSha}</code>`;
            
            if (data.has_updates) {
                badge.style.display = 'inline-block';
                badge.style.cssText = 'display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid #f59e0b;';
                badge.textContent = 'Update Available';
                updateBtn.disabled = false;
            } else {
                badge.style.display = 'inline-block';
                badge.style.cssText = 'display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid #10b981;';
                badge.textContent = 'Up to Date';
                updateBtn.disabled = true;
            }
            
            if (data.latest_commit) {
                details.style.display = 'block';
                document.getElementById('githubLatestSha').textContent = data.latest_commit.sha ? data.latest_commit.sha.substring(0, 7) : '';
                document.getElementById('githubLatestMessage').textContent = data.latest_commit.message || '';
                document.getElementById('githubLatestAuthor').textContent = data.latest_commit.author || '';
                document.getElementById('githubLatestDate').textContent = data.latest_commit.date ? new Date(data.latest_commit.date).toLocaleString() : '';
            }
        } else {
            versionInfo.textContent = 'Error checking for updates';
            resultBanner.style.cssText = 'display: block; background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; border-radius: 8px; padding: 20px; margin-top: 20px;';
            resultBanner.innerHTML = `<p style="margin: 0;"><i class="fas fa-exclamation-triangle"></i> ${data.message || 'Failed to check for updates'}</p>`;
        }
    } catch (error) {
        console.error('Error:', error);
        versionInfo.textContent = 'Error checking for updates';
        resultBanner.style.cssText = 'display: block; background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; border-radius: 8px; padding: 20px; margin-top: 20px;';
        resultBanner.innerHTML = `<p style="margin: 0;"><i class="fas fa-exclamation-triangle"></i> ${error.message || 'Network error or server not responding'}</p>`;
    }
    
    checkBtn.disabled = false;
    checkBtn.innerHTML = '<i class="fas fa-sync"></i> Check for Updates';
}

async function githubApplyUpdate() {
    if (!await showConfirmModal('Apply the latest update from GitHub?\n\nThis will update system files. Your configuration, uploads, and encryption keys are preserved automatically.\n\nThe page will reload when complete.')) {
        return;
    }
    
    const checkBtn = document.getElementById('githubCheckBtn');
    const updateBtn = document.getElementById('githubUpdateBtn');
    const progressSection = document.getElementById('githubProgressSection');
    const progressBar = document.getElementById('githubProgressBar');
    const resultBanner = document.getElementById('githubResultBanner');
    const logContainer = document.getElementById('githubLogContainer');
    
    checkBtn.disabled = true;
    updateBtn.disabled = true;
    updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    progressSection.style.display = 'block';
    logContainer.innerHTML = '';
    resultBanner.style.display = 'none';
    progressBar.style.width = '10%';
    
    githubAddLogEntry('Starting update from GitHub...', 'info');
    githubAddLogEntry('Backing up configuration files...', 'info');
    progressBar.style.width = '20%';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const maxRetries = 2;
    const fetchTimeoutMs = 5 * 60 * 1000; // 5 minute timeout for the fetch request
    
    for (let attempt = 0; attempt <= maxRetries; attempt++) {
        try {
            if (attempt > 0) {
                githubAddLogEntry(`Retrying update (attempt ${attempt + 1}/${maxRetries + 1})...`, 'warning');
                await new Promise(resolve => setTimeout(resolve, Math.pow(2, attempt) * 2000));
            }
            
            githubAddLogEntry('Downloading files from repository...', 'info');
            progressBar.style.width = '40%';
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), fetchTimeoutMs);
            
            const response = await fetch('process_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=apply_updates&csrf_token=${encodeURIComponent(csrfToken)}`,
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            progressBar.style.width = '80%';
            
            if (!response.ok) {
                const isRetryableStatus = [502, 503, 504].includes(response.status);
                if (isRetryableStatus && attempt < maxRetries) {
                    githubAddLogEntry(`Server error (HTTP ${response.status}). Will retry...`, 'warning');
                    continue;
                }
                throw new Error(`Server error (HTTP ${response.status})`);
            }
            
            let data;
            try {
                data = await response.json();
            } catch (parseError) {
                throw new Error('Invalid response from server — the update may have partially applied. Please check the site and try again.');
            }
            
            progressBar.style.width = '100%';
            
            if (data.success) {
                githubAddLogEntry(data.message || 'Update completed', 'success');
                
                if (data.errors && data.errors.length > 0) {
                    data.errors.forEach(err => githubAddLogEntry(err, 'warning'));
                }
                
                githubAddLogEntry('Persistent files restored.', 'success');
                
                // Display schema check results
                if (data.schema_check) {
                    githubAddLogEntry('Running database schema check...', 'info');
                    if (data.schema_check.success) {
                        const changes = data.schema_check.changes_applied || 0;
                        if (changes > 0 && data.schema_check.results) {
                            data.schema_check.results.forEach(r => githubAddLogEntry(r, 'info'));
                        }
                        githubAddLogEntry(`Schema check complete: ${changes} change(s) applied.`, 'success');
                    } else {
                        githubAddLogEntry('Schema check failed: ' + (data.schema_check.message || 'Unknown error'), 'warning');
                    }
                    if (data.schema_check.errors && data.schema_check.errors.length > 0) {
                        data.schema_check.errors.forEach(err => githubAddLogEntry(err, 'warning'));
                    }
                }
                
                githubAddLogEntry('Update applied successfully!', 'success');
                
                document.getElementById('githubProgressTitle').innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> Update Complete';
                
                resultBanner.style.cssText = 'display: block; background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; border-radius: 8px; padding: 20px; margin-top: 20px;';
                resultBanner.innerHTML = `
                    <h3 style="margin: 0 0 10px 0; font-size: 18px;"><i class="fas fa-check-circle"></i> Update Successful</h3>
                    <p style="margin: 0;">${data.message || 'System updated successfully'}</p>
                    <button onclick="window.location.reload()" class="btn btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-sync"></i> Reload Page
                    </button>
                `;
            } else {
                githubAddLogEntry(data.message || 'Update failed', 'error');
                document.getElementById('githubProgressTitle').innerHTML = '<i class="fas fa-times-circle" style="color: #ef4444;"></i> Update Failed';
                
                resultBanner.style.cssText = 'display: block; background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; border-radius: 8px; padding: 20px; margin-top: 20px;';
                resultBanner.innerHTML = `
                    <h3 style="margin: 0 0 10px 0; font-size: 18px;"><i class="fas fa-times-circle"></i> Update Failed</h3>
                    <p style="margin: 0;">${data.message || 'An error occurred during update'}</p>
                `;
                updateBtn.disabled = false;
            }
            
            break; // Exit retry loop on successful response
            
        } catch (error) {
            const isRetryable = error.name === 'AbortError' || 
                               error.message.includes('Failed to fetch') ||
                               error.message.includes('NetworkError');
            
            if (isRetryable && attempt < maxRetries) {
                githubAddLogEntry(`Connection issue: ${error.message || 'Request timed out'}. Will retry...`, 'warning');
                continue;
            }
            
            console.error('Error:', error);
            const errorMsg = error.name === 'AbortError' 
                ? 'Request timed out — the update may still be completing on the server. Please wait and check for updates again.'
                : (error.message || 'Network error or server not responding');
            githubAddLogEntry(errorMsg, 'error');
            document.getElementById('githubProgressTitle').innerHTML = '<i class="fas fa-times-circle" style="color: #ef4444;"></i> Update Failed';
            
            resultBanner.style.cssText = 'display: block; background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; border-radius: 8px; padding: 20px; margin-top: 20px;';
            resultBanner.innerHTML = `
                <h3 style="margin: 0 0 10px 0; font-size: 18px;"><i class="fas fa-times-circle"></i> Update Failed</h3>
                <p style="margin: 0;">${errorMsg}</p>
            `;
            updateBtn.disabled = false;
        }
    }
    
    checkBtn.disabled = false;
    updateBtn.innerHTML = '<i class="fas fa-download"></i> Update Now';
}

// Stripe Library Update functions
function checkStripeUpdates() {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    // Route through server-side endpoint for security
    fetch('process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=check_stripe_updates&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        const statusDiv = document.getElementById('stripe-update-status');
        const updateBtn = document.getElementById('update-stripe-btn');
        
        if (data.success && data.tag_name) {
            const latestVersion = data.tag_name.replace('v', '');
            statusDiv.style.display = 'block';
            statusDiv.className = 'integration-status connected';
            statusDiv.innerHTML = `
                <div class="status-icon"><i class="fas fa-info-circle"></i></div>
                <div class="status-info">
                    <h4>Latest Version: ${latestVersion}</h4>
                    <p>${data.name || 'Stripe PHP Library'}<br>
                    <small>Released: ${new Date(data.published_at).toLocaleDateString()}</small></p>
                </div>
            `;
            updateBtn.disabled = false;
            updateBtn.setAttribute('data-version', data.tag_name);
        } else {
            statusDiv.style.display = 'block';
            statusDiv.className = 'integration-status disconnected';
            statusDiv.innerHTML = `
                <div class="status-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="status-info">
                    <h4>Check Failed</h4>
                    <p>${data.message || 'Could not retrieve latest Stripe version.'}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        showToast('Error checking Stripe updates', 'error');
        console.error('Error:', error);
    });
}

async function updateStripeLibrary() {
    if (!await showConfirmModal('Update the Stripe PHP library?\n\nThis will download the latest version from GitHub.\n\nMake sure you have a backup before proceeding.')) return;
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    fetch('process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_stripe_library&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.success) {
            persistToast('Success: Stripe Library Updated! ' + (data.message || 'Update completed successfully.'), 'success');
            location.reload();
        } else {
            showToast('Error: Update Failed. ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        showToast('Error updating Stripe library', 'error');
        console.error('Error:', error);
    });
}

// Test DocuSeal Connection
document.getElementById('test-docuseal')?.addEventListener('click', function() {
    const btn = this;
    const statusSpan = document.getElementById('docuseal-status');
    const url = document.querySelector('input[name="docuseal_url"]').value;
    const apiKey = document.querySelector('input[name="docuseal_api_key"]').value;
    
    if (!url) {
        statusSpan.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times-circle"></i> Please enter a DocuSeal URL first</span>';
        return;
    }
    
    if (!apiKey) {
        statusSpan.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times-circle"></i> Please enter your DocuSeal API key</span>';
        return;
    }
    
    btn.disabled = true;
    statusSpan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing connection...';
    
    const formData = new FormData();
    formData.append('action', 'test_docuseal');
    formData.append('docuseal_url', url);
    formData.append('docuseal_api_key', apiKey);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
    
    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            statusSpan.innerHTML = '<span style="color: #00ff88;"><i class="fas fa-check-circle"></i> ' + (data.message || 'Connection successful!') + '</span>';
        } else {
            statusSpan.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times-circle"></i> ' + (data.message || 'Connection failed') + '</span>';
        }
    })
    .catch(error => {
        btn.disabled = false;
        statusSpan.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times-circle"></i> Error testing connection</span>';
        console.error('Error:', error);
    });
});

// Test Stallion Express Connection
document.getElementById('test-stallion')?.addEventListener('click', function() {
    const btn = this;
    const statusSpan = document.getElementById('stallion-status');
    const url = document.querySelector('input[name="stallion_api_url"]').value;
    const apiKey = document.querySelector('input[name="stallion_api_key"]').value;
    
    if (!url) {
        statusSpan.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times-circle"></i> Please enter a Stallion Express API URL first</span>';
        return;
    }
    
    if (!apiKey) {
        statusSpan.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times-circle"></i> Please enter your Stallion Express API key</span>';
        return;
    }
    
    btn.disabled = true;
    statusSpan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing connection...';
    
    const formData = new FormData();
    formData.append('action', 'test_stallion');
    formData.append('stallion_api_url', url);
    formData.append('stallion_api_key', apiKey);
    formData.append('stallion_api_secret', document.querySelector('input[name="stallion_api_secret"]')?.value || '');
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
    
    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            statusSpan.innerHTML = '<span style="color: #00ff88;"><i class="fas fa-check-circle"></i> ' + (data.message || 'Connection successful!') + '</span>';
        } else {
            statusSpan.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times-circle"></i> ' + (data.message || 'Connection failed') + '</span>';
        }
    })
    .catch(error => {
        btn.disabled = false;
        statusSpan.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times-circle"></i> Error testing connection</span>';
        console.error('Error:', error);
    });
});
</script>

<style>
/* =========================================================
   SYSTEM TOOLS - Enhanced Modern Design
   ========================================================= */

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    max-width: 450px;
    width: 90%;
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border, #2D2D3F);
    background: linear-gradient(180deg, rgba(107, 70, 193, 0.08) 0%, transparent 100%);
}

.modal-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h3 i {
    color: #8B5CF6;
}

.modal-close {
    background: none;
    border: none;
    color: #9ca3af;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.modal-close:hover {
    color: #fff;
}

.modal-body {
    padding: 24px;
}

.modal-body p {
    color: #9ca3af;
    font-size: 14px;
    margin: 0;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 24px;
    border-top: 1px solid var(--border, #2D2D3F);
}

/* Alert Styles */
.alert {
    padding: 16px 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    font-weight: 500;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid #10b981;
    color: #10b981;
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid #ef4444;
    color: #ef4444;
}

/* Page Header Styles */
.system-tools-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
}

.page-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.page-header-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.3);
}

.page-header-text h1 {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 4px 0;
    letter-spacing: -0.5px;
    color: var(--text-primary);
}

.page-header-text p {
    font-size: 14px;
    color: #9ca3af;
    margin: 0;
}

/* Modern System Tools Tabs */
.system-tools-tabs-wrapper {
    margin-bottom: 28px;
}

.system-tools-tabs {
    display: flex;
    gap: 6px;
    padding: 6px;
    background: var(--bg-card);
    border-radius: 14px;
    border: 1px solid var(--border);
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.system-tools-tabs::-webkit-scrollbar {
    display: none;
}

.tools-tab-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    background: transparent;
    border: none;
    border-radius: 10px;
    color: var(--text-secondary);
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.tools-tab-link:hover {
    background: rgba(107, 70, 193, 0.1);
    color: var(--text-primary);
}

.tools-tab-link.active {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
}

.tools-tab-link i {
    font-size: 16px;
}

/* Tab Navigation Styles (legacy) */
.system-tools-content {
    max-width: 1200px;
    margin: 0 auto;
}

.tab-navigation {
    display: none;
}

.tab-link {
    display: none;
}

/* Legacy .tabs and .tab-btn styles for backward compatibility */
.tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 2px solid var(--border);
}

.tab-btn {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--text-dim);
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: -2px;
}

.tab-btn:hover {
    color: var(--text-white);
    background: rgba(107, 70, 193, 0.1);
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
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Enhanced Card Styles for System Tools */
.tab-content .card {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border);
    border-radius: 16px;
    margin-bottom: 24px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.tab-content .card-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(180deg, rgba(107, 70, 193, 0.08) 0%, transparent 100%);
}

.tab-content .card-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.tab-content .card-header h3 i {
    color: #8B5CF6;
}

.tab-content .card-body {
    padding: 28px;
}

/* Settings List Enhancement */
.settings-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.05) 0%, transparent 100%);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: all 0.3s ease;
    gap: 20px;
}

.setting-item:hover {
    border-color: var(--primary);
    transform: translateX(4px);
}

.setting-info {
    flex: 1;
    min-width: 0;
}

.setting-info h4 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-white);
    margin: 0 0 4px 0;
}

.setting-info p {
    font-size: 13px;
    color: var(--text-dim);
    margin: 0;
}

.setting-item .form-input,
.setting-item .form-select {
    max-width: 280px;
    min-width: 200px;
}

/* Mileage Rates Styles */
.rate-input-group {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 6px 14px;
    transition: all 0.3s ease;
}

.rate-input-group:hover,
.rate-input-group:focus-within {
    border-color: var(--primary);
}

.rate-input-group .form-input {
    max-width: 100px;
    background: transparent;
    border: none;
    text-align: center;
    font-size: 18px;
    font-weight: 700;
    color: #8B5CF6;
}

.rate-input-group .form-input:focus {
    outline: none;
}

.currency-symbol {
    color: var(--primary);
    font-weight: 700;
    font-size: 18px;
}

.rate-unit {
    color: var(--text-dim);
    font-size: 14px;
}

.info-box {
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid rgba(107, 70, 193, 0.3);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 24px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.info-box i {
    color: var(--primary);
    font-size: 20px;
    margin-top: 2px;
}

.info-box p {
    color: var(--text-white);
    font-size: 13px;
    line-height: 1.6;
    margin: 0;
}

.settings-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 24px;
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: border-color 0.3s ease;
}

.setting-item:hover {
    border-color: var(--primary);
}

.setting-info {
    flex: 1;
    max-width: 60%;
}

.setting-info h4 {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 4px;
}

.setting-info p {
    font-size: 13px;
    color: var(--text-dim);
    margin: 0;
}

.setting-item .form-input {
    max-width: 300px;
}

/* Form Actions styling */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
    margin-top: 24px;
}

.form-actions .btn {
    min-width: 140px;
}

.theme-colors {
    display: grid;
    gap: 20px;
    margin-bottom: 24px;
}

.color-picker-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.color-picker-item label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-white);
}

.color-input-group {
    display: flex;
    gap: 12px;
    align-items: center;
}

.color-input-group input[type="color"] {
    width: 60px;
    height: 45px;
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    background: transparent;
}

.color-input-group .form-input {
    flex: 1;
    max-width: 200px;
}

.db-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.db-tool-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    transition: all 0.3s ease;
}

.db-tool-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(107, 70, 193, 0.2);
}

.db-tool-card.warning:hover {
    border-color: var(--error);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.2);
}

.db-tool-card > i {
    font-size: 40px;
    color: var(--primary);
    margin-bottom: 16px;
}

.db-tool-card.warning > i {
    color: var(--error);
}

.db-tool-card h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 8px;
}

.db-tool-card p {
    font-size: 13px;
    color: var(--text-dim);
    margin-bottom: 20px;
}

.db-tool-card button {
    width: 100%;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
    flex-shrink: 0;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--border);
    transition: 0.3s;
    border-radius: 26px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
    background-color: var(--primary);
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

@media (max-width: 768px) {
    .system-tools-page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .system-tools-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .tools-tab-link span {
        display: none;
    }
    
    .tools-tab-link {
        padding: 14px;
    }
    
    .tabs {
        overflow-x: auto;
    }
    
    .setting-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .setting-info {
        max-width: 100%;
    }
    
    .setting-item .form-input {
        max-width: 100%;
        width: 100%;
    }
    
    .db-tools-grid {
        grid-template-columns: 1fr;
    }
}

.toggle-switch {
    position: relative;
    width: 56px;
    height: 28px;
    display: inline-block;
    flex-shrink: 0;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--border);
    transition: all 0.3s ease;
    border-radius: 28px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background: #fff;
    transition: all 0.3s ease;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-switch input:checked + .toggle-slider {
    background: linear-gradient(135deg, var(--primary), var(--primary-light, #8B5CF6));
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(28px);
}

.toggle-switch:hover .toggle-slider {
    box-shadow: 0 0 8px rgba(107, 70, 193, 0.3);
}

.theme-colors {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.color-picker-item label {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
    margin-bottom: 10px;
}

.color-input-group {
    display: flex;
    gap: 10px;
}

.color-input-group input[type="color"] {
    width: 60px;
    height: 45px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--bg-main);
    cursor: pointer;
}

.db-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.db-tool-card {
    background: linear-gradient(135deg, var(--bg-main) 0%, rgba(107, 70, 193, 0.03) 100%);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 28px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.db-tool-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--primary-light, #8B5CF6));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.db-tool-card:hover {
    border-color: var(--primary);
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(107, 70, 193, 0.15);
}

.db-tool-card:hover::before {
    opacity: 1;
}

.db-tool-card > i {
    font-size: 48px;
    color: var(--primary);
    display: block;
    margin-bottom: 16px;
    transition: transform 0.3s ease;
}

.db-tool-card:hover > i {
    transform: scale(1.1);
}

.db-tool-card.warning > i {
    color: #ef4444;
}

.db-tool-card.warning::before {
    background: linear-gradient(90deg, #ef4444, #f87171);
}

.db-tool-card.warning:hover {
    border-color: #ef4444;
    box-shadow: 0 12px 24px rgba(239, 68, 68, 0.15);
}

.db-tool-card h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}

.db-tool-card p {
    font-size: 14px;
    color: var(--text-dim);
    margin-bottom: 20px;
    line-height: 1.5;
}

/* Integration Status Styles */
.integration-status {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    border: 1px solid var(--border);
}

.integration-status.connected {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
}

.integration-status.disconnected {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
}

.integration-status .status-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.integration-status.connected .status-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.integration-status.disconnected .status-icon {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.integration-status .status-info h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 4px;
}

.integration-status .status-info p {
    font-size: 13px;
    color: var(--text-dim);
    margin: 0;
}

/* Sync Options */
.sync-options {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-top: 24px;
    margin-bottom: 24px;
}

.sync-options h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sync-options h4 i {
    color: var(--primary);
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.checkbox-item:hover {
    border-color: var(--primary);
}

.checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
    cursor: pointer;
}

.checkbox-item span {
    font-size: 14px;
    color: var(--text-white);
}

.checkbox-item:has(input:checked) {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.1);
}

/* Mileage rate input group */
.rate-input-group {
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: 200px;
}

.rate-input-group .currency-symbol {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-white);
}

.rate-input-group .form-input {
    flex: 1;
    text-align: right;
    max-width: 100px;
}

.rate-input-group .rate-unit {
    font-size: 14px;
    color: var(--text-dim);
    font-weight: 500;
}

/* Badge Styles */
.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-primary {
    background: var(--primary);
    color: #fff;
}

.badge-secondary {
    background: rgba(156, 163, 175, 0.2);
    color: #9ca3af;
}

/* Radio Item Styles */
.radio-item {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 8px 16px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: all 0.3s ease;
}

.radio-item:hover {
    border-color: var(--primary);
}

.radio-item:has(input:checked) {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.1);
}

.radio-item input[type="radio"] {
    width: 16px;
    height: 16px;
    accent-color: var(--primary);
    cursor: pointer;
}

.radio-item span {
    font-size: 14px;
    color: var(--text-white);
}

/* Logo Preview Styles */
.logo-preview {
    display: inline-block;
}

.logo-preview img {
    display: block;
    object-fit: contain;
}

/* Update Log Styles */
#update-log-content {
    white-space: pre-wrap;
    word-break: break-word;
}

/* Card Header with Badge */
.card-header .badge {
    margin-left: auto;
}
</style>

<script>
// SMTP Connection Test
function testSmtpConnection() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    btn.disabled = true;
    
    const formData = new FormData(document.getElementById('smtp-form'));
    formData.append('action', 'test_smtp');
    
    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.success) {
            showToast('Success: SMTP Connection Successful! Test email sent successfully.', 'success');
        } else {
            showToast('Error: SMTP Connection Failed. ' + (data.message || 'Could not connect to SMTP server'), 'error');
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        showToast('Error testing SMTP connection', 'error');
        console.error('Error:', error);
    });
}

// Test Paperless-NGX Connection
function testPaperlessConnection() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    btn.disabled = true;
    
    const formData = new FormData(document.getElementById('paperless-form'));
    formData.set('action', 'test_paperless');
    
    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.success) {
            showToast('Success: Paperless-NGX Connection Successful! ' + (data.message || 'Connected successfully'), 'success');
        } else {
            showToast('Error: Paperless-NGX Connection Failed. ' + (data.message || 'Could not connect to Paperless-NGX server'), 'error');
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        showToast('Error testing Paperless-NGX connection', 'error');
        console.error('Error:', error);
    });
}

// Encryption Key Functions
function generateEncryptionKey() {
    const array = new Uint8Array(32);
    crypto.getRandomValues(array);
    const hex = Array.from(array, b => b.toString(16).padStart(2, '0')).join('');
    document.getElementById('encryption-key-input').value = hex;
}

function validateEncryptionKey() {
    const key = document.getElementById('encryption-key-input').value.trim();
    if (!/^[a-fA-F0-9]{64}$/.test(key)) {
        showToast('The encryption key must be exactly 64 hexadecimal characters (0-9, a-f).', 'error');
        return false;
    }
    return true;
}

async function validateEncryptionKeyUpdate() {
    // Validate the current key field
    const currentKeyInput = document.getElementById('current-encryption-key-input');
    if (currentKeyInput) {
        const currentKey = currentKeyInput.value.trim();
        if (!/^[a-fA-F0-9]{64}$/.test(currentKey)) {
            showToast('The current encryption key must be exactly 64 hexadecimal characters (0-9, a-f).', 'error');
            return false;
        }
    }
    
    // Validate the new key field
    const newKey = document.getElementById('encryption-key-input').value.trim();
    if (!/^[a-fA-F0-9]{64}$/.test(newKey)) {
        showToast('The new encryption key must be exactly 64 hexadecimal characters (0-9, a-f).', 'error');
        return false;
    }
    
    // Multiple verification prompts
    if (!await showConfirmModal('WARNING: You are about to change the encryption key.\n\nChanging the encryption key will make ALL previously encrypted data unreadable unless you decrypt it first with the current key.\n\nAre you sure you want to proceed?')) {
        return false;
    }
    
    if (!await showConfirmModal('FINAL CONFIRMATION: This action cannot be undone.\n\nPlease confirm that you have:\n1. Backed up your current encryption key\n2. Backed up your database\n3. Understand that existing encrypted data will become unreadable\n\nDo you want to continue with the key change?')) {
        return false;
    }
    
    return true;
}

// NDI Camera Management Functions
function toggleNdiCamera(cameraId, newState) {
    const formData = new FormData();
    formData.append('action', 'toggle_ndi_camera');
    formData.append('ndi_camera_id', cameraId);
    formData.append('is_active', newState);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            persistToast(data.message || 'Operation completed successfully', 'success');
            window.location.reload();
        } else {
            showToast('Error: ' + (data.message || 'Failed to update camera status'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while updating camera status.', 'error');
    });
}

function editNdiCamera(cameraId) {
    const formData = new FormData();
    formData.append('action', 'get_ndi_camera');
    formData.append('ndi_camera_id', cameraId);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.camera) {
            document.getElementById('edit-ndi-camera-id').value = data.camera.id;
            document.getElementById('edit-ndi-camera-name').value = data.camera.name;
            document.getElementById('edit-ndi-camera-ip').value = data.camera.ip_address;
            document.getElementById('edit-ndi-camera-port').value = data.camera.port || 5960;
            document.getElementById('edit-ndi-camera-ndi-name').value = data.camera.ndi_name || '';
            document.getElementById('edit-ndi-camera-location').value = data.camera.location || '';
            document.getElementById('ndi-camera-edit-modal').style.display = 'flex';
        } else {
            showToast('Error: ' + (data.message || 'Failed to load camera details'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while loading camera details.', 'error');
    });
}

function closeNdiEditModal() {
    document.getElementById('ndi-camera-edit-modal').style.display = 'none';
}

function saveNdiCamera() {
    const cameraId = document.getElementById('edit-ndi-camera-id').value;
    const name = document.getElementById('edit-ndi-camera-name').value.trim();
    const ip = document.getElementById('edit-ndi-camera-ip').value.trim();
    const port = document.getElementById('edit-ndi-camera-port').value;
    const ndiName = document.getElementById('edit-ndi-camera-ndi-name').value.trim();
    const location = document.getElementById('edit-ndi-camera-location').value.trim();

    if (!name || !ip) {
        showToast('Camera name and IP address are required.', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_ndi_camera');
    formData.append('ndi_camera_id', cameraId);
    formData.append('ndi_camera_name', name);
    formData.append('ndi_camera_ip', ip);
    formData.append('ndi_camera_port', port);
    formData.append('ndi_camera_ndi_name', ndiName);
    formData.append('ndi_camera_location', location);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeNdiEditModal();
            persistToast(data.message || 'Operation completed successfully', 'success');
            window.location.reload();
        } else {
            showToast('Error: ' + (data.message || 'Failed to update camera'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while saving camera changes.', 'error');
    });
}

async function deleteNdiCamera(cameraId, cameraName) {
    if (!await showConfirmModal('Are you sure you want to delete the camera "' + cameraName + '"? This action cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_ndi_camera');
    formData.append('ndi_camera_id', cameraId);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            persistToast(data.message || 'Operation completed successfully', 'success');
            window.location.reload();
        } else {
            showToast('Error: ' + (data.message || 'Failed to delete camera'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while deleting the camera.', 'error');
    });
}

// Game Plan Settings Functions
function gpToggleVisibility(inputId, button) {
    var input = document.getElementById(inputId);
    var icon = button.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}


function gpShowTestAlert(type, message) {
    var container = document.getElementById('gpTestAlertContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'gpTestAlertContainer';
        var formActions = document.getElementById('gpTestCompanionBtn').closest('.form-actions');
        formActions.parentNode.insertBefore(container, formActions);
    }
    var cls = (type === 'success') ? 'alert-success' : 'alert-danger';
    var icon = (type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle';
    container.textContent = '';
    var alertDiv = document.createElement('div');
    alertDiv.className = 'alert ' + cls;
    alertDiv.style.marginBottom = '16px';
    var iconEl = document.createElement('i');
    iconEl.className = 'fa-solid ' + icon;
    alertDiv.appendChild(iconEl);
    alertDiv.appendChild(document.createTextNode(' ' + message));
    container.appendChild(alertDiv);
}

function gpTestCompanion() {
    var urlInput = document.querySelector('#gameplan-tab input[name="companion_url"]');
    var keyInput = document.querySelector('#gameplan-tab input[name="companion_api_key"]');
    var btn = document.getElementById('gpTestCompanionBtn');
    var statusBadge = document.getElementById('gpCompanionStatus');

    if (!urlInput.value) {
        gpShowTestAlert('error', 'Please enter the companion server URL first.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing...';

    var form = new FormData();
    form.append('csrf_token', document.querySelector('#gameplan-tab input[name="csrf_token"]').value);
    form.append('action', 'test_companion');
    form.append('companion_url', urlInput.value);
    form.append('companion_api_key', keyInput.value);

    fetch('process_gameplan_settings.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                statusBadge.className = 'badge badge-success';
                statusBadge.textContent = 'Connected';
                gpShowTestAlert('success', 'Connection successful! Companion server is online.');
            } else {
                statusBadge.className = 'badge badge-danger';
                statusBadge.textContent = 'Error';
                gpShowTestAlert('error', 'Connection failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(function(err) {
            statusBadge.className = 'badge badge-danger';
            statusBadge.textContent = 'Error';
            gpShowTestAlert('error', 'Connection failed: ' + err.message);
        })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-plug"></i> Test Connection';
        });
}

// FusionPBX Test Connection

// ===== Galera Cluster Management =====

function toggleClusterConfigFields() {
    var mode = document.getElementById('cfg-db-mode').value;
    var row  = document.getElementById('cfg-cluster-nodes-row');
    if (row) row.style.display = mode === 'cluster' ? 'block' : 'none';
}
// Run after DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('cfg-db-mode')) toggleClusterConfigFields();
});

function clusterPost(action, extraData) {
    var body = new URLSearchParams({ action: action, csrf_token: getCsrfToken() });
    if (extraData) {
        for (var k in extraData) body.set(k, extraData[k]);
    }
    return fetch('process_settings.php', { method: 'POST', body: body, headers: { 'Accept': 'application/json' } })
           .then(r => r.json());
}

function saveClusterSettings() {
    var mode       = document.getElementById('cfg-db-mode').value;
    var clusterName = document.getElementById('cfg-cluster-name').value.trim();
    var clusterNodes = document.getElementById('cfg-cluster-nodes').value.trim();
    var result = document.getElementById('cluster-settings-result');
    result.innerHTML = '<span style="color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Saving…</span>';
    clusterPost('save_cluster_settings', { db_mode: mode, db_cluster_name: clusterName, db_cluster_nodes: clusterNodes })
    .then(data => {
        if (data.success) {
            result.innerHTML = '<span style="color:#00ff88;"><i class="fas fa-check"></i> ' + (data.message || 'Saved') + '</span>';
            // Update the mode badge
            var badge = document.getElementById('cluster-mode-badge');
            if (badge) {
                badge.textContent = mode === 'cluster' ? 'CLUSTER MODE' : 'SINGLE DB';
                badge.style.color = mode === 'cluster' ? '#00ff88' : '#94a3b8';
                badge.style.background = mode === 'cluster' ? 'rgba(0,255,136,0.15)' : 'rgba(148,163,184,0.15)';
                badge.style.borderColor = mode === 'cluster' ? '#00ff88' : '#94a3b8';
            }
        } else {
            result.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-times"></i> ' + (data.message || 'Error') + '</span>';
        }
    })
    .catch(() => { result.innerHTML = '<span style="color:#ef4444;">Request failed</span>'; });
}

function loadClusterStatus() {
    var content = document.getElementById('cluster-status-content');
    content.innerHTML = '<span style="color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading…</span>';
    clusterPost('get_cluster_status')
    .then(data => {
        if (!data.success) {
            content.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-times"></i> ' + (data.message || 'Failed to get cluster status') + '</span>';
            return;
        }
        var rows = [
            ['Mode',           data.db_mode === 'cluster' ? 'Galera Cluster' : 'Single Database'],
            ['Cluster Name',   data.cluster_name || '—'],
            ['Cluster Size',   data.cluster_size ?? '—'],
            ['Cluster Status', data.cluster_status ?? '—'],
            ['Ready',          data.ready ?? '—'],
            ['Node State',     data.state ?? '—'],
            ['Connected Node', data.node_address ?? '—'],
        ];
        var html = '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
        rows.forEach(function(r) {
            var color = '#fff';
            if (r[1] === 'Primary') color = '#00ff88';
            else if (r[1] === 'OFF' || r[1] === 'Non-Primary') color = '#ef4444';
            html += '<tr style="border-bottom:1px solid var(--border);">'
                  + '<td style="padding:7px 10px;color:#94a3b8;width:40%;">' + r[0] + '</td>'
                  + '<td style="padding:7px 10px;color:' + color + ';font-weight:600;">' + r[1] + '</td>'
                  + '</tr>';
        });
        html += '</table>';
        content.innerHTML = html;
    })
    .catch(() => { content.innerHTML = '<span style="color:#ef4444;">Request failed</span>'; });
}

function testNode(node) {
    clusterPost('test_cluster_node', { node: node })
    .then(data => {
        var msg = (data.success ? '✓ ' : '✗ ') + (data.message || '');
        var color = data.success ? '#00ff88' : '#ef4444';
        // Find the row and show inline
        document.querySelectorAll('.cluster-node-row').forEach(function(row) {
            if (row.dataset.node === node) {
                var existing = row.querySelector('.test-result');
                if (existing) existing.remove();
                var span = document.createElement('span');
                span.className = 'test-result';
                span.style.cssText = 'font-size:11px;color:' + color + ';margin-left:6px;';
                span.textContent = msg;
                row.appendChild(span);
            }
        });
    });
}

async function removeNode(node) {
    var safeNode = node.replace(/['"\\]/g, '\\$&');
    if (!await showConfirmModal('Remove node ' + safeNode + ' from the cluster configuration?')) return;
    var result = document.getElementById('add-node-result');
    clusterPost('remove_cluster_node', { node: node })
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            result.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-times"></i> ' + (data.message || 'Error') + '</span>';
        }
    });
}

function addClusterNode() {
    var newNode = document.getElementById('new-node-address').value.trim();
    var result  = document.getElementById('add-node-result');
    if (!newNode) {
        result.innerHTML = '<span style="color:#ef4444;">Please enter a node address.</span>';
        return;
    }
    result.innerHTML = '<span style="color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Adding node…</span>';
    clusterPost('add_cluster_node', { node: newNode })
    .then(data => {
        if (data.success) {
            var html = '<div style="margin-bottom:10px;"><span style="color:#00ff88;"><i class="fas fa-check"></i> ' + escapeHtml(data.message || 'Node added') + '</span></div>';
            if (data.docker_cmd) {
                html += '<div style="font-size:12px;color:#94a3b8;margin-bottom:6px;">Run this command on the new node\'s Docker host to join the cluster:</div>'
                      + '<pre id="docker-join-cmd" style="background:var(--bg-card);border:1px solid var(--border);border-radius:6px;padding:12px;font-size:11px;color:#e2e8f0;overflow-x:auto;white-space:pre-wrap;">'
                      + escapeHtml(data.docker_cmd) + '</pre>'
                      + '<button type="button" id="btn-copy-docker-cmd" class="btn btn-secondary" style="font-size:11px;padding:4px 10px;margin-top:6px;"><i class="fas fa-copy"></i> Copy Command</button>';
            }
            result.innerHTML = html;
            // Bind copy button after DOM update
            if (data.docker_cmd) {
                var copyBtn = document.getElementById('btn-copy-docker-cmd');
                if (copyBtn) {
                    var cmdText = data.docker_cmd;
                    copyBtn.addEventListener('click', function() {
                        navigator.clipboard.writeText(cmdText);
                    });
                }
            }
            // Reload node list
            setTimeout(() => location.reload(), 300);
        } else {
            result.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-times"></i> ' + (data.message || 'Error') + '</span>';
        }
    })
    .catch(() => { result.innerHTML = '<span style="color:#ef4444;">Request failed</span>'; });
}

function escapeHtml(text) {
    return text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

// ===== End Galera Cluster Management =====
</script>
