<?php
/**
 * PWA Packages - Mobile-native packages list
 * Purpose-built for mobile phones.
 */

// Permission check — match desktop views/packages.php
if (!isset($_SESSION['user_id'])) {
    echo '<div style="text-align:center;padding:60px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:48px;display:block;margin-bottom:16px;opacity:0.5;"></i>';
    echo '<h3 style="color:#fff;">Access Denied</h3>';
    echo '<p style="font-size:14px;">Please log in to view packages.</p>';
    echo '</div>';
    return;
}

$packages = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, price, credits, valid_days, is_active, waitlist_only FROM packages WHERE is_active = 1 ORDER BY price ASC");
    $stmt->execute();
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback without waitlist_only column if migration hasn't run yet
    try {
        $stmt = $pdo->prepare("SELECT id, name, description, price, credits, valid_days, is_active FROM packages WHERE is_active = 1 ORDER BY price ASC");
        $stmt->execute();
        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) { $packages = []; }
}

// Check user waitlist status for packages
$userWaitlistPkgIds = [];
if (isset($_SESSION['user_id'])) {
    try {
        $wlStmt = $pdo->prepare("SELECT package_id FROM waitlists WHERE user_id = ? AND package_id IS NOT NULL AND status IN ('waiting','offered')");
        $wlStmt->execute([$_SESSION['user_id']]);
        $userWaitlistPkgIds = $wlStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {}
}
?>
<style>
.m-packages { padding: 16px; font-family: Inter, sans-serif; }
.m-packages-header { margin-bottom: 16px; }
.m-packages-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-packages-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-package-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 20px; margin-bottom: 12px;
}
.m-package-name { font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 6px; }
.m-package-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 14px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.m-package-details { display: flex; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.m-package-detail {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: #A8A8B8;
}
.m-package-detail i { font-size: 12px; color: #6B6B7B; }
.m-package-footer {
    display: flex; justify-content: space-between; align-items: center;
    padding-top: 14px; border-top: 1px solid #2D2D3F;
}
.m-package-price { font-size: 22px; font-weight: 700; color: #10B981; }
.m-package-price-sub { font-size: 11px; color: #6B6B7B; font-weight: 400; }
.m-package-buy {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 20px; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 13px; font-weight: 600;
    text-decoration: none; min-height: 44px;
    font-family: Inter, sans-serif; border: none; cursor: pointer;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-packages">
    <div class="m-packages-header">
        <h2 class="m-packages-title">Training Packages</h2>
        <p class="m-packages-sub"><?= count($packages) ?> package<?= count($packages) !== 1 ? 's' : '' ?> available</p>
    </div>

    <?php if (empty($packages)): ?>
        <div class="m-empty-state">
            <i class="fas fa-box-open"></i>
            <p>No packages available</p>
        </div>
    <?php else: ?>
        <?php foreach ($packages as $pkg): ?>
        <div class="m-package-card">
            <h3 class="m-package-name"><?= htmlspecialchars($pkg['name']) ?></h3>
            <?php if (!empty($pkg['description'])): ?>
            <p class="m-package-desc"><?= htmlspecialchars($pkg['description']) ?></p>
            <?php endif; ?>
            <div class="m-package-details">
                <?php if (!empty($pkg['credits'])): ?>
                <span class="m-package-detail">
                    <i class="fas fa-ticket"></i>
                    <?= (int)$pkg['credits'] ?> credit<?= (int)$pkg['credits'] !== 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($pkg['valid_days'])): ?>
                <span class="m-package-detail">
                    <i class="fas fa-clock"></i>
                    <?= (int)$pkg['valid_days'] ?> day<?= (int)$pkg['valid_days'] !== 1 ? 's' : '' ?> validity
                </span>
                <?php endif; ?>
            </div>
            <div class="m-package-footer">
                <div>
                    <span class="m-package-price">$<?= number_format((float)$pkg['price'], 2) ?></span>
                    <?php if (!empty($pkg['credits']) && (float)$pkg['price'] > 0): ?>
                    <div class="m-package-price-sub">$<?= number_format((float)$pkg['price'] / (int)$pkg['credits'], 2) ?>/credit</div>
                    <?php endif; ?>
                </div>
                <?php
                    $isWaitlistOnly = !empty($pkg['waitlist_only']);
                    $isOnWaitlist = in_array($pkg['id'], $userWaitlistPkgIds);
                ?>
                <?php if ($isOnWaitlist): ?>
                    <span style="font-size:12px;font-weight:600;color:#F59E0B;"><i class="fas fa-clock"></i> On Waitlist</span>
                <?php elseif ($isWaitlistOnly): ?>
                    <button type="button" class="m-package-buy" style="background:#F59E0B;" onclick="mJoinPackageWaitlist(<?= (int)$pkg['id'] ?>)">
                        <i class="fas fa-clock"></i> Join Waitlist
                    </button>
                <?php else: ?>
                <form action="process_purchase_package.php" method="POST" style="display:inline;">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="package_id" value="<?= (int)$pkg['id'] ?>">
                    <button type="submit" class="m-package-buy">
                        <i class="fas fa-cart-plus"></i> Purchase
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<script>
function mJoinPackageWaitlist(packageId) {
    var csrfToken = document.querySelector('input[name="csrf_token"]');
    var form = new FormData();
    form.append('action', 'join_waitlist');
    form.append('package_id', packageId);
    if (csrfToken) form.append('csrf_token', csrfToken.value);
    fetch('process_booking.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { if (typeof persistToast === 'function') persistToast(data.message, 'success'); location.reload(); }
            else { if (typeof showToast === 'function') showToast(data.message || 'Failed to join waitlist', 'error'); else alert(data.message); }
        })
        .catch(function() { if (typeof showToast === 'function') showToast('Network error', 'error'); else alert('Network error'); });
}
</script>
