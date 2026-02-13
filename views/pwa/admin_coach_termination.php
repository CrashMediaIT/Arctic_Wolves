<?php
/**
 * PWA Admin Coach Termination - Mobile-native terminated coaches list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$coaches = [];
try {
    $stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.role FROM users u WHERE u.role IN ('coach','team_coach') AND u.is_active = 0 ORDER BY u.last_name LIMIT 20");
    $stmt->execute();
    $coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $coaches = []; }
?>
<style>
.m-coachterm { padding: 16px; font-family: Inter, sans-serif; }
.m-coachterm-header { margin-bottom: 16px; }
.m-coachterm-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-coachterm-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-coachterm-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-coachterm-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0;
    background: linear-gradient(135deg, #6B6B7B, #A8A8B8);
}
.m-coachterm-body { flex: 1; min-width: 0; }
.m-coachterm-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-coachterm-role { font-size: 12px; color: #A8A8B8; margin-top: 1px; }
.m-coachterm-badge {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    background: rgba(239,68,68,0.15); color: #EF4444; flex-shrink: 0;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-coachterm">
    <div class="m-coachterm-header">
        <h2 class="m-coachterm-title">Terminated Coaches</h2>
        <p class="m-coachterm-sub"><?= count($coaches) ?> terminated coach<?= count($coaches) !== 1 ? 'es' : '' ?></p>
    </div>

    <?php if (empty($coaches)): ?>
        <div class="m-empty-state">
            <i class="fas fa-user-check"></i>
            <p>No terminated coaches</p>
        </div>
    <?php else: ?>
        <?php foreach ($coaches as $c):
            $initial = strtoupper(mb_substr($c['first_name'] ?? '?', 0, 1));
            $fullName = htmlspecialchars(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
        ?>
        <div class="m-coachterm-card">
            <div class="m-coachterm-avatar"><?= $initial ?></div>
            <div class="m-coachterm-body">
                <div class="m-coachterm-name"><?= $fullName ?></div>
                <div class="m-coachterm-role"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $c['role'] ?? ''))) ?></div>
            </div>
            <span class="m-coachterm-badge">Inactive</span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
