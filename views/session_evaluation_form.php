<!-- Session Evaluation Form View -->
<!-- Form to perform evaluations with skills, categories, notes, and athlete dropdown -->

<?php
$evaluation_id = isset($_GET['evaluation_id']) ? intval($_GET['evaluation_id']) : 0;

if ($evaluation_id <= 0) {
    echo '<div class="error-container"><h2>Invalid Evaluation</h2><p>No evaluation ID provided.</p><a href="?page=coach_session_evaluations" class="btn btn-primary">Back to Evaluations</a></div>';
    return;
}

try {
    // Get evaluation with session info
    $stmt = $pdo->prepare("
        SELECT se.*, se.name as evaluation_name, s.title as session_title, s.session_date, s.duration_minutes,
               COALESCE(l.name, 'TBD') as location_name
        FROM session_evaluations se
        INNER JOIN sessions s ON se.session_id = s.id
        LEFT JOIN locations l ON s.location_id = l.id
        WHERE se.id = ?
    ");
    $stmt->execute([$evaluation_id]);
    $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evaluation) {
        echo '<div class="error-container"><h2>Evaluation Not Found</h2><p>The requested evaluation does not exist.</p><a href="?page=coach_session_evaluations" class="btn btn-primary">Back to Evaluations</a></div>';
        return;
    }
    
    // Get athletes
    $stmt = $pdo->prepare("
        SELECT * FROM session_evaluation_athletes 
        WHERE session_evaluation_id = ?
        ORDER BY last_name, first_name
    ");
    $stmt->execute([$evaluation_id]);
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $athletes = decryptUserRows($athletes);
    
    // Get categories and skills
    $stmt = $pdo->prepare("
        SELECT 
            c.id as category_id, c.name as category_name, c.description as category_description,
            c.display_order as category_order,
            s.id as skill_id, s.name as skill_name, s.description as skill_description,
            s.display_order as skill_order
        FROM eval_categories c
        LEFT JOIN eval_skills s ON c.id = s.category_id
        ORDER BY c.display_order ASC, c.id ASC, s.display_order ASC, s.id ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by category
    $categories = [];
    foreach ($rows as $row) {
        $catId = $row['category_id'];
        if (!isset($categories[$catId])) {
            $categories[$catId] = [
                'id' => $catId,
                'name' => $row['category_name'],
                'description' => $row['category_description'],
                'skills' => []
            ];
        }
        if ($row['skill_id']) {
            $categories[$catId]['skills'][] = [
                'id' => $row['skill_id'],
                'name' => $row['skill_name'],
                'description' => $row['skill_description']
            ];
        }
    }
    
    $session_date = strtotime($evaluation['session_date']);
    
} catch (PDOException $e) {
    error_log("Session evaluation form error: " . $e->getMessage());
    echo '<div class="error-container"><h2>Error</h2><p>An error occurred loading the evaluation.</p><a href="?page=coach_session_evaluations" class="btn btn-primary">Back to Evaluations</a></div>';
    return;
}
?>

<div class="eval-form-header">
    <div class="header-left">
        <a href="?page=coach_session_evaluations" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Sessions
        </a>
        <h1 class="page-title">
            <i class="fas fa-clipboard-check"></i>
            <?= htmlspecialchars($evaluation['evaluation_name'] ?: $evaluation['session_title']) ?>
        </h1>
        <div class="session-info">
            <span><i class="fas fa-calendar"></i> <?= date('l, F j, Y', $session_date) ?></span>
            <span><i class="fas fa-clock"></i> <?= date('g:i A', $session_date) ?></span>
            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($evaluation['location_name']) ?></span>
            <span class="status-badge status-<?= $evaluation['status'] ?>"><?= ucfirst($evaluation['status']) ?></span>
        </div>
    </div>
    <div class="header-right">
        <button class="btn btn-secondary" onclick="window.location='?page=coach_session_evaluations'">
            <i class="fas fa-list"></i> All Sessions
        </button>
    </div>
</div>

<?php if (empty($athletes)): ?>
    <div class="placeholder-container">
        <i class="fas fa-users placeholder-icon"></i>
        <h3>No Athletes Assigned</h3>
        <p class="placeholder-text">No athletes have been added to this evaluation yet. Add athletes to begin evaluating.</p>
        <a href="?page=coach_session_evaluations" class="btn btn-primary" style="margin-top: 20px;">
            <i class="fas fa-user-plus"></i> Manage Athletes
        </a>
    </div>
<?php elseif (empty($categories)): ?>
    <div class="placeholder-container">
        <i class="fas fa-clipboard-list placeholder-icon"></i>
        <h3>No Evaluation Criteria</h3>
        <p class="placeholder-text">No evaluation categories or skills have been set up yet.</p>
        <?php if ($user_role === 'admin'): ?>
        <a href="?page=eval_framework" class="btn btn-primary" style="margin-top: 20px;">
            <i class="fas fa-cog"></i> Set Up Evaluation Framework
        </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="eval-form-content">
        <!-- Athlete Selector -->
        <div class="athlete-selector-card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Select Athlete</h3>
                <span class="athlete-count"><?= count($athletes) ?> athletes</span>
            </div>
            <div class="card-body">
                <div class="athlete-nav">
                    <button class="nav-btn" onclick="previousAthlete()" id="prev-btn" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <select id="athlete-select" class="form-select" onchange="loadAthlete(this.value)">
                        <?php foreach ($athletes as $index => $athlete): ?>
                            <option value="<?= $athlete['id'] ?>" data-index="<?= $index ?>">
                                <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="nav-btn" onclick="nextAthlete()" id="next-btn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="athlete-progress">
                    <span id="current-position">1</span> of <?= count($athletes) ?>
                </div>
            </div>
        </div>

        <!-- Evaluation Form -->
        <form id="evaluation-form" class="evaluation-form">
            <input type="hidden" name="evaluation_id" value="<?= $evaluation_id ?>">
            <input type="hidden" id="current-athlete-id" name="athlete_id" value="<?= $athletes[0]['id'] ?? '' ?>">
            <?= csrfTokenInput() ?>
            
            <?php foreach ($categories as $category): ?>
                <div class="category-section">
                    <div class="category-header">
                        <h3><?= htmlspecialchars($category['name']) ?></h3>
                        <?php if ($category['description']): ?>
                            <p class="category-description"><?= htmlspecialchars($category['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="skills-list">
                        <?php foreach ($category['skills'] as $skill): ?>
                            <div class="skill-item" data-skill-id="<?= $skill['id'] ?>">
                                <div class="skill-header">
                                    <div class="skill-info">
                                        <h4 class="skill-name"><?= htmlspecialchars($skill['name']) ?></h4>
                                        <?php if ($skill['description']): ?>
                                            <p class="skill-description"><?= htmlspecialchars($skill['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rating-control">
                                        <div class="star-rating" data-skill="<?= $skill['id'] ?>">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <button type="button" class="star" data-value="<?= $i ?>" title="<?= $i ?>/5">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            <?php endfor; ?>
                                        </div>
                                        <input type="hidden" name="scores[<?= $skill['id'] ?>][rating]" 
                                               class="rating-input" data-skill="<?= $skill['id'] ?>">
                                    </div>
                                </div>
                                <div class="skill-notes">
                                    <textarea name="scores[<?= $skill['id'] ?>][notes]" 
                                              class="notes-input" 
                                              data-skill="<?= $skill['id'] ?>"
                                              placeholder="Add notes for this skill..."
                                              rows="2"></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                    <i class="fas fa-eraser"></i> Clear
                </button>
                <button type="submit" class="btn btn-primary btn-large">
                    <i class="fas fa-save"></i> Save Evaluation
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<style>
/* Evaluation Form Styles */
.eval-form-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border, #2D2D3F);
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--text-dim, #6B6B7B);
    text-decoration: none;
    font-size: 14px;
    margin-bottom: 12px;
    transition: color 0.2s;
}

.back-link:hover {
    color: var(--primary-light, #8B5CF6);
}

.page-title {
    font-size: 28px;
    font-weight: 800;
    color: var(--text-white, #fff);
    margin: 0 0 12px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-title i {
    color: var(--primary, #6B46C1);
}

.session-info {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 14px;
    color: var(--text-dim, #6B6B7B);
}

.session-info span {
    display: flex;
    align-items: center;
    gap: 8px;
}

.session-info i {
    color: var(--primary, #6B46C1);
}

/* Status Badges */
.status-badge {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
}

.status-draft {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.status-active {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.status-completed {
    background: rgba(107, 70, 193, 0.15);
    color: #8B5CF6;
}

/* Athlete Selector */
.athlete-selector-card {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    margin-bottom: 24px;
    overflow: hidden;
}

.athlete-selector-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.1) 0%, transparent 100%);
    border-bottom: 1px solid var(--border, #2D2D3F);
}

.athlete-selector-card .card-header h3 {
    font-size: 16px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.athlete-selector-card .card-header h3 i {
    color: var(--primary, #6B46C1);
}

.athlete-count {
    font-size: 12px;
    color: var(--text-dim, #6B6B7B);
    background: var(--bg-main, #0A0A0F);
    padding: 6px 12px;
    border-radius: 20px;
}

.athlete-selector-card .card-body {
    padding: 20px;
}

.athlete-nav {
    display: flex;
    align-items: center;
    gap: 12px;
}

.nav-btn {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    border: 1px solid var(--border, #2D2D3F);
    background: var(--bg-main, #0A0A0F);
    color: var(--text, #A8A8B8);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nav-btn:hover:not(:disabled) {
    border-color: var(--primary, #6B46C1);
    color: var(--primary-light, #8B5CF6);
}

.nav-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.athlete-nav .form-select {
    flex: 1;
    padding: 12px 16px;
    font-size: 16px;
    font-weight: 600;
}

.athlete-progress {
    text-align: center;
    margin-top: 12px;
    font-size: 13px;
    color: var(--text-dim, #6B6B7B);
}

#current-position {
    font-weight: 700;
    color: var(--primary-light, #8B5CF6);
}

/* Category Section */
.category-section {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    margin-bottom: 20px;
    overflow: hidden;
}

.category-header {
    padding: 20px 24px;
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.08) 0%, transparent 100%);
    border-bottom: 1px solid var(--border, #2D2D3F);
}

.category-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white, #fff);
    margin: 0 0 6px 0;
}

.category-description {
    font-size: 13px;
    color: var(--text-dim, #6B6B7B);
    margin: 0;
}

/* Skills List */
.skills-list {
    padding: 16px;
}

.skill-item {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 12px;
    transition: all 0.2s;
}

.skill-item:last-child {
    margin-bottom: 0;
}

.skill-item:hover {
    border-color: var(--primary, #6B46C1);
}

.skill-item.rated {
    border-color: var(--primary, #6B46C1);
    background: rgba(107, 70, 193, 0.05);
}

.skill-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
}

.skill-info {
    flex: 1;
    min-width: 200px;
}

.skill-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-white, #fff);
    margin: 0 0 4px 0;
}

.skill-description {
    font-size: 13px;
    color: var(--text-dim, #6B6B7B);
    margin: 0;
}

/* Star Rating */
.rating-control {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.star-rating {
    display: flex;
    gap: 4px;
}

.star {
    width: 36px;
    height: 36px;
    border: none;
    background: transparent;
    color: var(--border, #2D2D3F);
    cursor: pointer;
    font-size: 20px;
    transition: all 0.2s;
    padding: 0;
}

.star:hover {
    transform: scale(1.1);
}

.star.active {
    color: #f59e0b;
}

/* Star rating hover effect - fill stars up to and including hovered star */
.star-rating:hover .star {
    color: #f59e0b;
}

.star-rating .star:hover ~ .star {
    color: var(--border, #2D2D3F);
}

/* Notes */
.skill-notes {
    margin-top: 16px;
}

.notes-input {
    width: 100%;
    padding: 12px 16px;
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 8px;
    color: var(--text-white, #fff);
    font-size: 14px;
    resize: vertical;
    min-height: 60px;
    font-family: inherit;
    transition: border-color 0.2s;
}

.notes-input:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
}

.notes-input::placeholder {
    color: var(--text-dim, #6B6B7B);
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 16px;
    padding: 24px;
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    margin-top: 20px;
}

.btn-large {
    padding: 14px 28px;
    font-size: 16px;
}

/* Placeholder */
.placeholder-container {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    padding: 60px 24px;
    text-align: center;
}

.placeholder-container h3 {
    font-size: 20px;
    color: var(--text-white, #fff);
    margin-bottom: 12px;
}

.placeholder-icon {
    font-size: 64px;
    color: var(--primary, #6B46C1);
    opacity: 0.5;
    display: block;
    margin-bottom: 20px;
}

.placeholder-text {
    font-size: 14px;
    color: var(--text-dim, #6B6B7B);
    line-height: 1.6;
}

/* Error Container */
.error-container {
    text-align: center;
    padding: 60px 24px;
}

.error-container h2 {
    color: #ef4444;
    margin-bottom: 12px;
}

/* Form Inputs */
.form-select {
    width: 100%;
    padding: 12px 16px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 8px;
    color: var(--text-white, #fff);
    font-size: 14px;
    cursor: pointer;
}

.form-select:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
}

/* Saving Indicator */
.saving-indicator {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 1000;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    transition: all 0.3s ease;
    transform: translateY(100px);
    opacity: 0;
}

.saving-indicator.show {
    transform: translateY(0);
    opacity: 1;
}

.saving-indicator.success {
    border-color: #10b981;
}

.saving-indicator.success i {
    color: #10b981;
}

.saving-indicator.error {
    border-color: #ef4444;
}

.saving-indicator.error i {
    color: #ef4444;
}

/* Responsive */
@media (max-width: 768px) {
    .eval-form-header {
        flex-direction: column;
        gap: 16px;
    }
    
    .header-right {
        width: 100%;
    }
    
    .header-right .btn {
        width: 100%;
    }
    
    .skill-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .rating-control {
        align-items: flex-start;
        margin-top: 12px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}
</style>

<div id="saving-indicator" class="saving-indicator">
    <i class="fas fa-spinner fa-spin"></i>
    <span>Saving...</span>
</div>

<script>
const evaluationId = <?= $evaluation_id ?>;
const athletes = <?= json_encode($athletes) ?>;
let currentAthleteIndex = 0;
let athleteScores = {}; // Cache for loaded scores

// Initialize star ratings
document.querySelectorAll('.star-rating').forEach(rating => {
    const stars = rating.querySelectorAll('.star');
    const skillId = rating.dataset.skill;
    
    stars.forEach((star, index) => {
        star.addEventListener('click', () => {
            const value = index + 1;
            setRating(skillId, value);
        });
        
        star.addEventListener('mouseenter', () => {
            highlightStars(rating, index + 1);
        });
    });
    
    rating.addEventListener('mouseleave', () => {
        const input = document.querySelector(`.rating-input[data-skill="${skillId}"]`);
        const value = parseInt(input.value) || 0;
        highlightStars(rating, value);
    });
});

function highlightStars(ratingContainer, count) {
    const stars = ratingContainer.querySelectorAll('.star');
    stars.forEach((star, index) => {
        star.classList.toggle('active', index < count);
    });
}

function setRating(skillId, value) {
    const input = document.querySelector(`.rating-input[data-skill="${skillId}"]`);
    const rating = document.querySelector(`.star-rating[data-skill="${skillId}"]`);
    const skillItem = document.querySelector(`.skill-item[data-skill-id="${skillId}"]`);
    
    input.value = value;
    highlightStars(rating, value);
    skillItem.classList.add('rated');
}

// Athlete navigation
function updateNavigationButtons() {
    document.getElementById('prev-btn').disabled = currentAthleteIndex === 0;
    document.getElementById('next-btn').disabled = currentAthleteIndex === athletes.length - 1;
    document.getElementById('current-position').textContent = currentAthleteIndex + 1;
}

function previousAthlete() {
    if (currentAthleteIndex > 0) {
        saveCurrentScores();
        currentAthleteIndex--;
        const select = document.getElementById('athlete-select');
        select.selectedIndex = currentAthleteIndex;
        loadAthlete(athletes[currentAthleteIndex].id);
        updateNavigationButtons();
    }
}

function nextAthlete() {
    if (currentAthleteIndex < athletes.length - 1) {
        saveCurrentScores();
        currentAthleteIndex++;
        const select = document.getElementById('athlete-select');
        select.selectedIndex = currentAthleteIndex;
        loadAthlete(athletes[currentAthleteIndex].id);
        updateNavigationButtons();
    }
}

async function loadAthlete(athleteId) {
    document.getElementById('current-athlete-id').value = athleteId;
    
    // Update index
    currentAthleteIndex = athletes.findIndex(a => a.id == athleteId);
    updateNavigationButtons();
    
    // Check cache first
    if (athleteScores[athleteId]) {
        applyScores(athleteScores[athleteId]);
        return;
    }
    
    // Load scores from server
    try {
        const params = new URLSearchParams({
            action: 'get_athlete_scores',
            evaluation_id: evaluationId,
            athlete_id: athleteId
        });
        const response = await fetch(`process_session_evaluations.php?${params.toString()}`);
        const data = await response.json();
        
        if (data.success) {
            athleteScores[athleteId] = data.scores;
            applyScores(data.scores);
        } else {
            clearFormInputs();
        }
    } catch (error) {
        console.error('Error loading scores:', error);
        clearFormInputs();
    }
}

function applyScores(scores) {
    // Clear all first
    clearFormInputs();
    
    // Apply loaded scores
    Object.keys(scores).forEach(skillId => {
        const score = scores[skillId];
        
        if (score.rating) {
            setRating(skillId, parseInt(score.rating));
        }
        
        if (score.notes) {
            const notesInput = document.querySelector(`.notes-input[data-skill="${skillId}"]`);
            if (notesInput) {
                notesInput.value = score.notes;
            }
        }
    });
}

function clearFormInputs() {
    // Clear all ratings
    document.querySelectorAll('.rating-input').forEach(input => {
        input.value = '';
    });
    document.querySelectorAll('.star-rating').forEach(rating => {
        highlightStars(rating, 0);
    });
    document.querySelectorAll('.skill-item').forEach(item => {
        item.classList.remove('rated');
    });
    
    // Clear all notes
    document.querySelectorAll('.notes-input').forEach(input => {
        input.value = '';
    });
}

function clearForm() {
    if (confirm('Are you sure you want to clear all ratings and notes for this athlete?')) {
        clearFormInputs();
        // Also clear from cache
        const athleteId = document.getElementById('current-athlete-id').value;
        delete athleteScores[athleteId];
    }
}

function saveCurrentScores() {
    // Save current form data to cache
    const athleteId = document.getElementById('current-athlete-id').value;
    const scores = {};
    
    document.querySelectorAll('.skill-item').forEach(item => {
        const skillId = item.dataset.skillId;
        const ratingInput = item.querySelector('.rating-input');
        const notesInput = item.querySelector('.notes-input');
        
        scores[skillId] = {
            rating: ratingInput.value,
            notes: notesInput.value
        };
    });
    
    athleteScores[athleteId] = scores;
}

// Form submission
document.getElementById('evaluation-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const indicator = document.getElementById('saving-indicator');
    indicator.className = 'saving-indicator show';
    indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Saving...</span>';
    
    const formData = new FormData(this);
    formData.append('action', 'save_evaluation_scores');
    
    try {
        const response = await fetch('process_session_evaluations.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            // Update cache
            saveCurrentScores();
            
            indicator.className = 'saving-indicator show success';
            indicator.innerHTML = '<i class="fas fa-check-circle"></i><span>Saved successfully!</span>';
        } else {
            indicator.className = 'saving-indicator show error';
            indicator.innerHTML = `<i class="fas fa-exclamation-circle"></i><span>${data.message}</span>`;
        }
    } catch (error) {
        indicator.className = 'saving-indicator show error';
        indicator.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Error saving evaluation</span>';
    }
    
    setTimeout(() => {
        indicator.classList.remove('show');
    }, 3000);
});

// Auto-save on athlete change
document.getElementById('athlete-select').addEventListener('change', function() {
    saveCurrentScores();
    loadAthlete(this.value);
});

// Initialize
if (athletes.length > 0) {
    loadAthlete(athletes[0].id);
}
updateNavigationButtons();
</script>
