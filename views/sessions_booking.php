<?php
// Get available packages
$packages_query = "
    SELECT p.*
    FROM packages p
    WHERE p.is_active = 1
    ORDER BY p.price
";
$packages = $pdo->query($packages_query)->fetchAll();

// Get coaches for individual sessions
$coaches_query = "
    SELECT u.id, u.first_name, u.last_name
    FROM users u
    WHERE u.role IN ('coach', 'admin') AND u.is_active = 1
    ORDER BY u.last_name, u.first_name
";
$coaches = $pdo->query($coaches_query)->fetchAll();
$coaches = decryptUserRows($coaches);

// Get session types
$session_types = $pdo->query("SELECT * FROM session_types ORDER BY name")->fetchAll();

// Get available sessions for booking - combine regular sessions and training session templates
$available_sessions_query = "
    SELECT CONCAT('session_', s.id) as unique_id, s.id, s.title as session_type_name, s.description, 
           s.session_date, s.session_time,
           s.duration_minutes, COALESCE(s.price, st.default_price, 0) as session_price,
           s.max_participants, 'session' as source_type, NULL as date_id,
           CONCAT(c.first_name, ' ', c.last_name) as coach_name,
           l.name as location_name,
           COUNT(DISTINCT b.id) as registered_count
    FROM sessions s
    LEFT JOIN users c ON s.coach_id = c.id
    LEFT JOIN session_types st ON s.session_type_id = st.id
    LEFT JOIN locations l ON s.location_id = l.id
    LEFT JOIN bookings b ON b.session_id = s.id
    WHERE s.session_date >= CURDATE() 
      AND s.status = 'scheduled'
      AND (s.max_participants IS NULL OR s.max_participants > (SELECT COUNT(*) FROM bookings WHERE session_id = s.id))
    GROUP BY s.id
    
    UNION ALL
    
    SELECT CONCAT('template_', tst.id, '_', tsd.id) as unique_id, tst.id, tst.name as session_type_name, tst.description, 
           DATE(tsd.session_date) as session_date, TIME(tsd.session_date) as session_time,
           tst.duration_minutes, COALESCE(tst.price, 0) as session_price,
           COALESCE(tsd.max_participants, tst.max_participants) as max_participants,
           'template' as source_type, tsd.id as date_id,
           CONCAT(c.first_name, ' ', c.last_name) as coach_name,
           l.name as location_name,
           0 as registered_count
    FROM training_session_templates tst
    INNER JOIN training_session_dates tsd ON tsd.template_id = tst.id AND tsd.is_active = 1
    LEFT JOIN users c ON tst.coach_id = c.id
    LEFT JOIN locations l ON tst.location_id = l.id
    WHERE tst.is_active = 1
      AND tsd.session_date >= NOW()
    
    ORDER BY session_date
    LIMIT 20
";
$available_sessions = $pdo->query($available_sessions_query)->fetchAll();

// No demo data - show empty state when no real data exists
$is_demo_packages = false;
$is_demo_sessions = false;
?>

<!-- Session Booking View - Two Section Layout -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-calendar-plus"></i> Book a Session
    </h1>
    <p class="page-description">Browse individual sessions or purchase training packages</p>
</div>

<?php if ((isset($is_demo_packages) && $is_demo_packages) || (isset($is_demo_sessions) && $is_demo_sessions)): ?>
<div class="demo-data-notice">
    <i class="fas fa-info-circle"></i>
    <span>Showing demo data. Contact admin to set up real sessions and packages.</span>
</div>
<?php endif; ?>

<div class="booking-content">
    <!-- ============================================
         SECTION 1: INDIVIDUAL SESSIONS (Upper Section)
         ============================================ -->
    <div class="booking-section sessions-section">
        <div class="section-header-bar">
            <div class="section-title-group">
                <h2 class="section-title"><i class="fas fa-calendar-day"></i> Individual Sessions</h2>
                <p class="section-subtitle">Register for upcoming group sessions or book a private lesson</p>
            </div>
            <div class="view-toggle">
                <button class="view-btn active" data-view="list" title="List View">
                    <i class="fas fa-list"></i>
                </button>
                <button class="view-btn" data-view="calendar" title="Calendar View">
                    <i class="fas fa-calendar-alt"></i>
                </button>
            </div>
        </div>
        
        <!-- List View -->
        <div class="sessions-view active" id="list-view">
            <?php if (count($available_sessions) > 0): ?>
            <div class="sessions-list-grid">
                <?php foreach ($available_sessions as $session): 
                    $session_datetime = strtotime($session['session_date']);
                    $spots_left = ($session['max_participants'] ?? 10) - ($session['registered_count'] ?? 0);
                    $is_almost_full = $spots_left <= 3;
                ?>
                <div class="session-list-card" data-session-id="<?= $session['id'] ?>" data-date="<?= date('Y-m-d', $session_datetime) ?>">
                    <div class="session-date-column">
                        <div class="date-badge">
                            <span class="date-month"><?= date('M', $session_datetime) ?></span>
                            <span class="date-day"><?= date('j', $session_datetime) ?></span>
                            <span class="date-weekday"><?= date('D', $session_datetime) ?></span>
                        </div>
                        <span class="session-time"><?= date('g:i A', $session_datetime) ?></span>
                    </div>
                    <div class="session-details-column">
                        <h4 class="session-title"><?= htmlspecialchars($session['session_type_name'] ?? 'Training Session') ?></h4>
                        <div class="session-meta">
                            <span class="meta-item"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($session['coach_name'] ?? 'TBD') ?></span>
                            <?php if (!empty($session['location_name'])): ?>
                            <span class="meta-item"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($session['location_name']) ?></span>
                            <?php endif; ?>
                            <span class="meta-item"><i class="fas fa-clock"></i> <?= $session['duration_minutes'] ?? 60 ?> min</span>
                        </div>
                        <?php if (!empty($session['description'])): ?>
                        <p class="session-description"><?= htmlspecialchars(substr($session['description'], 0, 120)) ?><?= strlen($session['description'] ?? '') > 120 ? '...' : '' ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="session-action-column">
                        <div class="spots-indicator <?= $is_almost_full ? 'almost-full' : '' ?>">
                            <span class="spots-number"><?= $spots_left ?></span>
                            <span class="spots-text">spots left</span>
                        </div>
                        <div class="session-price-tag">$<?= number_format($session['session_price'] ?? 0, 0) ?></div>
                        <button class="btn-register" data-action="register-session" data-session-id="<?= $session['id'] ?>" data-price="<?= $session['session_price'] ?? 0 ?>">
                            <i class="fas fa-plus-circle"></i> Register
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state-card">
                <i class="fas fa-calendar-times"></i>
                <h4>No Upcoming Sessions</h4>
                <p>Check back soon for new training sessions or book a private lesson below.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Calendar View -->
        <div class="sessions-view" id="calendar-view">
            <div class="calendar-container">
                <div class="calendar-header">
                    <button class="calendar-nav-btn" id="prev-month"><i class="fas fa-chevron-left"></i></button>
                    <h3 class="calendar-month-title" id="calendar-title"><?= date('F Y') ?></h3>
                    <button class="calendar-nav-btn" id="next-month"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="calendar-weekdays">
                    <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                </div>
                <div class="calendar-grid" id="calendar-grid">
                    <!-- Calendar days will be populated by JavaScript -->
                </div>
            </div>
            <div class="calendar-sessions-panel" id="calendar-sessions-panel">
                <h4 class="panel-title"><i class="fas fa-calendar-check"></i> <span id="selected-date-title">Select a date</span></h4>
                <div class="panel-sessions-list" id="panel-sessions-list">
                    <p class="no-sessions-msg">Click on a date to see available sessions</p>
                </div>
            </div>
        </div>
        
        <!-- Private Session Booking Form -->
        <div class="private-session-form-container">
            <div class="form-section-divider">
                <span>OR BOOK A PRIVATE SESSION</span>
            </div>
            <div class="booking-form-card">
                <div class="form-card-header">
                    <i class="fas fa-user-plus"></i>
                    <div>
                        <h3>Book Private Session</h3>
                        <p>Schedule a one-on-one session with a coach</p>
                    </div>
                </div>
                
                <form class="booking-form" method="POST" action="process_booking.php" data-form="session-booking">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="book_private_session">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Session Type <span class="required">*</span></label>
                            <select name="session_type_id" class="form-input" required data-field="session-type">
                                <option value="">-- Select Type --</option>
                                <?php foreach ($session_types as $type): ?>
                                    <option value="<?= $type['id'] ?>" data-price="<?= $type['price'] ?>">
                                        <?= htmlspecialchars($type['name']) ?> - $<?= number_format($type['price'], 0) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Coach <span class="required">*</span></label>
                            <div id="coach-typeahead-container"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Date <span class="required">*</span></label>
                            <input type="date" name="session_date" class="form-input" required min="<?= date('Y-m-d') ?>" data-field="session-date">
                        </div>
                        <div class="form-group">
                            <label>Time <span class="required">*</span></label>
                            <select name="session_time" class="form-input" required data-field="session-time">
                                <option value="">-- Select Time --</option>
                                <?php 
                                $times = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00'];
                                foreach ($times as $time): 
                                ?>
                                    <option value="<?= $time ?>"><?= date('g:i A', strtotime($time)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Special Notes (Optional)</label>
                        <textarea name="notes" class="form-textarea" rows="3" placeholder="Any specific goals or focus areas for this session?"></textarea>
                    </div>

                    <div class="form-actions">
                        <div class="session-price-display">
                            <span class="price-label">Session Price:</span>
                            <span class="price-value" data-display="session-price">$0</span>
                        </div>
                        <button type="submit" class="btn-primary btn-book-session" data-action="submit-form">
                            <i class="fas fa-check"></i> Book Session
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================
         SECTION 2: PACKAGES (Lower Section - Card Style)
         ============================================ -->
    <div class="booking-section packages-section">
        <div class="section-header-bar">
            <div class="section-title-group">
                <h2 class="section-title"><i class="fas fa-box-open"></i> Training Packages</h2>
                <p class="section-subtitle">Save money with our bundled session packages</p>
            </div>
        </div>
        
        <?php if (count($packages) > 0): ?>
        <div class="packages-cards-grid" data-component="PackageGrid">
            <?php foreach ($packages as $idx => $package): 
                $is_popular = $idx === 1; // Mark middle package as popular
            ?>
            <div class="package-card <?= $is_popular ? 'featured' : '' ?>" data-component="PackageCard" data-package-id="<?= $package['id'] ?>">
                <?php if ($is_popular): ?>
                <div class="package-badge">Most Popular</div>
                <?php endif; ?>
                <div class="package-card-header">
                    <div class="package-icon">
                        <i class="fas fa-<?= $idx === 0 ? 'rocket' : ($idx === 1 ? 'star' : 'trophy') ?>"></i>
                    </div>
                    <h3 class="package-name"><?= htmlspecialchars($package['name']) ?></h3>
                </div>
                <div class="package-card-body">
                    <div class="package-pricing">
                        <span class="package-price">$<?= number_format($package['price'], 0) ?></span>
                        <span class="package-credits"><?= $package['credits'] ?> sessions</span>
                    </div>
                    <div class="package-per-session">
                        <span>$<?= number_format($package['price'] / max(1, $package['credits']), 2) ?> per session</span>
                    </div>
                    <p class="package-description"><?= htmlspecialchars($package['description'] ?? '') ?></p>
                    <ul class="package-features">
                        <li><i class="fas fa-check"></i> <?= $package['credits'] ?> training sessions</li>
                        <?php if ($package['valid_days']): ?>
                        <li><i class="fas fa-check"></i> Valid for <?= $package['valid_days'] ?> days</li>
                        <?php endif; ?>
                        <li><i class="fas fa-check"></i> Flexible scheduling</li>
                        <li><i class="fas fa-check"></i> All session types included</li>
                    </ul>
                </div>
                <div class="package-card-footer">
                    <button class="btn-purchase <?= $is_popular ? 'btn-purchase-featured' : '' ?>" data-action="purchase-package" data-package-id="<?= $package['id'] ?>">
                        <i class="fas fa-shopping-cart"></i> Purchase Package
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state-card">
            <i class="fas fa-box-open"></i>
            <h4>No Packages Available</h4>
            <p>Check back soon for training package offers.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* ============================================
   TWO-SECTION BOOKING PAGE STYLES
   ============================================ */

/* Section Layout */
.booking-section {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    padding: 28px;
    margin-bottom: 32px;
}

.section-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border, #2D2D3F);
}

.section-title-group {
    flex: 1;
}

.section-title {
    font-size: 22px;
    font-weight: 800;
    color: var(--text-white, #FFFFFF);
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-title i {
    color: var(--primary, #6B46C1);
    font-size: 24px;
}

.section-subtitle {
    font-size: 14px;
    color: var(--text-dim, #A8A8B8);
    margin: 0;
}

/* View Toggle Buttons */
.view-toggle {
    display: flex;
    gap: 4px;
    background: var(--bg-main, #0A0A0F);
    padding: 4px;
    border-radius: 8px;
    border: 1px solid var(--border, #2D2D3F);
}

.view-btn {
    width: 40px;
    height: 36px;
    border: none;
    background: transparent;
    color: var(--text-dim, #A8A8B8);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.view-btn:hover {
    color: var(--text-white, #FFFFFF);
    background: rgba(107, 70, 193, 0.1);
}

.view-btn.active {
    background: var(--primary, #6B46C1);
    color: #FFFFFF;
}

/* Sessions View Container */
.sessions-view {
    display: none;
}

.sessions-view.active {
    display: block;
}

/* ============================================
   LIST VIEW STYLES
   ============================================ */
.sessions-list-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.session-list-card {
    display: grid;
    grid-template-columns: 100px 1fr 180px;
    gap: 24px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
    padding: 20px;
    transition: all 0.3s ease;
    align-items: center;
}

.session-list-card:hover {
    border-color: var(--primary, #6B46C1);
    transform: translateX(4px);
    box-shadow: 0 4px 20px rgba(107, 70, 193, 0.15);
}

.session-date-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.date-badge {
    background: linear-gradient(135deg, var(--primary, #6B46C1), var(--accent, #8B5CF6));
    border-radius: 10px;
    padding: 12px 16px;
    text-align: center;
    min-width: 70px;
}

.date-month {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.9);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.date-day {
    display: block;
    font-size: 26px;
    font-weight: 900;
    color: #FFFFFF;
    line-height: 1.1;
}

.date-weekday {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.8);
}

.session-time {
    font-size: 13px;
    font-weight: 700;
    color: var(--primary, #6B46C1);
}

.session-details-column {
    flex: 1;
}

.session-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white, #FFFFFF);
    margin: 0 0 10px 0;
}

.session-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 8px;
}

.meta-item {
    font-size: 13px;
    color: var(--text-dim, #A8A8B8);
    display: flex;
    align-items: center;
    gap: 6px;
}

.meta-item i {
    color: var(--primary, #6B46C1);
    font-size: 12px;
}

.session-description {
    font-size: 13px;
    color: var(--text-dim, #A8A8B8);
    margin: 8px 0 0 0;
    line-height: 1.5;
}

.session-action-column {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
}

.spots-indicator {
    text-align: right;
}

.spots-indicator.almost-full .spots-number {
    color: #EF4444;
}

.spots-number {
    font-size: 24px;
    font-weight: 900;
    color: var(--primary, #6B46C1);
    display: block;
    line-height: 1;
}

.spots-text {
    font-size: 11px;
    color: var(--text-dim, #A8A8B8);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.session-price-tag {
    font-size: 20px;
    font-weight: 800;
    color: var(--text-white, #FFFFFF);
}

.btn-register {
    background: var(--primary, #6B46C1);
    color: #FFFFFF;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-register:hover {
    background: var(--primary-hover, #7C3AED);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.4);
}

/* ============================================
   CALENDAR VIEW STYLES
   ============================================ */
#calendar-view {
    display: none;
}

#calendar-view.active {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 24px;
}

.calendar-container {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
    padding: 20px;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.calendar-month-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white, #FFFFFF);
    margin: 0;
}

.calendar-nav-btn {
    width: 36px;
    height: 36px;
    border: 1px solid var(--border, #2D2D3F);
    background: transparent;
    color: var(--text-dim, #A8A8B8);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.calendar-nav-btn:hover {
    border-color: var(--primary, #6B46C1);
    color: var(--primary, #6B46C1);
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    margin-bottom: 8px;
}

.calendar-weekdays span {
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim, #A8A8B8);
    padding: 8px 0;
    text-transform: uppercase;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}

.calendar-day {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--bg-card, #16161F);
    border: 1px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.calendar-day:hover {
    border-color: var(--primary, #6B46C1);
    background: rgba(107, 70, 193, 0.1);
}

.calendar-day.other-month {
    opacity: 0.3;
}

.calendar-day.today {
    border-color: var(--primary, #6B46C1);
}

.calendar-day.has-sessions {
    background: rgba(107, 70, 193, 0.15);
}

.calendar-day.has-sessions::after {
    content: '';
    position: absolute;
    bottom: 6px;
    width: 6px;
    height: 6px;
    background: var(--primary, #6B46C1);
    border-radius: 50%;
}

.calendar-day.selected {
    background: var(--primary, #6B46C1);
    color: #FFFFFF;
}

.calendar-day.selected::after {
    background: #FFFFFF;
}

.calendar-day-number {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-white, #FFFFFF);
}

/* Calendar Sessions Panel */
.calendar-sessions-panel {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
    padding: 20px;
    max-height: 500px;
    overflow-y: auto;
}

.panel-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white, #FFFFFF);
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.panel-title i {
    color: var(--primary, #6B46C1);
}

.panel-sessions-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.no-sessions-msg {
    color: var(--text-dim, #A8A8B8);
    font-size: 14px;
    text-align: center;
    padding: 20px 0;
}

.panel-session-item {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 8px;
    padding: 14px;
    transition: all 0.2s ease;
}

.panel-session-item:hover {
    border-color: var(--primary, #6B46C1);
}

/* ============================================
   PRIVATE SESSION FORM STYLES
   ============================================ */
.private-session-form-container {
    margin-top: 32px;
}

.form-section-divider {
    text-align: center;
    position: relative;
    margin: 32px 0;
}

.form-section-divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: var(--border, #2D2D3F);
}

.form-section-divider span {
    position: relative;
    background: var(--bg-card, #16161F);
    padding: 0 20px;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim, #A8A8B8);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.booking-form-card {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
    padding: 24px;
}

.form-card-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border, #2D2D3F);
}

.form-card-header i {
    font-size: 28px;
    color: var(--primary, #6B46C1);
}

.form-card-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white, #FFFFFF);
    margin: 0 0 4px 0;
}

.form-card-header p {
    font-size: 13px;
    color: var(--text-dim, #A8A8B8);
    margin: 0;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dim, #A8A8B8);
    margin-bottom: 8px;
}

.form-group label .required {
    color: #EF4444;
}

.form-input {
    height: 44px;
    padding: 0 14px;
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 6px;
    color: var(--text-white, #FFFFFF);
    font-size: 14px;
    transition: all 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.15);
}

.form-textarea {
    width: 100%;
    padding: 12px 14px;
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 6px;
    color: var(--text-white, #FFFFFF);
    font-size: 14px;
    resize: vertical;
    font-family: 'Inter', sans-serif;
    transition: all 0.2s ease;
}

.form-textarea:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.15);
}

.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--border, #2D2D3F);
}

.session-price-display {
    display: flex;
    align-items: baseline;
    gap: 10px;
}

.price-label {
    font-size: 14px;
    color: var(--text-dim, #A8A8B8);
}

.price-value {
    font-size: 28px;
    font-weight: 900;
    color: var(--primary, #6B46C1);
}

.btn-book-session {
    height: 48px;
    padding: 0 32px;
}

/* ============================================
   PACKAGES SECTION STYLES (Card Style)
   ============================================ */
.packages-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
}

.package-card {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    flex-direction: column;
}

.package-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 40px rgba(107, 70, 193, 0.2);
    border-color: var(--primary, #6B46C1);
}

.package-card.featured {
    border: 2px solid var(--primary, #6B46C1);
    transform: scale(1.02);
}

.package-card.featured:hover {
    transform: scale(1.02) translateY(-8px);
}

.package-badge {
    position: absolute;
    top: 0;
    right: 20px;
    background: linear-gradient(135deg, var(--primary, #6B46C1), var(--accent, #8B5CF6));
    color: #FFFFFF;
    padding: 8px 16px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-radius: 0 0 8px 8px;
}

.package-card-header {
    padding: 28px 24px 20px;
    text-align: center;
    border-bottom: 1px solid var(--border, #2D2D3F);
}

.package-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 16px;
    background: linear-gradient(135deg, var(--primary, #6B46C1), var(--accent, #8B5CF6));
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.package-icon i {
    font-size: 26px;
    color: #FFFFFF;
}

.package-name {
    font-size: 22px;
    font-weight: 800;
    color: var(--text-white, #FFFFFF);
    margin: 0;
}

.package-card-body {
    padding: 24px;
    flex: 1;
}

.package-pricing {
    text-align: center;
    margin-bottom: 8px;
}

.package-price {
    font-size: 48px;
    font-weight: 900;
    color: var(--primary, #6B46C1);
    line-height: 1;
}

.package-credits {
    display: block;
    font-size: 14px;
    color: var(--text-dim, #A8A8B8);
    margin-top: 4px;
}

.package-per-session {
    text-align: center;
    margin-bottom: 20px;
}

.package-per-session span {
    font-size: 13px;
    color: var(--text-dim, #A8A8B8);
    background: rgba(107, 70, 193, 0.1);
    padding: 4px 12px;
    border-radius: 20px;
}

.package-description {
    font-size: 14px;
    color: var(--text-dim, #A8A8B8);
    text-align: center;
    margin-bottom: 20px;
    line-height: 1.5;
}

.package-features {
    list-style: none;
    padding: 0;
    margin: 0;
}

.package-features li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border, #2D2D3F);
    font-size: 14px;
    color: var(--text-dim, #A8A8B8);
}

.package-features li:last-child {
    border-bottom: none;
}

.package-features i {
    color: #10B981;
    font-size: 12px;
}

.package-card-footer {
    padding: 20px 24px 24px;
}

.btn-purchase {
    width: 100%;
    height: 48px;
    background: var(--bg-card, #16161F);
    border: 2px solid var(--primary, #6B46C1);
    color: var(--primary, #6B46C1);
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-purchase:hover {
    background: var(--primary, #6B46C1);
    color: #FFFFFF;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(107, 70, 193, 0.4);
}

.btn-purchase-featured {
    background: var(--primary, #6B46C1);
    color: #FFFFFF;
}

.btn-purchase-featured:hover {
    background: var(--primary-hover, #7C3AED);
}

/* ============================================
   EMPTY STATE & NOTICE STYLES
   ============================================ */
.empty-state-card {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
    padding: 48px 24px;
    text-align: center;
}

.empty-state-card i {
    font-size: 48px;
    color: var(--primary, #6B46C1);
    opacity: 0.4;
    margin-bottom: 16px;
}

.empty-state-card h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white, #FFFFFF);
    margin: 0 0 8px 0;
}

.empty-state-card p {
    font-size: 14px;
    color: var(--text-dim, #A8A8B8);
    margin: 0;
}

.demo-data-notice {
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid rgba(107, 70, 193, 0.3);
    border-radius: 8px;
    padding: 14px 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--accent, #8B5CF6);
    font-size: 14px;
}

.demo-data-notice i {
    font-size: 18px;
}

/* ============================================
   RESPONSIVE STYLES
   ============================================ */
@media (max-width: 1024px) {
    #calendar-view.active {
        grid-template-columns: 1fr;
    }
    
    .calendar-sessions-panel {
        max-height: 300px;
    }
}

@media (max-width: 768px) {
    .booking-section {
        padding: 20px;
    }
    
    .section-header-bar {
        flex-direction: column;
        gap: 16px;
    }
    
    .view-toggle {
        width: 100%;
        justify-content: center;
    }
    
    .session-list-card {
        grid-template-columns: 1fr;
        gap: 16px;
        text-align: center;
    }
    
    .session-date-column {
        flex-direction: row;
        justify-content: center;
        gap: 16px;
    }
    
    .session-action-column {
        align-items: center;
        flex-direction: row;
        justify-content: space-between;
        width: 100%;
        padding-top: 16px;
        border-top: 1px solid var(--border, #2D2D3F);
    }
    
    .spots-indicator {
        text-align: left;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
        gap: 16px;
    }
    
    .btn-book-session {
        width: 100%;
    }
    
    .packages-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .package-card.featured {
        transform: none;
    }
    
    .package-card.featured:hover {
        transform: translateY(-8px);
    }
}
</style>

<script>
// Booking page functionality - Two Section Layout with Calendar
document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // VIEW TOGGLE (List/Calendar)
    // ============================================
    const viewBtns = document.querySelectorAll('.view-btn');
    const sessionViews = document.querySelectorAll('.sessions-view');
    
    viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const viewType = this.dataset.view;
            
            // Update active button
            viewBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Update active view
            sessionViews.forEach(view => {
                view.classList.remove('active');
                if (view.id === viewType + '-view') {
                    view.classList.add('active');
                }
            });
            
            // Initialize calendar if switching to calendar view
            if (viewType === 'calendar') {
                initCalendar();
            }
        });
    });
    
    // ============================================
    // CALENDAR FUNCTIONALITY
    // ============================================
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();
    let selectedDate = null;
    
    // Session data for calendar (gathered from the list)
    const sessionData = [];
    document.querySelectorAll('.session-list-card').forEach(card => {
        sessionData.push({
            id: card.dataset.sessionId,
            date: card.dataset.date,
            element: card
        });
    });
    
    function initCalendar() {
        renderCalendar(currentMonth, currentYear);
    }
    
    function renderCalendar(month, year) {
        const calendarGrid = document.getElementById('calendar-grid');
        const calendarTitle = document.getElementById('calendar-title');
        
        if (!calendarGrid || !calendarTitle) return;
        
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
        
        calendarTitle.textContent = `${monthNames[month]} ${year}`;
        
        // Clear existing days
        calendarGrid.innerHTML = '';
        
        // Get first day of month and total days
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        
        // Today's date for comparison
        const today = new Date();
        const todayStr = today.toISOString().split('T')[0];
        
        // Get dates that have sessions
        const sessionDates = sessionData.map(s => s.date);
        
        // Previous month days
        for (let i = firstDay - 1; i >= 0; i--) {
            const dayNum = daysInPrevMonth - i;
            const dayEl = createDayElement(dayNum, true, false, false);
            calendarGrid.appendChild(dayEl);
        }
        
        // Current month days
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isToday = dateStr === todayStr;
            const hasSessions = sessionDates.includes(dateStr);
            const dayEl = createDayElement(day, false, isToday, hasSessions, dateStr);
            calendarGrid.appendChild(dayEl);
        }
        
        // Next month days to fill grid
        const totalCells = calendarGrid.children.length;
        const remainingCells = 42 - totalCells; // 6 rows * 7 days
        for (let i = 1; i <= remainingCells; i++) {
            const dayEl = createDayElement(i, true, false, false);
            calendarGrid.appendChild(dayEl);
        }
    }
    
    function createDayElement(dayNum, isOtherMonth, isToday, hasSessions, dateStr = null) {
        const dayEl = document.createElement('div');
        dayEl.className = 'calendar-day';
        
        if (isOtherMonth) dayEl.classList.add('other-month');
        if (isToday) dayEl.classList.add('today');
        if (hasSessions) dayEl.classList.add('has-sessions');
        
        const dayNumEl = document.createElement('span');
        dayNumEl.className = 'calendar-day-number';
        dayNumEl.textContent = dayNum;
        dayEl.appendChild(dayNumEl);
        
        if (dateStr && !isOtherMonth) {
            dayEl.dataset.date = dateStr;
            dayEl.addEventListener('click', function() {
                selectDate(dateStr);
            });
        }
        
        return dayEl;
    }
    
    function selectDate(dateStr) {
        // Update selected state
        document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
        const selectedDay = document.querySelector(`.calendar-day[data-date="${dateStr}"]`);
        if (selectedDay) selectedDay.classList.add('selected');
        
        selectedDate = dateStr;
        
        // Update panel
        const dateTitle = document.getElementById('selected-date-title');
        const sessionsList = document.getElementById('panel-sessions-list');
        
        const dateObj = new Date(dateStr + 'T00:00:00');
        const options = { weekday: 'long', month: 'long', day: 'numeric' };
        dateTitle.textContent = dateObj.toLocaleDateString('en-US', options);
        
        // Find sessions for this date
        const daySessions = sessionData.filter(s => s.date === dateStr);
        
        if (daySessions.length > 0) {
            sessionsList.innerHTML = '';
            daySessions.forEach(session => {
                const card = session.element;
                const title = card.querySelector('.session-title')?.textContent || 'Training Session';
                const time = card.querySelector('.session-time')?.textContent || '';
                const price = card.querySelector('.session-price-tag')?.textContent || '';
                const spots = card.querySelector('.spots-number')?.textContent || '';
                
                // Create elements safely to prevent XSS
                const itemEl = document.createElement('div');
                itemEl.className = 'panel-session-item';
                
                // Header row
                const headerDiv = document.createElement('div');
                headerDiv.style.cssText = 'display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;';
                
                const titleEl = document.createElement('h5');
                titleEl.style.cssText = 'margin: 0; font-size: 15px; font-weight: 700; color: #fff;';
                titleEl.textContent = title;
                
                const priceEl = document.createElement('span');
                priceEl.style.cssText = 'font-weight: 800; color: var(--primary, #6B46C1);';
                priceEl.textContent = price;
                
                headerDiv.appendChild(titleEl);
                headerDiv.appendChild(priceEl);
                
                // Details row
                const detailsDiv = document.createElement('div');
                detailsDiv.style.cssText = 'font-size: 13px; color: #A8A8B8; margin-bottom: 10px;';
                detailsDiv.innerHTML = '<i class="fas fa-clock" style="color: var(--primary, #6B46C1); margin-right: 6px;"></i>';
                const timeSpan = document.createElement('span');
                timeSpan.textContent = time;
                detailsDiv.appendChild(timeSpan);
                
                const spotsContainer = document.createElement('span');
                spotsContainer.style.marginLeft = '12px';
                spotsContainer.innerHTML = '<i class="fas fa-users" style="color: var(--primary, #6B46C1); margin-right: 6px;"></i>';
                const spotsSpan = document.createElement('span');
                spotsSpan.textContent = spots + ' spots left';
                spotsContainer.appendChild(spotsSpan);
                detailsDiv.appendChild(spotsContainer);
                
                // Register button
                const registerBtn = document.createElement('button');
                registerBtn.className = 'btn-register';
                registerBtn.setAttribute('data-action', 'register-session');
                registerBtn.setAttribute('data-session-id', session.id);
                registerBtn.style.cssText = 'width: 100%; justify-content: center;';
                registerBtn.innerHTML = '<i class="fas fa-plus-circle"></i> Register';
                
                itemEl.appendChild(headerDiv);
                itemEl.appendChild(detailsDiv);
                itemEl.appendChild(registerBtn);
                sessionsList.appendChild(itemEl);
            });
        } else {
            sessionsList.innerHTML = '<p class="no-sessions-msg">No sessions available on this date</p>';
        }
    }
    
    // Calendar navigation
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');
    
    if (prevMonthBtn) {
        prevMonthBtn.addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar(currentMonth, currentYear);
        });
    }
    
    if (nextMonthBtn) {
        nextMonthBtn.addEventListener('click', function() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar(currentMonth, currentYear);
        });
    }
    
    // ============================================
    // PACKAGE PURCHASE FUNCTIONALITY
    // ============================================
    document.querySelectorAll('[data-action="purchase-package"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const packageId = this.dataset.packageId;
            const packageCard = this.closest('.package-card');
            const packageName = packageCard?.querySelector('.package-name')?.textContent || 'Package';
            
            if (packageId.startsWith('demo-')) {
                showBookingNotification('Demo Mode: This is a demo package. Contact admin to set up real packages for purchase.', 'info');
            } else {
                // Validate CSRF token exists
                const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
                if (!csrfToken) {
                    showBookingNotification('Security token missing. Please refresh the page and try again.', 'error');
                    return;
                }
                
                // Validate packageId is a valid numeric ID
                if (!/^\d+$/.test(packageId)) {
                    showBookingNotification('Invalid package ID.', 'error');
                    return;
                }
                
                // Submit via form for Stripe checkout with CSRF protection
                // Use DOM methods instead of innerHTML to prevent XSS
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'process_purchase_package.php';
                
                const packageInput = document.createElement('input');
                packageInput.type = 'hidden';
                packageInput.name = 'package_id';
                packageInput.value = packageId;
                form.appendChild(packageInput);
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
    
    // ============================================
    // SESSION REGISTRATION FUNCTIONALITY
    // ============================================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-action="register-session"]');
        if (!btn) return;
        
        const sessionId = btn.dataset.sessionId;
        const price = btn.dataset.price;
        
        if (sessionId.startsWith('demo-')) {
            showBookingNotification('Demo Mode: This is a demo session. Book real sessions when they become available.', 'info');
        } else {
            // Validate CSRF token exists
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
            if (!csrfToken) {
                showBookingNotification('Security token missing. Please refresh the page and try again.', 'error');
                return;
            }
            
            // Validate sessionId is a valid numeric ID  
            if (!/^\d+$/.test(sessionId)) {
                showBookingNotification('Invalid session ID.', 'error');
                return;
            }
            
            // Submit registration - use DOM methods instead of innerHTML to prevent XSS
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'process_booking.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'register_session';
            form.appendChild(actionInput);
            
            const sessionInput = document.createElement('input');
            sessionInput.type = 'hidden';
            sessionInput.name = 'session_id';
            sessionInput.value = sessionId;
            form.appendChild(sessionInput);
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    });
    
    // ============================================
    // PRIVATE SESSION FORM - PRICE UPDATE
    // ============================================
    const sessionTypeSelect = document.querySelector('[data-field="session-type"]');
    const priceDisplay = document.querySelector('[data-display="session-price"]');
    
    if (sessionTypeSelect && priceDisplay) {
        sessionTypeSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.dataset.price || 0;
            priceDisplay.textContent = '$' + price;
        });
    }
    
    // Form submission
    const bookingForm = document.querySelector('[data-form="session-booking"]');
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const sessionTypeId = formData.get('session_type_id');
            const coachId = formData.get('coach_id');
            
            // Check for demo data
            if (sessionTypeId?.startsWith('demo-') || coachId?.startsWith('demo-')) {
                showBookingNotification('Demo Mode: Private session booking requires real coaches and session types. Contact admin for setup.', 'info');
                return;
            }
            
            // Submit the form
            this.submit();
        });
    }
});

// Notification helper
function showBookingNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'booking-notification';
    
    let icon = 'info-circle';
    let bgColor = 'rgba(107, 70, 193, 0.9)';
    
    if (type === 'error') {
        icon = 'exclamation-circle';
        bgColor = 'rgba(239, 68, 68, 0.9)';
    } else if (type === 'success') {
        icon = 'check-circle';
        bgColor = 'rgba(16, 185, 129, 0.9)';
    }
    
    alertDiv.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
    alertDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        min-width: 300px;
        max-width: 500px;
        padding: 16px 20px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        background: ${bgColor};
        color: #fff;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideInRight 0.3s ease;
        box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    `;
    
    // Add animation keyframes
    if (!document.getElementById('booking-notification-styles')) {
        const styleSheet = document.createElement('style');
        styleSheet.id = 'booking-notification-styles';
        styleSheet.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(styleSheet);
    }
    
    document.body.appendChild(alertDiv);
    setTimeout(() => {
        alertDiv.style.animation = 'slideInRight 0.3s ease reverse';
        setTimeout(() => alertDiv.remove(), 300);
    }, 4500);
}
</script>
<script>
// Initialize coach typeahead for private session booking
new ArcticTypeahead({
    container: '#coach-typeahead-container',
    name: 'coach_id',
    placeholder: 'Search for a coach…',
    roles: 'coach,admin',
    multiple: false,
    required: true
});
</script>
