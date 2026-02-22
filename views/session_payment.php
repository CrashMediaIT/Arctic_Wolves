<?php
/**
 * Session Payment View
 * Allows athletes to pay for sessions they've been assigned to
 */

require_once __DIR__ . '/../security.php';

// Get system settings for currency
$settings = [];
try {
    $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Failed to load settings: " . $e->getMessage());
}
$currency = $settings['currency'] ?? 'CAD';

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

// Verify booking belongs to current user and is pending
$booking = null;
$session = null;

if ($booking_id > 0) {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               s.title as session_title, s.session_date, s.start_time, s.duration_minutes,
               s.description as session_description, s.price as session_price,
               COALESCE(l.name, s.arena) as location_name,
               COALESCE(l.city, s.city) as location_city,
               coach.first_name as coach_first_name, coach.last_name as coach_last_name
        FROM bookings b
        JOIN sessions s ON b.session_id = s.id
        LEFT JOIN locations l ON s.location_id = l.id
        LEFT JOIN users coach ON s.coach_id = coach.id
        WHERE b.id = ? AND b.user_id = ? AND b.status = 'pending' AND b.payment_status = 'pending'
    ");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    $booking = decryptUserRow($booking);
}

// Handle payment via Stripe
$stripe_error = null;
$payment_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now']) && $booking) {
    checkCsrfToken();
    
    try {
        // Load Stripe
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        } elseif (file_exists(__DIR__ . '/../stripe-php/init.php')) {
            require_once __DIR__ . '/../stripe-php/init.php';
        } else {
            throw new Exception("Payment system not configured.");
        }
        
        // Get Stripe settings
        $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $stripe_secret = $settings['stripe_secret_key'] ?? '';
        if (function_exists('decryptCredential') && !empty($stripe_secret)) { $stripe_secret = decryptCredential($stripe_secret); }
        
        if (empty($stripe_secret)) {
            throw new Exception("Payment system not configured.");
        }
        
        \Stripe\Stripe::setApiKey($stripe_secret);
        
        $amount = floatval($booking['amount_due']);
        
        // Validate amount is positive
        if ($amount <= 0) {
            throw new Exception("Invalid payment amount.");
        }
        
        $amount_cents = intval($amount * 100); // Convert to cents
        $currency = $settings['currency'] ?? 'CAD';
        
        // Get base URL from settings or construct safely
        $base_url = $settings['base_url'] ?? '';
        if (empty($base_url)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
        }
        $base_url = rtrim($base_url, '/');
        
        // Create Stripe Checkout Session
        $checkout_session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($currency),
                    'product_data' => [
                        'name' => $booking['session_title'],
                        'description' => 'Session on ' . date('M j, Y', strtotime($booking['session_date'])),
                    ],
                    'unit_amount' => $amount_cents,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $base_url . '/dashboard.php?page=sessions_upcoming&payment=success&booking_id=' . $booking_id,
            'cancel_url' => $base_url . '/dashboard.php?page=session_payment&booking_id=' . $booking_id . '&payment=cancelled',
            'metadata' => [
                'booking_id' => $booking_id,
                'user_id' => $user_id,
                'session_id' => $booking['session_id']
            ]
        ]);
        
        // Redirect to Stripe Checkout
        header('Location: ' . $checkout_session->url);
        exit();
        
    } catch (Exception $e) {
        $stripe_error = $e->getMessage();
        error_log("Session payment error: " . $e->getMessage());
    }
}

// Check for success/cancelled status
$payment_cancelled = isset($_GET['payment']) && $_GET['payment'] === 'cancelled';
?>

<style>
.payment-container {
    max-width: 600px;
    margin: 0 auto;
}

.payment-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

.payment-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
    padding: 24px;
    color: #fff;
}

.payment-header h2 {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.payment-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.payment-body {
    padding: 24px;
}

.session-details {
    margin-bottom: 24px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    color: var(--text-dim);
    font-size: 14px;
}

.detail-value {
    color: #fff;
    font-weight: 600;
    font-size: 14px;
    text-align: right;
}

.detail-value i {
    color: var(--primary);
    margin-right: 6px;
}

.amount-row {
    background: rgba(107, 70, 193, 0.1);
    border-radius: 8px;
    padding: 16px;
    margin: 20px 0;
}

.amount-label {
    font-size: 14px;
    color: var(--text-dim);
    margin-bottom: 4px;
}

.amount-value {
    font-size: 32px;
    font-weight: 900;
    color: var(--primary);
}

.pay-button {
    width: 100%;
    padding: 16px;
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.2s;
}

.pay-button:hover {
    background: var(--primary-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.4);
}

.error-message {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.cancelled-message {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #f59e0b;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.not-found {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-dim);
}

.not-found i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.not-found h3 {
    font-size: 20px;
    color: #fff;
    margin-bottom: 12px;
}

.stripe-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 16px;
    color: var(--text-dim);
    font-size: 13px;
}

.stripe-badge img {
    height: 20px;
}
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-credit-card"></i> Complete Payment</h1>
</div>

<div class="payment-container">
    <?php if (!$booking): ?>
        <div class="not-found">
            <i class="fas fa-search"></i>
            <h3>Session Not Found</h3>
            <p>This session payment is not available. It may have already been paid or cancelled.</p>
            <a href="?page=sessions_upcoming" class="btn btn-primary" style="margin-top: 20px;">
                <i class="fas fa-calendar"></i> View My Sessions
            </a>
        </div>
    <?php else: ?>
        <?php if ($stripe_error): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <span>Payment error: <?= htmlspecialchars($stripe_error) ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($payment_cancelled): ?>
        <div class="cancelled-message">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Payment was cancelled. You can try again below.</span>
        </div>
        <?php endif; ?>
        
        <div class="payment-card">
            <div class="payment-header">
                <h2><?= htmlspecialchars($booking['session_title']) ?></h2>
                <p>Complete your payment to confirm your spot</p>
            </div>
            
            <div class="payment-body">
                <div class="session-details">
                    <div class="detail-row">
                        <span class="detail-label">Date & Time</span>
                        <span class="detail-value">
                            <i class="fas fa-calendar"></i>
                            <?= date('l, F j, Y', strtotime($booking['session_date'])) ?> 
                            at <?= date('g:i A', strtotime($booking['start_time'])) ?>
                        </span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Duration</span>
                        <span class="detail-value">
                            <i class="fas fa-clock"></i>
                            <?= $booking['duration_minutes'] ?> minutes
                        </span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Location</span>
                        <span class="detail-value">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= htmlspecialchars($booking['location_name'] ?: 'TBD') ?>
                            <?php if ($booking['location_city']): ?>
                                <br><small style="color: var(--text-dim);"><?= htmlspecialchars($booking['location_city']) ?></small>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <?php $coach_name = trim(($booking['coach_first_name'] ?? '') . ' ' . ($booking['coach_last_name'] ?? '')); ?>
                    <?php if ($coach_name): ?>
                    <div class="detail-row">
                        <span class="detail-label">Coach</span>
                        <span class="detail-value">
                            <i class="fas fa-user-tie"></i>
                            <?= htmlspecialchars($coach_name) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="amount-row">
                    <div class="amount-label">Amount Due</div>
                    <div class="amount-value">$<?= number_format($booking['amount_due'], 2) ?> <?= htmlspecialchars($currency) ?></div>
                </div>
                
                <form method="POST">
                    <?= csrfTokenInput() ?>
                    <button type="submit" name="pay_now" class="pay-button">
                        <i class="fas fa-lock"></i> Pay Securely Now
                    </button>
                </form>
                
                <div class="stripe-badge">
                    <i class="fas fa-lock"></i> Secure payment powered by Stripe
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="?page=sessions_upcoming" style="color: var(--text-dim); text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Sessions
            </a>
        </div>
    <?php endif; ?>
</div>
