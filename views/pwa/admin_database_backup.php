<?php
/**
 * PWA Admin Database Backup - Mobile-native backup page
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}
?>
<style>
.m-backup { padding: 16px; font-family: Inter, sans-serif; }
.m-backup-header { margin-bottom: 16px; }
.m-backup-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-backup-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-backup-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 24px 20px; text-align: center;
}
.m-backup-icon {
    width: 56px; height: 56px; border-radius: 16px; margin: 0 auto 16px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(16,185,129,0.15); color: #10B981; font-size: 24px;
}
.m-backup-label { font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 8px; }
.m-backup-desc { font-size: 13px; color: #A8A8B8; margin-bottom: 20px; line-height: 1.5; }
.m-backup-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 24px; border-radius: 10px;
    background: rgba(107,70,193,0.15); color: #8B5CF6;
    font-size: 14px; font-weight: 600; text-decoration: none;
    min-height: 44px; font-family: Inter, sans-serif;
}
</style>

<div class="m-backup">
    <div class="m-backup-header">
        <h2 class="m-backup-title">Database Backup</h2>
        <p class="m-backup-sub">Create & manage backups</p>
    </div>

    <div class="m-backup-card">
        <div class="m-backup-icon"><i class="fas fa-download"></i></div>
        <div class="m-backup-label">Create Backup</div>
        <div class="m-backup-desc">
            Database backups should be run from the desktop interface to ensure reliable large file handling and download.
        </div>
        <a href="?page=admin_database_backup&desktop=1" class="m-backup-btn">
            <i class="fas fa-desktop"></i> Run Backup on Desktop
        </a>
    </div>
</div>
