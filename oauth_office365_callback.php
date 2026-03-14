<?php
/**
 * oauth_office365_callback.php
 * OAuth 2.0 authorization-code callback for Microsoft Office 365.
 * Handles both SMTP (org-level) and Calendar (per-coach) token exchange.
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/auditor.php';

setSecurityHeaders();

// ── Auth guard ────────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';
$type     = $_SESSION['office365_oauth_type'] ?? 'smtp'; // 'smtp' | 'calendar'

$allowedCalendarRoles = ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'];
if ($type === 'smtp' && $userRole !== 'admin') {
    header('Location: dashboard.php?page=system_tools&tab=smtp&oauth_error=' . urlencode('Admin access required'));
    exit;
}
if ($type === 'calendar' && !in_array($userRole, $allowedCalendarRoles)) {
    header('Location: dashboard.php?page=home&oauth_error=' . urlencode('Access denied'));
    exit;
}

// ── Error from Microsoft ──────────────────────────────────────────────────────
if (!empty($_GET['error'])) {
    $errMsg = htmlspecialchars($_GET['error_description'] ?? $_GET['error']);
    $dest   = $type === 'smtp'
        ? 'dashboard.php?page=system_tools&tab=smtp&oauth_error='
        : 'dashboard.php?page=coach_calendar&oauth_error=';
    header('Location: ' . $dest . urlencode($errMsg));
    exit;
}

// ── Validate state (CSRF) ────────────────────────────────────────────────────
if (empty($_GET['code']) || empty($_GET['state'])) {
    header('Location: dashboard.php?page=home&oauth_error=' . urlencode('Missing OAuth parameters'));
    exit;
}

$storedState = $_SESSION['office365_oauth_state'] ?? '';
if (empty($storedState) || !hash_equals($storedState, $_GET['state'])) {
    header('Location: dashboard.php?page=home&oauth_error=' . urlencode('Invalid OAuth state – please try again'));
    exit;
}
unset($_SESSION['office365_oauth_state'], $_SESSION['office365_oauth_type']);

// ── Load Azure app config from system_settings ───────────────────────────────
function o365Setting($pdo, $key) {
    try {
        $s = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $s->execute([$key]);
        return $s->fetchColumn() ?: '';
    } catch (Exception $e) {
        return '';
    }
}

$clientId     = o365Setting($pdo, 'office365_client_id');
$clientSecret = decryptCredential(o365Setting($pdo, 'office365_client_secret'));
$tenantId     = o365Setting($pdo, 'office365_tenant_id');

if (empty($clientId) || empty($clientSecret) || empty($tenantId)) {
    header('Location: dashboard.php?page=system_tools&tab=smtp&oauth_error=' . urlencode('Office 365 app not configured. Please save Client ID, Client Secret, and Tenant ID first.'));
    exit;
}

// ── Build redirect URI (must match Azure app registration) ───────────────────
$scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$redirectUri = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/oauth_office365_callback.php';

// ── Exchange authorization code for tokens ───────────────────────────────────
$tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

// Scope must match the authorization request (SMTP or Calendar).
// Include openid so the response contains an id_token with the user's email.
$scope = $type === 'smtp'
    ? 'https://outlook.office365.com/SMTP.Send offline_access openid'
    : 'https://graph.microsoft.com/Calendars.ReadWrite offline_access openid';

$postData = http_build_query([
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'code'          => $_GET['code'],
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
    'scope'         => $scope,
]);

$ctx = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content'       => $postData,
        'ignore_errors' => true,
        'timeout'       => 15,
    ],
    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
]);

$response  = @file_get_contents($tokenUrl, false, $ctx);
$tokenData = $response ? json_decode($response, true) : [];

if (empty($tokenData['access_token'])) {
    $errMsg = $tokenData['error_description'] ?? ($tokenData['error'] ?? 'Failed to obtain access token from Microsoft');
    $dest   = $type === 'smtp'
        ? 'dashboard.php?page=system_tools&tab=smtp&oauth_error='
        : 'dashboard.php?page=coach_calendar&oauth_error=';
    header('Location: ' . $dest . urlencode($errMsg));
    exit;
}

$accessToken  = $tokenData['access_token'];
$refreshToken = $tokenData['refresh_token'] ?? '';
$expiresIn    = (int)($tokenData['expires_in'] ?? 3600);
$expiresAt    = time() + $expiresIn;

// Decode id_token to get the signed-in email
$connectedEmail = '';
if (!empty($tokenData['id_token'])) {
    $parts = explode('.', $tokenData['id_token']);
    if (count($parts) === 3) {
        $padded  = str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=');
        $payload = json_decode(base64_decode($padded), true);
        $connectedEmail = $payload['preferred_username'] ?? $payload['email'] ?? $payload['upn'] ?? '';
    }
}

// Fallback: if id_token didn't yield an email, call Microsoft Graph /me endpoint
if (empty($connectedEmail)) {
    $graphCtx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => "Authorization: Bearer {$accessToken}\r\n",
            'ignore_errors' => true,
            'timeout'       => 10,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $graphResponse = @file_get_contents('https://graph.microsoft.com/v1.0/me?$select=mail,userPrincipalName', false, $graphCtx);
    if ($graphResponse) {
        $graphData = json_decode($graphResponse, true);
        $connectedEmail = $graphData['mail'] ?? $graphData['userPrincipalName'] ?? '';
    }
}

// ── Persist tokens ────────────────────────────────────────────────────────────
$upsert = $pdo->prepare("
    INSERT INTO system_settings (setting_key, setting_value)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE setting_value = ?
");

if ($type === 'smtp') {
    // Org-level: stored in system_settings
    $encAccess  = encryptPassword($accessToken);
    $encRefresh = !empty($refreshToken) ? encryptPassword($refreshToken) : '';

    $upsert->execute(['office365_smtp_access_token', $encAccess,  $encAccess]);
    $upsert->execute(['office365_smtp_expires_at',   $expiresAt,  $expiresAt]);
    if (!empty($encRefresh)) {
        $upsert->execute(['office365_smtp_refresh_token', $encRefresh, $encRefresh]);
    }
    if (!empty($connectedEmail)) {
        $upsert->execute(['office365_smtp_connected_email', $connectedEmail, $connectedEmail]);
    }

    Auditor::log($pdo, $userId, 'create', 'system_settings', null, [
        'action' => 'office365_smtp_connected',
        'email'  => $connectedEmail,
    ]);

    header('Location: dashboard.php?page=system_tools&tab=smtp&oauth_success=1');
    exit;
}

// Calendar: per-user, stored in user_oauth_tokens
// Ensure table exists (graceful for fresh installs before migration runs)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user_oauth_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `provider` VARCHAR(50) NOT NULL,
            `access_token` TEXT NOT NULL,
            `refresh_token` TEXT DEFAULT NULL,
            `expires_at` DATETIME DEFAULT NULL,
            `connected_email` VARCHAR(255) DEFAULT NULL,
            `scope` VARCHAR(500) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_user_provider` (`user_id`, `provider`),
            INDEX `idx_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) { /* table already exists */ }

$encAccess  = encryptPassword($accessToken);
$encRefresh = !empty($refreshToken) ? encryptPassword($refreshToken) : '';

$stmt = $pdo->prepare("
    INSERT INTO user_oauth_tokens
        (user_id, provider, access_token, refresh_token, expires_at, connected_email, scope)
    VALUES (?, 'office365_calendar', ?, ?, ?, ?, 'Calendars.ReadWrite')
    ON DUPLICATE KEY UPDATE
        access_token    = VALUES(access_token),
        refresh_token   = VALUES(refresh_token),
        expires_at      = VALUES(expires_at),
        connected_email = VALUES(connected_email),
        updated_at      = NOW()
");
$stmt->execute([
    $userId,
    $encAccess,
    $encRefresh,
    date('Y-m-d H:i:s', $expiresAt),
    $connectedEmail,
]);

Auditor::log($pdo, $userId, 'create', 'user_oauth_tokens', null, [
    'action' => 'office365_calendar_connected',
    'email'  => $connectedEmail,
]);

header('Location: dashboard.php?page=coach_calendar&oauth_success=1');
exit;
