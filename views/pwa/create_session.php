<?php
/**
 * PWA Create Session - Mobile-native session creation form
 * Purpose-built for mobile phones.
 */

if (!$isAnyCoach) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Coach access required.</p>';
    echo '</div>';
    return;
}

$sessionTypes = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM session_types ORDER BY name ASC");
    $sessionTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sessionTypes = []; }
?>
<style>
.m-createsess { padding: 16px; font-family: Inter, sans-serif; }
.m-createsess-header { margin-bottom: 16px; }
.m-createsess-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-createsess-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-form-group { margin-bottom: 16px; }
.m-form-label {
    font-size: 13px; font-weight: 600; color: #A8A8B8;
    display: block; margin-bottom: 6px;
}
.m-form-input, .m-form-select {
    width: 100%; padding: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; min-height: 44px; outline: none;
}
.m-form-input::placeholder { color: #6B6B7B; }
.m-form-input:focus, .m-form-select:focus { border-color: #6B46C1; }
.m-form-select { appearance: none; -webkit-appearance: none; }
.m-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.m-form-btn {
    display: block; width: 100%; padding: 14px; border: none; border-radius: 12px;
    background: #6B46C1; color: #fff; font-size: 15px; font-weight: 600;
    font-family: Inter, sans-serif; cursor: pointer; min-height: 44px;
    text-align: center; margin-top: 8px;
}
.m-form-btn:active { background: #8B5CF6; }
</style>

<div class="m-createsess">
    <div class="m-createsess-header">
        <h2 class="m-createsess-title">Create Session</h2>
        <p class="m-createsess-sub">Schedule a new training session</p>
    </div>

    <form method="post" action="process_create_session.php">
        <?= csrfTokenInput() ?>
        <div class="m-form-group">
            <label class="m-form-label">Session Title</label>
            <input type="text" name="title" class="m-form-input" placeholder="e.g. Morning Skills" required>
        </div>

        <div class="m-form-row">
            <div class="m-form-group">
                <label class="m-form-label">Date</label>
                <input type="date" name="session_date" class="m-form-input" required>
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Time</label>
                <input type="time" name="session_time" class="m-form-input" required>
            </div>
        </div>

        <div class="m-form-row">
            <div class="m-form-group">
                <label class="m-form-label">Duration (min)</label>
                <input type="number" name="duration_minutes" class="m-form-input" placeholder="60" min="15" max="480">
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Max Participants</label>
                <input type="number" name="max_participants" class="m-form-input" placeholder="20" min="1" max="100">
            </div>
        </div>

        <div class="m-form-group">
            <label class="m-form-label">Arena / Location</label>
            <input type="text" name="arena" class="m-form-input" placeholder="e.g. Rink A">
        </div>

        <div class="m-form-group">
            <label class="m-form-label">Session Type</label>
            <select name="session_type_id" class="m-form-select">
                <option value="">Select type...</option>
                <?php foreach ($sessionTypes as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="m-form-btn">Create Session</button>
    </form>
</div>
