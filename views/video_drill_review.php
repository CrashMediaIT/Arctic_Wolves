<!-- Player Drill Video Review View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-video"></i> Drill Video Reviews
    </h1>
    <p class="page-description">View and review your drill performance videos</p>
</div>

<div class="video-content">
    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-group">
            <select class="form-input-small">
                <option>All Videos</option>
                <option>Not Reviewed</option>
                <option>Reviewed</option>
                <option>Flagged</option>
            </select>
            <select class="form-input-small">
                <option>All Drills</option>
                <option>Skating</option>
                <option>Shooting</option>
                <option>Passing</option>
                <option>Stickhandling</option>
            </select>
            <input type="text" class="form-input-small" placeholder="Search videos...">
        </div>
    </div>

    <!-- Video Grid -->
    <div class="video-grid">
        <!-- Sample Video Card -->
        <div class="video-card">
            <div class="video-thumbnail">
                <div class="video-placeholder">
                    <i class="fas fa-play-circle"></i>
                </div>
                <span class="video-duration">2:35</span>
                <span class="video-status reviewed"><i class="fas fa-check-circle"></i></span>
            </div>
            <div class="video-info">
                <h4 class="video-title">Crossover Drill - Session #23</h4>
                <div class="video-meta">
                    <span><i class="fas fa-calendar"></i> Jan 15, 2024</span>
                    <span><i class="fas fa-user"></i> Coach Smith</span>
                </div>
                <div class="video-rating">
                    <span class="rating-label">Rating:</span>
                    <div class="stars">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="far fa-star"></i>
                    </div>
                </div>
            </div>
            <div class="video-actions">
                <button class="btn-primary btn-full"><i class="fas fa-play"></i> Watch & Review</button>
            </div>
        </div>

        <div class="placeholder-container">
            <i class="fas fa-video placeholder-icon"></i>
            <p class="placeholder-text">No drill videos available yet. Your coach will upload videos after your sessions.</p>
        </div>
    </div>
</div>

<!-- Video Modal (Hidden by default) -->
<div class="video-modal" id="videoModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-video"></i> Video Review</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="video-player-container">
                <div class="video-player-placeholder">
                    <i class="fas fa-play-circle"></i>
                    <p>Video Player</p>
                </div>
            </div>
            <div class="video-review-section">
                <h4>Coach's Review</h4>
                <div class="coach-comments">
                    <p class="placeholder-text">Coach comments will appear here.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.video-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.video-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
}

.video-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(255, 77, 0, 0.1);
    border-color: var(--neon);
}

.video-thumbnail {
    position: relative;
    width: 100%;
    padding-top: 56.25%; /* 16:9 aspect ratio */
    background: var(--bg-main);
    overflow: hidden;
}

.video-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
}

.video-placeholder i {
    font-size: 48px;
    color: var(--neon);
    opacity: 0.5;
}

.video-duration {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(0, 0, 0, 0.8);
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.video-status {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.video-status.reviewed {
    background: #10b981;
    color: #fff;
}

.video-status.pending {
    background: var(--accent);
    color: #fff;
}

.video-info {
    padding: 15px;
}

.video-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}

.video-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.video-meta span {
    font-size: 12px;
    color: var(--text-dim);
}

.video-meta i {
    color: var(--neon);
    margin-right: 5px;
}

.video-rating {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rating-label {
    font-size: 12px;
    color: var(--text-dim);
}

.stars {
    display: flex;
    gap: 3px;
}

.stars i {
    color: var(--accent);
    font-size: 14px;
}

.video-actions {
    padding: 15px;
    border-top: 1px solid var(--border);
}

.video-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
}

.modal-content {
    position: relative;
    width: 90%;
    max-width: 1200px;
    margin: 50px auto;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    font-size: 20px;
    font-weight: 700;
}

.modal-header h3 i {
    color: var(--neon);
    margin-right: 10px;
}

.modal-close {
    width: 40px;
    height: 40px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-white);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.modal-close:hover {
    background: var(--neon);
    border-color: var(--neon);
}

.modal-body {
    padding: 20px;
}

.video-player-container {
    margin-bottom: 20px;
}

.video-player-placeholder {
    width: 100%;
    padding-top: 56.25%;
    background: var(--bg-main);
    position: relative;
    border-radius: 8px;
    overflow: hidden;
}

.video-player-placeholder i {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 64px;
    color: var(--neon);
    opacity: 0.3;
}

.video-review-section h4 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 15px;
}

.coach-comments {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
}

.placeholder-icon {
    font-size: 64px;
    color: var(--neon);
    opacity: 0.3;
    display: block;
    text-align: center;
    margin-bottom: 20px;
}
</style>
