<?php
// Get available packages
$packages_query = "
    SELECT p.*, pt.name as package_type_name
    FROM packages p
    LEFT JOIN package_types pt ON p.type_id = pt.id
    WHERE p.is_active = 1 AND p.is_available = 1
    ORDER BY p.display_order, p.price
";
$packages = $pdo->query($packages_query)->fetchAll();

// Get coaches for individual sessions
$coaches_query = "
    SELECT u.id, u.first_name, u.last_name, u.specialty
    FROM users u
    WHERE u.role IN ('coach', 'admin') AND u.is_active = 1
    ORDER BY u.last_name, u.first_name
";
$coaches = $pdo->query($coaches_query)->fetchAll();

// Get session types
$session_types = $pdo->query("SELECT * FROM session_types WHERE is_active = 1 ORDER BY name")->fetchAll();
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
            <div class="package-card <?= $package['is_featured'] ? 'featured' : '' ?>" data-component="PackageCard" data-package-id="<?= $package['id'] ?>">
                <?php if ($package['badge_text']): ?>
                    <div class="package-badge"><?= htmlspecialchars($package['badge_text']) ?></div>
                <?php endif; ?>
                <h3 class="package-title"><?= htmlspecialchars($package['name']) ?></h3>
                <div class="package-price">
                    <span class="price">$<?= number_format($package['price'], 0) ?></span>
                    <span class="price-detail"><?= $package['session_count'] ?> sessions</span>
                </div>
                <ul class="package-features">
                    <?php 
                    $features = json_decode($package['features_json'], true) ?? [];
                    foreach ($features as $feature): 
                    ?>
                        <li><i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?></li>
                    <?php endforeach; ?>
                </ul>
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
        <div class="booking-form-card">
            <h3><i class="fas fa-calendar-check"></i> Book Individual Session</h3>
            
            <form class="booking-form" method="POST" action="process_booking.php" data-form="session-booking">
                <input type="hidden" name="action" value="book_session">
                <input type="hidden" name="athlete_id" value="<?= $user_id ?>">
                
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
</style>
