<?php
/**
 * PWA Sessions - Mobile-native sessions list with tab switching
 * Purpose-built for mobile phones.
 */

// Filter params
$filterType = $_GET['filter_type'] ?? '';
$filterLocation = $_GET['filter_location'] ?? '';
$showHistory = !empty($_GET['history']);

// Build filter conditions
$filterWhere = '';
$filterParams = [];
if ($filterType !== '') {
    $filterWhere .= ' AND s.session_type = ?';
    $filterParams[] = $filterType;
}
if ($filterLocation !== '') {
    $filterWhere .= ' AND s.arena = ?';
    $filterParams[] = $filterLocation;
}
$dateStatusWhere = $showHistory
    ? "s.status IN ('scheduled','completed')"
    : "s.session_date >= CURDATE() AND s.status = 'scheduled'";

// Fetch filter options
$filterSessionTypes = [];
$filterLocations = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT session_type FROM sessions WHERE session_type IS NOT NULL AND session_type != '' ORDER BY session_type");
    $filterSessionTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->query("SELECT DISTINCT arena FROM sessions WHERE arena IS NOT NULL AND arena != '' ORDER BY arena");
    $filterLocations = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

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
            WHERE $dateStatusWhere $filterWhere
            ORDER BY s.session_date ASC, s.session_time ASC
            LIMIT 30
        ");
        $stmt->execute($filterParams);
    } else {
        $stmt = $pdo->prepare("
            SELECT s.id, s.title, s.session_date, s.session_time, s.duration_minutes,
                   s.status, s.arena, s.session_type, s.price, s.max_participants,
                   b.id as booking_id, b.status as booking_status
            FROM sessions s
            LEFT JOIN bookings b ON b.session_id = s.id AND b.user_id = ? AND b.status = 'confirmed'
            WHERE $dateStatusWhere $filterWhere
            ORDER BY s.session_date ASC, s.session_time ASC
            LIMIT 30
        ");
        $stmt->execute(array_merge([$user_id], $filterParams));
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
    text-decoration: none; cursor: pointer;
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
.m-filter-toggle {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px; background: #0A0A0F; border-bottom: 1px solid #2D2D3F;
    cursor: pointer; min-height: 44px;
}
.m-filter-toggle span { font-size: 13px; color: #A8A8B8; font-weight: 600; }
.m-filter-toggle i { color: #6B6B7B; font-size: 12px; transition: transform 0.2s; }
.m-filter-toggle.m-filter-open i { transform: rotate(180deg); }
.m-filter-bar {
    display: none; padding: 12px 16px; background: #0A0A0F;
    border-bottom: 1px solid #2D2D3F; gap: 10px; flex-wrap: wrap;
}
.m-filter-bar.m-filter-visible { display: flex; }
.m-filter-select {
    flex: 1; min-width: 120px; padding: 10px 12px; border-radius: 8px;
    background: #16161F; border: 1px solid #2D2D3F; color: #fff;
    font-size: 13px; font-family: Inter, sans-serif; min-height: 44px;
    appearance: none; -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B6B7B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center;
}
.m-filter-select option { background: #16161F; color: #fff; }
.m-filter-check {
    display: flex; align-items: center; gap: 8px; min-height: 44px;
    font-size: 13px; color: #A8A8B8; cursor: pointer; white-space: nowrap;
}
.m-filter-check input { width: 18px; height: 18px; accent-color: #6B46C1; cursor: pointer; }
.m-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: #6B46C1; color: #fff; border: none;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; cursor: pointer; text-decoration: none;
    box-shadow: 0 4px 12px rgba(107,70,193,0.4);
    transition: background 0.2s;
}
.m-fab:active { background: #8B5CF6; }
</style>

<div class="m-sessions">
    <!-- Tabs -->
    <div class="m-tabs">
        <button class="m-tab m-tab-active" onclick="mSwitchTab('upcoming', this)" type="button">Upcoming</button>
        <button class="m-tab" onclick="mSwitchTab('booking', this)" type="button">Booking</button>
    </div>

    <!-- Filter Bar -->
    <div class="m-filter-toggle" onclick="mToggleFilters(this)" id="m-filter-toggle">
        <span><i class="fas fa-sliders"></i> Filters<?php if ($filterType !== '' || $filterLocation !== '' || $showHistory): ?> (active)<?php endif; ?></span>
        <i class="fas fa-chevron-down"></i>
    </div>
    <form method="GET" id="m-filter-form" class="m-filter-bar">
        <input type="hidden" name="page" value="sessions">
        <select name="filter_type" class="m-filter-select" onchange="this.form.submit()">
            <option value="">All Types</option>
            <?php foreach ($filterSessionTypes as $ft): ?>
            <option value="<?= htmlspecialchars($ft) ?>"<?= $filterType === $ft ? ' selected' : '' ?>><?= htmlspecialchars($ft) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_location" class="m-filter-select" onchange="this.form.submit()">
            <option value="">All Locations</option>
            <?php foreach ($filterLocations as $fl): ?>
            <option value="<?= htmlspecialchars($fl) ?>"<?= $filterLocation === $fl ? ' selected' : '' ?>><?= htmlspecialchars($fl) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="m-filter-check">
            <input type="checkbox" name="history" value="1"<?= $showHistory ? ' checked' : '' ?> onchange="this.form.submit()">
            Past sessions
        </label>
    </form>

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
            <div class="m-sess-card" tabindex="0" role="link" onclick="location.href='?page=session_detail&id=<?= (int)$sess['id'] ?>'" onkeydown="if(event.key==='Enter')location.href='?page=session_detail&id=<?= (int)$sess['id'] ?>'">
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
                        <button type="button" class="m-book-btn m-book-btn-danger" onclick="event.stopPropagation();mCancelBooking(<?= (int)$sess['booking_id'] ?>)">Cancel</button>
                    <?php else: ?>
                        <span class="m-badge m-badge-upcoming">Open</span>
                        <button type="button" class="m-book-btn m-book-btn-primary" onclick="event.stopPropagation();mBookSession(<?= (int)$sess['id'] ?>)">Book</button>
                    <?php endif; ?>
                </div>
            </div>
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

    <?php if ($isAnyCoach): ?>
    <a href="?page=create_session" class="m-fab" title="Create Session"><i class="fas fa-plus"></i></a>
    <?php endif; ?>
</div>

<!-- Hidden form for booking -->
<form id="m-book-form" method="POST" action="process_booking.php" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="book_session">
    <input type="hidden" name="session_id" id="m-book-session-id" value="">
</form>

<script>
function mSwitchTab(tabId, btn) {
    document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
    document.querySelectorAll('.m-tab').forEach(function(t) { t.classList.remove('m-tab-active'); });
    var panel = document.getElementById('m-panel-' + tabId);
    if (panel) panel.classList.add('m-tab-visible');
    if (btn) btn.classList.add('m-tab-active');
}

function mToggleFilters(el) {
    var bar = document.getElementById('m-filter-form');
    bar.classList.toggle('m-filter-visible');
    el.classList.toggle('m-filter-open');
}

function mCancelBooking(bookingId) {
    if (!confirm('Cancel this booking?')) return;
    var form = new FormData();
    form.append('action', 'cancel_booking');
    form.append('booking_id', bookingId);
    form.append('csrf_token', document.querySelector('#m-book-form input[name="csrf_token"]').value);
    fetch('process_booking.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { location.reload(); }
            else { alert(data.message || 'Failed to cancel booking'); }
        })
        .catch(function() { alert('Network error. Please try again.'); });
}

function mBookSession(sessionId) {
    document.getElementById('m-book-session-id').value = sessionId;
    document.getElementById('m-book-form').submit();
}
</script>
