<?php
/**
 * PWA Admin Email Reports - Mobile-native email logs
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$emails = [];
try {
    $stmt = $pdo->prepare("SELECT id, recipient, subject, status, sent_at FROM email_logs ORDER BY sent_at DESC LIMIT 20");
    $stmt->execute();
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $emails = []; }

function mEmailTimeAgo($datetime) {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $ts);
}
?>
<style>
.m-emaillogs { padding: 16px; font-family: Inter, sans-serif; }
.m-emaillogs-header { margin-bottom: 16px; }
.m-emaillogs-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-emaillogs-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-email-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-email-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.m-email-subject { font-size: 13px; font-weight: 600; color: #fff; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-email-badge { font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600; flex-shrink: 0; margin-left: 8px; }
.m-email-sent { background: rgba(16,185,129,0.15); color: #10B981; }
.m-email-failed { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-email-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-email-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-email-recipient { font-size: 12px; color: #A8A8B8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-email-time { font-size: 11px; color: #6B6B7B; margin-top: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-emaillogs">
    <div class="m-emaillogs-header">
        <h2 class="m-emaillogs-title">Email Logs</h2>
        <p class="m-emaillogs-sub"><?= count($emails) ?> recent email<?= count($emails) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($emails)): ?>
        <div class="m-empty-state">
            <i class="fas fa-envelope-open"></i>
            <p>No email logs found</p>
        </div>
    <?php else: ?>
        <?php foreach ($emails as $e):
            $status = strtolower($e['status'] ?? 'unknown');
            $badgeClass = match($status) {
                'sent', 'delivered', 'success' => 'sent',
                'failed', 'error', 'bounced' => 'failed',
                'pending', 'queued' => 'pending',
                default => 'default',
            };
        ?>
        <div class="m-email-card">
            <div class="m-email-top">
                <div class="m-email-subject"><?= htmlspecialchars($e['subject'] ?? 'No subject') ?></div>
                <span class="m-email-badge m-email-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <div class="m-email-recipient"><i class="fas fa-user" style="font-size:10px;color:#6B6B7B;margin-right:4px;"></i><?= htmlspecialchars($e['recipient'] ?? '') ?></div>
            <?php if (!empty($e['sent_at'])): ?>
            <div class="m-email-time"><?= mEmailTimeAgo($e['sent_at']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
