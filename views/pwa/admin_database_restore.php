<?php
/**
 * PWA Admin Database Restore - Mobile-native restore page
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}
?>
<style>
.m-restore { padding: 16px; font-family: Inter, sans-serif; }
.m-restore-header { margin-bottom: 16px; }
.m-restore-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-restore-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-restore-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 24px 20px; text-align: center;
}
.m-restore-icon {
    width: 56px; height: 56px; border-radius: 16px; margin: 0 auto 16px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(245,158,11,0.15); color: #F59E0B; font-size: 24px;
}
.m-restore-label { font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 8px; }
.m-restore-warn {
    font-size: 13px; color: #EF4444; margin-bottom: 12px;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.m-restore-desc { font-size: 13px; color: #A8A8B8; margin-bottom: 20px; line-height: 1.5; }
.m-restore-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 24px; border-radius: 10px;
    background: rgba(107,70,193,0.15); color: #8B5CF6;
    font-size: 14px; font-weight: 600; text-decoration: none;
    min-height: 44px; font-family: Inter, sans-serif;
}
</style>

<div class="m-restore">
    <div class="m-restore-header">
        <h2 class="m-restore-title">Database Restore</h2>
        <p class="m-restore-sub">Restore from backup</p>
    </div>

    <div class="m-restore-card">
        <div class="m-restore-icon"><i class="fas fa-upload"></i></div>
        <div class="m-restore-label">Restore Database</div>
        <div class="m-restore-warn">
            <i class="fas fa-exclamation-triangle"></i> This action will overwrite current data
        </div>
        <div class="m-restore-desc">
            Database restore requires file upload and confirmation steps best handled on a desktop browser.
        </div>
        <a href="?page=admin_database_restore&desktop=1" class="m-restore-btn">
            <i class="fas fa-desktop"></i> Manage on Desktop
        </a>
    </div>
</div>
