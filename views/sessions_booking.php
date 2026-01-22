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

// Get session types
$session_types = $pdo->query("SELECT * FROM session_types ORDER BY name")->fetchAll();
?>

<!-- Session Booking View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-calendar-plus"></i> Book a Session
    </h1>
    <p class="page-description">Choose from packages or individual sessions</p>
</div>

<div class="booking-content">
    <!-- Booking Type Tabs -->
    <div class="booking-tabs">
        <button class="tab-btn active" data-tab="packages">
            <i class="fas fa-box"></i> Packages
        </button>
        <button class="tab-btn" data-tab="individual">
            <i class="fas fa-calendar-day"></i> Individual Sessions
        </button>
    </div>

    <!-- Packages Tab -->
    <div class="tab-content active" id="packages-tab">
        <?php if (count($packages) > 0): ?>
        <div class="packages-grid" data-component="PackageGrid">
            <?php foreach ($packages as $idx => $package): ?>
            <div class="package-card" data-component="PackageCard" data-package-id="<?= $package['id'] ?>">
                <h3 class="package-title"><?= htmlspecialchars($package['name']) ?></h3>
                <div class="package-price">
                    <span class="price">$<?= number_format($package['price'], 0) ?></span>
                    <span class="price-detail"><?= $package['credits'] ?> credits</span>
                </div>
                <div class="package-description">
                    <p><?= htmlspecialchars($package['description'] ?? '') ?></p>
                </div>
                <?php if ($package['valid_days']): ?>
                    <div class="package-validity">
                        <i class="fas fa-clock"></i> Valid for <?= $package['valid_days'] ?> days
                    </div>
                <?php endif; ?>
                <button class="btn-primary btn-full" data-action="purchase-package" data-package-id="<?= $package['id'] ?>"><i class="fas fa-shopping-cart"></i> Purchase</button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="placeholder-container">
            <i class="fas fa-box placeholder-icon"></i>
            <p class="placeholder-text">No packages available at this time.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Individual Sessions Tab -->
    <div class="tab-content" id="individual-tab">
        <?php
        // Get available sessions for booking
        $available_sessions_query = "
            SELECT s.*, 
                   CONCAT(c.first_name, ' ', c.last_name) as coach_name,
                   st.name as session_type_name,
                   st.price as session_price,
                   l.name as location_name,
                   COUNT(DISTINCT b.id) as registered_count,
                   s.max_participants
            FROM sessions s
            LEFT JOIN users c ON s.coach_id = c.id
            LEFT JOIN session_types st ON s.session_type_id = st.id
            LEFT JOIN locations l ON s.location_id = l.id
            LEFT JOIN bookings b ON b.session_id = s.id
            WHERE s.session_date > NOW() 
              AND s.status = 'scheduled'
              AND s.max_participants > (SELECT COUNT(*) FROM bookings WHERE session_id = s.id)
            GROUP BY s.id
            ORDER BY s.session_date
            LIMIT 20
        ";
        $available_sessions = $pdo->query($available_sessions_query)->fetchAll();
        ?>
        
        <div class="available-sessions">
            <h3><i class="fas fa-calendar-check"></i> Available Sessions</h3>
            <p class="section-description">Register for an upcoming group session</p>
            
            <?php if (count($available_sessions) > 0): ?>
            <div class="sessions-grid">
                <?php foreach ($available_sessions as $session): 
                    $session_datetime = strtotime($session['session_date']);
                    $session_end_time = $session_datetime + ($session['duration_minutes'] ?? 60) * 60;
                    $spots_left = $session['max_participants'] - $session['registered_count'];
                ?>
                <div class="available-session-card" data-session-id="<?= $session['id'] ?>">
                    <div class="session-header">
                        <div class="session-date-badge">
                            <span class="date-day"><?= date('M j', $session_datetime) ?></span>
                            <span class="date-time"><?= date('g:i A', $session_datetime) ?></span>
                        </div>
                        <div class="session-spots">
                            <span class="spots-count"><?= $spots_left ?></span>
                            <span class="spots-label">spots left</span>
                        </div>
                    </div>
                    <h4 class="session-name"><?= htmlspecialchars($session['session_type_name']) ?></h4>
                    <div class="session-info">
                        <div class="info-item">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($session['coach_name'] ?? 'TBD') ?>
                        </div>
                        <?php if ($session['location_name']): ?>
                        <div class="info-item">
                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($session['location_name']) ?>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <i class="fas fa-clock"></i> <?= $session['duration_minutes'] ?? 60 ?> min
                        </div>
                    </div>
                    <?php if (!empty($session['description'])): ?>
                    <div class="session-brief">
                        <p><?= htmlspecialchars(substr($session['description'], 0, 100)) ?><?= strlen($session['description']) > 100 ? '...' : '' ?></p>
                    </div>
                    <?php endif; ?>
                    <div class="session-footer">
                        <div class="session-cost">
                            <span class="price-amount">$<?= number_format($session['session_price'], 0) ?></span>
                        </div>
                        <button class="btn-primary" data-action="register-session" data-session-id="<?= $session['id'] ?>" data-price="<?= $session['session_price'] ?>">
                            <i class="fas fa-check-circle"></i> Register
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="placeholder-container">
                <i class="fas fa-calendar placeholder-icon"></i>
                <p class="placeholder-text">No sessions currently available for registration.</p>
                <p class="placeholder-subtext">Check back soon or book a private session below.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="booking-divider">
            <span>OR</span>
        </div>
        
        <div class="booking-form-card">
            <h3><i class="fas fa-user-plus"></i> Book Private Session</h3>
            <p class="section-description">Schedule a one-on-one session with a coach</p>
            
            <form class="booking-form" method="POST" action="process_booking.php" data-form="session-booking">
                <input type="hidden" name="action" value="book_private_session">
                <!-- Note: athlete_id will be validated server-side from session -->
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Session Type *</label>
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
                        <label>Coach *</label>
                        <select name="coach_id" class="form-input" required data-field="coach-select">
                            <option value="">-- Select Coach --</option>
                            <?php foreach ($coaches as $coach): ?>
                                <option value="<?= $coach['id'] ?>">
                                    <?= htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']) ?>
                                    <?php if ($coach['specialty']): ?>
                                        - <?= htmlspecialchars($coach['specialty']) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="session_date" class="form-input" required min="<?= date('Y-m-d') ?>" data-field="session-date">
                    </div>
                    <div class="form-group">
                        <label>Time *</label>
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
                    <textarea name="notes" class="form-textarea" rows="4" placeholder="Any specific goals or focus areas for this session?"></textarea>
                </div>

                <div class="form-actions">
                    <div class="session-price">
                        <span class="price-label">Session Price:</span>
                        <span class="price" data-display="session-price">$0</span>
                    </div>
                    <button type="submit" class="btn-primary" data-action="submit-form"><i class="fas fa-check"></i> Book Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.booking-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
}

.tab-btn {
    height: 45px;
    padding: 0 30px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-dim);
    border-radius: 4px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.tab-btn:hover {
    border-color: var(--neon);
    color: var(--text-white);
}

.tab-btn.active {
    background: var(--neon);
    border-color: var(--neon);
    color: #fff;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.packages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
}

.package-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 30px;
    position: relative;
    transition: all 0.3s;
}

.package-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(255, 77, 0, 0.2);
    border-color: var(--neon);
}

.package-card.featured {
    border: 2px solid var(--neon);
}

.package-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    color: #fff;
    padding: 5px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.package-title {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
    margin-bottom: 15px;
}

.package-price {
    margin-bottom: 25px;
}

.price {
    font-size: 48px;
    font-weight: 900;
    color: var(--neon);
    display: block;
    line-height: 1;
}

.price-detail {
    font-size: 14px;
    color: var(--text-dim);
}

.package-features {
    list-style: none;
    margin-bottom: 25px;
}

.package-features li {
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    color: var(--text-dim);
}

.package-features li:last-child {
    border-bottom: none;
}

.package-features i {
    color: var(--neon);
    margin-right: 10px;
}

.btn-full {
    width: 100%;
}

.booking-form-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 30px;
}

.booking-form-card h3 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 25px;
}

.booking-form-card h3 i {
    color: var(--neon);
    margin-right: 10px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
    margin-bottom: 8px;
}

.form-textarea {
    width: 100%;
    background: var(--bg-main);
    border: 1px solid var(--border);
    color: var(--text-white);
    padding: 12px 15px;
    border-radius: 4px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    resize: vertical;
}

.form-textarea:focus {
    outline: none;
    border-color: var(--neon);
}

.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

.session-price {
    display: flex;
    align-items: baseline;
    gap: 10px;
}

.price-label {
    font-size: 14px;
    color: var(--text-dim);
}

.available-sessions {
    margin-bottom: 40px;
}

.available-sessions h3 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 8px;
}

.available-sessions h3 i {
    color: var(--neon);
    margin-right: 10px;
}

.section-description {
    font-size: 14px;
    color: var(--text-dim);
    margin-bottom: 25px;
}

.sessions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.available-session-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s;
}

.available-session-card:hover {
    transform: translateY(-3px);
    border-color: var(--neon);
    box-shadow: 0 8px 25px rgba(255, 77, 0, 0.15);
}

.session-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.session-date-badge {
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 6px;
    padding: 10px 15px;
    text-align: center;
}

.date-day {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: #fff;
}

.date-time {
    display: block;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.9);
    margin-top: 2px;
}

.session-spots {
    text-align: center;
}

.spots-count {
    display: block;
    font-size: 24px;
    font-weight: 900;
    color: var(--neon);
    line-height: 1;
}

.spots-label {
    display: block;
    font-size: 12px;
    color: var(--text-dim);
}

.session-name {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 12px;
}

.session-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 12px;
}

.info-item {
    font-size: 13px;
    color: var(--text-dim);
}

.info-item i {
    color: var(--neon);
    margin-right: 8px;
    width: 16px;
}

.session-brief {
    margin-bottom: 15px;
}

.session-brief p {
    font-size: 14px;
    color: var(--text-dim);
    line-height: 1.5;
}

.session-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

.session-cost {
    display: flex;
    align-items: baseline;
}

.price-amount {
    font-size: 28px;
    font-weight: 900;
    color: var(--neon);
}

.booking-divider {
    text-align: center;
    margin: 40px 0;
    position: relative;
}

.booking-divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: var(--border);
}

.booking-divider span {
    position: relative;
    background: var(--bg-main);
    padding: 0 20px;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
}

.placeholder-subtext {
    font-size: 14px;
    color: var(--text-dim);
    margin-top: 10px;
}
</style>
