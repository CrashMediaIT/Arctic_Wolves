<?php
/**
 * PWA Audit Log - Mobile-native audit log viewer
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$logs = [];
try {
    $stmt = $pdo->prepare("SELECT al.id, al.action, al.action_type, al.table_name, al.record_id, al.description, al.changes, al.old_values, al.new_values, al.user_id, al.ip_address, al.created_at, u.first_name as user_first_name, u.last_name as user_last_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 30");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (class_exists('FieldEncryption')) {
        foreach ($logs as &$lr) {
            try {
                $lr['user_first_name'] = FieldEncryption::decrypt($lr['user_first_name'] ?? '');
                $lr['user_last_name'] = FieldEncryption::decrypt($lr['user_last_name'] ?? '');
            } catch (Exception $e) {
                $lr['user_first_name'] = $lr['user_first_name'] ?? '';
                $lr['user_last_name'] = $lr['user_last_name'] ?? '';
            }
            $lr['user_name'] = trim(($lr['user_first_name'] ?? '') . ' ' . ($lr['user_last_name'] ?? ''));
        }
        unset($lr);
    }
} catch (PDOException $e) { $logs = []; }

function mAuditTimeAgo($datetime) {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $ts);
}

function mAuditIcon($action_type) {
    switch ($action_type) {
        case 'INSERT': return 'fa-plus-circle';
        case 'UPDATE': return 'fa-pen';
        case 'DELETE': return 'fa-trash';
        case 'AUTH': return 'fa-sign-in-alt';
        case 'VIEW': return 'fa-eye';
        default: return 'fa-shield-alt';
    }
}

function mAuditColor($action_type) {
    switch ($action_type) {
        case 'INSERT': return 'rgba(16,185,129,0.15)'; // green
        case 'UPDATE': return 'rgba(59,130,246,0.15)'; // blue
        case 'DELETE': return 'rgba(239,68,68,0.15)'; // red
        default: return 'rgba(59,130,246,0.15)';
    }
}

function mAuditIconColor($action_type) {
    switch ($action_type) {
        case 'INSERT': return '#10B981';
        case 'UPDATE': return '#3B82F6';
        case 'DELETE': return '#EF4444';
        default: return '#3B82F6';
    }
}

function mAuditChangesSummary($log) {
    if (!empty($log['changes'])) {
        $changes = json_decode($log['changes'], true);
        if (is_array($changes)) {
            $parts = [];
            foreach ($changes as $key => $val) {
                if ($key === 'action') continue;
                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        $parts[] = str_replace('_', ' ', $k) . ': ' . (is_string($v) ? $v : json_encode($v));
                    }
                } else {
                    $parts[] = str_replace('_', ' ', $key) . ': ' . (is_string($val) ? $val : json_encode($val));
                }
            }
            return implode(', ', array_slice($parts, 0, 3)) . (count($parts) > 3 ? '…' : '');
        }
    }
    return '';
}
?>
<style>
.m-audit { padding: 16px; font-family: Inter, sans-serif; }
.m-audit-header { margin-bottom: 16px; }
.m-audit-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-audit-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-audit-card {
    display: flex; align-items: flex-start; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px; cursor: pointer;
}
.m-audit-card.expanded .m-audit-detail { display: block; }
.m-audit-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}
.m-audit-body { flex: 1; min-width: 0; }
.m-audit-action { font-size: 13px; font-weight: 600; color: #fff; }
.m-audit-table { font-size: 11px; color: #8B5CF6; margin-top: 1px; }
.m-audit-changes { font-size: 12px; color: #A8A8B8; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-audit-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-audit-meta { font-size: 11px; color: #6B6B7B; margin-top: 4px; display: flex; gap: 10px; flex-wrap: wrap; }
.m-audit-detail {
    display: none; margin-top: 10px; padding-top: 10px;
    border-top: 1px solid #2D2D3F; font-size: 12px;
}
.m-audit-detail pre {
    padding: 8px; border-radius: 8px; overflow-x: auto;
    font-size: 11px; max-height: 150px; margin: 4px 0 8px;
    white-space: pre-wrap; word-break: break-word;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-audit">
    <div class="m-audit-header">
        <h2 class="m-audit-title">Audit Log</h2>
        <p class="m-audit-sub">Last <?= count($logs) ?> entr<?= count($logs) !== 1 ? 'ies' : 'y' ?></p>
    </div>

    <?php if (empty($logs)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-list"></i>
            <p>No audit logs found</p>
        </div>
    <?php else: ?>
        <?php foreach ($logs as $log):
            $action_type = $log['action_type'] ?? strtoupper($log['action'] ?? '');
            $changes_summary = mAuditChangesSummary($log);
            $has_detail = !empty($log['changes']) || !empty($log['old_values']) || !empty($log['new_values']) || !empty($log['description']);
        ?>
        <div class="m-audit-card" <?php if ($has_detail): ?>tabindex="0" role="button" aria-expanded="false" onclick="this.classList.toggle('expanded');this.setAttribute('aria-expanded',this.classList.contains('expanded'))" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click()}"<?php endif; ?>>
            <div class="m-audit-icon" style="background: <?= mAuditColor($action_type) ?>; color: <?= mAuditIconColor($action_type) ?>;">
                <i class="fas <?= mAuditIcon($action_type) ?>"></i>
            </div>
            <div class="m-audit-body">
                <div class="m-audit-action"><?= htmlspecialchars($action_type) ?><?php if (!empty($log['action']) && $log['action'] !== strtolower($action_type)): ?> <span style="font-weight:400;color:#A8A8B8;">— <?= htmlspecialchars($log['action']) ?></span><?php endif; ?></div>
                <?php if (!empty($log['table_name'])): ?>
                <div class="m-audit-table"><i class="fas fa-database" style="margin-right:3px;"></i> <?= htmlspecialchars($log['table_name']) ?><?php if (!empty($log['record_id'])): ?> #<?= (int)$log['record_id'] ?><?php endif; ?></div>
                <?php endif; ?>
                <?php if (!empty($changes_summary)): ?>
                <div class="m-audit-changes"><?= htmlspecialchars($changes_summary) ?></div>
                <?php elseif (!empty($log['description'])): ?>
                <div class="m-audit-desc"><?= htmlspecialchars($log['description']) ?></div>
                <?php endif; ?>
                <div class="m-audit-meta">
                    <span><i class="fas fa-clock"></i> <?= mAuditTimeAgo($log['created_at']) ?></span>
                    <?php if (!empty($log['ip_address'])): ?>
                    <span><i class="fas fa-globe"></i> <?= htmlspecialchars($log['ip_address']) ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-user"></i> <?= htmlspecialchars($log['user_name'] ?? '#' . (int)$log['user_id']) ?></span>
                </div>
                <?php if ($has_detail): ?>
                <div class="m-audit-detail">
                    <?php if (!empty($log['old_values'])):
                        $old = json_decode($log['old_values'], true);
                    ?>
                    <div style="color:#EF4444;font-weight:600;margin-bottom:2px;">Old Values:</div>
                    <pre style="background:rgba(239,68,68,0.1);"><?= htmlspecialchars(json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                    <?php endif; ?>
                    <?php if (!empty($log['new_values'])):
                        $new = json_decode($log['new_values'], true);
                    ?>
                    <div style="color:#10B981;font-weight:600;margin-bottom:2px;">New Values:</div>
                    <pre style="background:rgba(16,185,129,0.1);"><?= htmlspecialchars(json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                    <?php endif; ?>
                    <?php if (!empty($log['changes']) && empty($log['old_values']) && empty($log['new_values'])):
                        $changes = json_decode($log['changes'], true);
                    ?>
                    <div style="color:#3B82F6;font-weight:600;margin-bottom:2px;">Changes:</div>
                    <pre style="background:rgba(59,130,246,0.1);"><?= htmlspecialchars(json_encode($changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
