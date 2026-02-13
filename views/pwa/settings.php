<?php
/**
 * PWA Settings - Mobile-native app settings
 * Purpose-built for mobile phones.
 */
?>
<style>
.m-settings { padding: 16px; font-family: Inter, sans-serif; }
.m-settings-header { margin-bottom: 16px; }
.m-settings-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-settings-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-settings-section { margin-bottom: 20px; }
.m-settings-section-title {
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 0 0 10px; padding: 0 4px;
}
.m-settings-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    overflow: hidden;
}
.m-settings-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px; min-height: 44px;
    border-bottom: 1px solid #2D2D3F;
}
.m-settings-item:last-child { border-bottom: none; }
.m-settings-item-left { display: flex; align-items: center; gap: 12px; }
.m-settings-item-icon { font-size: 16px; color: #8B5CF6; width: 20px; text-align: center; }
.m-settings-item-label { font-size: 14px; color: #fff; }
.m-settings-item-desc { font-size: 11px; color: #6B6B7B; margin-top: 2px; }
.m-settings-toggle {
    position: relative; width: 48px; height: 28px;
    background: #2D2D3F; border-radius: 14px; cursor: pointer;
    border: none; padding: 0; flex-shrink: 0;
}
.m-settings-toggle::after {
    content: ''; position: absolute; top: 3px; left: 3px;
    width: 22px; height: 22px; border-radius: 50%;
    background: #6B6B7B; transition: all 0.2s ease;
}
.m-settings-toggle.m-toggle-on { background: rgba(107,70,193,0.3); }
.m-settings-toggle.m-toggle-on::after { left: 23px; background: #8B5CF6; }
.m-settings-chevron { color: #6B6B7B; font-size: 14px; }
.m-settings-version {
    text-align: center; padding: 20px; color: #6B6B7B; font-size: 12px;
}
</style>

<div class="m-settings">
    <div class="m-settings-header">
        <h2 class="m-settings-title">Settings</h2>
        <p class="m-settings-sub">App preferences</p>
    </div>

    <div class="m-settings-section">
        <h3 class="m-settings-section-title">Display</h3>
        <div class="m-settings-card">
            <div class="m-settings-item">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-moon"></i></span>
                    <div>
                        <div class="m-settings-item-label">Dark Mode</div>
                        <div class="m-settings-item-desc">Always on for PWA</div>
                    </div>
                </div>
                <button class="m-settings-toggle m-toggle-on" onclick="this.classList.toggle('m-toggle-on')" type="button"></button>
            </div>
            <div class="m-settings-item">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-text-height"></i></span>
                    <div>
                        <div class="m-settings-item-label">Large Text</div>
                        <div class="m-settings-item-desc">Increase font sizes</div>
                    </div>
                </div>
                <button class="m-settings-toggle" onclick="this.classList.toggle('m-toggle-on')" type="button"></button>
            </div>
        </div>
    </div>

    <div class="m-settings-section">
        <h3 class="m-settings-section-title">Notifications</h3>
        <div class="m-settings-card">
            <div class="m-settings-item">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-bell"></i></span>
                    <div>
                        <div class="m-settings-item-label">Push Notifications</div>
                        <div class="m-settings-item-desc">Session reminders & updates</div>
                    </div>
                </div>
                <button class="m-settings-toggle m-toggle-on" onclick="this.classList.toggle('m-toggle-on')" type="button"></button>
            </div>
            <div class="m-settings-item">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-envelope"></i></span>
                    <div>
                        <div class="m-settings-item-label">Email Notifications</div>
                        <div class="m-settings-item-desc">Weekly digest & alerts</div>
                    </div>
                </div>
                <button class="m-settings-toggle m-toggle-on" onclick="this.classList.toggle('m-toggle-on')" type="button"></button>
            </div>
        </div>
    </div>

    <div class="m-settings-section">
        <h3 class="m-settings-section-title">Account</h3>
        <div class="m-settings-card">
            <a href="?page=profile" class="m-settings-item" style="text-decoration:none;">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-user"></i></span>
                    <div class="m-settings-item-label">Edit Profile</div>
                </div>
                <i class="fas fa-chevron-right m-settings-chevron"></i>
            </a>
            <a href="?page=notifications" class="m-settings-item" style="text-decoration:none;">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-bell"></i></span>
                    <div class="m-settings-item-label">Notifications</div>
                </div>
                <i class="fas fa-chevron-right m-settings-chevron"></i>
            </a>
        </div>
    </div>

    <div class="m-settings-version">
        Arctic Wolves PWA v1.0
    </div>
</div>
