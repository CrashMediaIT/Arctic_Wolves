<?php
/**
 * PWA Session Evaluation Form - Mobile-native session evaluation
 * Purpose-built for mobile phones.
 */

$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

$session = null;
$athletes = [];

if ($session_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, title, session_date, session_time FROM sessions WHERE id = ?");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $session = null; }

    if ($session) {
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.first_name, u.last_name
                FROM users u
                INNER JOIN bookings b ON b.user_id = u.id
                WHERE b.session_id = ? AND b.status = 'confirmed'
                ORDER BY u.first_name
            ");
            $stmt->execute([$session_id]);
            $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $athletes = []; }
    }
}
?>
<style>
.m-sesseval { padding: 16px; font-family: Inter, sans-serif; }
.m-sesseval-header { margin-bottom: 16px; }
.m-sesseval-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-sesseval-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-sesseval-info {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 16px;
}
.m-sesseval-info-title { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 4px; }
.m-sesseval-info-meta { font-size: 12px; color: #A8A8B8; display: flex; gap: 8px; }
.m-sesseval-form-group { margin-bottom: 16px; }
.m-sesseval-label {
    font-size: 13px; font-weight: 600; color: #A8A8B8; display: block; margin-bottom: 6px;
}
.m-sesseval-athlete-row {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px 14px; margin-bottom: 8px;
}
.m-sesseval-athlete-name { flex: 1; font-size: 14px; color: #fff; font-weight: 500; }
.m-sesseval-score-input {
    width: 64px; padding: 8px; text-align: center;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    min-height: 44px; outline: none; box-sizing: border-box;
}
.m-sesseval-score-input:focus { border-color: #6B46C1; }
.m-sesseval-btn {
    display: block; width: 100%; padding: 14px; border: none; border-radius: 12px;
    background: #6B46C1; color: #fff; font-size: 15px; font-weight: 600;
    font-family: Inter, sans-serif; cursor: pointer; min-height: 44px;
    text-align: center;
}
.m-sesseval-btn:active { background: #8B5CF6; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-sesseval">
    <div class="m-sesseval-header">
        <h2 class="m-sesseval-title">Session Evaluation</h2>
        <p class="m-sesseval-sub">Rate athlete performance</p>
    </div>

    <?php if (!$session): ?>
        <div class="m-empty-state">
            <i class="fas fa-calendar-xmark"></i>
            <p>Session not found</p>
        </div>
    <?php else: ?>
        <div class="m-sesseval-info">
            <div class="m-sesseval-info-title"><?= htmlspecialchars($session['title']) ?></div>
            <div class="m-sesseval-info-meta">
                <?php if (!empty($session['session_date'])): ?>
                <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($session['session_date'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($session['session_time'])): ?>
                <span><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($session['session_time'])) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($athletes)): ?>
            <div class="m-empty-state">
                <i class="fas fa-users-slash"></i>
                <p>No athletes booked for this session</p>
            </div>
        <?php else: ?>
            <form method="post" action="process_session_evaluations.php">
                <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
                <div class="m-sesseval-form-group">
                    <label class="m-sesseval-label">Athlete Scores (1-10)</label>
                    <?php foreach ($athletes as $a):
                        $aName = htmlspecialchars($a['first_name'] . ' ' . $a['last_name']);
                    ?>
                    <div class="m-sesseval-athlete-row">
                        <span class="m-sesseval-athlete-name"><?= $aName ?></span>
                        <input type="number" name="scores[<?= (int)$a['id'] ?>]" class="m-sesseval-score-input" min="1" max="10" placeholder="—">
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="m-sesseval-btn">Submit Evaluations</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
