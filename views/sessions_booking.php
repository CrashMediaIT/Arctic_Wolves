<!-- Session Booking View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-calendar-plus"></i> Book a Session
    </h1>
    <p class="page-description">Choose from packages or individual sessions</p>
</div>

<div class="booking-content">
    <!-- Booking Type Tabs -->
    <div class="booking-tabs">
        <button class="tab-btn active" data-tab="packages">
            <i class="fas fa-box"></i> Packages
        </button>
        <button class="tab-btn" data-tab="individual">
            <i class="fas fa-calendar-day"></i> Individual Sessions
        </button>
    </div>

    <!-- Packages Tab -->
    <div class="tab-content active" id="packages-tab">
        <div class="packages-grid">
            <!-- Sample Package Card -->
            <div class="package-card">
                <div class="package-badge">Popular</div>
                <h3 class="package-title">Starter Package</h3>
                <div class="package-price">
                    <span class="price">$299</span>
                    <span class="price-detail">5 sessions</span>
                </div>
                <ul class="package-features">
                    <li><i class="fas fa-check"></i> Individual Training</li>
                    <li><i class="fas fa-check"></i> Video Analysis</li>
                    <li><i class="fas fa-check"></i> Progress Tracking</li>
                    <li><i class="fas fa-check"></i> Valid for 3 months</li>
                </ul>
                <button class="btn-primary btn-full"><i class="fas fa-shopping-cart"></i> Purchase</button>
            </div>

            <div class="package-card featured">
                <div class="package-badge">Best Value</div>
                <h3 class="package-title">Pro Package</h3>
                <div class="package-price">
                    <span class="price">$549</span>
                    <span class="price-detail">10 sessions</span>
                </div>
                <ul class="package-features">
                    <li><i class="fas fa-check"></i> Individual Training</li>
                    <li><i class="fas fa-check"></i> Video Analysis</li>
                    <li><i class="fas fa-check"></i> Progress Tracking</li>
                    <li><i class="fas fa-check"></i> Nutrition Plan</li>
                    <li><i class="fas fa-check"></i> Valid for 6 months</li>
                </ul>
                <button class="btn-primary btn-full"><i class="fas fa-shopping-cart"></i> Purchase</button>
            </div>

            <div class="package-card">
                <div class="package-badge">Premium</div>
                <h3 class="package-title">Elite Package</h3>
                <div class="package-price">
                    <span class="price">$999</span>
                    <span class="price-detail">20 sessions</span>
                </div>
                <ul class="package-features">
                    <li><i class="fas fa-check"></i> Individual Training</li>
                    <li><i class="fas fa-check"></i> Video Analysis</li>
                    <li><i class="fas fa-check"></i> Progress Tracking</li>
                    <li><i class="fas fa-check"></i> Nutrition Plan</li>
                    <li><i class="fas fa-check"></i> Workout Program</li>
                    <li><i class="fas fa-check"></i> Valid for 12 months</li>
                </ul>
                <button class="btn-primary btn-full"><i class="fas fa-shopping-cart"></i> Purchase</button>
            </div>
        </div>
    </div>

    <!-- Individual Sessions Tab -->
    <div class="tab-content" id="individual-tab">
        <div class="booking-form-card">
            <h3><i class="fas fa-calendar-check"></i> Book Individual Session</h3>
            
            <form class="booking-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Session Type</label>
                        <select class="form-input">
                            <option>-- Select Type --</option>
                            <option>Individual Training</option>
                            <option>Group Training</option>
                            <option>Skills Development</option>
                            <option>Evaluation</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Coach</label>
                        <select class="form-input">
                            <option>-- Select Coach --</option>
                            <!-- Coaches will be populated here -->
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>Time</label>
                        <select class="form-input">
                            <option>-- Select Time --</option>
                            <!-- Available times will be populated here -->
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Special Notes (Optional)</label>
                    <textarea class="form-textarea" rows="4" placeholder="Any specific goals or focus areas for this session?"></textarea>
                </div>

                <div class="form-actions">
                    <div class="session-price">
                        <span class="price-label">Session Price:</span>
                        <span class="price">$75</span>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Book Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.booking-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
}

.tab-btn {
    height: 45px;
    padding: 0 30px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-dim);
    border-radius: 4px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.tab-btn:hover {
    border-color: var(--neon);
    color: var(--text-white);
}

.tab-btn.active {
    background: var(--neon);
    border-color: var(--neon);
    color: #fff;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.packages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
}

.package-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 30px;
    position: relative;
    transition: all 0.3s;
}

.package-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(255, 77, 0, 0.2);
    border-color: var(--neon);
}

.package-card.featured {
    border: 2px solid var(--neon);
}

.package-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    color: #fff;
    padding: 5px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.package-title {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
    margin-bottom: 15px;
}

.package-price {
    margin-bottom: 25px;
}

.price {
    font-size: 48px;
    font-weight: 900;
    color: var(--neon);
    display: block;
    line-height: 1;
}

.price-detail {
    font-size: 14px;
    color: var(--text-dim);
}

.package-features {
    list-style: none;
    margin-bottom: 25px;
}

.package-features li {
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    color: var(--text-dim);
}

.package-features li:last-child {
    border-bottom: none;
}

.package-features i {
    color: var(--neon);
    margin-right: 10px;
}

.btn-full {
    width: 100%;
}

.booking-form-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 30px;
}

.booking-form-card h3 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 25px;
}

.booking-form-card h3 i {
    color: var(--neon);
    margin-right: 10px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
    margin-bottom: 8px;
}

.form-textarea {
    width: 100%;
    background: var(--bg-main);
    border: 1px solid var(--border);
    color: var(--text-white);
    padding: 12px 15px;
    border-radius: 4px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    resize: vertical;
}

.form-textarea:focus {
    outline: none;
    border-color: var(--neon);
}

.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

.session-price {
    display: flex;
    align-items: baseline;
    gap: 10px;
}

.price-label {
    font-size: 14px;
    color: var(--text-dim);
}
</style>
