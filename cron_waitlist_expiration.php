<?php
/**
 * Waitlist Expiration Cron Job
 * Expires waitlist offers that have not been accepted within 48 hours
 * and automatically offers the spot to the next person in line.
 * 
 * Example crontab entry (every 15 minutes):
 * 0,15,30,45 * * * * /usr/bin/php /path/to/cron_waitlist_expiration.php
 * (Run every 15 minutes to check for expired offers)
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/error_logger.php';

// Only run via CLI or with secret key
if (php_sapi_name() !== 'cli') {
    $secret_key = $_GET['key'] ?? '';
    $expected_key = getenv('CRON_SECRET_KEY');
    
    if (empty($expected_key) || !hash_equals($expected_key, $secret_key)) {
        http_response_code(403);
        die('Unauthorized');
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Waitlist Expiration Cron: Starting...\n";

try {
    // Find expired offers (token_expires_at has passed)
    $expiredStmt = $pdo->query("
        SELECT w.id, w.session_id, w.package_id, w.template_id, w.user_id, w.position,
               u.first_name, u.last_name,
               s.title as session_title,
               p.name as package_name,
               tst.name as template_name
        FROM waitlists w
        JOIN users u ON w.user_id = u.id
        LEFT JOIN sessions s ON w.session_id = s.id
        LEFT JOIN packages p ON w.package_id = p.id
        LEFT JOIN training_session_templates tst ON w.template_id = tst.id
        WHERE w.status = 'offered' AND w.token_expires_at IS NOT NULL AND w.token_expires_at <= NOW()
    ");
    $expiredOffers = $expiredStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $expiredCount = 0;
    $offeredCount = 0;
    
    foreach ($expiredOffers as $expired) {
        $productName = $expired['session_title'] ?? $expired['package_name'] ?? $expired['template_name'] ?? 'Unknown';
        $firstName = !empty($expired['first_name']) && class_exists('FieldEncryption') ? FieldEncryption::decrypt($expired['first_name']) : ($expired['first_name'] ?? '');
        $lastName = !empty($expired['last_name']) && class_exists('FieldEncryption') ? FieldEncryption::decrypt($expired['last_name']) : ($expired['last_name'] ?? '');
        
        echo "  Expiring offer #{$expired['id']} for " . trim($firstName . ' ' . $lastName) . " - $productName\n";
        
        // Mark as expired
        $pdo->prepare("UPDATE waitlists SET status = 'expired', waitlist_token = NULL, token_expires_at = NULL WHERE id = ?")
            ->execute([$expired['id']]);
        $expiredCount++;
        
        // Find and offer to next person in line for the same product
        $nextWhere = '';
        $nextParams = [];
        if (!empty($expired['session_id'])) {
            $nextWhere = 'w.session_id = ?';
            $nextParams = [$expired['session_id']];
        } elseif (!empty($expired['package_id'])) {
            $nextWhere = 'w.package_id = ?';
            $nextParams = [$expired['package_id']];
        } elseif (!empty($expired['template_id'])) {
            $nextWhere = 'w.template_id = ?';
            $nextParams = [$expired['template_id']];
        }
        
        if ($nextWhere) {
            $nextStmt = $pdo->prepare("
                SELECT w.id, w.user_id, u.first_name, u.last_name, u.email
                FROM waitlists w
                JOIN users u ON w.user_id = u.id
                WHERE $nextWhere AND w.status = 'waiting'
                ORDER BY w.position ASC
                LIMIT 1
            ");
            $nextStmt->execute($nextParams);
            $nextPerson = $nextStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($nextPerson) {
                // Generate new token and offer to next person
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+48 hours'));
                
                $pdo->prepare("UPDATE waitlists SET status = 'offered', notified_at = NOW(), waitlist_token = ?, token_expires_at = ? WHERE id = ?")
                    ->execute([$token, $expiresAt, $nextPerson['id']]);
                
                $nextFirstName = !empty($nextPerson['first_name']) && class_exists('FieldEncryption') ? FieldEncryption::decrypt($nextPerson['first_name']) : ($nextPerson['first_name'] ?? '');
                $nextLastName = !empty($nextPerson['last_name']) && class_exists('FieldEncryption') ? FieldEncryption::decrypt($nextPerson['last_name']) : ($nextPerson['last_name'] ?? '');
                $nextEmail = !empty($nextPerson['email']) && class_exists('FieldEncryption') ? FieldEncryption::decrypt($nextPerson['email']) : ($nextPerson['email'] ?? '');
                
                // Send enrollment email
                if (!empty($nextEmail) && function_exists('sendEmail')) {
                    // Build base URL from system settings or environment
                    $baseUrl = '';
                    try {
                        $urlStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'site_url'");
                        $urlStmt->execute();
                        $baseUrl = $urlStmt->fetchColumn() ?: '';
                    } catch (PDOException $ue) {}
                    if (empty($baseUrl)) {
                        $baseUrl = getenv('APP_URL') ?: (isset($_SERVER['HTTP_HOST']) ? "https://" . $_SERVER['HTTP_HOST'] : '');
                    }
                    $purchaseLink = rtrim($baseUrl, '/') . "/pwa.php?page=sessions&waitlist_token=" . urlencode($token);
                    sendEmail($nextEmail, 'notification', [
                        'title' => 'A Spot Is Available!',
                        'name' => trim($nextFirstName . ' ' . $nextLastName),
                        'message' => "Great news! A spot has opened up for: $productName. You have 48 hours to complete your enrollment. Click the button below to secure your spot.",
                        'link' => $purchaseLink,
                    ]);
                }
                
                // Create in-app notification
                try {
                    $pdo->prepare("
                        INSERT INTO notifications (user_id, type, title, message, created_at) 
                        VALUES (?, 'session', 'Spot Available!', ?, NOW())
                    ")->execute([
                        $nextPerson['user_id'],
                        "A spot opened up for: $productName. You have 48 hours to enroll!"
                    ]);
                } catch (PDOException $ne) { /* notifications table may not exist */ }
                
                echo "  -> Offered to next: " . trim($nextFirstName . ' ' . $nextLastName) . "\n";
                $offeredCount++;
            }
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Done. Expired: $expiredCount, Offered to next: $offeredCount\n";
    
} catch (Exception $e) {
    $errorMsg = "Waitlist expiration cron error: " . $e->getMessage();
    echo "ERROR: $errorMsg\n";
    if (class_exists('ErrorLogger')) {
        ErrorLogger::error($errorMsg, ['context' => 'cron_waitlist_expiration']);
    }
}
