<!-- Create Practice Plan View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-clipboard-list"></i> Create Practice Plan
    </h1>
    <p class="page-description">Build a comprehensive practice plan for your team</p>
</div>

<div class="create-practice-content">
    <!-- Practice Info Form -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Practice Information</h3>
        </div>
        <div class="card-body">
            <form class="practice-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Practice Title *</label>
                        <input type="text" class="form-input" placeholder="e.g., Power Play Development" required>
                    </div>
                    <div class="form-group">
                        <label>Team *</label>
                        <select class="form-input" required>
                            <option value="">-- Select Team --</option>
                            <option>Bantam AA - Blue Devils</option>
                            <option>Peewee A - Thunder</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Time *</label>
                        <input type="time" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Duration (minutes) *</label>
                        <input type="number" class="form-input" placeholder="90" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" class="form-input" placeholder="e.g., Main Rink">
                </div>

                <div class="form-group">
                    <label>Practice Goals</label>
                    <textarea class="form-textarea" rows="3" placeholder="What are the key objectives for this practice?"></textarea>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea class="form-textarea" rows="3" placeholder="Any additional notes or reminders..."></textarea>
                </div>
            </form>
        </div>
    </div>

    <!-- Drill Builder -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list-ol"></i> Practice Drills</h3>
            <button class="btn-primary" id="addDrillBtn"><i class="fas fa-plus"></i> Add Drill</button>
        </div>
        <div class="card-body">
            <!-- Drills Timeline -->
            <div class="drills-timeline" id="drillsTimeline">
                <!-- Sample Drill Item -->
                <div class="timeline-drill-item">
                    <div class="drill-handle">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                    <div class="drill-timing">
                        <input type="number" class="time-input" value="5" min="1">
                        <span>min</span>
                    </div>
                    <div class="drill-details">
                        <select class="form-input">
                            <option value="">-- Select Drill from Library --</option>
                            <option selected>Dynamic Warmup</option>
                            <option>Figure 8 Skating</option>
                            <option>Shooting Drill</option>
                        </select>
                        <textarea class="form-textarea" rows="2" placeholder="Add notes or modifications..."></textarea>
                    </div>
                    <div class="drill-actions-inline">
                        <button class="btn-icon" title="Move Up"><i class="fas fa-arrow-up"></i></button>
                        <button class="btn-icon" title="Move Down"><i class="fas fa-arrow-down"></i></button>
                        <button class="btn-icon" title="Remove"><i class="fas fa-trash"></i></button>
                    </div>
                </div>

                <div class="timeline-drill-item">
                    <div class="drill-handle">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                    <div class="drill-timing">
                        <input type="number" class="time-input" value="15" min="1">
                        <span>min</span>
                    </div>
                    <div class="drill-details">
                        <select class="form-input">
                            <option value="">-- Select Drill from Library --</option>
                            <option>Dynamic Warmup</option>
                            <option selected>Figure 8 Skating</option>
                            <option>Shooting Drill</option>
                        </select>
                        <textarea class="form-textarea" rows="2" placeholder="Add notes or modifications..."></textarea>
                    </div>
                    <div class="drill-actions-inline">
                        <button class="btn-icon" title="Move Up"><i class="fas fa-arrow-up"></i></button>
                        <button class="btn-icon" title="Move Down"><i class="fas fa-arrow-down"></i></button>
                        <button class="btn-icon" title="Remove"><i class="fas fa-trash"></i></button>
                    </div>
                </div>

                <div class="timeline-summary">
                    <div class="summary-item">
                        <span class="summary-label">Total Drills:</span>
                        <span class="summary-value">2</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Time:</span>
                        <span class="summary-value">20 min</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Remaining:</span>
                        <span class="summary-value">70 min</span>
                    </div>
                </div>
            </div>

            <div class="empty-state" style="display: none;">
                <i class="fas fa-clipboard-list placeholder-icon"></i>
                <p class="placeholder-text">No drills added yet. Click "Add Drill" to start building your practice plan.</p>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="form-actions-bar">
        <button class="btn-secondary"><i class="fas fa-times"></i> Cancel</button>
        <div class="action-group">
            <button class="btn-secondary"><i class="fas fa-save"></i> Save Draft</button>
            <button class="btn-secondary"><i class="fas fa-print"></i> Print</button>
            <button class="btn-primary"><i class="fas fa-check"></i> Create Practice Plan</button>
        </div>
    </div>
</div>

<style>
.drills-timeline {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.timeline-drill-item {
    display: flex;
    gap: 15px;
    align-items: start;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: all 0.3s;
}

.timeline-drill-item:hover {
    border-color: var(--neon);
}

.drill-handle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    color: var(--text-dim);
    cursor: grab;
}

.drill-handle:active {
    cursor: grabbing;
}

.drill-timing {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 80px;
}

.time-input {
    width: 60px;
    height: 45px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-white);
    padding: 0 10px;
    border-radius: 4px;
    font-family: 'Inter', sans-serif;
    font-size: 16px;
    font-weight: 700;
    text-align: center;
}

.time-input:focus {
    outline: none;
    border-color: var(--neon);
}

.drill-timing span {
    font-size: 14px;
    color: var(--text-dim);
}

.drill-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.timeline-summary {
    display: flex;
    justify-content: space-around;
    padding: 25px;
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
    border: 1px solid var(--neon);
    border-radius: 8px;
    margin-top: 10px;
}

.summary-item {
    text-align: center;
}

.summary-label {
    display: block;
    font-size: 12px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.summary-value {
    display: block;
    font-size: 24px;
    font-weight: 900;
    color: var(--neon);
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
}
</style>
