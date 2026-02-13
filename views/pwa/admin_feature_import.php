<?php
/**
 * PWA Admin Feature Import - Mobile-native feature import tool
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$lastImport = null;
try {
    $stmt = $pdo->prepare("SELECT created_at FROM audit_logs WHERE action LIKE '%import%' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $lastImport = $row['created_at'];
} catch (PDOException $e) { /* silent */ }
?>
<style>
.m-featimp { padding: 16px; font-family: Inter, sans-serif; }
.m-featimp-header { margin-bottom: 16px; }
.m-featimp-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-featimp-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-featimp-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 24px 20px; text-align: center;
}
.m-featimp-icon {
    width: 56px; height: 56px; border-radius: 16px; margin: 0 auto 16px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(16,185,129,0.15); color: #10B981; font-size: 24px;
}
.m-featimp-label { font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 8px; }
.m-featimp-desc { font-size: 13px; color: #A8A8B8; margin-bottom: 16px; line-height: 1.5; }
.m-featimp-last { font-size: 12px; color: #6B6B7B; margin-bottom: 20px; }
.m-featimp-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 24px; border-radius: 10px;
    background: rgba(107,70,193,0.15); color: #8B5CF6;
    font-size: 14px; font-weight: 600; text-decoration: none;
    min-height: 44px; font-family: Inter, sans-serif;
}
</style>

<div class="m-featimp">
    <div class="m-featimp-header">
        <h2 class="m-featimp-title">Feature Import</h2>
        <p class="m-featimp-sub">Import features & data</p>
    </div>

    <div class="m-featimp-card">
        <div class="m-featimp-icon"><i class="fas fa-file-import"></i></div>
        <div class="m-featimp-label">Import Features</div>
        <div class="m-featimp-desc">
            Feature imports require file uploads and validation best handled on a desktop browser.
        </div>
        <?php if ($lastImport): ?>
        <div class="m-featimp-last"><i class="fas fa-clock"></i> Last import: <?= htmlspecialchars(date('M j, Y g:ia', strtotime($lastImport))) ?></div>
        <?php endif; ?>
        <a href="?page=admin_feature_import&desktop=1" class="m-featimp-btn">
            <i class="fas fa-desktop"></i> Import on Desktop
        </a>
    </div>
</div>
