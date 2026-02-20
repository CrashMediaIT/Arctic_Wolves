<?php
/**
 * Process Marketing Email Campaigns
 * Send marketing emails with camp/program details to opted-in users
 */
session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';
require_once __DIR__ . '/mailer.php';

setSecurityHeaders();

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit();
    }
    http_response_code(403);
    die('Access denied.');
}

checkCsrfToken();

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

try {
    switch ($action) {
        case 'send_campaign':
            $subject = trim($_POST['subject'] ?? '');
            $custom_message = trim($_POST['custom_message'] ?? '');
            $package_ids_raw = $_POST['package_ids'] ?? [];
            $include_child_pickup = isset($_POST['include_child_pickup']) ? 1 : 0;
            $recipient_filter = $_POST['recipient_filter'] ?? 'opted_in';
            
            if (empty($subject)) {
                throw new Exception('Email subject is required');
            }
            
            if (empty($package_ids_raw) || !is_array($package_ids_raw)) {
                throw new Exception('Please select at least one camp or program');
            }
            
            $package_ids = array_map('intval', $package_ids_raw);
            $package_ids = array_filter($package_ids, function($id) { return $id > 0; });
            
            if (empty($package_ids)) {
                throw new Exception('Invalid package selection');
            }
            
            // Validate recipient filter
            $valid_filters = ['all', 'opted_in', 'parents', 'athletes'];
            if (!in_array($recipient_filter, $valid_filters)) {
                $recipient_filter = 'opted_in';
            }
            
            // Fetch package details
            $placeholders = str_repeat('?,', count($package_ids) - 1) . '?';
            $pkgStmt = $pdo->prepare("
                SELECT p.*, 
                       ag.name as age_group_name,
                       sl.name as skill_level_name
                FROM packages p
                LEFT JOIN age_groups ag ON p.age_group_id = ag.id
                LEFT JOIN skill_levels sl ON p.skill_level_id = sl.id
                WHERE p.id IN ($placeholders)
            ");
            $pkgStmt->execute($package_ids);
            $selectedPackages = $pkgStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($selectedPackages)) {
                throw new Exception('No valid packages found');
            }
            
            // Get camp schedules and program dates for each package
            $packageDetails = [];
            foreach ($selectedPackages as $pkg) {
                $detail = $pkg;
                if ($pkg['package_type'] === 'camp') {
                    $schedStmt = $pdo->prepare("SELECT * FROM camp_daily_schedules WHERE package_id = ? ORDER BY schedule_date");
                    $schedStmt->execute([$pkg['id']]);
                    $detail['schedules'] = $schedStmt->fetchAll(PDO::FETCH_ASSOC);
                } elseif ($pkg['package_type'] === 'multi_week') {
                    $datesStmt = $pdo->prepare("SELECT * FROM multiweek_program_dates WHERE package_id = ? ORDER BY session_date");
                    $datesStmt->execute([$pkg['id']]);
                    $detail['program_dates'] = $datesStmt->fetchAll(PDO::FETCH_ASSOC);
                }
                $packageDetails[] = $detail;
            }
            
            // Build recipient list
            $recipientQuery = "SELECT u.id, u.first_name, u.last_name, u.email FROM users u";
            $conditions = ["u.is_active = 1"];
            
            if ($recipient_filter === 'opted_in') {
                $recipientQuery .= " LEFT JOIN user_preferences up ON u.id = up.user_id AND up.preference_key = 'marketing_emails'";
                $conditions[] = "(up.preference_value = '1')";
            } elseif ($recipient_filter === 'parents') {
                $conditions[] = "u.role = 'parent'";
            } elseif ($recipient_filter === 'athletes') {
                $conditions[] = "u.role = 'athlete'";
            }
            
            $recipientQuery .= " WHERE " . implode(' AND ', $conditions);
            $recipientStmt = $pdo->query($recipientQuery);
            $recipients = $recipientStmt->fetchAll(PDO::FETCH_ASSOC);
            $recipients = decryptUserRows($recipients);
            
            if (empty($recipients)) {
                throw new Exception('No recipients found matching the selected filter');
            }
            
            // Build email HTML body
            $emailBody = buildMarketingEmailBody($packageDetails, $custom_message, $include_child_pickup);
            
            // Create campaign record
            $campaignStmt = $pdo->prepare("
                INSERT INTO marketing_email_campaigns 
                (subject, body, package_ids, include_child_pickup, recipient_filter, status, created_by)
                VALUES (?, ?, ?, ?, ?, 'sending', ?)
            ");
            $campaignStmt->execute([
                $subject, $emailBody, json_encode($package_ids),
                $include_child_pickup, $recipient_filter, $user_id
            ]);
            $campaign_id = $pdo->lastInsertId();
            
            // Send emails
            $sent_count = 0;
            $failed_count = 0;
            $config = getEmailConfig();
            $mailer = new SmtpMailer();
            
            foreach ($recipients as $recipient) {
                $email = $recipient['email'] ?? '';
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failed_count++;
                    continue;
                }
                
                try {
                    $mailer->send($email, $subject, $emailBody, $config);
                    logEmailAttempt($email, $subject, 'marketing_campaign', 'sent', null, ['campaign_id' => $campaign_id]);
                    $sent_count++;
                } catch (Exception $mailError) {
                    logEmailAttempt($email, $subject, 'marketing_campaign', 'failed', $mailError->getMessage(), ['campaign_id' => $campaign_id]);
                    $failed_count++;
                }
            }
            
            // Update campaign record
            $updateStmt = $pdo->prepare("
                UPDATE marketing_email_campaigns 
                SET sent_count = ?, failed_count = ?, status = 'sent', sent_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$sent_count, $failed_count, $campaign_id]);
            
            Auditor::log($pdo, $user_id, 'CREATE', 'marketing_email_campaigns', $campaign_id, [
                'action' => 'Sent marketing campaign',
                'subject' => $subject,
                'sent' => $sent_count,
                'failed' => $failed_count
            ]);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => "Campaign sent! $sent_count emails delivered" . ($failed_count > 0 ? ", $failed_count failed" : ""),
                'sent_count' => $sent_count,
                'failed_count' => $failed_count
            ]);
            exit();
            
        case 'get_campaigns':
            $stmt = $pdo->query("
                SELECT mec.*, u.first_name, u.last_name
                FROM marketing_email_campaigns mec
                LEFT JOIN users u ON mec.created_by = u.id
                ORDER BY mec.created_at DESC
                LIMIT 50
            ");
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $campaigns = decryptUserRows($campaigns);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'campaigns' => $campaigns]);
            exit();
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    ErrorLogger::error("Marketing email error: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}

/**
 * Build the HTML email body for a marketing campaign
 */
function buildMarketingEmailBody($packages, $customMessage, $includeChildPickup) {
    $theme = getThemeSettings();
    $primary = htmlspecialchars($theme['primary_color'] ?? '#7000a4', ENT_QUOTES, 'UTF-8');
    $bg = htmlspecialchars($theme['background_color'] ?? '#0a0f16', ENT_QUOTES, 'UTF-8');
    $cardBg = htmlspecialchars($theme['card_background_color'] ?? '#0d1117', ENT_QUOTES, 'UTF-8');
    $textMuted = htmlspecialchars($theme['text_muted_color'] ?? '#64748b', ENT_QUOTES, 'UTF-8');
    $borderClr = htmlspecialchars($theme['border_color'] ?? '#1e293b', ENT_QUOTES, 'UTF-8');
    $logoUrl = $theme['logo_url'] ?? '';
    $year = date('Y');
    
    $logoHtml = '';
    if (!empty($logoUrl)) {
        $safeLogo = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
        $logoHtml = "<img src='$safeLogo' alt='Arctic Wolves' style='max-height: 60px; max-width: 200px;'>";
    }
    
    $baseUrl = rtrim(getenv('APP_URL') ?: 'https://arcticwolves.ca', '/');
    
    $html = "
    <div style='font-family: Arial, sans-serif; background: $bg; color: #fff; padding: 30px; border-radius: 8px; max-width: 650px; margin: 0 auto;'>
        <div style='text-align: center; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid $borderClr;'>
            $logoHtml
        </div>";
    
    if (!empty($customMessage)) {
        $safeMessage = nl2br(htmlspecialchars($customMessage, ENT_QUOTES, 'UTF-8'));
        $html .= "<div style='color: #ccc; margin-bottom: 24px; line-height: 1.6;'>$safeMessage</div>";
    }
    
    foreach ($packages as $pkg) {
        $safeName = htmlspecialchars($pkg['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeDesc = htmlspecialchars($pkg['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $price = number_format(floatval($pkg['price'] ?? 0), 2);
        $headerColor = ($pkg['package_type'] === 'camp') ? '#10b981' : '#f59e0b';
        $typeLabel = ($pkg['package_type'] === 'camp') ? '🏕️ Camp' : '📅 Weekly Program';
        
        $html .= "
        <div style='background: $cardBg; border: 1px solid $borderClr; border-radius: 10px; margin-bottom: 20px; overflow: hidden;'>
            <div style='background: $headerColor; padding: 16px 20px; color: #fff;'>
                <div style='font-size: 12px; text-transform: uppercase; font-weight: 700; opacity: 0.9;'>$typeLabel</div>
                <div style='font-size: 20px; font-weight: 800; margin-top: 4px;'>$safeName</div>
            </div>
            <div style='padding: 20px;'>";
        
        if (!empty($safeDesc)) {
            $html .= "<p style='color: #94a3b8; margin: 0 0 12px;'>$safeDesc</p>";
        }
        
        // Camp specific details
        if ($pkg['package_type'] === 'camp') {
            if (!empty($pkg['camp_start_date']) && !empty($pkg['camp_end_date'])) {
                $startDate = date('M j', strtotime($pkg['camp_start_date']));
                $endDate = date('M j, Y', strtotime($pkg['camp_end_date']));
                $html .= "<p style='color: #ccc; margin: 4px 0;'>📆 <strong>Dates:</strong> $startDate - $endDate</p>";
            }
            if (!empty($pkg['daily_start_time']) && !empty($pkg['daily_end_time'])) {
                $startTime = date('g:i A', strtotime($pkg['daily_start_time']));
                $endTime = date('g:i A', strtotime($pkg['daily_end_time']));
                $html .= "<p style='color: #ccc; margin: 4px 0;'>⏰ <strong>Hours:</strong> $startTime - $endTime</p>";
            }
            // Show daily schedule
            if (!empty($pkg['schedules'])) {
                $html .= "<div style='margin-top: 12px; padding: 12px; background: rgba(16,185,129,0.1); border-radius: 6px;'>";
                $html .= "<div style='font-weight: 700; color: #10b981; margin-bottom: 8px; font-size: 13px;'>Daily Schedule</div>";
                foreach ($pkg['schedules'] as $sched) {
                    $schedDate = date('D, M j', strtotime($sched['schedule_date']));
                    $schedTime = date('g:i A', strtotime($sched['start_time'])) . ' - ' . date('g:i A', strtotime($sched['end_time']));
                    $schedTitle = !empty($sched['title']) ? ' — ' . htmlspecialchars($sched['title'], ENT_QUOTES, 'UTF-8') : '';
                    $schedLoc = !empty($sched['location']) ? ' 📍 ' . htmlspecialchars($sched['location'], ENT_QUOTES, 'UTF-8') : '';
                    $html .= "<div style='color: #ccc; padding: 4px 0; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.05);'>$schedDate &bull; $schedTime$schedTitle$schedLoc</div>";
                }
                $html .= "</div>";
            }
        }
        
        // Multi-week program details
        if ($pkg['package_type'] === 'multi_week' && !empty($pkg['program_dates'])) {
            $html .= "<p style='color: #ccc; margin: 4px 0;'>📅 <strong>" . count($pkg['program_dates']) . " sessions</strong> over multiple weeks</p>";
            if (!empty($pkg['allow_individual_sessions'])) {
                $html .= "<p style='color: #10b981; margin: 4px 0;'>✅ Individual sessions available — register for single sessions!</p>";
            }
            $html .= "<div style='margin-top: 12px; padding: 12px; background: rgba(245,158,11,0.1); border-radius: 6px;'>";
            $html .= "<div style='font-weight: 700; color: #f59e0b; margin-bottom: 8px; font-size: 13px;'>Session Dates</div>";
            foreach ($pkg['program_dates'] as $pd) {
                $pdDate = date('D, M j', strtotime($pd['session_date']));
                $pdTime = date('g:i A', strtotime($pd['start_time'])) . ' - ' . date('g:i A', strtotime($pd['end_time']));
                $pdTitle = !empty($pd['title']) ? ' — ' . htmlspecialchars($pd['title'], ENT_QUOTES, 'UTF-8') : '';
                $pdLoc = !empty($pd['location']) ? ' 📍 ' . htmlspecialchars($pd['location'], ENT_QUOTES, 'UTF-8') : '';
                $html .= "<div style='color: #ccc; padding: 4px 0; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.05);'>$pdDate &bull; $pdTime$pdTitle$pdLoc</div>";
            }
            $html .= "</div>";
        }
        
        // Age group and skill level
        if (!empty($pkg['age_group_name'])) {
            $safeAge = htmlspecialchars($pkg['age_group_name'], ENT_QUOTES, 'UTF-8');
            $html .= "<p style='color: #ccc; margin: 8px 0 0;'>👥 <strong>Age Group:</strong> $safeAge</p>";
        }
        if (!empty($pkg['skill_level_name'])) {
            $safeSkill = htmlspecialchars($pkg['skill_level_name'], ENT_QUOTES, 'UTF-8');
            $html .= "<p style='color: #ccc; margin: 4px 0;'>⭐ <strong>Skill Level:</strong> $safeSkill</p>";
        }
        
        // Child pickup info
        if ($includeChildPickup && !empty($pkg['enable_child_checkin'])) {
            $html .= "<div style='margin-top: 12px; padding: 10px; background: rgba(139,92,246,0.15); border-radius: 6px; border-left: 3px solid #8B5CF6;'>";
            $html .= "<p style='color: #c4b5fd; margin: 0; font-size: 13px;'><strong>👶 Child Check-In/Check-Out:</strong> This program uses secure QR code check-in for child drop-off and pickup. Parents will receive a QR code to share with authorized pickup persons.</p>";
            $html .= "</div>";
        }
        
        // Price and CTA
        $html .= "
            <div style='margin-top: 16px; text-align: center;'>
                <div style='font-size: 28px; font-weight: 900; color: #fff;'>\$$price</div>
                <a href='{$baseUrl}/sessions_public.php?register=1&type=package&id={$pkg['id']}' 
                   style='display: inline-block; margin-top: 12px; padding: 12px 28px; background: $primary; color: #fff; 
                          text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 14px;'>
                    Register Now →
                </a>
            </div>";
        
        $html .= "</div></div>";
    }
    
    // Footer
    $html .= "
        <div style='margin-top: 24px; padding-top: 20px; border-top: 1px solid $borderClr; text-align: center; color: $textMuted; font-size: 11px;'>
            &copy; $year Arctic Wolves Performance. All rights reserved.<br>
            <a href='{$baseUrl}' style='color: $textMuted; text-decoration: none;'>arcticwolves.ca</a><br>
            <span style='font-size: 10px;'>You received this because you opted in to marketing emails. 
            <a href='{$baseUrl}/dashboard.php?page=profile' style='color: $textMuted;'>Manage preferences</a></span>
        </div>
    </div>";
    
    return $html;
}
?>
