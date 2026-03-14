<?php
/**
 * PWA Personal Development - Mobile-native hub
 * Links to development programs and my program views
 */
?>
<style>
.m-pdev { padding: 16px; font-family: Inter, sans-serif; }
.m-pdev-header { margin-bottom: 16px; }
.m-pdev-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-pdev-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-pdev-cards { display: flex; flex-direction: column; gap: 12px; }
.m-pdev-card {
    display: flex; align-items: center; gap: 14px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 18px 16px; text-decoration: none; min-height: 72px;
    transition: border-color 0.2s;
}
.m-pdev-card:active { border-color: #6B46C1; }
.m-pdev-card-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.m-pdev-card-body { flex: 1; min-width: 0; }
.m-pdev-card-label { font-size: 15px; font-weight: 600; color: #fff; margin-bottom: 2px; }
.m-pdev-card-desc { font-size: 12px; color: #A8A8B8; }
.m-pdev-card-arrow { color: #6B6B7B; font-size: 14px; flex-shrink: 0; }
</style>

<div class="m-pdev">
    <div class="m-pdev-header">
        <h2 class="m-pdev-title"><i class="fas fa-hockey-puck" style="color:#8B5CF6;margin-right:6px;"></i> Personal Development</h2>
        <p class="m-pdev-sub">Browse programs and track your progress</p>
    </div>

    <div class="m-pdev-cards">
        <a href="?page=personal_development_programs" class="m-pdev-card">
            <div class="m-pdev-card-icon" style="background:rgba(139,92,246,0.15);color:#8B5CF6;">
                <i class="fas fa-skating"></i>
            </div>
            <div class="m-pdev-card-body">
                <div class="m-pdev-card-label">Development Programs</div>
                <div class="m-pdev-card-desc">Browse and enroll in available programs</div>
            </div>
            <i class="fas fa-chevron-right m-pdev-card-arrow"></i>
        </a>

        <a href="?page=personal_development_my_program" class="m-pdev-card">
            <div class="m-pdev-card-icon" style="background:rgba(16,185,129,0.15);color:#10B981;">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="m-pdev-card-body">
                <div class="m-pdev-card-label">My Program</div>
                <div class="m-pdev-card-desc">View assigned drills, videos &amp; progress</div>
            </div>
            <i class="fas fa-chevron-right m-pdev-card-arrow"></i>
        </a>
    </div>
</div>
