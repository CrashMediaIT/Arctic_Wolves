<?php
/**
 * Create Session
 * Create new training sessions
 */

require_once __DIR__ . '/../security.php';

// Check if user has permission
if (!in_array($user_role, ['coach_plus', 'admin'])) {
    header('Location: dashboard.php?page=home');
    exit;
}

// Get age groups and skill levels
$age_groups = $pdo->query("SELECT * FROM age_groups ORDER BY display_order")->fetchAll();
$skill_levels = $pdo->query("SELECT * FROM skill_levels ORDER BY display_order")->fetchAll();
$session_types = $pdo->query("SELECT * FROM session_types ORDER BY name")->fetchAll();
$locations = $pdo->query("SELECT * FROM locations ORDER BY city, name")->fetchAll();
$practice_plans = $pdo->query("SELECT * FROM practice_plans ORDER BY created_at DESC LIMIT 50")->fetchAll();

// Get system settings for defaults
$settings = $pdo->query("SELECT * FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        background: linear-gradient(135deg, var(--primary) 0%, #4a0070 100%);
        border-radius: 8px;
        padding: 30px;
        margin-bottom: 30px;
        color: #fff;
    }
    .page-header h1 {
        margin: 0 0 10px 0;
        font-size: 28px;
        font-weight: 900;
    }
    .form-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 30px;
    }
    .form-section {
        margin-bottom: 30px;
    }
    .section-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #1e293b;
    }
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 8px;
        text-transform: uppercase;
    }
    .form-input, .form-select, .form-textarea {
        width: 100%;
        padding: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        transition: all 0.2s;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--primary);
    }
    .form-textarea {
        min-height: 120px;
        resize: vertical;
        font-family: inherit;
    }
    .btn-submit {
        background: var(--primary);
        color: #fff;
        padding: 14px 32px;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 16px;
        transition: all 0.2s;
    }
    .btn-submit:hover {
        background: #e64500;
    }
    .help-text {
        font-size: 13px;
        color: #64748b;
        margin-top: 8px;
    }
    .info-box {
        background: rgba(255, 77, 0, 0.1);
        border: 1px solid var(--primary);
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .info-box p {
        color: #94a3b8;
        font-size: 14px;
        margin: 0;
        line-height: 1.6;
    }
</style>

<div class="page-header">
    <h1><i class="fas fa-plus-circle"></i> Create New Session</h1>
    <div style="font-size: 14px; opacity: 0.9;">
        Set up a new training session for athletes to book
    </div>
</div>

<form method="POST" action="process_create_session.php">
    <?= csrfTokenInput() ?>
    
    <div class="form-card">
        <!-- Basic Information -->
        <div class="form-section">
            <h2 class="section-title">Basic Information</h2>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Session Title *</label>
                    <input type="text" name="title" class="form-input" required 
                           placeholder="e.g., Elite Skills Training">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Session Type *</label>
                    <select name="session_type" class="form-select" required>
                        <option value="">Select Type</option>
                        <?php foreach ($session_types as $type): ?>
                            <option value="<?= htmlspecialchars($type['name']) ?>">
                                <?= htmlspecialchars($type['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Session Category *</label>
                    <select name="session_type_category" class="form-select" required>
                        <option value="group">Group Session</option>
                        <option value="semi-private">Semi-Private</option>
                        <option value="private">Private Session</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Date & Time -->
        <div class="form-section">
            <h2 class="section-title">Date & Time</h2>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Date *</label>
                    <input type="date" name="session_date" class="form-input" required
                           min="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Time *</label>
                    <input type="time" name="session_time" class="form-input" required>
                </div>
            </div>
        </div>
        
        <!-- Location -->
        <div class="form-section">
            <h2 class="section-title">Location</h2>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Arena *</label>
                    <select name="location_id" class="form-select" required onchange="updateLocation(this)">
                        <option value="">Select Location</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= $location['id'] ?>" 
                                    data-arena="<?= htmlspecialchars($location['name']) ?>"
                                    data-city="<?= htmlspecialchars($location['city']) ?>">
                                <?= htmlspecialchars($location['name'] . ' - ' . $location['city']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="custom">Custom Location</option>
                    </select>
                    <input type="hidden" name="arena" id="arena_field">
                    <input type="hidden" name="city" id="city_field">
                </div>
                
                <div class="form-group" id="custom_arena" style="display: none;">
                    <label class="form-label">Custom Arena Name</label>
                    <input type="text" name="custom_arena" class="form-input">
                </div>
                
                <div class="form-group" id="custom_city" style="display: none;">
                    <label class="form-label">Custom City</label>
                    <input type="text" name="custom_city" class="form-input">
                </div>
            </div>
        </div>
        
        <!-- Participant Details -->
        <div class="form-section">
            <h2 class="section-title">Participant Details</h2>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Age Group</label>
                    <select name="age_group_id" class="form-select">
                        <option value="">All Ages</option>
                        <?php foreach ($age_groups as $ag): ?>
                            <option value="<?= $ag['id'] ?>">
                                <?= htmlspecialchars($ag['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Skill Level</label>
                    <select name="skill_level_id" class="form-select">
                        <option value="">All Levels</option>
                        <?php foreach ($skill_levels as $sl): ?>
                            <option value="<?= $sl['id'] ?>">
                                <?= htmlspecialchars($sl['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Max Capacity *</label>
                    <input type="number" name="max_capacity" class="form-input" 
                           value="20" min="1" max="100" required>
                    <p class="help-text">Maximum number of participants</p>
                </div>
            </div>
        </div>
        
        <!-- Pricing -->
        <div class="form-section">
            <h2 class="section-title">Pricing</h2>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Price (Before Tax) *</label>
                    <input type="number" name="price" class="form-input" 
                           step="0.01" min="0" value="0.00" required>
                    <p class="help-text">Enter 0 for free sessions</p>
                </div>
            </div>
        </div>
        
        <!-- Session Plan -->
        <div class="form-section">
            <h2 class="section-title">Session Plan (Optional)</h2>
            
            <div class="form-group">
                <label class="form-label">Practice Plan</label>
                <select name="practice_plan_id" class="form-select">
                    <option value="">No Practice Plan</option>
                    <?php foreach ($practice_plans as $plan): ?>
                        <option value="<?= $plan['id'] ?>">
                            <?= htmlspecialchars($plan['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="help-text">Link a practice plan to this session</p>
            </div>
            
            <div class="form-group">
                <label class="form-label">Session Notes</label>
                <textarea name="session_plan" class="form-textarea" 
                          placeholder="Add notes about what will be covered in this session..."></textarea>
            </div>
        </div>
        
        <button type="submit" class="btn-submit">
            <i class="fas fa-calendar-plus"></i> Create Session
        </button>
    </div>
</form>

<script>
function updateLocation(select) {
    const customArena = document.getElementById('custom_arena');
    const customCity = document.getElementById('custom_city');
    const arenaField = document.getElementById('arena_field');
    const cityField = document.getElementById('city_field');
    
    if (select.value === 'custom') {
        customArena.style.display = 'block';
        customCity.style.display = 'block';
        arenaField.value = '';
        cityField.value = '';
    } else if (select.value) {
        customArena.style.display = 'none';
        customCity.style.display = 'none';
        const option = select.options[select.selectedIndex];
        arenaField.value = option.dataset.arena;
        cityField.value = option.dataset.city;
    } else {
        customArena.style.display = 'none';
        customCity.style.display = 'none';
        arenaField.value = '';
        cityField.value = '';
    }
}
</script>
