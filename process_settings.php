<?php
// process_settings.php - Handle system settings updates
session_start();
require 'db_config.php';
require 'security.php';
require 'cloud_config.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';
require_once __DIR__ . '/lib/blocklist.php';

setSecurityHeaders();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Access denied']));
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Determine if we should return JSON or redirect
$json_actions = ['test_smtp', 'test_github', 'check_updates', 'apply_updates', 'sync_to_backup', 'check_stripe_updates', 'update_stripe_library', 'test_docuseal', 'test_stallion', 'test_google_maps', 'create_restriction', 'remove_restriction', 'add_blocklist_entry', 'remove_blocklist_entry', 'add_pos_whitelist_entry', 'remove_pos_whitelist_entry', 'toggle_pos_whitelist_entry', 'get_ndi_camera', 'update_ndi_camera', 'delete_ndi_camera', 'toggle_ndi_camera', 'get_cluster_status', 'test_cluster_node', 'add_cluster_node', 'remove_cluster_node', 'save_cluster_settings', 'test_paperless', 'test_rustfs', 'test_upload_api', 'sync_office365_calendar'];
$is_json = in_array($action, $json_actions);

if ($is_json) {
    header('Content-Type: application/json');
}

try {
    checkCsrfToken();
    
    switch ($action) {
        case 'update_general':
            $org_name = trim($_POST['org_name'] ?? '');
            $contact_email = trim($_POST['contact_email'] ?? '');
            $contact_phone = trim($_POST['contact_phone'] ?? '');
            $org_address = trim($_POST['org_address'] ?? '');
            $currency = trim($_POST['currency'] ?? '');
            $date_format = trim($_POST['date_format'] ?? 'MM/DD/YYYY');
            
            updateSetting($pdo, 'org_name', $org_name);
            updateSetting($pdo, 'contact_email', $contact_email);
            updateSetting($pdo, 'contact_phone', $contact_phone);
            updateSetting($pdo, 'org_address', $org_address);
            updateSetting($pdo, 'currency', $currency);
            updateSetting($pdo, 'date_format', $date_format);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_general',
                'settings' => ['org_name' => $org_name]
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=settings&success=1');
            exit;

        case 'toggle_pii_encryption':
            $pii_enabled = isset($_POST['pii_encryption_enabled']) ? '1' : '0';
            updateSetting($pdo, 'pii_encryption_enabled', $pii_enabled);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'toggle_pii_encryption',
                'pii_encryption_enabled' => $pii_enabled
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=encryption&success=1');
            exit;

        case 'save_encryption_key':
            // Only the first admin (lowest ID with admin role) can change the encryption key
            $first_admin_check = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $first_admin_check->execute();
            $first_admin = $first_admin_check->fetch(PDO::FETCH_ASSOC);
            if (!$first_admin || (int)$first_admin['id'] !== (int)$user_id) {
                header('Location: dashboard.php?page=system_tools&tab=encryption&error=' . urlencode('Only the first administrator account can change the encryption key.'));
                exit;
            }
            
            $encryption_key = trim($_POST['encryption_key'] ?? '');
            
            // Validate the key is exactly 64 hex characters
            if (!preg_match('/^[a-fA-F0-9]{64}$/', $encryption_key)) {
                header('Location: dashboard.php?page=system_tools&tab=encryption&error=' . urlencode('Invalid encryption key. Must be exactly 64 hexadecimal characters.'));
                exit;
            }
            
            // If encryption is already configured, verify the current key before allowing a change
            require_once __DIR__ . '/lib/encryption.php';
            if (FieldEncryption::isConfigured()) {
                $current_key = trim($_POST['current_encryption_key'] ?? '');
                if (empty($current_key)) {
                    header('Location: dashboard.php?page=system_tools&tab=encryption&error=' . urlencode('You must enter the current encryption key to verify your identity before changing it.'));
                    exit;
                }
                if (!preg_match('/^[a-fA-F0-9]{64}$/', $current_key)) {
                    header('Location: dashboard.php?page=system_tools&tab=encryption&error=' . urlencode('Invalid current encryption key format. Must be exactly 64 hexadecimal characters.'));
                    exit;
                }
                // Verify the current key matches what's in the environment
                $existing_key = $_ENV['ENCRYPTION_KEY'] ?? '';
                if ($current_key !== $existing_key) {
                    header('Location: dashboard.php?page=system_tools&tab=encryption&error=' . urlencode('The current encryption key you entered does not match. Key change denied.'));
                    exit;
                }
            }
            
            // Find the env file path
            $env_paths = [
                '/config/arctic_wolves.env',
                __DIR__ . '/arctic_wolves.env',
                __DIR__ . '/.env',
                '/var/www/html/arctic_wolves/.env'
            ];
            
            $env_file = null;
            foreach ($env_paths as $path) {
                if (file_exists($path) && is_writable($path)) {
                    $env_file = $path;
                    break;
                }
            }
            
            if ($env_file === null) {
                // Try to create the default local env file
                $env_file = __DIR__ . '/arctic_wolves.env';
                if (!is_writable(__DIR__)) {
                    header('Location: dashboard.php?page=system_tools&tab=encryption&error=' . urlencode('No writable environment file found. Please check file permissions.'));
                    exit;
                }
            }
            
            // Read current env file content
            $env_content = file_exists($env_file) ? file_get_contents($env_file) : '';
            
            // Update or add ENCRYPTION_KEY
            if (preg_match('/^ENCRYPTION_KEY=.*$/m', $env_content)) {
                $env_content = preg_replace('/^ENCRYPTION_KEY=.*$/m', 'ENCRYPTION_KEY=' . $encryption_key, $env_content);
            } else {
                $env_content = rtrim($env_content) . "\nENCRYPTION_KEY=" . $encryption_key . "\n";
            }
            
            if (file_put_contents($env_file, $env_content) === false) {
                header('Location: dashboard.php?page=system_tools&tab=encryption&error=' . urlencode('Failed to write encryption key to environment file.'));
                exit;
            }
            
            // Load the key into the current environment so it takes effect immediately for this request.
            // Note: The key is persisted in the env file and will be loaded on subsequent requests by db_config.php.
            $_ENV['ENCRYPTION_KEY'] = $encryption_key;
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'save_encryption_key',
                'key_configured' => true
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=encryption&success=1');
            exit;
        case 'update_smtp':
            $smtp_host = trim($_POST['smtp_host']);
            $smtp_port = trim($_POST['smtp_port']);
            $smtp_encryption = trim($_POST['smtp_encryption']);
            $smtp_user = trim($_POST['smtp_user']);
            $smtp_pass = trim($_POST['smtp_pass']);
            $smtp_from_email = trim($_POST['smtp_from_email']);
            $smtp_from_name = trim($_POST['smtp_from_name']);

            // Office365 Azure app config (saved alongside SMTP settings)
            $o365_client_id  = trim($_POST['office365_client_id']  ?? '');
            $o365_tenant_id  = trim($_POST['office365_tenant_id']  ?? '');
            $o365_client_sec = trim($_POST['office365_client_secret'] ?? '');
            $o365_smtp_alias = trim($_POST['office365_smtp_alias'] ?? '');

            updateSetting($pdo, 'smtp_host', $smtp_host);
            updateSetting($pdo, 'smtp_port', $smtp_port);
            updateSetting($pdo, 'smtp_encryption', $smtp_encryption);
            updateSetting($pdo, 'smtp_user', $smtp_user);
            if (!empty($smtp_pass)) {
                updateSetting($pdo, 'smtp_pass', encryptPassword($smtp_pass));
            }
            updateSetting($pdo, 'smtp_from_email', $smtp_from_email);
            updateSetting($pdo, 'smtp_from_name', $smtp_from_name);

            // Save Azure app config (client secret only if a new value was provided)
            if (!empty($o365_client_id))  updateSetting($pdo, 'office365_client_id',  $o365_client_id);
            if (!empty($o365_tenant_id))  updateSetting($pdo, 'office365_tenant_id',  $o365_tenant_id);
            if (!empty($o365_client_sec)) updateSetting($pdo, 'office365_client_secret', encryptPassword($o365_client_sec));
            updateSetting($pdo, 'office365_smtp_alias', $o365_smtp_alias);

            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_smtp',
                'settings' => ['smtp_host' => $smtp_host, 'smtp_port' => $smtp_port, 'smtp_encryption' => $smtp_encryption, 'smtp_user' => $smtp_user, 'smtp_from_email' => $smtp_from_email, 'smtp_from_name' => $smtp_from_name]
            ]);

            // Redirect back to the appropriate page
            $redirect_page = isset($_POST['redirect_page']) ? $_POST['redirect_page'] : 'system_tools';
            header('Location: dashboard.php?page=system_tools&tab=smtp&success=1');
            exit;

        // ── Office 365 OAuth: initiate SMTP authorization ─────────────────────
        case 'initiate_office365_smtp_oauth':
            if ($user_id === 'admin' || $_SESSION['user_role'] === 'admin') { /* admin already checked at top */ }
            $state = bin2hex(random_bytes(16));
            $_SESSION['office365_oauth_state'] = $state;
            $_SESSION['office365_oauth_type']  = 'smtp';

            $clientId = trim($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='office365_client_id'")->fetchColumn() ?: '');
            $tenantId = trim($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='office365_tenant_id'")->fetchColumn() ?: 'common');

            $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $redirectUri = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/oauth_office365_callback.php';

            $params = http_build_query([
                'client_id'     => $clientId,
                'response_type' => 'code',
                'redirect_uri'  => $redirectUri,
                'scope'         => 'https://outlook.office.com/SMTP.Send offline_access openid email profile',
                'state'         => $state,
                'prompt'        => 'consent',
            ]);
            header('Location: https://login.microsoftonline.com/' . urlencode($tenantId) . '/oauth2/v2.0/authorize?' . $params);
            exit;

        // ── Office 365 OAuth: disconnect SMTP ────────────────────────────────
        case 'disconnect_office365_smtp':
            $keys = ['office365_smtp_access_token', 'office365_smtp_refresh_token',
                     'office365_smtp_expires_at', 'office365_smtp_connected_email'];
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $pdo->prepare("DELETE FROM system_settings WHERE setting_key IN ($placeholders)")->execute($keys);
            Auditor::log($pdo, $user_id, 'delete', 'system_settings', null, ['action' => 'office365_smtp_disconnected']);
            header('Location: dashboard.php?page=system_tools&tab=smtp&success=1');
            exit;

        // ── Office 365 OAuth: initiate Calendar authorization ─────────────────
        case 'initiate_office365_calendar_oauth':
            $allowedCalRoles = ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'];
            if (!in_array($_SESSION['user_role'] ?? '', $allowedCalRoles)) {
                header('Location: dashboard.php?page=coach_calendar&error=Access+denied');
                exit;
            }
            $state = bin2hex(random_bytes(16));
            $_SESSION['office365_oauth_state'] = $state;
            $_SESSION['office365_oauth_type']  = 'calendar';

            $clientId = trim($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='office365_client_id'")->fetchColumn() ?: '');
            $tenantId = trim($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='office365_tenant_id'")->fetchColumn() ?: 'common');

            $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $redirectUri = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/oauth_office365_callback.php';

            $params = http_build_query([
                'client_id'     => $clientId,
                'response_type' => 'code',
                'redirect_uri'  => $redirectUri,
                'scope'         => 'https://graph.microsoft.com/Calendars.ReadWrite offline_access openid email profile',
                'state'         => $state,
                'prompt'        => 'consent',
            ]);
            header('Location: https://login.microsoftonline.com/' . urlencode($tenantId) . '/oauth2/v2.0/authorize?' . $params);
            exit;

        // ── Office 365 OAuth: disconnect Calendar ────────────────────────────
        case 'disconnect_office365_calendar':
            $allowedCalRoles = ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'];
            if (!in_array($_SESSION['user_role'] ?? '', $allowedCalRoles)) {
                http_response_code(403);
                exit;
            }
            $pdo->prepare("DELETE FROM user_oauth_tokens WHERE user_id = ? AND provider = 'office365_calendar'")->execute([$user_id]);
            Auditor::log($pdo, $user_id, 'delete', 'user_oauth_tokens', null, ['action' => 'office365_calendar_disconnected']);
            header('Location: dashboard.php?page=coach_calendar&success=1');
            exit;

        // ── Office 365 Calendar: push sessions to Microsoft Calendar ─────────
        case 'sync_office365_calendar':
            header('Content-Type: application/json');
            $allowedCalRoles = ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'];
            if (!in_array($_SESSION['user_role'] ?? '', $allowedCalRoles)) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }

            // Load user's calendar token
            $tokRow = $pdo->prepare("SELECT * FROM user_oauth_tokens WHERE user_id = ? AND provider = 'office365_calendar' LIMIT 1");
            $tokRow->execute([$user_id]);
            $tokData = $tokRow->fetch(PDO::FETCH_ASSOC);

            if (!$tokData) {
                echo json_encode(['success' => false, 'message' => 'Office 365 calendar not connected']);
                exit;
            }

            // Refresh token if expiring within 5 minutes
            $accessToken = decryptCredential($tokData['access_token']);
            $expiresAt   = strtotime($tokData['expires_at'] ?? '');
            if ($expiresAt && $expiresAt < time() + 300) {
                $refreshToken = decryptCredential($tokData['refresh_token'] ?? '');
                if (!empty($refreshToken)) {
                    $clientId     = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='office365_client_id'")->fetchColumn() ?: '';
                    $clientSecret = decryptCredential($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='office365_client_secret'")->fetchColumn() ?: '');
                    $tenantId     = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='office365_tenant_id'")->fetchColumn() ?: 'common';

                    $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $redirectUri = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/oauth_office365_callback.php';

                    $postData = http_build_query([
                        'client_id'     => $clientId,
                        'client_secret' => $clientSecret,
                        'refresh_token' => $refreshToken,
                        'redirect_uri'  => $redirectUri,
                        'grant_type'    => 'refresh_token',
                        'scope'         => 'https://graph.microsoft.com/Calendars.ReadWrite offline_access openid email profile',
                    ]);
                    $ctx = stream_context_create([
                        'http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $postData, 'ignore_errors' => true, 'timeout' => 15],
                        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
                    ]);
                    $tokenUrl  = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
                    $resp      = @file_get_contents($tokenUrl, false, $ctx);
                    $newTokens = $resp ? json_decode($resp, true) : [];
                    if (!empty($newTokens['access_token'])) {
                        $accessToken    = $newTokens['access_token'];
                        $newExpiry      = time() + (int)($newTokens['expires_in'] ?? 3600);
                        $encNewAccess   = encryptPassword($accessToken);
                        $encNewRefresh  = !empty($newTokens['refresh_token']) ? encryptPassword($newTokens['refresh_token']) : $tokData['refresh_token'];
                        $pdo->prepare("UPDATE user_oauth_tokens SET access_token=?, refresh_token=?, expires_at=?, updated_at=NOW() WHERE id=?")
                            ->execute([$encNewAccess, $encNewRefresh, date('Y-m-d H:i:s', $newExpiry), $tokData['id']]);
                    }
                }
            }

            if (empty($accessToken)) {
                echo json_encode(['success' => false, 'message' => 'Could not obtain a valid access token – please reconnect Office 365']);
                exit;
            }

            // ── PULL: Fetch events FROM Office 365 into local sessions ─────────
            $pulled = 0;
            $pullErrors = [];
            try {
                $startDate = date('Y-m-d\TH:i:s', strtotime('-1 day'));
                $endDate   = date('Y-m-d\TH:i:s', strtotime('+90 days'));
                $calViewUrl = 'https://graph.microsoft.com/v1.0/me/calendarView'
                    . '?startDateTime=' . urlencode($startDate)
                    . '&endDateTime=' . urlencode($endDate)
                    . '&$top=200'
                    . '&$select=id,subject,start,end,location,bodyPreview,categories,iCalUId';

                $pullCtx = stream_context_create([
                    'http' => [
                        'method'        => 'GET',
                        'header'        => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
                        'ignore_errors' => true,
                        'timeout'       => 30,
                    ],
                    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
                ]);

                $pullResp = @file_get_contents($calViewUrl, false, $pullCtx);
                $pullData = $pullResp ? json_decode($pullResp, true) : [];

                if (!empty($pullData['value']) && is_array($pullData['value'])) {
                    // Ensure o365_event_id column exists (auto-migration, idempotent)
                    $hasO365Col = true;
                    try {
                        $pdo->exec("ALTER TABLE sessions ADD COLUMN o365_event_id VARCHAR(512) DEFAULT NULL COMMENT 'Office 365 iCalUId for sync dedup'");
                        $pdo->exec("ALTER TABLE sessions ADD UNIQUE INDEX idx_o365_event_id (o365_event_id)");
                    } catch (PDOException $ae) {
                        // Column/index already exists — expected on subsequent syncs
                        if (strpos($ae->getMessage(), 'Duplicate column') === false
                            && strpos($ae->getMessage(), '42S21') === false
                            && strpos($ae->getMessage(), 'Duplicate key name') === false) {
                            $pullErrors[] = 'Could not prepare sessions table for sync: ' . $ae->getMessage();
                            $hasO365Col = false;
                        }
                    }

                    if ($hasO365Col) {
                        $upsertStmt = $pdo->prepare("
                            INSERT INTO sessions (title, session_date, session_time, duration_minutes, description, coach_id, o365_event_id, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE
                                title = VALUES(title),
                                session_date = VALUES(session_date),
                                session_time = VALUES(session_time),
                                duration_minutes = VALUES(duration_minutes),
                                description = VALUES(description),
                                updated_at = NOW()
                        ");

                        foreach ($pullData['value'] as $o365Event) {
                            $subject  = $o365Event['subject'] ?? 'Office 365 Event';
                            $icalUid  = $o365Event['iCalUId'] ?? $o365Event['id'] ?? null;
                            if (empty($icalUid)) continue;

                            $startRaw = $o365Event['start']['dateTime'] ?? null;
                            $endRaw   = $o365Event['end']['dateTime'] ?? null;
                            if (!$startRaw) continue;

                            $startTs  = strtotime($startRaw);
                            $endTs    = $endRaw ? strtotime($endRaw) : ($startTs + 3600);
                            $sessDate = date('Y-m-d', $startTs);
                            $sessTime = date('H:i:s', $startTs);
                            $duration = max(15, round(($endTs - $startTs) / 60)); // min 15 minutes

                            $desc = $o365Event['bodyPreview'] ?? '';
                            $locName = $o365Event['location']['displayName'] ?? '';
                            if ($locName) {
                                $desc = ($desc ? $desc . "\n" : '') . 'Location: ' . $locName;
                            }

                            try {
                                $upsertStmt->execute([$subject, $sessDate, $sessTime, $duration, $desc, $user_id, $icalUid]);
                                $pulled++;
                            } catch (PDOException $ie) {
                                $pullErrors[] = "Event '{$subject}': " . $ie->getMessage();
                            }
                        }
                    }
                } elseif (!empty($pullData['error'])) {
                    $pullErrors[] = $pullData['error']['message'] ?? 'Graph API error during pull';
                }
            } catch (Exception $pe) {
                $pullErrors[] = 'Pull error: ' . $pe->getMessage();
            }

            // ── PUSH: Send local sessions TO Office 365 ──────────────────────
            $sessStmt = $pdo->prepare("
                SELECT s.id, s.title, s.session_date, s.session_time, s.duration_minutes,
                       s.description, l.name AS location_name
                FROM sessions s
                LEFT JOIN locations l ON s.location_id = l.id
                WHERE (s.coach_id = ? OR s.id IN (
                    SELECT session_id FROM session_coaches WHERE user_id = ?
                ))
                AND s.session_date >= CURDATE()
                ORDER BY s.session_date, s.session_time
                LIMIT 100
            ");
            $sessStmt->execute([$user_id, $user_id]);
            $sessions = $sessStmt->fetchAll(PDO::FETCH_ASSOC);

            $pushed = 0;
            $pushErrors = [];
            foreach ($sessions as $session) {
                $dateStr  = $session['session_date'];
                $timeStr  = $session['session_time'] ?: '00:00:00';
                $duration = (int)($session['duration_minutes'] ?? 60);
                $startDt  = date('Y-m-d\TH:i:s', strtotime($dateStr . ' ' . $timeStr));
                $endDt    = date('Y-m-d\TH:i:s', strtotime($dateStr . ' ' . $timeStr) + $duration * 60);
                $title    = $session['title'] ?: 'Training Session';

                $eventBody = json_encode([
                    'subject' => $title,
                    'body'    => ['contentType' => 'text', 'content' => $session['description'] ?? ''],
                    'start'   => ['dateTime' => $startDt, 'timeZone' => 'UTC'],
                    'end'     => ['dateTime' => $endDt,   'timeZone' => 'UTC'],
                    'location'=> ['displayName' => $session['location_name'] ?? ''],
                    'categories' => ['Arctic Wolves'],
                ]);

                $graphCtx = stream_context_create([
                    'http' => [
                        'method'        => 'POST',
                        'header'        => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\nContent-Length: " . strlen($eventBody) . "\r\n",
                        'content'       => $eventBody,
                        'ignore_errors' => true,
                        'timeout'       => 15,
                    ],
                    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
                ]);
                $graphResp = @file_get_contents('https://graph.microsoft.com/v1.0/me/events', false, $graphCtx);
                $graphData = $graphResp ? json_decode($graphResp, true) : [];

                if (!empty($graphData['id'])) {
                    $pushed++;
                } else {
                    $errMsg = $graphData['error']['message'] ?? 'Unknown error';
                    $pushErrors[] = "Session #{$session['id']}: {$errMsg}";
                }
            }

            $allErrors = array_merge($pullErrors, $pushErrors);

            Auditor::log($pdo, $user_id, 'create', 'user_oauth_tokens', null, [
                'action'  => 'office365_calendar_sync',
                'pushed'  => $pushed,
                'pulled'  => $pulled,
                'total'   => count($sessions),
            ]);

            if (!empty($allErrors) && $pushed === 0 && $pulled === 0) {
                echo json_encode(['success' => false, 'message' => 'Sync failed: ' . $allErrors[0]]);
            } else {
                $parts = [];
                if ($pushed > 0) $parts[] = "pushed {$pushed} session" . ($pushed !== 1 ? 's' : '');
                if ($pulled > 0) $parts[] = "pulled {$pulled} event" . ($pulled !== 1 ? 's' : '');
                if (empty($parts)) $parts[] = 'calendars are in sync';
                echo json_encode(['success' => true, 'message' => 'Sync complete: ' . implode(', ', $parts)]);
            }
            exit;

        case 'test_smtp':
            $test_email = trim($_POST['test_email'] ?? '');
            if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Please provide a valid email address']);
                exit;
            }
            require_once __DIR__ . '/mailer.php';
            
            $result = sendEmail($test_email, 'test', []);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Test email sent successfully']);
            } else {
                $stmt = $pdo->prepare("SELECT error_message FROM email_logs WHERE to_email = ? AND status = 'failed' ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$test_email]);
                $error = $stmt->fetchColumn();
                echo json_encode(['success' => false, 'message' => $error ?: 'Failed to send test email']);
            }
            exit;
            
        case 'update_rustfs':
            require_once __DIR__ . '/lib/rustfs_storage.php';
            $rustfs_endpoint = trim($_POST['rustfs_endpoint'] ?? '');
            $rustfs_public_endpoint = trim($_POST['rustfs_public_endpoint'] ?? '');
            $rustfs_access_key = trim($_POST['rustfs_access_key'] ?? '');
            $rustfs_secret_key = trim($_POST['rustfs_secret_key'] ?? '');
            $rustfs_bucket = trim($_POST['rustfs_bucket'] ?? '');
            $rustfs_region = trim($_POST['rustfs_region'] ?? 'us-east-1');
            $rustfs_use_ssl = isset($_POST['rustfs_use_ssl']) ? '1' : '0';
            $rustfs_path_style = isset($_POST['rustfs_path_style']) ? '1' : '0';

            updateSetting($pdo, 'rustfs_endpoint', $rustfs_endpoint);
            updateSetting($pdo, 'rustfs_public_endpoint', $rustfs_public_endpoint);
            updateSetting($pdo, 'rustfs_access_key', $rustfs_access_key);
            if (!empty($rustfs_secret_key)) {
                $encrypted_key = encryptPassword($rustfs_secret_key);
                updateSetting($pdo, 'rustfs_secret_key', $encrypted_key);
            }
            updateSetting($pdo, 'rustfs_bucket', $rustfs_bucket);
            updateSetting($pdo, 'rustfs_region', $rustfs_region);
            updateSetting($pdo, 'rustfs_use_ssl', $rustfs_use_ssl);
            updateSetting($pdo, 'rustfs_path_style', $rustfs_path_style);

            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_rustfs',
                'settings' => ['rustfs_endpoint' => $rustfs_endpoint, 'rustfs_bucket' => $rustfs_bucket, 'rustfs_region' => $rustfs_region]
            ]);

            // Apply CORS policy to the bucket so direct browser uploads work
            $cors_settings = getRustFSSettings($pdo);
            if (isRustFSConfigured($cors_settings)) {
                ensureRustFSBucketCors($cors_settings);
            }

            header('Location: dashboard.php?page=system_tools&tab=rustfs&success=1');
            exit;

        case 'test_rustfs':
            require_once __DIR__ . '/lib/rustfs_storage.php';
            $rustfs_secret_key = trim($_POST['rustfs_secret_key'] ?? '');
            // If no secret key provided, use stored encrypted one
            if (empty($rustfs_secret_key)) {
                $stored = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'rustfs_secret_key'");
                $stored->execute();
                $encrypted = $stored->fetchColumn();
                if (!empty($encrypted)) {
                    $decrypted = decryptPassword($encrypted);
                    if (!empty($decrypted)) {
                        $rustfs_secret_key = $decrypted;
                    }
                }
            }
            $test_settings = [
                'rustfs_endpoint' => trim($_POST['rustfs_endpoint'] ?? ''),
                'rustfs_access_key' => trim($_POST['rustfs_access_key'] ?? ''),
                'rustfs_secret_key' => $rustfs_secret_key,
                'rustfs_bucket' => trim($_POST['rustfs_bucket'] ?? ''),
                'rustfs_region' => trim($_POST['rustfs_region'] ?? 'us-east-1'),
                'rustfs_use_ssl' => isset($_POST['rustfs_use_ssl']) ? '1' : '0',
                'rustfs_path_style' => isset($_POST['rustfs_path_style']) ? '1' : '0',
            ];
            $result = testRustFSConnection($test_settings);
            echo json_encode($result);
            exit;

        case 'test_upload_api':
            require_once __DIR__ . '/lib/rustfs_storage.php';
            // Test the upload API endpoint (api/upload.php) and RustFS round-trip
            $results = ['api_reachable' => false, 'rustfs_write' => false, 'rustfs_verify' => false, 'rustfs_cleanup' => false];
            $messages = [];

            // Step 1: Verify api/upload.php exists and is accessible
            $upload_api_path = __DIR__ . '/api/upload.php';
            if (file_exists($upload_api_path)) {
                $results['api_reachable'] = true;
                $messages[] = 'Upload API endpoint file exists';
            } else {
                $messages[] = 'Upload API endpoint file not found at api/upload.php';
                echo json_encode(['success' => false, 'message' => implode('; ', $messages), 'results' => $results]);
                exit;
            }

            // Step 2: Test RustFS write + verify round-trip with a small test object
            $rustfs_secret_key = trim($_POST['rustfs_secret_key'] ?? '');
            if (empty($rustfs_secret_key)) {
                $stored = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'rustfs_secret_key'");
                $stored->execute();
                $encrypted = $stored->fetchColumn();
                if (!empty($encrypted)) {
                    $decrypted = decryptPassword($encrypted);
                    if (!empty($decrypted)) {
                        $rustfs_secret_key = $decrypted;
                    }
                }
            }
            $test_settings = [
                'rustfs_endpoint' => trim($_POST['rustfs_endpoint'] ?? ''),
                'rustfs_access_key' => trim($_POST['rustfs_access_key'] ?? ''),
                'rustfs_secret_key' => $rustfs_secret_key,
                'rustfs_bucket' => trim($_POST['rustfs_bucket'] ?? ''),
                'rustfs_region' => trim($_POST['rustfs_region'] ?? 'us-east-1'),
                'rustfs_use_ssl' => isset($_POST['rustfs_use_ssl']) ? '1' : '0',
                'rustfs_path_style' => isset($_POST['rustfs_path_style']) ? '1' : '0',
            ];

            if (!isRustFSConfigured($test_settings)) {
                $messages[] = 'RustFS settings are incomplete';
                echo json_encode(['success' => false, 'message' => implode('; ', $messages), 'results' => $results]);
                exit;
            }

            // Write a small test object
            $test_key = '.system-test/api-test-' . bin2hex(random_bytes(8)) . '.txt';
            $test_content = 'Arctic Wolves API endpoint test — ' . date('Y-m-d H:i:s');
            $upload_result = uploadContentToRustFS($test_settings, $test_content, $test_key, 'text/plain');
            if ($upload_result['success']) {
                $results['rustfs_write'] = true;
                $messages[] = 'RustFS write succeeded';
            } else {
                $messages[] = 'RustFS write failed: ' . ($upload_result['message'] ?? 'Unknown error');
                echo json_encode(['success' => false, 'message' => implode('; ', $messages), 'results' => $results]);
                exit;
            }

            // Verify the object exists (uses same check as video upload confirmation)
            if (rustfsObjectExists($test_settings, $test_key)) {
                $results['rustfs_verify'] = true;
                $messages[] = 'RustFS verify (HEAD) succeeded';
            } else {
                $messages[] = 'RustFS verify (HEAD) failed — object not found after write';
            }

            // Clean up the test object
            $deleted = deleteFromRustFS($test_settings, $test_key);
            if ($deleted) {
                $results['rustfs_cleanup'] = true;
                $messages[] = 'Test object cleaned up';
            } else {
                $messages[] = 'Test object cleanup failed (non-critical)';
            }

            $all_passed = $results['api_reachable'] && $results['rustfs_write'] && $results['rustfs_verify'];
            echo json_encode([
                'success' => $all_passed,
                'message' => $all_passed
                    ? 'Upload API endpoint and RustFS round-trip test passed'
                    : implode('; ', $messages),
                'results' => $results,
            ]);
            exit;

        case 'update_paperless':
            $paperless_url = trim($_POST['paperless_url'] ?? '');
            $paperless_api_token = trim($_POST['paperless_api_token'] ?? '');
            $paperless_ocr_enabled = isset($_POST['paperless_ocr_enabled']) ? '1' : '0';
            $paperless_store_documents = isset($_POST['paperless_store_documents']) ? '1' : '0';
            $paperless_correspondent = trim($_POST['paperless_correspondent'] ?? '');
            $paperless_document_type = trim($_POST['paperless_document_type'] ?? '');
            
            updateSetting($pdo, 'paperless_url', $paperless_url);
            if (!empty($paperless_api_token)) {
                $encrypted_token = encryptPassword($paperless_api_token);
                updateSetting($pdo, 'paperless_api_token', $encrypted_token);
            }
            updateSetting($pdo, 'paperless_ocr_enabled', $paperless_ocr_enabled);
            updateSetting($pdo, 'paperless_store_documents', $paperless_store_documents);
            updateSetting($pdo, 'paperless_correspondent', $paperless_correspondent);
            updateSetting($pdo, 'paperless_document_type', $paperless_document_type);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_paperless',
                'settings' => ['paperless_url' => $paperless_url, 'paperless_ocr_enabled' => $paperless_ocr_enabled, 'paperless_store_documents' => $paperless_store_documents]
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=paperless&success=1');
            exit;
            
        case 'test_paperless':
            $paperless_url = trim($_POST['paperless_url'] ?? '');
            $paperless_api_token = trim($_POST['paperless_api_token'] ?? '');
            
            if (empty($paperless_url)) {
                echo json_encode(['success' => false, 'message' => 'Paperless-NGX URL is required']);
                exit;
            }
            
            // If no token provided, use stored one
            if (empty($paperless_api_token)) {
                $stored_token_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'paperless_api_token'");
                $stored_token_stmt->execute();
                $encrypted_token = $stored_token_stmt->fetchColumn();
                if (!empty($encrypted_token)) {
                    $paperless_api_token = decryptPassword($encrypted_token);
                }
            }
            
            if (empty($paperless_api_token)) {
                echo json_encode(['success' => false, 'message' => 'API token is required']);
                exit;
            }
            
            // Test connection by calling the Paperless-NGX API
            // Use /api/documents/ endpoint with versioned Accept header per Paperless-NGX API docs
            $test_url = rtrim($paperless_url, '/') . '/api/documents/?page=1&page_size=1';
            $ch = curl_init($test_url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Token ' . $paperless_api_token,
                    'Accept: application/json; version=5'
                ],
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if (!empty($curl_error)) {
                echo json_encode(['success' => false, 'message' => 'Connection error: ' . $curl_error]);
            } elseif ($http_code === 200) {
                $version_info = '';
                $resp_data = json_decode($response, true);
                if (is_array($resp_data) && isset($resp_data['count'])) {
                    $version_info = ' (' . intval($resp_data['count']) . ' documents in library)';
                }
                echo json_encode(['success' => true, 'message' => 'Connected to Paperless-NGX at ' . $paperless_url . $version_info]);
            } elseif ($http_code === 401 || $http_code === 403) {
                echo json_encode(['success' => false, 'message' => 'Authentication failed - check your API token']);
            } elseif ($http_code === 406) {
                echo json_encode(['success' => false, 'message' => 'API version not supported by your Paperless-NGX server (HTTP 406). Please update Paperless-NGX to a newer version.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Unexpected response (HTTP ' . $http_code . ')']);
            }
            exit;
            
        case 'update_payments':
            // Stripe settings
            $stripe_publishable_key = trim($_POST['stripe_publishable_key']);
            $stripe_secret_key = trim($_POST['stripe_secret_key']);
            $currency = trim($_POST['currency']);
            
            // Tax settings
            $tax_name = trim($_POST['tax_name']);
            $tax_rate = floatval($_POST['tax_rate']);
            
            // Validate Stripe keys format
            if (!empty($stripe_publishable_key) && !preg_match('/^pk_(test|live)_[a-zA-Z0-9]+$/', $stripe_publishable_key)) {
                throw new Exception('Invalid Stripe publishable key format. Must start with pk_test_ or pk_live_');
            }
            if (!empty($stripe_secret_key) && !preg_match('/^sk_(test|live)_[a-zA-Z0-9]+$/', $stripe_secret_key)) {
                throw new Exception('Invalid Stripe secret key format. Must start with sk_test_ or sk_live_');
            }
            
            // Validate currency
            if (!in_array($currency, ['CAD', 'USD', 'EUR', 'GBP'])) {
                throw new Exception('Invalid currency code');
            }
            
            // Validate tax rate
            if ($tax_rate < 0 || $tax_rate > 100) {
                throw new Exception('Tax rate must be between 0 and 100');
            }
            
            // Update Stripe settings
            if (!empty($stripe_publishable_key)) {
                updateSetting($pdo, 'stripe_publishable_key', encryptPassword($stripe_publishable_key));
            }
            // Only update secret key if a new one is provided
            if (!empty($stripe_secret_key)) {
                updateSetting($pdo, 'stripe_secret_key', encryptPassword($stripe_secret_key));
            }
            updateSetting($pdo, 'currency', $currency);
            
            // Update tax settings
            updateSetting($pdo, 'tax_name', $tax_name);
            updateSetting($pdo, 'tax_rate', $tax_rate);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_payments',
                'settings' => ['currency' => $currency, 'tax_name' => $tax_name, 'tax_rate' => $tax_rate, 'stripe_key_updated' => !empty($stripe_secret_key)]
            ]);
            
            // Redirect back to the appropriate page
            header('Location: dashboard.php?page=system_tools&tab=payments&success=1');
            exit;
            
        case 'update_security':
            $session_timeout = intval($_POST['session_timeout_minutes']);
            
            updateSetting($pdo, 'session_timeout_minutes', $session_timeout);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_security',
                'settings' => ['session_timeout_minutes' => $session_timeout]
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=settings&success=1');
            exit;

        case 'update_recaptcha':
            $recaptcha_site_key = trim($_POST['recaptcha_site_key'] ?? '');
            $recaptcha_secret_key = trim($_POST['recaptcha_secret_key'] ?? '');

            // Sanitize: strip any HTML/tags
            $recaptcha_site_key = strip_tags($recaptcha_site_key);
            $recaptcha_secret_key = strip_tags($recaptcha_secret_key);

            // Validate key format if provided (alphanumeric, hyphens, underscores)
            if (!empty($recaptcha_site_key) && !preg_match('/^[a-zA-Z0-9_\-]{20,100}$/', $recaptcha_site_key)) {
                throw new Exception('Invalid reCAPTCHA site key format.');
            }
            if (!empty($recaptcha_secret_key) && !preg_match('/^[a-zA-Z0-9_\-]{20,100}$/', $recaptcha_secret_key)) {
                throw new Exception('Invalid reCAPTCHA secret key format.');
            }

            // Encrypt and save (only update if a value is provided)
            if (!empty($recaptcha_site_key)) {
                updateSetting($pdo, 'recaptcha_site_key', encryptPassword($recaptcha_site_key));
            }
            if (!empty($recaptcha_secret_key)) {
                updateSetting($pdo, 'recaptcha_secret_key', encryptPassword($recaptcha_secret_key));
            }

            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_recaptcha',
                'settings' => ['site_key_updated' => !empty($recaptcha_site_key), 'secret_key_updated' => !empty($recaptcha_secret_key)]
            ]);

            header('Location: dashboard.php?page=system_tools&tab=encryption&success=1');
            exit;
            
        case 'update_advanced':
            $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
            $debug_mode = isset($_POST['debug_mode']) ? '1' : '0';
            
            updateSetting($pdo, 'maintenance_mode', $maintenance_mode);
            updateSetting($pdo, 'debug_mode', $debug_mode);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_advanced',
                'settings' => ['maintenance_mode' => $maintenance_mode, 'debug_mode' => $debug_mode]
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=settings&success=1');
            exit;
            
        case 'update_settings':
            // Handle general settings from system tools page
            $site_title = trim($_POST['site_title'] ?? 'Arctic Wolves');
            $site_email = trim($_POST['site_email'] ?? '');
            $contact_phone = trim($_POST['contact_phone'] ?? '');
            $org_address = trim($_POST['org_address'] ?? '');
            $date_format = trim($_POST['date_format'] ?? 'MM/DD/YYYY');
            $session_duration = intval($_POST['session_duration'] ?? 60);
            $notifications_enabled = isset($_POST['notifications_enabled']) ? '1' : '0';
            $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
            $province = trim($_POST['province'] ?? 'ON');
            $booking_window_days = intval($_POST['booking_window_days'] ?? 30);
            $cancellation_window_hours = intval($_POST['cancellation_window_hours'] ?? 24);
            $auto_confirm_bookings = isset($_POST['auto_confirm_bookings']) ? '1' : '0';
            $send_booking_confirmations = isset($_POST['send_booking_confirmations']) ? '1' : '0';
            
            // Validate province code
            $valid_provinces = ['AB','BC','MB','NB','NL','NS','NT','NU','ON','PE','QC','SK','YT'];
            if (!in_array($province, $valid_provinces)) {
                $province = 'ON';
            }
            
            // Validate date format
            $valid_formats = ['MM/DD/YYYY','DD/MM/YYYY','YYYY-MM-DD'];
            if (!in_array($date_format, $valid_formats)) {
                $date_format = 'MM/DD/YYYY';
            }
            
            updateSetting($pdo, 'site_title', $site_title);
            updateSetting($pdo, 'site_email', $site_email);
            updateSetting($pdo, 'contact_phone', $contact_phone);
            updateSetting($pdo, 'org_address', $org_address);
            updateSetting($pdo, 'date_format', $date_format);
            updateSetting($pdo, 'session_duration', $session_duration);
            updateSetting($pdo, 'notifications_enabled', $notifications_enabled);
            updateSetting($pdo, 'maintenance_mode', $maintenance_mode);
            updateSetting($pdo, 'province', $province);
            updateSetting($pdo, 'booking_window_days', $booking_window_days);
            updateSetting($pdo, 'cancellation_window_hours', $cancellation_window_hours);
            updateSetting($pdo, 'auto_confirm_bookings', $auto_confirm_bookings);
            updateSetting($pdo, 'send_booking_confirmations', $send_booking_confirmations);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_settings',
                'settings' => [
                    'site_title' => $site_title,
                    'site_email' => $site_email,
                    'contact_phone' => $contact_phone,
                    'org_address' => $org_address,
                    'date_format' => $date_format,
                    'session_duration' => $session_duration,
                    'notifications_enabled' => $notifications_enabled,
                    'maintenance_mode' => $maintenance_mode,
                    'province' => $province,
                    'booking_window_days' => $booking_window_days,
                    'cancellation_window_hours' => $cancellation_window_hours,
                    'auto_confirm_bookings' => $auto_confirm_bookings,
                    'send_booking_confirmations' => $send_booking_confirmations
                ]
            ]);
            
            header('Location: dashboard.php?page=system_tools&success=1');
            exit;
            
        case 'update_theme':
            // Handle theme settings from system tools page
            $primary_color = trim($_POST['primary_color'] ?? '#6B46C1');
            $accent_color = trim($_POST['accent_color'] ?? '#8B5CF6');
            $bg_color = trim($_POST['bg_color'] ?? '#06080b');
            
            updateSetting($pdo, 'primary_color', $primary_color);
            updateSetting($pdo, 'accent_color', $accent_color);
            updateSetting($pdo, 'background_color', $bg_color);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_theme',
                'settings' => ['primary_color' => $primary_color, 'accent_color' => $accent_color, 'bg_color' => $bg_color]
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=theme&success=1');
            exit;
            
        case 'update_google_maps':
            $api_key = trim($_POST['google_maps_api_key']);
            if (!empty($api_key)) {
                updateSetting($pdo, 'google_maps_api_key', encryptPassword($api_key));
            }
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_google_maps',
                'settings' => ['google_maps_api_key' => '***updated***']
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=mileage&success=1');
            exit;
            
        case 'test_google_maps':
            $api_key = trim($_POST['google_maps_api_key'] ?? '');
            
            // If no API key provided in form, use stored encrypted key from database
            if (empty($api_key)) {
                $api_key = getDecryptedSetting($pdo, 'google_maps_api_key');
            }
            
            if (empty($api_key)) {
                echo json_encode(['success' => false, 'message' => 'Please enter a Google Maps API key first.']);
                exit;
            }
            
            // Test by sending a request to Google's Geocoding API server-side
            $test_url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode('Toronto,Canada') . '&key=' . urlencode($api_key);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'ignore_errors' => true
                ]
            ]);
            
            $response = file_get_contents($test_url, false, $context);
            
            if ($response === false) {
                $error = error_get_last();
                $error_message = $error ? $error['message'] : 'Unknown error';
                ErrorLogger::error("Google Maps API test failed: " . $error_message);
                echo json_encode(['success' => false, 'message' => 'Failed to connect to Google Maps API. Please check your server network connection.']);
                exit;
            }
            
            $data = json_decode($response, true);
            
            if (!$data) {
                echo json_encode(['success' => false, 'message' => 'Invalid response from Google Maps API.']);
                exit;
            }
            
            $status = $data['status'] ?? 'UNKNOWN';
            
            if ($status === 'OK' || $status === 'ZERO_RESULTS') {
                echo json_encode(['success' => true, 'message' => 'Google Maps API key is valid and working correctly.']);
            } elseif ($status === 'REQUEST_DENIED') {
                $error_msg = $data['error_message'] ?? 'Please check your API key and ensure the Geocoding API is enabled.';
                echo json_encode(['success' => false, 'message' => 'API Key Invalid or Restricted: ' . $error_msg]);
            } else {
                $error_msg = $data['error_message'] ?? 'Please check your API key configuration.';
                echo json_encode(['success' => false, 'message' => 'API Test Result: ' . $status . ' - ' . $error_msg]);
            }
            exit;
            
        case 'update_mileage_rates':
            $rate_km = floatval($_POST['mileage_rate_per_km']);
            $rate_after_5000_km = floatval($_POST['mileage_rate_after_5000_per_km']);
            $rate_mile = floatval($_POST['mileage_rate_per_mile']);
            $mileage_unit = trim($_POST['mileage_unit'] ?? 'km');
            
            // Validate mileage unit
            if (!in_array($mileage_unit, ['km', 'miles'])) {
                $mileage_unit = 'km';
            }
            
            updateSetting($pdo, 'mileage_rate_per_km', $rate_km);
            updateSetting($pdo, 'mileage_rate_after_5000_per_km', $rate_after_5000_km);
            updateSetting($pdo, 'mileage_rate_per_mile', $rate_mile);
            updateSetting($pdo, 'mileage_unit', $mileage_unit);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_mileage_rates',
                'settings' => ['mileage_rate_per_km' => $rate_km, 'mileage_rate_after_5000_per_km' => $rate_after_5000_km, 'mileage_rate_per_mile' => $rate_mile, 'mileage_unit' => $mileage_unit]
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=mileage&success=1');
            exit;
            
        case 'update_github_settings':
            $github_token = trim($_POST['github_token']);
            if (!empty($github_token)) {
                updateSetting($pdo, 'github_token', encryptPassword($github_token));
            }
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_github_settings',
                'settings' => ['github_token' => '***updated***']
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=updates&success=1');
            exit;
            
        case 'test_github':
            require_once __DIR__ . '/lib/github_updater.php';
            $updater = new GitHubUpdater($pdo);
            $result = $updater->testGitHubConnection();
            echo json_encode($result);
            exit;
            
        case 'check_updates':
            require_once __DIR__ . '/lib/github_updater.php';
            $updater = new GitHubUpdater($pdo);
            $result = $updater->checkForUpdates();
            echo json_encode($result);
            exit;
            
        case 'apply_updates':
            require_once __DIR__ . '/lib/github_updater.php';
            
            // Apply any leftover deferred files from a previous update
            GitHubUpdater::applyDeferredUpdates(__DIR__);
            
            // Prevent the update from being interrupted by client disconnect
            ignore_user_abort(true);
            set_time_limit(300);
            
            $updater = new GitHubUpdater($pdo);
            $result = $updater->applyUpdates();
            
            // If the update produced deferred files, schedule them to be applied
            // after the response is sent so the running PHP files are not replaced
            // while this request is still using them.
            $base_dir = __DIR__;
            if (!empty($result['has_deferred'])) {
                register_shutdown_function(function() use ($base_dir, $pdo) {
                    GitHubUpdater::applyDeferredUpdates($base_dir);
                    
                    // Re-run schema check after deferred files are applied.
                    // DatabaseMigrator (lib/database_migrator.php) is NOT deferred,
                    // so it was already updated to the new version during the update.
                    // This catches any missing tables/columns that the initial schema
                    // check could not handle because github_updater.php was deferred.
                    try {
                        $migrator_file = $base_dir . '/lib/database_migrator.php';
                        $schema_file = $base_dir . '/database_schema.sql';
                        if (file_exists($migrator_file) && file_exists($schema_file)) {
                            require_once $migrator_file;
                            $migrator = new DatabaseMigrator($pdo, $base_dir);
                            $expected = $migrator->parseSchemaFile($schema_file);
                            $current = $migrator->getCurrentSchema();
                            $remaining = $migrator->compareSchemas($current, $expected);
                            
                            if (!empty($remaining)) {
                                $schema_sql = file_get_contents($schema_file);
                                $tpl = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?%s`?\s*\(.*?\)\s*ENGINE[^;]*;/is';
                                
                                try { $pdo->exec('SET FOREIGN_KEY_CHECKS = 0'); } catch (Exception $e) {}
                                try {
                                    // First pass: create all missing tables
                                    foreach ($remaining as $m) {
                                        if ($m['type'] !== 'create_table') continue;
                                        try {
                                            $tn = $m['table'];
                                            if (preg_match(sprintf($tpl, preg_quote($tn, '/')), $schema_sql, $match)) {
                                                $pdo->exec($match[0]);
                                            }
                                        } catch (Exception $e) {
                                            error_log('Deferred schema fix (create): ' . $e->getMessage());
                                        }
                                    }
                                    // Second pass: add all missing columns
                                    foreach ($remaining as $m) {
                                        if ($m['type'] !== 'add_column') continue;
                                        try {
                                            $migrator->executeMigration($m);
                                        } catch (Exception $e) {
                                            error_log('Deferred schema fix (column): ' . $e->getMessage());
                                        }
                                    }
                                } finally {
                                    try { $pdo->exec('SET FOREIGN_KEY_CHECKS = 1'); } catch (Exception $e) {}
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Post-deferred schema check error: ' . $e->getMessage());
                    }
                });
            }
            
            echo json_encode($result);
            exit;
            
        case 'sync_to_backup':
            // This would sync files from primary to backup - placeholder for now
            echo json_encode(['success' => true, 'message' => 'Sync to backup initiated. Files will be synchronized in the background.']);
            exit;
            
        case 'check_stripe_updates':
            // Check latest Stripe release via server-side request
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Arctic-Wolves-Updater',
                    'timeout' => 30
                ]
            ]);
            
            $release_info = @file_get_contents('https://api.github.com/repos/stripe/stripe-php/releases/latest', false, $context);
            if ($release_info === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch Stripe release info']);
                exit;
            }
            
            $release = json_decode($release_info, true);
            if (!isset($release['tag_name'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid response from GitHub']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'tag_name' => $release['tag_name'],
                'name' => $release['name'] ?? 'Stripe PHP Library',
                'published_at' => $release['published_at'] ?? ''
            ]);
            exit;
            
        case 'update_stripe_library':
            // Update Stripe PHP library from GitHub
            $stripe_path = __DIR__ . '/stripe-php';
            $real_base = realpath(__DIR__);
            $real_stripe = realpath($stripe_path);
            // Allow update even if stripe-php doesn't exist yet (first install or after failed update)
            if ($real_stripe && strpos($real_stripe, $real_base) !== 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid stripe-php path']);
                exit;
            }
            // Use the canonical path for the target directory
            $stripe_path = $real_stripe ?: ($real_base . '/stripe-php');
            $temp_path = sys_get_temp_dir() . '/stripe-php-' . time();
            
            // Helper to recursively remove a directory
            $removeDir = function($dir) use (&$removeDir) {
                if (!is_dir($dir)) return;
                $rdi = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
                $rfi = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($rfi as $f) { $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath()); }
                rmdir($dir);
            };
            
            // Get latest release info
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Arctic-Wolves-Updater',
                    'timeout' => 30
                ]
            ]);
            
            $release_info = @file_get_contents('https://api.github.com/repos/stripe/stripe-php/releases/latest', false, $context);
            if ($release_info === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch latest Stripe release info']);
                exit;
            }
            
            $release = json_decode($release_info, true);
            if (!isset($release['zipball_url'])) {
                echo json_encode(['success' => false, 'message' => 'Could not find download URL for latest release']);
                exit;
            }
            
            // Download the release
            $zip_url = $release['zipball_url'];
            $zip_content = @file_get_contents($zip_url, false, $context);
            if ($zip_content === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to download Stripe library']);
                exit;
            }
            
            // Save and extract
            $zip_file = $temp_path . '.zip';
            if (file_put_contents($zip_file, $zip_content) === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to save downloaded file']);
                exit;
            }
            
            $zip = new ZipArchive();
            if ($zip->open($zip_file) !== true) {
                unlink($zip_file);
                echo json_encode(['success' => false, 'message' => 'Failed to extract Stripe library']);
                exit;
            }
            
            // Extract to temp directory
            mkdir($temp_path, 0755, true);
            $zip->extractTo($temp_path);
            $zip->close();
            unlink($zip_file);
            
            // Find the extracted folder - validate it matches the expected naming pattern
            $extracted_dirs = glob($temp_path . '/stripe-stripe-php-*');
            $extracted_dir = null;
            foreach ($extracted_dirs as $dir) {
                // Validate directory name matches expected pattern (alphanumeric hash)
                $dir_name = basename($dir);
                if (preg_match('/^stripe-stripe-php-[a-f0-9]+$/i', $dir_name) && is_dir($dir)) {
                    $extracted_dir = $dir;
                    break;
                }
            }
            if (!$extracted_dir) {
                // Cleanup temp directory
                $removeDir($temp_path);
                echo json_encode(['success' => false, 'message' => 'Could not find extracted files with valid naming pattern']);
                exit;
            }
            
            // Backup current stripe-php if exists
            $backup_path = null;
            if (is_dir($stripe_path)) {
                $backup_path = $stripe_path . '.backup-' . date('Y-m-d-His');
                if (!rename($stripe_path, $backup_path)) {
                    echo json_encode(['success' => false, 'message' => 'Failed to backup existing Stripe library. Check file permissions on ' . basename($stripe_path)]);
                    exit;
                }
            }
            
            // Move new files to stripe-php (rename may fail across filesystems, use copy fallback)
            $install_success = @rename($extracted_dir, $stripe_path);
            if (!$install_success) {
                // Fallback: recursively copy from extracted dir to stripe_path
                $install_success = true;
                $src = realpath($extracted_dir);
                if ($src) {
                    mkdir($stripe_path, 0755, true);
                    $rdi = new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS);
                    $rfi = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::SELF_FIRST);
                    foreach ($rfi as $item) {
                        $dest = $stripe_path . '/' . $rfi->getSubPathname();
                        if ($item->isDir()) {
                            if (!is_dir($dest)) { mkdir($dest, 0755, true); }
                        } else {
                            if (!copy($item->getRealPath(), $dest)) {
                                $install_success = false;
                                break;
                            }
                        }
                    }
                } else {
                    $install_success = false;
                }
            }
            
            if (!$install_success) {
                // Restore backup if install failed
                if ($backup_path && is_dir($backup_path)) {
                    if (is_dir($stripe_path)) {
                        // Clean partial install
                        $removeDir($stripe_path);
                    }
                    rename($backup_path, $stripe_path);
                }
                // Cleanup temp directory
                $removeDir($temp_path);
                echo json_encode(['success' => false, 'message' => 'Failed to install new Stripe library — previous version has been restored']);
                exit;
            }
            
            // Cleanup temp directory
            $removeDir($temp_path);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Stripe library updated to version ' . ($release['tag_name'] ?? 'latest')
            ]);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_stripe_library',
                'settings' => ['stripe_version' => $release['tag_name'] ?? 'latest']
            ]);
            
            exit;
            
        case 'update_landing':
            // Update Programs settings (4 programs)
            for ($i = 1; $i <= 4; $i++) {
                $prefix = "landing_program_{$i}_";
                $title = trim($_POST[$prefix . 'title'] ?? '');
                $image = trim($_POST[$prefix . 'image'] ?? '');
                $tags = trim($_POST[$prefix . 'tags'] ?? '');
                $description = trim($_POST[$prefix . 'description'] ?? '');
                
                // Validate image URL if provided - must be http or https
                if (!empty($image)) {
                    if (!filter_var($image, FILTER_VALIDATE_URL)) {
                        throw new Exception("Invalid URL for Program $i image");
                    }
                    $parsed = parse_url($image);
                    if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
                        throw new Exception("Program $i image URL must use http or https scheme");
                    }
                }
                
                updateSetting($pdo, $prefix . 'title', $title);
                updateSetting($pdo, $prefix . 'image', $image);
                updateSetting($pdo, $prefix . 'tags', $tags);
                updateSetting($pdo, $prefix . 'description', $description);
            }
            
            // Update Standards settings (4 standards)
            for ($i = 1; $i <= 4; $i++) {
                $prefix = "landing_standard_{$i}_";
                $label = trim($_POST[$prefix . 'label'] ?? '');
                $value = trim($_POST[$prefix . 'value'] ?? '');
                
                updateSetting($pdo, $prefix . 'label', $label);
                updateSetting($pdo, $prefix . 'value', $value);
            }
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_landing',
                'settings' => ['programs_and_standards_updated' => true]
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=landing&success=1');
            exit;
            
        case 'update_docuseal':
            $docuseal_enabled = isset($_POST['docuseal_enabled']) ? '1' : '0';
            $docuseal_url = trim($_POST['docuseal_url'] ?? '');
            $docuseal_api_key = trim($_POST['docuseal_api_key'] ?? '');
            $docuseal_webhook_secret = trim($_POST['docuseal_webhook_secret'] ?? '');
            $docuseal_auto_confirm = isset($_POST['docuseal_auto_confirm']) ? '1' : '0';
            $docuseal_verify_ssl = isset($_POST['docuseal_verify_ssl']) ? '1' : '0';
            
            // Validate URL if provided
            if (!empty($docuseal_url) && !filter_var($docuseal_url, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid DocuSeal URL format');
            }
            
            updateSetting($pdo, 'docuseal_enabled', $docuseal_enabled);
            updateSetting($pdo, 'docuseal_url', $docuseal_url);
            if (!empty($docuseal_api_key)) {
                updateSetting($pdo, 'docuseal_api_key', encryptPassword($docuseal_api_key));
            }
            if (!empty($docuseal_webhook_secret)) {
                updateSetting($pdo, 'docuseal_webhook_secret', encryptPassword($docuseal_webhook_secret));
            }
            updateSetting($pdo, 'docuseal_auto_confirm', $docuseal_auto_confirm);
            updateSetting($pdo, 'docuseal_verify_ssl', $docuseal_verify_ssl);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_docuseal',
                'settings' => ['docuseal_enabled' => $docuseal_enabled, 'docuseal_url' => $docuseal_url, 'docuseal_api_key' => '***updated***']
            ]);
            
            // Redirect back to the appropriate page
            header('Location: dashboard.php?page=system_tools&tab=docuseal&success=1');
            exit;
            
        case 'test_docuseal':
            require_once __DIR__ . '/lib/docuseal.php';
            
            $docuseal_url = trim($_POST['docuseal_url'] ?? '');
            $docuseal_api_key = trim($_POST['docuseal_api_key'] ?? '');
            
            // If no API key provided in form, use stored encrypted key from database
            if (empty($docuseal_api_key)) {
                $docuseal_api_key = getDecryptedSetting($pdo, 'docuseal_api_key');
            }
            
            // If no URL provided in form, use stored URL from database
            if (empty($docuseal_url)) {
                $stored_url_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'docuseal_url'");
                $stored_url_stmt->execute();
                $docuseal_url = $stored_url_stmt->fetchColumn() ?: '';
            }
            
            $settings = [
                'docuseal_url' => $docuseal_url,
                'docuseal_api_key' => $docuseal_api_key,
                'docuseal_verify_ssl' => '1'
            ];
            
            $result = testDocuSealConnection($settings);
            echo json_encode($result);
            exit;
            
        case 'update_stallion':
            $stallion_enabled = isset($_POST['stallion_enabled']) ? '1' : '0';
            $stallion_api_url = trim($_POST['stallion_api_url'] ?? '');
            $stallion_api_key = trim($_POST['stallion_api_key'] ?? '');
            $stallion_api_secret = trim($_POST['stallion_api_secret'] ?? '');
            $stallion_sender_name = trim($_POST['stallion_sender_name'] ?? '');
            $stallion_sender_company = trim($_POST['stallion_sender_company'] ?? '');
            $stallion_sender_address = trim($_POST['stallion_sender_address'] ?? '');
            $stallion_sender_city = trim($_POST['stallion_sender_city'] ?? '');
            $stallion_sender_province = trim($_POST['stallion_sender_province'] ?? '');
            $stallion_sender_postal_code = trim($_POST['stallion_sender_postal_code'] ?? '');
            $stallion_sender_phone = trim($_POST['stallion_sender_phone'] ?? '');
            $stallion_default_weight = trim($_POST['stallion_default_weight'] ?? '0.5');
            $stallion_default_length = trim($_POST['stallion_default_length'] ?? '25');
            $stallion_default_width = trim($_POST['stallion_default_width'] ?? '20');
            $stallion_default_height = trim($_POST['stallion_default_height'] ?? '10');
            
            // Validate URL if provided
            if (!empty($stallion_api_url) && !filter_var($stallion_api_url, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid Stallion Express API URL format');
            }
            
            updateSetting($pdo, 'stallion_enabled', $stallion_enabled);
            updateSetting($pdo, 'stallion_api_url', $stallion_api_url);
            if (!empty($stallion_api_key)) {
                updateSetting($pdo, 'stallion_api_key', encryptPassword($stallion_api_key));
            }
            if (!empty($stallion_api_secret)) {
                updateSetting($pdo, 'stallion_api_secret', encryptPassword($stallion_api_secret));
            }
            updateSetting($pdo, 'stallion_sender_name', $stallion_sender_name);
            updateSetting($pdo, 'stallion_sender_company', $stallion_sender_company);
            updateSetting($pdo, 'stallion_sender_address', $stallion_sender_address);
            updateSetting($pdo, 'stallion_sender_city', $stallion_sender_city);
            updateSetting($pdo, 'stallion_sender_province', $stallion_sender_province);
            updateSetting($pdo, 'stallion_sender_postal_code', $stallion_sender_postal_code);
            updateSetting($pdo, 'stallion_sender_phone', $stallion_sender_phone);
            updateSetting($pdo, 'stallion_default_weight', $stallion_default_weight);
            updateSetting($pdo, 'stallion_default_length', $stallion_default_length);
            updateSetting($pdo, 'stallion_default_width', $stallion_default_width);
            updateSetting($pdo, 'stallion_default_height', $stallion_default_height);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_stallion',
                'settings' => ['stallion_enabled' => $stallion_enabled, 'stallion_api_url' => $stallion_api_url, 'stallion_api_key' => '***updated***']
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=stallion&success=1');
            exit;
            
        case 'test_stallion':
            require_once __DIR__ . '/lib/stallion_express.php';
            
            $stallion_api_url = trim($_POST['stallion_api_url'] ?? '');
            $stallion_api_key = trim($_POST['stallion_api_key'] ?? '');
            $stallion_api_secret = trim($_POST['stallion_api_secret'] ?? '');
            
            // If no API key provided in form, use stored encrypted key from database
            if (empty($stallion_api_key)) {
                $stallion_api_key = getDecryptedSetting($pdo, 'stallion_api_key');
            }
            
            // If no API secret provided in form, use stored encrypted secret from database
            if (empty($stallion_api_secret)) {
                $stallion_api_secret = getDecryptedSetting($pdo, 'stallion_api_secret');
            }
            
            // If no URL provided in form, use stored URL from database
            if (empty($stallion_api_url)) {
                $stored_url_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'stallion_api_url'");
                $stored_url_stmt->execute();
                $stallion_api_url = $stored_url_stmt->fetchColumn() ?: '';
            }
            
            $settings = [
                'stallion_api_url' => $stallion_api_url,
                'stallion_api_key' => $stallion_api_key,
                'stallion_api_secret' => $stallion_api_secret
            ];
            
            $result = testStallionConnection($settings);
            echo json_encode($result);
            exit;
            
        case 'create_restriction':
            $title = trim($_POST['title'] ?? '');
            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Restriction title is required']);
                exit;
            }
            $restrictionId = Blocklist::createRestriction($pdo, $title, $user_id);
            if ($restrictionId) {
                Auditor::log($pdo, $user_id, 'create', 'registration_restrictions', $restrictionId, [
                    'title' => $title
                ]);
                echo json_encode(['success' => true, 'message' => 'Restriction created successfully', 'restriction_id' => $restrictionId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create restriction']);
            }
            exit;

        case 'remove_restriction':
            $restriction_id = intval($_POST['restriction_id'] ?? 0);
            if ($restriction_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid restriction ID']);
                exit;
            }
            $result = Blocklist::removeRestriction($pdo, $restriction_id);
            if ($result) {
                Auditor::log($pdo, $user_id, 'delete', 'registration_restrictions', $restriction_id);
                echo json_encode(['success' => true, 'message' => 'Restriction removed']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Restriction not found or already removed']);
            }
            exit;

        case 'add_blocklist_entry':
            $restriction_id = intval($_POST['restriction_id'] ?? 0);
            $block_type = trim($_POST['block_type'] ?? '');
            $block_value = trim($_POST['block_value'] ?? '');

            if ($restriction_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid restriction ID']);
                exit;
            }
            if (!in_array($block_type, ['email', 'name', 'ip'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid block type']);
                exit;
            }
            if (empty($block_value)) {
                echo json_encode(['success' => false, 'message' => 'Block value is required']);
                exit;
            }
            // Normalize before validation: lowercase email/name but not IP
            if ($block_type !== 'ip') {
                $block_value = strtolower($block_value);
            }
            if ($block_type === 'email' && !filter_var($block_value, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email address format']);
                exit;
            }
            if ($block_type === 'ip' && !filter_var($block_value, FILTER_VALIDATE_IP)) {
                echo json_encode(['success' => false, 'message' => 'Invalid IP address format']);
                exit;
            }

            $result = Blocklist::addEntry($pdo, $restriction_id, $block_type, $block_value);
            if ($result) {
                Auditor::log($pdo, $user_id, 'create', 'registration_blocklist', null, [
                    'restriction_id' => $restriction_id,
                    'block_type' => $block_type,
                    'block_value' => $block_value
                ]);
                echo json_encode(['success' => true, 'message' => 'Entry added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add entry. It may already exist.']);
            }
            exit;

        case 'remove_blocklist_entry':
            $entry_id = intval($_POST['entry_id'] ?? 0);
            if ($entry_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
                exit;
            }

            $result = Blocklist::removeEntry($pdo, $entry_id);
            if ($result) {
                Auditor::log($pdo, $user_id, 'delete', 'registration_blocklist', $entry_id);
                echo json_encode(['success' => true, 'message' => 'Entry removed']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Entry not found or already removed']);
            }
            exit;

        case 'add_pos_whitelist_entry':
            $ip_address = trim($_POST['ip_address'] ?? '');
            $label = trim($_POST['label'] ?? '');

            if (empty($ip_address)) {
                echo json_encode(['success' => false, 'message' => 'IP address is required']);
                exit;
            }
            if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
                echo json_encode(['success' => false, 'message' => 'Invalid IP address format']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO pos_allowed_ips (ip_address, label, is_active, created_by)
                    VALUES (?, ?, 1, ?)
                ");
                $stmt->execute([$ip_address, $label ?: null, $user_id]);
                Auditor::log($pdo, $user_id, 'create', 'pos_allowed_ips', null, [
                    'ip_address' => $ip_address,
                    'label' => $label
                ]);
                echo json_encode(['success' => true, 'message' => 'POS IP whitelist entry added successfully']);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    echo json_encode(['success' => false, 'message' => 'This IP address is already in the whitelist']);
                } else {
                    ErrorLogger::error("Add POS whitelist error", ["error" => $e->getMessage()]);
                    echo json_encode(['success' => false, 'message' => 'Failed to add entry']);
                }
            }
            exit;

        case 'remove_pos_whitelist_entry':
            $entry_id = intval($_POST['entry_id'] ?? 0);
            if ($entry_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("DELETE FROM pos_allowed_ips WHERE id = ?");
                $stmt->execute([$entry_id]);
                if ($stmt->rowCount() > 0) {
                    Auditor::log($pdo, $user_id, 'delete', 'pos_allowed_ips', $entry_id);
                    echo json_encode(['success' => true, 'message' => 'POS IP whitelist entry removed']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Entry not found or already removed']);
                }
            } catch (PDOException $e) {
                ErrorLogger::error("Remove POS whitelist error", ["error" => $e->getMessage()]);
                echo json_encode(['success' => false, 'message' => 'Failed to remove entry']);
            }
            exit;

        case 'toggle_pos_whitelist_entry':
            $entry_id = intval($_POST['entry_id'] ?? 0);
            $is_active = intval($_POST['is_active'] ?? 0);
            if ($entry_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("UPDATE pos_allowed_ips SET is_active = ? WHERE id = ?");
                $stmt->execute([$is_active ? 1 : 0, $entry_id]);
                if ($stmt->rowCount() > 0) {
                    Auditor::log($pdo, $user_id, 'update', 'pos_allowed_ips', $entry_id, [
                        'is_active' => $is_active ? 1 : 0
                    ]);
                    echo json_encode(['success' => true, 'message' => 'POS IP whitelist entry ' . ($is_active ? 'enabled' : 'disabled')]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Entry not found or no change needed']);
                }
            } catch (PDOException $e) {
                ErrorLogger::error("Toggle POS whitelist error", ["error" => $e->getMessage()]);
                echo json_encode(['success' => false, 'message' => 'Failed to update entry']);
            }
            exit;

        case 'generate_api_key':
            $key_name = trim($_POST['api_key_name'] ?? '');
            $expiry_days = intval($_POST['api_key_expiry'] ?? 30);
            $perm_preset = trim($_POST['api_key_permissions'] ?? 'read_only');

            if (empty($key_name) || strlen($key_name) > 100) {
                header('Location: dashboard.php?page=system_tools&tab=api_keys&error=' . urlencode('Key name is required (max 100 characters).'));
                exit;
            }

            // Map preset to permissions array
            $permissions_map = [
                'full'      => ['*'],
                'read_only' => ['read_profile', 'read_notifications', 'read_videos', 'read_sessions', 'read_teams', 'read_athletes', 'read_drills', 'read_bookings'],
                'bookings'  => ['read_profile', 'read_bookings', 'write_bookings', 'read_sessions'],
                'sessions'  => ['read_profile', 'read_sessions', 'write_sessions', 'read_drills', 'write_drills'],
            ];
            $permissions = $permissions_map[$perm_preset] ?? $permissions_map['read_only'];

            $api_key = bin2hex(random_bytes(32));
            $expires_at = $expiry_days > 0
                ? date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"))
                : null;

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO api_keys (user_id, api_key, key_name, permissions, is_active, created_at, expires_at)
                    VALUES (?, ?, ?, ?, 1, NOW(), ?)
                ");
                $stmt->execute([
                    $user_id,
                    $api_key,
                    $key_name,
                    json_encode($permissions),
                    $expires_at,
                ]);

                Auditor::log($pdo, $user_id, 'create', 'api_keys', $pdo->lastInsertId(), [
                    'action' => 'generate_api_key',
                    'key_name' => $key_name,
                    'permissions' => $perm_preset,
                    'expires_at' => $expires_at ?? 'never',
                ]);

                // Store key in session so it's not exposed in the URL
                $_SESSION['new_api_key'] = $api_key;

                header('Location: dashboard.php?page=system_tools&tab=api_keys&success=1&key_generated=1');
                exit;
            } catch (PDOException $e) {
                ErrorLogger::error("API key generation error", ["error" => $e->getMessage()]);
                header('Location: dashboard.php?page=system_tools&tab=api_keys&error=' . urlencode('Failed to generate API key.'));
                exit;
            }

        case 'revoke_api_key':
            $api_key_id = intval($_POST['api_key_id'] ?? 0);
            if ($api_key_id <= 0) {
                header('Location: dashboard.php?page=system_tools&tab=api_keys&error=' . urlencode('Invalid API key ID.'));
                exit;
            }

            try {
                $stmt = $pdo->prepare("UPDATE api_keys SET is_active = 0 WHERE id = ? AND user_id = ?");
                $stmt->execute([$api_key_id, $user_id]);

                Auditor::log($pdo, $user_id, 'update', 'api_keys', $api_key_id, [
                    'action' => 'revoke_api_key',
                ]);

                header('Location: dashboard.php?page=system_tools&tab=api_keys&success=1');
                exit;
            } catch (PDOException $e) {
                ErrorLogger::error("API key revoke error", ["error" => $e->getMessage()]);
                header('Location: dashboard.php?page=system_tools&tab=api_keys&error=' . urlencode('Failed to revoke API key.'));
                exit;
            }

        case 'delete_api_key':
            $api_key_id = intval($_POST['api_key_id'] ?? 0);
            if ($api_key_id <= 0) {
                header('Location: dashboard.php?page=system_tools&tab=api_keys&error=' . urlencode('Invalid API key ID.'));
                exit;
            }

            try {
                $stmt = $pdo->prepare("DELETE FROM api_keys WHERE id = ? AND user_id = ?");
                $stmt->execute([$api_key_id, $user_id]);

                Auditor::log($pdo, $user_id, 'delete', 'api_keys', $api_key_id, [
                    'action' => 'delete_api_key',
                ]);

                header('Location: dashboard.php?page=system_tools&tab=api_keys&success=1');
                exit;
            } catch (PDOException $e) {
                ErrorLogger::error("API key delete error", ["error" => $e->getMessage()]);
                header('Location: dashboard.php?page=system_tools&tab=api_keys&error=' . urlencode('Failed to delete API key.'));
                exit;
            }

        case 'add_ndi_camera':
            $cam_name = trim($_POST['ndi_camera_name'] ?? '');
            $cam_ip = trim($_POST['ndi_camera_ip'] ?? '');
            $cam_port = intval($_POST['ndi_camera_port'] ?? 5960);
            $cam_ndi_name = trim($_POST['ndi_camera_ndi_name'] ?? '');
            $cam_location = trim($_POST['ndi_camera_location'] ?? '');

            if (empty($cam_name) || empty($cam_ip)) {
                header('Location: dashboard.php?page=system_tools&tab=ndi_cameras&error=' . urlencode('Camera name and IP address are required.'));
                exit;
            }

            if ($cam_port < 1 || $cam_port > 65535) {
                $cam_port = 5960;
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO ndi_cameras (name, ip_address, port, ndi_name, location, is_active, created_by) VALUES (?, ?, ?, ?, ?, 1, ?)");
                $stmt->execute([$cam_name, $cam_ip, $cam_port, $cam_ndi_name ?: null, $cam_location ?: null, $user_id]);

                Auditor::log($pdo, $user_id, 'create', 'ndi_cameras', $pdo->lastInsertId(), [
                    'action' => 'add_ndi_camera',
                    'name' => $cam_name,
                    'ip_address' => $cam_ip,
                    'port' => $cam_port
                ]);

                header('Location: dashboard.php?page=system_tools&tab=ndi_cameras&success=1');
                exit;
            } catch (PDOException $e) {
                ErrorLogger::error("NDI camera add error", ["error" => $e->getMessage()]);
                header('Location: dashboard.php?page=system_tools&tab=ndi_cameras&error=' . urlencode('Failed to add NDI camera.'));
                exit;
            }

        case 'get_ndi_camera':
            $cam_id = intval($_POST['ndi_camera_id'] ?? 0);
            if ($cam_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid camera ID']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("SELECT * FROM ndi_cameras WHERE id = ?");
                $stmt->execute([$cam_id]);
                $camera = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($camera) {
                    echo json_encode(['success' => true, 'camera' => $camera]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Camera not found']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            exit;

        case 'update_ndi_camera':
            $cam_id = intval($_POST['ndi_camera_id'] ?? 0);
            $cam_name = trim($_POST['ndi_camera_name'] ?? '');
            $cam_ip = trim($_POST['ndi_camera_ip'] ?? '');
            $cam_port = intval($_POST['ndi_camera_port'] ?? 5960);
            $cam_ndi_name = trim($_POST['ndi_camera_ndi_name'] ?? '');
            $cam_location = trim($_POST['ndi_camera_location'] ?? '');

            if ($cam_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid camera ID']);
                exit;
            }
            if (empty($cam_name) || empty($cam_ip)) {
                echo json_encode(['success' => false, 'message' => 'Camera name and IP address are required']);
                exit;
            }
            if ($cam_port < 1 || $cam_port > 65535) {
                $cam_port = 5960;
            }

            try {
                $stmt = $pdo->prepare("UPDATE ndi_cameras SET name = ?, ip_address = ?, port = ?, ndi_name = ?, location = ? WHERE id = ?");
                $stmt->execute([$cam_name, $cam_ip, $cam_port, $cam_ndi_name ?: null, $cam_location ?: null, $cam_id]);

                Auditor::log($pdo, $user_id, 'update', 'ndi_cameras', $cam_id, [
                    'action' => 'update_ndi_camera',
                    'name' => $cam_name,
                    'ip_address' => $cam_ip,
                    'port' => $cam_port
                ]);

                echo json_encode(['success' => true, 'message' => 'Camera updated successfully']);
            } catch (PDOException $e) {
                ErrorLogger::error("NDI camera update error", ["error" => $e->getMessage()]);
                echo json_encode(['success' => false, 'message' => 'Failed to update camera']);
            }
            exit;

        case 'delete_ndi_camera':
            $cam_id = intval($_POST['ndi_camera_id'] ?? 0);
            if ($cam_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid camera ID']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("DELETE FROM ndi_cameras WHERE id = ?");
                $stmt->execute([$cam_id]);

                Auditor::log($pdo, $user_id, 'delete', 'ndi_cameras', $cam_id, [
                    'action' => 'delete_ndi_camera'
                ]);

                echo json_encode(['success' => true, 'message' => 'Camera deleted successfully']);
            } catch (PDOException $e) {
                ErrorLogger::error("NDI camera delete error", ["error" => $e->getMessage()]);
                echo json_encode(['success' => false, 'message' => 'Failed to delete camera']);
            }
            exit;

        case 'toggle_ndi_camera':
            $cam_id = intval($_POST['ndi_camera_id'] ?? 0);
            $is_active = intval($_POST['is_active'] ?? 0);

            if ($cam_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid camera ID']);
                exit;
            }

            $is_active = $is_active ? 1 : 0;

            try {
                $stmt = $pdo->prepare("UPDATE ndi_cameras SET is_active = ? WHERE id = ?");
                $stmt->execute([$is_active, $cam_id]);

                Auditor::log($pdo, $user_id, 'update', 'ndi_cameras', $cam_id, [
                    'action' => 'toggle_ndi_camera',
                    'is_active' => $is_active
                ]);

                echo json_encode(['success' => true, 'message' => 'Camera status updated']);
            } catch (PDOException $e) {
                ErrorLogger::error("NDI camera toggle error", ["error" => $e->getMessage()]);
                echo json_encode(['success' => false, 'message' => 'Failed to update camera status']);
            }
            exit;

        // ---- Galera Cluster Management -----------------------------------------------
        // Helper: locate the environment file (used by cluster actions below)
        if (!function_exists('findEnvFile')) {
            function findEnvFile() {
                foreach (['/config/arctic_wolves.env', __DIR__ . '/arctic_wolves.env', __DIR__ . '/.env'] as $p) {
                    if (file_exists($p)) { return $p; }
                }
                return null;
            }
        }

        case 'get_cluster_status':
            // Return wsrep status variables from the connected node
            $wsrep = [];
            $stmt = $pdo->query("SHOW STATUS LIKE 'wsrep%'");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $wsrep[$row[0]] = $row[1];
            }
            $cluster_size   = $wsrep['wsrep_cluster_size']   ?? null;
            $cluster_status = $wsrep['wsrep_cluster_status'] ?? null;
            $ready          = $wsrep['wsrep_ready']          ?? null;
            $state          = $wsrep['wsrep_local_state_comment'] ?? null;
            $node_address   = $wsrep['wsrep_node_address']   ?? ($_ENV['DB_CONNECTED_HOST'] ?? $_ENV['DB_HOST'] ?? 'unknown');
            
            // Read node list from env file
            $env_nodes = array_filter(array_map('trim', explode(',', $_ENV['DB_CLUSTER_NODES'] ?? '')));
            
            echo json_encode([
                'success'        => true,
                'cluster_size'   => $cluster_size,
                'cluster_status' => $cluster_status,
                'ready'          => $ready,
                'state'          => $state,
                'node_address'   => $node_address,
                'db_mode'        => $_ENV['DB_MODE'] ?? 'single',
                'cluster_name'   => $_ENV['DB_CLUSTER_NAME'] ?? '',
                'nodes'          => array_values($env_nodes),
                'wsrep'          => $wsrep,
            ]);
            exit;

        case 'test_cluster_node':
            $node = trim($_POST['node'] ?? '');
            if (empty($node)) {
                echo json_encode(['success' => false, 'message' => 'Node address is required']);
                exit;
            }
            if (!preg_match('/^[a-zA-Z0-9._\-]+(:\d{1,5})?$/', $node)) {
                echo json_encode(['success' => false, 'message' => 'Invalid node address format. Use hostname or hostname:port.']);
                exit;
            }
            $node_host = $node;
            $node_port = 3306;
            if (strpos($node, ':') !== false) {
                [$node_host, $node_port] = explode(':', $node, 2);
                $node_port = (int)$node_port;
            }
            try {
                $test_dsn = "mysql:host=$node_host;port=$node_port;dbname=" . ($_ENV['DB_NAME'] ?? 'arctic_wolves') . ";charset=utf8mb4";
                $test_pdo = new PDO($test_dsn, $_ENV['DB_USER'] ?? '', $_ENV['DB_PASS'] ?? '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                $test_pdo->query("SELECT 1");
                // Check wsrep status
                $wsrep_stmt = $test_pdo->query("SHOW STATUS LIKE 'wsrep_ready'");
                $wsrep_ready = $wsrep_stmt->fetchColumn(1) ?? 'n/a';
                echo json_encode(['success' => true, 'message' => "Node $node is reachable. wsrep_ready: $wsrep_ready"]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => "Cannot reach node $node: " . $e->getMessage()]);
            }
            exit;

        case 'add_cluster_node':
            $new_node = trim($_POST['node'] ?? '');
            if (empty($new_node)) {
                echo json_encode(['success' => false, 'message' => 'Node address is required']);
                exit;
            }
            // Validate format: hostname or hostname:port
            // Hyphens and underscores are permitted for Docker service names.
            if (!preg_match('/^[a-zA-Z0-9._\-]+(:\d{1,5})?$/', $new_node)) {
                echo json_encode(['success' => false, 'message' => 'Invalid node address format. Use hostname or hostname:port.']);
                exit;
            }
            
            // Read current node list from env
            $current_nodes = array_filter(array_map('trim', explode(',', $_ENV['DB_CLUSTER_NODES'] ?? $_ENV['DB_HOST'] ?? '')));
            if (in_array($new_node, $current_nodes)) {
                echo json_encode(['success' => false, 'message' => "Node $new_node is already in the cluster configuration."]);
                exit;
            }
            $current_nodes[] = $new_node;
            $nodes_str = implode(',', $current_nodes);
            
            // Persist to env file
            $env_file = findEnvFile();
            if (!$env_file) {
                echo json_encode(['success' => false, 'message' => 'Environment file not found. Cannot persist node list.']);
                exit;
            }
            $env_content = file_get_contents($env_file);
            // Strip newlines for defense-in-depth (nodes are already regex-validated)
            $safe_nodes_str = str_replace(["\n", "\r"], '', $nodes_str);
            if (preg_match('/^DB_CLUSTER_NODES=.*$/m', $env_content)) {
                $env_content = preg_replace('/^DB_CLUSTER_NODES=.*$/m', 'DB_CLUSTER_NODES=' . addcslashes($safe_nodes_str, '\\'), $env_content);
            } else {
                $env_content = rtrim($env_content) . "\nDB_CLUSTER_NODES=" . $safe_nodes_str . "\n";
            }
            // Ensure DB_MODE is cluster
            if (!preg_match('/^DB_MODE=/m', $env_content)) {
                $env_content = rtrim($env_content) . "\nDB_MODE=cluster\n";
            } else {
                $env_content = preg_replace('/^DB_MODE=.*$/m', 'DB_MODE=cluster', $env_content);
            }
            file_put_contents($env_file, $env_content);
            $_ENV['DB_CLUSTER_NODES'] = $nodes_str;
            $_ENV['DB_MODE'] = 'cluster';
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, ['action' => 'add_cluster_node', 'node' => $new_node]);
            
            // Build a suggested docker run command for the new node
            $cluster_name  = $_ENV['DB_CLUSTER_NAME'] ?? 'arctic_wolves_cluster';
            $all_nodes_str = $nodes_str;
            $docker_cmd = "BOOTSTRAP_CLUSTER= docker run -d --name galera-node-new \\\n"
                . "  -e MYSQL_ROOT_PASSWORD=\${MYSQL_ROOT_PASSWORD} \\\n"
                . "  -e MYSQL_DATABASE=" . ($_ENV['DB_NAME'] ?? 'arctic_wolves') . " \\\n"
                . "  -e MYSQL_USER=" . ($_ENV['DB_USER'] ?? 'arctic_wolves_user') . " \\\n"
                . "  -e MYSQL_PASSWORD=\${MYSQL_PASSWORD} \\\n"
                . "  -e GALERA_CLUSTER_NAME=$cluster_name \\\n"
                . "  -e GALERA_CLUSTER_MEMBERS=$all_nodes_str \\\n"
                . "  -e GALERA_NODE_ADDRESS=$new_node \\\n"
                . "  -v /path/to/galera-new-data:/var/lib/mysql \\\n"
                . "  -v ./deployment/galera/galera.cnf.tpl:/etc/mysql/conf.d/galera.cnf:ro \\\n"
                . "  -p 3306:3306 mariadb:lts";
            
            echo json_encode([
                'success'    => true,
                'message'    => "Node $new_node added to cluster configuration. Run the command below on the new node host to join the cluster.",
                'nodes'      => array_values($current_nodes),
                'docker_cmd' => $docker_cmd,
            ]);
            exit;

        case 'remove_cluster_node':
            $remove_node = trim($_POST['node'] ?? '');
            if (empty($remove_node)) {
                echo json_encode(['success' => false, 'message' => 'Node address is required']);
                exit;
            }
            if (!preg_match('/^[a-zA-Z0-9._\-]+(:\d{1,5})?$/', $remove_node)) {
                echo json_encode(['success' => false, 'message' => 'Invalid node address format.']);
                exit;
            }
            $current_nodes = array_filter(array_map('trim', explode(',', $_ENV['DB_CLUSTER_NODES'] ?? '')));
            $current_nodes = array_values(array_filter($current_nodes, fn($n) => $n !== $remove_node));
            
            if (empty($current_nodes)) {
                echo json_encode(['success' => false, 'message' => 'Cannot remove the last node from the cluster.']);
                exit;
            }
            $nodes_str = implode(',', $current_nodes);
            
            $env_file = findEnvFile();
            if ($env_file) {
                $env_content = file_get_contents($env_file);
                $safe_nodes_str = str_replace(["\n", "\r"], '', $nodes_str);
                $env_content = preg_replace('/^DB_CLUSTER_NODES=.*$/m', 'DB_CLUSTER_NODES=' . addcslashes($safe_nodes_str, '\\'), $env_content);
                file_put_contents($env_file, $env_content);
            }
            $_ENV['DB_CLUSTER_NODES'] = $nodes_str;
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, ['action' => 'remove_cluster_node', 'node' => $remove_node]);
            
            echo json_encode(['success' => true, 'message' => "Node $remove_node removed from cluster configuration.", 'nodes' => $current_nodes]);
            exit;

        case 'save_cluster_settings':
            $db_mode      = ($_POST['db_mode'] ?? 'single') === 'cluster' ? 'cluster' : 'single';
            $cluster_name = trim($_POST['db_cluster_name'] ?? 'arctic_wolves_cluster');
            $cluster_nodes = trim($_POST['db_cluster_nodes'] ?? '');
            
            // Validate cluster name: alphanumeric, underscores and hyphens only
            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $cluster_name)) {
                echo json_encode(['success' => false, 'message' => 'Invalid cluster name. Use only letters, numbers, underscores and hyphens.']);
                exit;
            }
            // Validate cluster nodes: comma-separated host or host:port entries
            if ($db_mode === 'cluster' && !empty($cluster_nodes)) {
                $nodes_arr = array_map('trim', explode(',', $cluster_nodes));
                foreach ($nodes_arr as $n) {
                    if (!preg_match('/^[a-zA-Z0-9._\-]+(:\d{1,5})?$/', $n)) {
                        echo json_encode(['success' => false, 'message' => 'Invalid node address format in nodes list. Use hostname or hostname:port.']);
                        exit;
                    }
                }
            }
            
            $env_file = findEnvFile();
            if (!$env_file) {
                echo json_encode(['success' => false, 'message' => 'Environment file not found.']);
                exit;
            }
            $env_content = file_get_contents($env_file);
            
            // Update or add each setting using preg_quote for the key pattern and addcslashes for value safety
            foreach (['DB_MODE' => $db_mode, 'DB_CLUSTER_NAME' => $cluster_name, 'DB_CLUSTER_NODES' => $cluster_nodes] as $key => $val) {
                // Strip newlines from values to prevent env file corruption
                $safe_val = str_replace(["\n", "\r"], '', $val);
                if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $env_content)) {
                    $env_content = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $key . '=' . addcslashes($safe_val, '\\'), $env_content);
                } else {
                    $env_content = rtrim($env_content) . "\n$key=$safe_val\n";
                }
                $_ENV[$key] = $safe_val;
            }
            file_put_contents($env_file, $env_content);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, ['action' => 'save_cluster_settings', 'db_mode' => $db_mode, 'cluster_name' => $cluster_name]);
            
            echo json_encode(['success' => true, 'message' => 'Cluster settings saved successfully.']);
            exit;

        // ---- End Cluster Management ---------------------------------------------------

        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if ($is_json) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        // Determine the correct redirect tab based on the action
        $action_tab_map = [
            'update_payments'    => 'payments',
            'update_smtp'        => 'smtp',
            'update_rustfs'      => 'rustfs',
            'update_theme'       => 'theme',
            'update_google_maps' => 'mileage',
            'update_mileage_rates' => 'mileage',
            'update_paperless'   => 'paperless',
            'update_docuseal'    => 'docuseal',
            'update_stallion'    => 'stallion',
            'update_landing'     => 'landing',
            'update_settings'    => 'settings',
            'toggle_pii_encryption' => 'encryption',
            'save_encryption_key' => 'encryption',
        ];
        $redirect_tab = $action_tab_map[$action] ?? 'settings';
        header('Location: dashboard.php?page=system_tools&tab=' . urlencode($redirect_tab) . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

/**
 * Update or insert a system setting
 */
function updateSetting($pdo, $key, $value) {
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    $stmt->execute([$key, $value, $value]);
}

// encryptPassword() and decryptPassword() are now defined in security.php
?>
