<?php
/**
 * PWA Admin Database Tools - Mobile-native database tools hub
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$tools = [
    ['icon' => 'fa-download', 'label' => 'Database Backup', 'desc' => 'Create a full database backup', 'page' => 'admin_database_backup', 'color' => '#10B981'],
    ['icon' => 'fa-upload', 'label' => 'Database Restore', 'desc' => 'Restore from a previous backup', 'page' => 'admin_database_restore', 'color' => '#F59E0B'],
    ['icon' => 'fa-heartbeat', 'label' => 'System Check', 'desc' => 'Check system health and status', 'page' => 'admin_system_check', 'color' => '#3B82F6'],
];
?>
<style>
.m-dbtools { padding: 16px; font-family: Inter, sans-serif; }
.m-dbtools-header { margin-bottom: 16px; }
.m-dbtools-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-dbtools-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-dbtool-card {
    display: flex; align-items: center; gap: 14px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 16px; margin-bottom: 10px; text-decoration: none; min-height: 60px;
    transition: border-color 0.2s;
}
.m-dbtool-card:active { border-color: #6B46C1; }
.m-dbtool-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.m-dbtool-body { flex: 1; min-width: 0; }
.m-dbtool-label { font-size: 14px; font-weight: 600; color: #fff; }
.m-dbtool-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-dbtool-arrow { color: #6B6B7B; font-size: 14px; flex-shrink: 0; }
.m-dbtool-btn {
    display: flex; align-items: center; gap: 14px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 16px; margin-bottom: 10px; min-height: 60px;
    transition: border-color 0.2s; width: 100%; cursor: pointer;
    -webkit-appearance: none; appearance: none;
}
.m-dbtool-btn:active { border-color: #6B46C1; }
.m-dbtool-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.m-schema-status { font-size: 12px; color: #A8A8B8; margin-top: 8px; padding: 10px; border-radius: 10px; display: none; }
.m-schema-status.m-show { display: block; }
.m-schema-status.m-ok { background: #10B98120; color: #10B981; }
.m-schema-status.m-err { background: #EF444420; color: #EF4444; }
</style>

<div class="m-dbtools">
    <div class="m-dbtools-header">
        <h2 class="m-dbtools-title">Database Tools</h2>
        <p class="m-dbtools-sub">Backup, restore & diagnostics</p>
    </div>

    <?php foreach ($tools as $t): ?>
    <a href="?page=<?= htmlspecialchars($t['page']) ?>" class="m-dbtool-card">
        <div class="m-dbtool-icon" style="background:<?= $t['color'] ?>20;color:<?= $t['color'] ?>;">
            <i class="fas <?= $t['icon'] ?>"></i>
        </div>
        <div class="m-dbtool-body">
            <div class="m-dbtool-label"><?= htmlspecialchars($t['label']) ?></div>
            <div class="m-dbtool-desc"><?= htmlspecialchars($t['desc']) ?></div>
        </div>
        <div class="m-dbtool-arrow"><i class="fas fa-chevron-right"></i></div>
    </a>
    <?php endforeach; ?>

    <!-- Rebuild Schema action button -->
    <button type="button" class="m-dbtool-btn" id="m-btn-rebuild-schema" onclick="mRebuildSchema(this)">
        <div class="m-dbtool-icon" style="background:#8B5CF620;color:#8B5CF6;">
            <i class="fas fa-sync-alt"></i>
        </div>
        <div class="m-dbtool-body">
            <div class="m-dbtool-label">Rebuild Schema</div>
            <div class="m-dbtool-desc">Create missing tables and add missing columns</div>
        </div>
        <div class="m-dbtool-arrow"><i class="fas fa-chevron-right"></i></div>
    </button>
    <div id="m-schema-status" class="m-schema-status"></div>
</div>

<script>
async function mRebuildSchema(btn) {
    if (!confirm('This will check the database schema and create any missing tables or columns. Continue?')) return;
    const origHTML = btn.querySelector('.m-dbtool-label').textContent;
    btn.disabled = true;
    btn.querySelector('.m-dbtool-label').textContent = 'Rebuilding...';
    btn.querySelector('.m-dbtool-icon i').className = 'fas fa-spinner fa-spin';
    const statusEl = document.getElementById('m-schema-status');
    statusEl.className = 'm-schema-status';
    statusEl.style.display = 'none';
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const resp = await fetch('process_database_backup.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=rebuild_schema&csrf_token=' + encodeURIComponent(csrf)
        });
        const data = await resp.json();
        statusEl.textContent = data.message || (data.success ? 'Schema rebuild complete' : 'Schema rebuild failed');
        statusEl.className = 'm-schema-status m-show ' + (data.success ? 'm-ok' : 'm-err');
    } catch (e) {
        statusEl.textContent = 'Network error: ' + (e.message || 'Failed to rebuild schema');
        statusEl.className = 'm-schema-status m-show m-err';
    }
    btn.disabled = false;
    btn.querySelector('.m-dbtool-label').textContent = origHTML;
    btn.querySelector('.m-dbtool-icon i').className = 'fas fa-sync-alt';
}
</script>
