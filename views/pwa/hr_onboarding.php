<?php
/**
 * PWA HR Onboarding - Mobile-native new employee onboarding
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$newUsers = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.role, u.created_at
        FROM users u
        WHERE u.is_active = 1 AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY u.created_at DESC LIMIT 20
    ");
    $stmt->execute();
    $newUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $newUsers = []; }
?>
<style>
.m-onboard { padding: 16px; font-family: Inter, sans-serif; }
.m-onboard-header { margin-bottom: 16px; }
.m-onboard-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-onboard-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-onboard-summary {
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border-radius: 16px; padding: 20px; margin-bottom: 16px;
    text-align: center;
}
.m-onboard-summary-label { font-size: 12px; color: rgba(255,255,255,0.7); }
.m-onboard-summary-value { font-size: 28px; font-weight: 700; color: #fff; margin-top: 4px; }
.m-section-title { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 12px; }
.m-onboard-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-onboard-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
}
.m-onboard-body { flex: 1; min-width: 0; }
.m-onboard-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-onboard-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-onboard-role {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    flex-shrink: 0;
}
.m-onboard-role-admin { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-onboard-role-coach { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-onboard-role-athlete { background: rgba(16,185,129,0.15); color: #10B981; }
.m-onboard-role-parent { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-onboard-role-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-onboard-checklist {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 20px;
}
.m-onboard-check-title { font-size: 13px; font-weight: 600; color: #fff; margin-bottom: 10px; }
.m-onboard-check-item {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 0; font-size: 12px; color: #A8A8B8;
}
.m-onboard-check-item i { font-size: 14px; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-onboard">
    <div class="m-onboard-header">
        <h2 class="m-onboard-title">Onboarding</h2>
        <p class="m-onboard-sub">New members in the last 30 days</p>
    </div>

    <div class="m-onboard-summary">
        <div class="m-onboard-summary-label">New Members (30 days)</div>
        <div class="m-onboard-summary-value"><?= count($newUsers) ?></div>
    </div>

    <div class="m-onboard-checklist">
        <div class="m-onboard-check-title"><i class="fas fa-clipboard-check" style="color:#8B5CF6;"></i> Onboarding Checklist</div>
        <div class="m-onboard-check-item"><i class="fas fa-circle" style="color:#10B981;"></i> Account created &amp; activated</div>
        <div class="m-onboard-check-item"><i class="fas fa-circle" style="color:#10B981;"></i> Role assigned</div>
        <div class="m-onboard-check-item"><i class="fas fa-circle" style="color:#F59E0B;"></i> Contract signed</div>
        <div class="m-onboard-check-item"><i class="fas fa-circle" style="color:#F59E0B;"></i> Equipment issued</div>
        <div class="m-onboard-check-item"><i class="fas fa-circle" style="color:#6B6B7B;"></i> First session scheduled</div>
    </div>

    <h3 class="m-section-title">Recent New Members</h3>
    <?php if (empty($newUsers)): ?>
        <div class="m-empty-state">
            <i class="fas fa-user-plus"></i>
            No new members in the last 30 days
        </div>
    <?php else: ?>
        <?php foreach ($newUsers as $nu):
            $role = strtolower($nu['role'] ?? 'default');
            $roleClass = match($role) {
                'admin' => 'admin',
                'coach', 'head_coach', 'team_coach', 'health_coach' => 'coach',
                'athlete' => 'athlete',
                'parent' => 'parent',
                default => 'default',
            };
            $initial = strtoupper(mb_substr($nu['first_name'] ?? '?', 0, 1));
            $fullName = htmlspecialchars(($nu['first_name'] ?? '') . ' ' . ($nu['last_name'] ?? ''));
        ?>
        <div class="m-onboard-card">
            <div class="m-onboard-avatar"><?= $initial ?></div>
            <div class="m-onboard-body">
                <div class="m-onboard-name"><?= $fullName ?></div>
                <div class="m-onboard-meta">
                    <i class="fas fa-calendar" style="font-size:10px;"></i> Joined <?= date('M j, Y', strtotime($nu['created_at'])) ?>
                </div>
            </div>
            <span class="m-onboard-role m-onboard-role-<?= $roleClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $role))) ?></span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
