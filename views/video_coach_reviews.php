<!-- Coach Review Videos View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-video"></i> Coach Review Videos
    </h1>
    <p class="page-description">Upload and manage athlete review videos</p>
</div>

<div class="coach-video-content">
    <!-- Action Bar -->
    <div class="action-bar">
        <button class="btn-primary btn-lg"><i class="fas fa-upload"></i> Upload Video</button>
        <div class="filter-group">
            <select class="form-input-small">
                <option>All Athletes</option>
                <!-- Athletes will be populated here -->
            </select>
            <select class="form-input-small">
                <option>All Sessions</option>
                <option>Today</option>
                <option>This Week</option>
                <option>This Month</option>
            </select>
        </div>
    </div>

    <!-- Upload Section (Initially Hidden) -->
    <div class="upload-section" id="uploadSection" style="display: none;">
        <div class="upload-card">
            <h3><i class="fas fa-cloud-upload-alt"></i> Upload Review Video</h3>
            
            <form class="upload-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Athlete</label>
                        <select class="form-input" required>
                            <option value="">-- Select Athlete --</option>
                            <!-- Athletes will be populated here -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Session Date</label>
                        <input type="date" class="form-input" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Drill Type</label>
                        <select class="form-input" required>
                            <option value="">-- Select Drill Type --</option>
                            <option>Skating</option>
                            <option>Shooting</option>
                            <option>Passing</option>
                            <option>Stickhandling</option>
                            <option>Defensive</option>
                            <option>Conditioning</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Drill Name</label>
                        <input type="text" class="form-input" placeholder="e.g., Crossover Drill" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Video File</label>
                    <div class="file-upload-area">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop video file here or click to browse</p>
                        <input type="file" accept="video/*" style="display: none;">
                        <button type="button" class="btn-secondary">Choose File</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Review Comments</label>
                    <textarea class="form-textarea" rows="4" placeholder="Provide feedback and notes for the athlete..."></textarea>
                </div>

                <div class="form-group">
                    <label>Rating</label>
                    <div class="rating-selector">
                        <i class="fas fa-star" data-rating="1"></i>
                        <i class="fas fa-star" data-rating="2"></i>
                        <i class="fas fa-star" data-rating="3"></i>
                        <i class="fas fa-star" data-rating="4"></i>
                        <i class="fas fa-star" data-rating="5"></i>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Upload Video</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Videos List -->
    <div class="videos-list">
        <h3 class="section-title">Recent Uploads</h3>
        
        <!-- Sample Video Item -->
        <div class="video-list-item">
            <div class="video-thumbnail-small">
                <i class="fas fa-video"></i>
            </div>
            <div class="video-details">
                <h4>Crossover Drill - John Doe</h4>
                <div class="video-meta">
                    <span><i class="fas fa-calendar"></i> Jan 15, 2024</span>
                    <span><i class="fas fa-clock"></i> 2:35</span>
                    <span><i class="fas fa-tag"></i> Skating</span>
                </div>
                <div class="video-rating">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="far fa-star"></i>
                </div>
            </div>
            <div class="video-status-badge">
                <span class="badge-success"><i class="fas fa-check-circle"></i> Reviewed</span>
            </div>
            <div class="video-actions-inline">
                <button class="btn-icon" title="View"><i class="fas fa-eye"></i></button>
                <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
            </div>
        </div>

        <div class="placeholder-container">
            <i class="fas fa-video placeholder-icon"></i>
            <p class="placeholder-text">No videos uploaded yet. Click "Upload Video" to add your first review video.</p>
        </div>
    </div>
</div>

<style>
.action-bar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.btn-lg {
    height: 45px;
    padding: 0 30px;
}

.upload-section {
    margin-bottom: 30px;
}

.upload-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 30px;
}

.upload-card h3 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 25px;
}

.upload-card h3 i {
    color: var(--neon);
    margin-right: 10px;
}

.file-upload-area {
    border: 2px dashed var(--border);
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    background: var(--bg-main);
    transition: all 0.3s;
}

.file-upload-area:hover {
    border-color: var(--neon);
    background: rgba(255, 77, 0, 0.05);
}

.file-upload-area i {
    font-size: 48px;
    color: var(--neon);
    opacity: 0.5;
    display: block;
    margin-bottom: 15px;
}

.file-upload-area p {
    color: var(--text-dim);
    margin-bottom: 15px;
}

.rating-selector {
    display: flex;
    gap: 10px;
    font-size: 24px;
}

.rating-selector i {
    color: var(--border);
    cursor: pointer;
    transition: all 0.3s;
}

.rating-selector i:hover,
.rating-selector i.active {
    color: var(--accent);
}

.videos-list {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 30px;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.video-list-item {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 15px;
    transition: all 0.3s;
}

.video-list-item:hover {
    border-color: var(--neon);
    box-shadow: 0 4px 20px rgba(255, 77, 0, 0.1);
}

.video-thumbnail-small {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.video-thumbnail-small i {
    font-size: 32px;
    color: var(--neon);
    opacity: 0.5;
}

.video-details {
    flex: 1;
}

.video-details h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 8px;
}

.video-status-badge {
    margin-left: auto;
}

.badge-success {
    background: #10b981;
    color: #fff;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.video-actions-inline {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 40px;
    height: 40px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-white);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    background: var(--neon);
    border-color: var(--neon);
    color: #fff;
}
</style>
