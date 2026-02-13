<?php
/**
 * PWA Sessions - Mobile-native sessions list with tab switching
 * Purpose-built for mobile phones.
 */

// Upcoming sessions
$upcomingSessions = [];
try {
    if ($isAnyCoach) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.title, s.session_date, s.session_time, s.duration_minutes,
                   s.status, s.arena, s.session_type, s.price,
                   (SELECT COUNT(*) FROM bookings b WHERE b.session_id = s.id AND b.status = 'confirmed') as attendee_count,
                   s.max_participants
            FROM sessions s
            WHERE s.session_date >= CURDATE() AND s.status = 'scheduled'
            ORDER BY s.session_date ASC, s.session_time ASC
            LIMIT 30
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT s.id, s.title, s.session_date, s.session_time, s.duration_minutes,
                   s.status, s.arena, s.session_type, s.price, s.max_participants,
                   b.id as booking_id, b.status as booking_status
            FROM sessions s
            LEFT JOIN bookings b ON b.session_id = s.id AND b.user_id = ? AND b.status = 'confirmed'
            WHERE s.session_date >= CURDATE() AND s.status = 'scheduled'
            ORDER BY s.session_date ASC, s.session_time ASC
            LIMIT 30
        ");
        $stmt->execute([$user_id]);
    }
    $upcomingSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $upcomingSessions = []; }

// Session types for booking tab
$sessionTypes = [];
try {
    $stmt = $pdo->query("SELECT id, name, description, default_price, duration_minutes FROM session_types ORDER BY name ASC");
    $sessionTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sessionTypes = []; }
?>
<style>
.m-sessions { padding: 0; font-family: Inter, sans-serif; }
.m-tabs {
    display: flex; position: sticky; top: 0; z-index: 10;
    background: #0A0A0F; border-bottom: 1px solid #2D2D3F;
    padding: 0 16px;
}
.m-tab {
    flex: 1; text-align: center; padding: 14px 0; font-size: 14px; font-weight: 600;
    color: #6B6B7B; border: none; background: none; cursor: pointer;
    border-bottom: 2px solid transparent;
    min-height: 44px; font-family: Inter, sans-serif;
}
.m-tab.m-tab-active { color: #8B5CF6; border-bottom-color: #8B5CF6; }
.m-tab-panel { display: none; padding: 16px; }
.m-tab-panel.m-tab-visible { display: block; }
.m-sess-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    text-decoration: none;
}
.m-sess-date {
    min-width: 48px; text-align: center;
    background: rgba(107,70,193,0.15); border-radius: 10px;
    padding: 8px 6px; flex-shrink: 0;
}
.m-sess-date-month { font-size: 10px; color: #8B5CF6; text-transform: uppercase; font-weight: 600; display: block; }
.m-sess-date-day { font-size: 20px; color: #fff; font-weight: 700; display: block; line-height: 1.1; }
.m-sess-body { flex: 1; min-width: 0; }
.m-sess-title { font-size: 14px; color: #fff; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-sess-detail { font-size: 12px; color: #A8A8B8; margin-top: 3px; display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
.m-sess-detail i { font-size: 10px; }
.m-sess-actions { display: flex; flex-direction: column; gap: 6px; align-items: flex-end; flex-shrink: 0; }
.m-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap;
}
.m-badge-upcoming { background: rgba(16,185,129,0.15); color: #10B981; }
.m-badge-booked { background: rgba(107,70,193,0.2); color: #8B5CF6; }
.m-badge-count { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-badge-type { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-book-btn {
    display: inline-block; padding: 6px 14px; border-radius: 8px;
    font-size: 12px; font-weight: 600; text-decoration: none;
    min-height: 32px; min-width: 44px; text-align: center;
    line-height: 20px; border: none; cursor: pointer;
    font-family: Inter, sans-serif;
}
.m-book-btn-primary { background: #6B46C1; color: #fff; }
.m-book-btn-danger { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-type-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; margin-bottom: 10px;
}
.m-type-name { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 6px; }
.m-type-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 10px; }
.m-type-meta {
    display: flex; justify-content: space-between; align-items: center;
    padding-top: 10px; border-top: 1px solid #2D2D3F;
}
.m-type-price { font-size: 16px; font-weight: 700; color: #10B981; }
.m-type-dur { font-size: 12px; color: #6B6B7B; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-sessions">
    <!-- Tabs -->
    <div class="m-tabs">
        <button class="m-tab m-tab-active" onclick="mSwitchTab('upcoming', this)" type="button">Upcoming</button>
        <button class="m-tab" onclick="mSwitchTab('booking', this)" type="button">Booking</button>
    </div>

    <!-- Upcoming Tab -->
    <div class="m-tab-panel m-tab-visible" id="m-panel-upcoming">
        <?php if (empty($upcomingSessions)): ?>
            <div class="m-empty-state">
                <i class="fas fa-calendar-xmark"></i>
                <p>No upcoming sessions scheduled</p>
            </div>
        <?php else: ?>
            <?php foreach ($upcomingSessions as $sess):
                $sDate = strtotime($sess['session_date']);
                $sTime = $sess['session_time'] ? date('g:i A', strtotime($sess['session_time'])) : '';
                $isBooked = !empty($sess['booking_id']);
            ?>
            <a href="?page=session_detail&id=<?= (int)$sess['id'] ?>" class="m-sess-card">
                <div class="m-sess-date">
                    <span class="m-sess-date-month"><?= date('M', $sDate) ?></span>
                    <span class="m-sess-date-day"><?= date('j', $sDate) ?></span>
                </div>
                <div class="m-sess-body">
                    <div class="m-sess-title"><?= htmlspecialchars($sess['title']) ?></div>
                    <div class="m-sess-detail">
                        <?php if ($sTime): ?><span><i class="fas fa-clock"></i> <?= $sTime ?></span><?php endif; ?>
                        <?php if (!empty($sess['duration_minutes'])): ?><span>· <?= (int)$sess['duration_minutes'] ?>min</span><?php endif; ?>
                        <?php if (!empty($sess['session_type'])): ?><span>· <?= htmlspecialchars($sess['session_type']) ?></span><?php endif; ?>
                    </div>
                    <?php if (!empty($sess['arena'])): ?>
                    <div style="font-size:11px;color:#6B6B7B;margin-top:2px;"><i class="fas fa-location-dot"></i> <?= htmlspecialchars($sess['arena']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="m-sess-actions">
                    <?php if ($isAnyCoach): ?>
                        <span class="m-badge m-badge-count"><i class="fas fa-users"></i> <?= (int)($sess['attendee_count'] ?? 0) ?><?php if ($sess['max_participants']): ?>/<?= (int)$sess['max_participants'] ?><?php endif; ?></span>
                    <?php elseif ($isBooked): ?>
                        <span class="m-badge m-badge-booked"><i class="fas fa-check"></i> Booked</span>
                    <?php else: ?>
                        <span class="m-badge m-badge-upcoming">Open</span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Booking Tab -->
    <div class="m-tab-panel" id="m-panel-booking">
        <?php if (empty($sessionTypes)): ?>
            <div class="m-empty-state">
                <i class="fas fa-tag"></i>
                <p>No session types available</p>
            </div>
        <?php else: ?>
            <?php foreach ($sessionTypes as $type): ?>
            <div class="m-type-card">
                <h4 class="m-type-name"><?= htmlspecialchars($type['name']) ?></h4>
                <?php if (!empty($type['description'])): ?>
                <p class="m-type-desc"><?= htmlspecialchars($type['description']) ?></p>
                <?php endif; ?>
                <div class="m-type-meta">
                    <div>
                        <span class="m-type-price">$<?= number_format((float)$type['default_price'], 2) ?></span>
                        <span class="m-type-dur"> · <?= (int)$type['duration_minutes'] ?> min</span>
                    </div>
                    <a href="?page=sessions&type=<?= (int)$type['id'] ?>" class="m-book-btn m-book-btn-primary">View Sessions</a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function mSwitchTab(tabId, btn) {
    document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
    document.querySelectorAll('.m-tab').forEach(function(t) { t.classList.remove('m-tab-active'); });
    var panel = document.getElementById('m-panel-' + tabId);
    if (panel) panel.classList.add('m-tab-visible');
    if (btn) btn.classList.add('m-tab-active');
}
</script>
