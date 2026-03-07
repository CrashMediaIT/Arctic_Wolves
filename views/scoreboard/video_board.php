<?php
/**
 * Video Board View
 * Full-screen video/presentation display for arena.
 * Sources: pregame hype video, in-arena cam + mic, browser video URL.
 * Switch back to scoreboard via button.
 */
?>
<div class="sb-video-board">
    <div class="sb-video-topbar">
        <div class="sb-topbar-brand">
            <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Arctic Wolves">
            <span>Video Board</span>
        </div>
        <div class="sb-topbar-actions">
            <a href="?view=scoreboard" class="sb-btn"><i class="fas fa-tachometer-alt"></i> Back to Scoreboard</a>
            <a href="?view=scoresheet" class="sb-btn"><i class="fas fa-clipboard-list"></i> Scoresheet</a>
            <span class="sb-clock" id="sbClock"></span>
        </div>
    </div>

    <!-- Video Display Area -->
    <div class="sb-video-container" id="sbVideoContainer">
        <div style="text-align:center;color:#555;">
            <i class="fas fa-tv" style="font-size:64px;margin-bottom:16px;display:block;"></i>
            <p>Select a video source below</p>
        </div>
    </div>

    <!-- Video Source Selector -->
    <div class="sb-video-source-bar">
        <button class="sb-video-source-btn" onclick="sbLoadVideo('pregame')" data-source="pregame">
            <i class="fas fa-fire"></i> Pregame Hype
        </button>
        <button class="sb-video-source-btn" onclick="sbLoadVideo('ingame_promo')" data-source="ingame_promo">
            <i class="fas fa-ad"></i> In-Game Promo
        </button>
        <button class="sb-video-source-btn" onclick="sbLoadVideo('arena_cam')" data-source="arena_cam">
            <i class="fas fa-video"></i> Arena Cam + Mic
        </button>
        <button class="sb-video-source-btn" onclick="sbLoadVideo('broadcast')" data-source="broadcast">
            <i class="fas fa-satellite-dish"></i> Broadcast Feed
        </button>
        <button class="sb-video-source-btn" onclick="sbShowBrowserVideoModal()" data-source="browser">
            <i class="fas fa-globe"></i> Browser Video (URL)
        </button>
        <button class="sb-video-source-btn" onclick="sbStopVideo()">
            <i class="fas fa-stop"></i> Stop
        </button>
    </div>
</div>

<!-- Browser Video URL Modal -->
<div class="sb-modal-overlay" id="sb-browser-video-modal">
    <div class="sb-modal">
        <h2><i class="fas fa-globe"></i> Browser Video Source</h2>
        <form onsubmit="return sbLoadBrowserVideo(event)">
            <label for="sb-video-url">Video URL (YouTube, Vimeo, or direct URL)</label>
            <input type="url" id="sb-video-url" name="video_url" placeholder="https://www.youtube.com/watch?v=..." required>
            <div class="sb-modal-actions">
                <button type="button" class="sb-btn" onclick="document.getElementById('sb-browser-video-modal').classList.remove('active')">Cancel</button>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-play"></i> Load Video</button>
            </div>
        </form>
    </div>
</div>
