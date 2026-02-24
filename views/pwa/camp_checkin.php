<?php
/**
 * PWA Camp Check-in - Mobile-native parent check-in view
 * Purpose-built for mobile phones.
 */

if (!$isParent) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Parent access required.</p>';
    echo '</div>';
    return;
}

$children = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name
        FROM users u
        INNER JOIN parent_athlete_relationships par ON par.athlete_id = u.id
        WHERE par.parent_id = ?
    ");
    $stmt->execute([$user_id]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $children = decryptUserRows($children);
} catch (PDOException $e) { $children = []; }
?>
<style>
.m-checkin { padding: 16px; font-family: Inter, sans-serif; }
.m-checkin-header { margin-bottom: 16px; }
.m-checkin-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-checkin-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-checkin-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; margin-bottom: 12px;
}
.m-checkin-child { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.m-checkin-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.m-checkin-name { font-size: 15px; font-weight: 600; color: #fff; }
.m-checkin-actions { display: flex; gap: 10px; }
.m-checkin-btn {
    flex: 1; padding: 12px; border: none; border-radius: 10px;
    font-size: 14px; font-weight: 600; font-family: Inter, sans-serif;
    cursor: pointer; min-height: 44px; text-align: center;
}
.m-checkin-btn-in { background: #10B981; color: #fff; }
.m-checkin-btn-in:active { background: #059669; }
.m-checkin-btn-out { background: rgba(239,68,68,0.15); color: #EF4444; border: 1px solid rgba(239,68,68,0.3); }
.m-checkin-btn-out:active { background: rgba(239,68,68,0.25); }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-checkin">
    <div class="m-checkin-header">
        <h2 class="m-checkin-title">Camp Check-in</h2>
        <p class="m-checkin-sub"><?= date('l, M j') ?></p>
    </div>

    <?php if (empty($children)): ?>
        <div class="m-empty-state">
            <i class="fas fa-users-slash"></i>
            <p>No children linked to your account</p>
        </div>
    <?php else: ?>
        <?php foreach ($children as $c):
            $initial = strtoupper(mb_substr($c['first_name'], 0, 1) . mb_substr($c['last_name'], 0, 1));
            $cName = htmlspecialchars($c['first_name'] . ' ' . $c['last_name']);
        ?>
        <div class="m-checkin-card">
            <div class="m-checkin-child">
                <div class="m-checkin-avatar"><?= $initial ?></div>
                <div class="m-checkin-name"><?= $cName ?></div>
            </div>
            <div class="m-checkin-actions">
                <form method="post" action="process_camp_checkin.php" style="flex:1;">
                    <input type="hidden" name="athlete_id" value="<?= (int)$c['id'] ?>">
                    <input type="hidden" name="action" value="checkin">
                    <button type="submit" class="m-checkin-btn m-checkin-btn-in"><i class="fas fa-sign-in-alt"></i> Check In</button>
                </form>
                <form method="post" action="process_camp_checkin.php" style="flex:1;">
                    <input type="hidden" name="athlete_id" value="<?= (int)$c['id'] ?>">
                    <input type="hidden" name="action" value="checkout">
                    <button type="submit" class="m-checkin-btn m-checkin-btn-out"><i class="fas fa-sign-out-alt"></i> Check Out</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
