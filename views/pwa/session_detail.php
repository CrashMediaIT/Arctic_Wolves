<?php
/**
 * PWA Session Detail - Mobile-native single session view
 * Purpose-built for mobile phones.
 */

$sessionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$session = null;
$bookingStatus = null;
$bookingId = null;

if ($sessionId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, u.first_name as coach_first, u.last_name as coach_last
            FROM sessions s
            LEFT JOIN users u ON u.id = s.coach_id
            WHERE s.id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($session) {
            $session['coach_first'] = FieldEncryption::decrypt($session['coach_first'] ?? '');
            $session['coach_last'] = FieldEncryption::decrypt($session['coach_last'] ?? '');
        }
    } catch (PDOException $e) { $session = null; }

    // Check if user has a booking
    if ($session && !$isAnyCoach) {
        try {
            $stmt = $pdo->prepare("SELECT id, status FROM bookings WHERE session_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$sessionId, $user_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($booking) {
                $bookingId = (int)$booking['id'];
                $bookingStatus = $booking['status'];
            }
        } catch (PDOException $e) { /* no booking */ }
    }
}
?>
<style>
.m-session-detail { padding: 16px; font-family: Inter, sans-serif; }
.m-back-link {
    display: inline-flex; align-items: center; gap: 6px;
    color: #8B5CF6; font-size: 13px; font-weight: 500;
    text-decoration: none; margin-bottom: 16px;
    min-height: 44px; padding: 8px 0;
}
.m-sd-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 20px; margin-bottom: 16px;
}
.m-sd-title { font-size: 18px; font-weight: 700; color: #fff; margin: 0 0 4px; }
.m-sd-status {
    display: inline-block; font-size: 11px; padding: 3px 10px; border-radius: 6px;
    font-weight: 600; margin-bottom: 16px;
}
.m-sd-status-scheduled { background: rgba(16,185,129,0.15); color: #10B981; }
.m-sd-status-completed { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-sd-status-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-sd-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-sd-field {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 0; border-bottom: 1px solid #2D2D3F;
    min-height: 44px;
}
.m-sd-field:last-child { border-bottom: none; }
.m-sd-field-icon { width: 20px; text-align: center; color: #6B6B7B; font-size: 14px; flex-shrink: 0; }
.m-sd-field-body { flex: 1; }
.m-sd-field-label { font-size: 11px; color: #6B6B7B; }
.m-sd-field-value { font-size: 14px; color: #fff; font-weight: 500; margin-top: 1px; }
.m-sd-actions { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; }
.m-sd-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 14px; border-radius: 12px;
    font-size: 14px; font-weight: 600; text-decoration: none;
    min-height: 44px; border: none; cursor: pointer;
    font-family: Inter, sans-serif; text-align: center;
}
.m-sd-btn-book { background: #6B46C1; color: #fff; }
.m-sd-btn-cancel { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-sd-btn-booked { background: rgba(16,185,129,0.15); color: #10B981; }
.m-sd-booking-badge {
    text-align: center; padding: 12px; border-radius: 10px;
    font-size: 13px; font-weight: 600; margin-bottom: 8px;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-session-detail">
    <a href="?page=sessions" class="m-back-link">
        <i class="fas fa-chevron-left"></i> Back to Sessions
    </a>

    <?php if (!$session): ?>
        <div class="m-empty-state">
            <i class="fas fa-calendar-xmark"></i>
            <p>Session not found</p>
        </div>
    <?php else:
        $status = strtolower($session['status'] ?? 'scheduled');
        $statusClass = match($status) {
            'scheduled' => 'scheduled',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'default',
        };
        $sDate = $session['session_date'] ? date('l, F j, Y', strtotime($session['session_date'])) : 'TBD';
        $sTime = $session['session_time'] ? date('g:i A', strtotime($session['session_time'])) : 'TBD';
        $coachName = trim(($session['coach_first'] ?? '') . ' ' . ($session['coach_last'] ?? ''));
    ?>
        <div class="m-sd-card">
            <h2 class="m-sd-title"><?= htmlspecialchars($session['title'] ?? 'Untitled Session') ?></h2>
            <span class="m-sd-status m-sd-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>

            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-calendar"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Date</div>
                    <div class="m-sd-field-value"><?= htmlspecialchars($sDate) ?></div>
                </div>
            </div>
            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-clock"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Time</div>
                    <div class="m-sd-field-value"><?= htmlspecialchars($sTime) ?></div>
                </div>
            </div>
            <?php if (!empty($session['duration_minutes'])): ?>
            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-hourglass-half"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Duration</div>
                    <div class="m-sd-field-value"><?= (int)$session['duration_minutes'] ?> minutes</div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($session['arena'])): ?>
            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-location-dot"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Arena</div>
                    <div class="m-sd-field-value"><?= htmlspecialchars($session['arena']) ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($coachName): ?>
            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-user-tie"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Coach</div>
                    <div class="m-sd-field-value"><?= htmlspecialchars($coachName) ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($session['session_type'])): ?>
            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-tag"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Type</div>
                    <div class="m-sd-field-value"><?= htmlspecialchars($session['session_type']) ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (isset($session['price']) && $session['price'] > 0): ?>
            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-dollar-sign"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Price</div>
                    <div class="m-sd-field-value">$<?= number_format((float)$session['price'], 2) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Booking actions for athletes -->
        <?php if (!$isAnyCoach && $status === 'scheduled'): ?>
            <div class="m-sd-actions">
                <?php if ($bookingStatus === 'confirmed'): ?>
                    <div class="m-sd-booking-badge" style="background:rgba(16,185,129,0.15);color:#10B981;">
                        <i class="fas fa-check-circle"></i> You're booked for this session
                    </div>
                    <a href="?page=cancel_booking&booking_id=<?= $bookingId ?>" class="m-sd-btn m-sd-btn-cancel">
                        <i class="fas fa-times"></i> Cancel Booking
                    </a>
                <?php else: ?>
                    <a href="?page=book_session&session_id=<?= $sessionId ?>" class="m-sd-btn m-sd-btn-book">
                        <i class="fas fa-calendar-plus"></i> Book This Session
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
