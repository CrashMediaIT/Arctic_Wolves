<?php
/**
 * PWA Profile - Mobile-native user profile view
 * Purpose-built for mobile phones.
 */

$profile = null;
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone, role, profile_image_url, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $profile = null; }

if (!$profile) {
    $profile = [
        'first_name' => 'Unknown',
        'last_name' => 'User',
        'email' => '',
        'phone' => '',
        'role' => $user_role ?? 'user',
        'profile_image_url' => '',
        'created_at' => null,
    ];
}

$initials = strtoupper(mb_substr($profile['first_name'], 0, 1) . mb_substr($profile['last_name'], 0, 1));
$fullName = $profile['first_name'] . ' ' . $profile['last_name'];
$memberSince = $profile['created_at'] ? date('F Y', strtotime($profile['created_at'])) : 'N/A';

$roleBadgeColors = [
    'admin' => ['#EF4444', 'rgba(239,68,68,0.15)'],
    'coach' => ['#8B5CF6', 'rgba(139,92,246,0.15)'],
    'head_coach' => ['#8B5CF6', 'rgba(139,92,246,0.15)'],
    'team_coach' => ['#8B5CF6', 'rgba(139,92,246,0.15)'],
    'health_coach' => ['#10B981', 'rgba(16,185,129,0.15)'],
    'athlete' => ['#3B82F6', 'rgba(59,130,246,0.15)'],
    'parent' => ['#F59E0B', 'rgba(245,158,11,0.15)'],
    'front_desk' => ['#A8A8B8', 'rgba(168,168,184,0.15)'],
];
$roleColor = $roleBadgeColors[$profile['role']][0] ?? '#A8A8B8';
$roleBg = $roleBadgeColors[$profile['role']][1] ?? 'rgba(168,168,184,0.15)';
?>
<style>
.m-profile { padding: 16px; font-family: Inter, sans-serif; }
.m-profile-hero { text-align: center; padding: 24px 0 20px; }
.m-profile-avatar {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 28px; font-weight: 700; color: #fff;
    margin-bottom: 12px; overflow: hidden;
}
.m-profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
.m-profile-name { font-size: 20px; font-weight: 700; color: #fff; margin: 0; }
.m-profile-role {
    display: inline-block; margin-top: 8px;
    padding: 4px 12px; border-radius: 6px;
    font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
}
.m-profile-section { margin-bottom: 16px; }
.m-profile-section-title { font-size: 13px; font-weight: 600; color: #6B6B7B; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 8px; padding: 0 4px; }
.m-profile-field {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
    display: flex; align-items: center; gap: 12px;
    min-height: 44px;
}
.m-profile-field-icon { color: #6B6B7B; font-size: 14px; width: 20px; text-align: center; flex-shrink: 0; }
.m-profile-field-body { flex: 1; min-width: 0; }
.m-profile-field-label { font-size: 11px; color: #6B6B7B; }
.m-profile-field-value { font-size: 14px; color: #fff; font-weight: 500; margin-top: 1px; }
.m-profile-actions { display: flex; flex-direction: column; gap: 8px; margin-top: 20px; }
.m-profile-action {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 14px; border-radius: 12px;
    font-size: 14px; font-weight: 600; text-decoration: none;
    min-height: 44px; border: none; cursor: pointer;
    font-family: Inter, sans-serif;
}
.m-profile-action-primary { background: #6B46C1; color: #fff; }
.m-profile-action-secondary { background: #16161F; border: 1px solid #2D2D3F; color: #A8A8B8; }
</style>

<div class="m-profile">
    <div class="m-profile-hero">
        <div class="m-profile-avatar">
            <?php if (!empty($profile['profile_image_url'])): ?>
                <img src="<?= htmlspecialchars($profile['profile_image_url']) ?>" alt="Profile">
            <?php else: ?>
                <?= $initials ?>
            <?php endif; ?>
        </div>
        <h2 class="m-profile-name"><?= htmlspecialchars($fullName) ?></h2>
        <span class="m-profile-role" style="background:<?= $roleBg ?>;color:<?= $roleColor ?>;">
            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $profile['role']))) ?>
        </span>
    </div>

    <div class="m-profile-section">
        <h3 class="m-profile-section-title">Contact Info</h3>
        <div class="m-profile-field">
            <span class="m-profile-field-icon"><i class="fas fa-envelope"></i></span>
            <div class="m-profile-field-body">
                <div class="m-profile-field-label">Email</div>
                <div class="m-profile-field-value"><?= htmlspecialchars($profile['email'] ?: 'Not set') ?></div>
            </div>
        </div>
        <div class="m-profile-field">
            <span class="m-profile-field-icon"><i class="fas fa-phone"></i></span>
            <div class="m-profile-field-body">
                <div class="m-profile-field-label">Phone</div>
                <div class="m-profile-field-value"><?= htmlspecialchars($profile['phone'] ?: 'Not set') ?></div>
            </div>
        </div>
    </div>

    <div class="m-profile-section">
        <h3 class="m-profile-section-title">Account</h3>
        <div class="m-profile-field">
            <span class="m-profile-field-icon"><i class="fas fa-calendar"></i></span>
            <div class="m-profile-field-body">
                <div class="m-profile-field-label">Member Since</div>
                <div class="m-profile-field-value"><?= htmlspecialchars($memberSince) ?></div>
            </div>
        </div>
    </div>

    <div class="m-profile-actions">
        <a href="?page=profile&edit=1" class="m-profile-action m-profile-action-primary">
            <i class="fas fa-pen"></i> Edit Profile
        </a>
        <a href="?page=profile&change_password=1" class="m-profile-action m-profile-action-secondary">
            <i class="fas fa-lock"></i> Change Password
        </a>
    </div>
</div>
