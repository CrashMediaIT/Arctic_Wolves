<?php
/**
 * PWA Admin Team Coaches - Mobile-native coaches list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$coaches = [];
try {
    $stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.role FROM users u WHERE u.role IN ('coach','team_coach','health_coach') AND u.is_active = 1 ORDER BY u.first_name");
    $stmt->execute();
    $coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $coaches = []; }
?>
<style>
.m-coaches { padding: 16px; font-family: Inter, sans-serif; }
.m-coaches-header { margin-bottom: 16px; }
.m-coaches-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-coaches-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-coach-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
    text-decoration: none;
}
.m-coach-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
}
.m-coach-body { flex: 1; min-width: 0; }
.m-coach-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-coach-email { font-size: 12px; color: #A8A8B8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-coach-role {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    background: rgba(59,130,246,0.15); color: #3B82F6; flex-shrink: 0;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-coaches">
    <div class="m-coaches-header">
        <h2 class="m-coaches-title">Team Coaches</h2>
        <p class="m-coaches-sub"><?= count($coaches) ?> active coach<?= count($coaches) !== 1 ? 'es' : '' ?></p>
    </div>

    <?php if (empty($coaches)): ?>
        <div class="m-empty-state">
            <i class="fas fa-user-tie"></i>
            <p>No coaches found</p>
        </div>
    <?php else: ?>
        <?php foreach ($coaches as $c):
            $initial = strtoupper(mb_substr($c['first_name'] ?? '?', 0, 1));
            $fullName = htmlspecialchars(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
            $role = strtolower($c['role'] ?? '');
        ?>
        <div class="m-coach-card">
            <div class="m-coach-avatar"><?= $initial ?></div>
            <div class="m-coach-body">
                <div class="m-coach-name"><?= $fullName ?></div>
                <div class="m-coach-email"><?= htmlspecialchars($c['email'] ?? '') ?></div>
            </div>
            <span class="m-coach-role"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $role))) ?></span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
