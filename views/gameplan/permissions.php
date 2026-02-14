<?php
/**
 * Game Plan - Permissions View
 * Manage video editing access (admin only).
 */
if (!$isAdmin) {
    echo '<div class="gp-empty"><i class="fas fa-lock"></i><p>Admin access required</p></div>';
    return;
}
?>
<div class="gp-page-header">
    <h1 class="gp-page-title"><i class="fas fa-user-shield"></i> Permissions</h1>
    <p class="gp-page-desc">Manage video editing access</p>
</div>

<div class="gp-section">
    <div class="gp-placeholder-card">
        <i class="fas fa-user-shield"></i>
        <h3>Video Access Permissions</h3>
        <p>Control who can view, edit, and manage video content.<br>Set role-based permissions for coaches, athletes, and team staff.</p>
    </div>
</div>
