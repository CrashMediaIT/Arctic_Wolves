<?php
/**
 * PWA IHS Import - Mobile-native IHS import tool
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}
?>
<style>
.m-ihsimp { padding: 16px; font-family: Inter, sans-serif; }
.m-ihsimp-header { margin-bottom: 16px; }
.m-ihsimp-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-ihsimp-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-ihsimp-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 24px 20px; text-align: center;
}
.m-ihsimp-icon {
    width: 56px; height: 56px; border-radius: 16px; margin: 0 auto 16px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(59,130,246,0.15); color: #3B82F6; font-size: 24px;
}
.m-ihsimp-label { font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 8px; }
.m-ihsimp-desc { font-size: 13px; color: #A8A8B8; margin-bottom: 20px; line-height: 1.5; }
.m-ihsimp-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 24px; border-radius: 10px;
    background: rgba(107,70,193,0.15); color: #8B5CF6;
    font-size: 14px; font-weight: 600; text-decoration: none;
    min-height: 44px; font-family: Inter, sans-serif;
}
</style>

<div class="m-ihsimp">
    <div class="m-ihsimp-header">
        <h2 class="m-ihsimp-title">IHS Import</h2>
        <p class="m-ihsimp-sub">Import IHS data</p>
    </div>

    <div class="m-ihsimp-card">
        <div class="m-ihsimp-icon"><i class="fas fa-file-medical"></i></div>
        <div class="m-ihsimp-label">IHS Data Import</div>
        <div class="m-ihsimp-desc">
            IHS data import requires file uploads and data mapping best performed on a desktop browser for accuracy.
        </div>
        <a href="?page=ihs_import&desktop=1" class="m-ihsimp-btn">
            <i class="fas fa-desktop"></i> Run Import on Desktop
        </a>
    </div>
</div>
