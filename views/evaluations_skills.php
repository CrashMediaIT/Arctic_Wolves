<?php
/**
 * Skills & Abilities Evaluation Platform (Type 2)
 * Comprehensive skills evaluation with scoring, notes, and media attachments
 * Enhanced with Team Evaluation Mode for multi-athlete evaluation
 */

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/image_helper.php';

$current_user_id = $user_id;
$current_user_role = $user_role;

// Team Evaluation Mode toggle
$team_mode = isset($_GET['team_mode']) && $_GET['team_mode'] === '1' && $isAnyCoach;

// Determine viewing athlete
$viewing_athlete_id = $current_user_id;
if ($isAnyCoach && isset($_GET['athlete_id'])) {
    $viewing_athlete_id = intval($_GET['athlete_id']);
} elseif ($isParent && !empty($_SESSION['viewing_athlete_id'])) {
    $viewing_athlete_id = intval($_SESSION['viewing_athlete_id']);
}

// Get athlete list for coaches (including non-system athletes in team mode)
$athletes = [];
if ($isAnyCoach) {
    $athletes = $pdo->query("
        SELECT id, first_name, last_name, email
        FROM users
        WHERE role = 'athlete' AND is_active = 1
        ORDER BY last_name, first_name
    ")->fetchAll();
    $athletes = decryptUserRows($athletes);
}

// Get athlete info
$athlete_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$athlete_stmt->execute([$viewing_athlete_id]);
$athlete_info = $athlete_stmt->fetch();
$athlete_info = decryptUserRow($athlete_info);

// In team mode, load all categories and skills for evaluation
$all_categories = [];
if ($team_mode) {
    $cats_stmt = $pdo->query("
        SELECT ec.*, 
               (SELECT COUNT(*) FROM eval_skills WHERE category_id = ec.id AND is_active = 1) as skill_count
        FROM eval_categories ec
        WHERE ec.is_active = 1
        ORDER BY ec.display_order
    ");
    $all_cats = $cats_stmt->fetchAll();
    
    foreach ($all_cats as $cat) {
        $skills_stmt = $pdo->prepare("
            SELECT * FROM eval_skills 
            WHERE category_id = ? AND is_active = 1
            ORDER BY display_order
        ");
        $skills_stmt->execute([$cat['id']]);
        $all_categories[$cat['id']] = [
            'name' => $cat['name'],
            'description' => $cat['description'],
            'order' => $cat['display_order'],
            'skills' => $skills_stmt->fetchAll()
        ];
    }
}

// Get evaluation ID if viewing specific evaluation
$eval_id = isset($_GET['eval_id']) ? intval($_GET['eval_id']) : null;
$evaluation = null;
$scores = [];
$categories = [];

if ($eval_id) {
    // Load evaluation
    try {
        $eval_stmt = $pdo->prepare("
            SELECT ae.*, u.first_name as creator_first_name, u.last_name as creator_last_name
            FROM athlete_evaluations ae
            LEFT JOIN users u ON ae.created_by = u.id
            WHERE ae.id = ? AND ae.athlete_id = ?
        ");
        $eval_stmt->execute([$eval_id, $viewing_athlete_id]);
        $evaluation = $eval_stmt->fetch();
        $evaluation = decryptUserRow($evaluation);
    } catch (PDOException $e) {
        // Fallback: created_by column may not exist yet
        error_log("evaluations_skills.php - created_by column missing, using fallback: " . $e->getMessage());
        $eval_stmt = $pdo->prepare("
            SELECT ae.*, NULL as creator_first_name, NULL as creator_last_name
            FROM athlete_evaluations ae
            WHERE ae.id = ? AND ae.athlete_id = ?
        ");
        $eval_stmt->execute([$eval_id, $viewing_athlete_id]);
        $evaluation = $eval_stmt->fetch();
        $evaluation = decryptUserRow($evaluation);
    }
    
    if ($evaluation) {
        // Load scores
        try {
            $scores_stmt = $pdo->prepare("
                SELECT es.*, 
                       evs.id as skill_id, evs.name as skill_name, evs.description as skill_description,
                       evs.criteria, evs.category_id,
                       ec.name as category_name, ec.display_order as category_order
                FROM evaluation_scores es
                JOIN eval_skills evs ON es.skill_id = evs.id
                JOIN eval_categories ec ON evs.category_id = ec.id
                WHERE es.evaluation_id = ?
                ORDER BY ec.display_order, evs.display_order
            ");
            $scores_stmt->execute([$eval_id]);
            $scores = $scores_stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("evaluations_skills.php - scores query failed (evaluation_id may be missing): " . $e->getMessage());
            $scores = [];
        }
        
        // Group by category
        foreach ($scores as $score) {
            $cat_id = $score['category_id'];
            if (!isset($categories[$cat_id])) {
                $categories[$cat_id] = [
                    'name' => $score['category_name'],
                    'order' => $score['category_order'],
                    'skills' => []
                ];
            }
            $categories[$cat_id]['skills'][] = $score;
        }
        
        // Sort categories
        uasort($categories, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        // Load media for this evaluation
        try {
            $media_stmt = $pdo->prepare("
                SELECT * FROM evaluation_media 
                WHERE evaluation_id = ?
                ORDER BY created_at DESC
            ");
            $media_stmt->execute([$eval_id]);
            $media_items = $media_stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback: created_at column may not exist yet (original table has uploaded_at)
            error_log("evaluations_skills.php - evaluation_media.created_at missing, using fallback: " . $e->getMessage());
            try {
                $media_stmt = $pdo->prepare("
                    SELECT * FROM evaluation_media 
                    WHERE evaluation_id = ?
                    ORDER BY uploaded_at DESC
                ");
                $media_stmt->execute([$eval_id]);
                $media_items = $media_stmt->fetchAll();
            } catch (PDOException $e2) {
                error_log("evaluations_skills.php - evaluation_media fallback also failed: " . $e2->getMessage());
                $media_items = [];
            }
        }
        
        // Index media by score_id (score_id may not exist on older schemas)
        $media_by_score = [];
        foreach ($media_items as $media) {
            $score_key = $media['score_id'] ?? 0;
            if (!isset($media_by_score[$score_key])) {
                $media_by_score[$score_key] = [];
            }
            $media_by_score[$score_key][] = $media;
        }
    }
}

// Get all evaluations list
try {
    $evals_stmt = $pdo->prepare("
        SELECT ae.*, 
               u.first_name as creator_first_name, u.last_name as creator_last_name,
               (SELECT COUNT(*) FROM evaluation_scores WHERE evaluation_id = ae.id AND score IS NOT NULL) as completed_scores,
               (SELECT COUNT(*) FROM evaluation_scores WHERE evaluation_id = ae.id) as total_scores
        FROM athlete_evaluations ae
        LEFT JOIN users u ON ae.created_by = u.id
        WHERE ae.athlete_id = ?
        ORDER BY ae.evaluation_date DESC, ae.created_at DESC
    ");
    $evals_stmt->execute([$viewing_athlete_id]);
    $evaluations_list = $evals_stmt->fetchAll();
    $evaluations_list = decryptUserRows($evaluations_list);
} catch (PDOException $e) {
    // Fallback: evaluation_scores.evaluation_id or ae.created_by may not exist yet
    error_log("evaluations_skills.php - primary eval list query failed, using fallback: " . $e->getMessage());
    try {
        $evals_stmt = $pdo->prepare("
            SELECT ae.*, 
                   u.first_name as creator_first_name, u.last_name as creator_last_name,
                   0 as completed_scores,
                   0 as total_scores
            FROM athlete_evaluations ae
            LEFT JOIN users u ON ae.created_by = u.id
            WHERE ae.athlete_id = ?
            ORDER BY ae.evaluation_date DESC, ae.created_at DESC
        ");
        $evals_stmt->execute([$viewing_athlete_id]);
        $evaluations_list = $evals_stmt->fetchAll();
        $evaluations_list = decryptUserRows($evaluations_list);
    } catch (PDOException $e2) {
        // Deep fallback: created_by column also missing
        error_log("evaluations_skills.php - created_by also missing, using deep fallback: " . $e2->getMessage());
        $evals_stmt = $pdo->prepare("
            SELECT ae.*, 
                   NULL as creator_first_name, NULL as creator_last_name,
                   0 as completed_scores,
                   0 as total_scores
            FROM athlete_evaluations ae
            WHERE ae.athlete_id = ?
            ORDER BY ae.id DESC
        ");
        $evals_stmt->execute([$viewing_athlete_id]);
        $evaluations_list = $evals_stmt->fetchAll();
        $evaluations_list = decryptUserRows($evaluations_list);
    }
}

// Get historical evaluations for comparison (if viewing evaluation)
$historical = [];
if ($eval_id && $evaluation) {
    try {
        $hist_stmt = $pdo->prepare("
            SELECT ae.id, ae.evaluation_date, ae.title,
                   es.skill_id, es.score
            FROM athlete_evaluations ae
            JOIN evaluation_scores es ON ae.id = es.evaluation_id
            WHERE ae.athlete_id = ? AND ae.id != ? AND ae.status = 'completed'
            ORDER BY ae.evaluation_date DESC
            LIMIT 3
        ");
        $hist_stmt->execute([$viewing_athlete_id, $eval_id]);
        $hist_scores = $hist_stmt->fetchAll();
        
        foreach ($hist_scores as $hs) {
            if (!isset($historical[$hs['id']])) {
                $historical[$hs['id']] = [
                    'date' => $hs['evaluation_date'],
                    'title' => $hs['title'],
                    'scores' => []
                ];
            }
            $historical[$hs['id']]['scores'][$hs['skill_id']] = $hs['score'];
        }
    } catch (PDOException $e) {
        error_log("evaluations_skills.php - historical query failed (evaluation_id may be missing): " . $e->getMessage());
    }
}
?>

<style>
    :root {
        --primary: #6B46C1;
        --primary-hover: #5a0083;
        --danger: #ef4444;
        --success: #10b981;
        --warning: #f59e0b;
        --bg-dark: #0d1117;
        --bg-darker: #06080b;
        --border: #1e293b;
        --text-light: #94a3b8;
    }
    
    .evaluations-container {
        padding: 20px;
        max-width: 1600px;
        margin: 0 auto;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    
    .header-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }
    
    .athlete-selector {
        padding: 10px 16px;
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .athlete-selector:hover {
        border-color: var(--primary);
    }
    
    .btn-create {
        background: var(--primary);
        color: #fff;
        padding: 12px 24px;
        border-radius: 6px;
        border: none;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-create:hover {
        background: var(--primary-hover);
    }
    
    .btn-back {
        background: transparent;
        color: var(--text-light);
        padding: 10px 20px;
        border: 1px solid var(--border);
        border-radius: 6px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s;
    }
    
    .btn-back:hover {
        border-color: var(--primary);
        color: #fff;
    }
    
    .evaluations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }
    
    .eval-card {
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 20px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .eval-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    
    .eval-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 12px;
    }
    
    .eval-card-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 5px;
    }
    
    .eval-card-date {
        font-size: 14px;
        color: var(--text-light);
    }
    
    .eval-status {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }
    
    .status-draft { background: rgba(148, 163, 184, 0.2); color: #94a3b8; }
    .status-completed { background: rgba(16, 185, 129, 0.2); color: #10b981; }
    .status-archived { background: rgba(100, 116, 139, 0.2); color: #64748b; }
    
    .eval-card-progress {
        margin-top: 12px;
        padding-top: 15px;
        border-top: 1px solid var(--border);
    }
    
    .progress-bar {
        height: 6px;
        background: var(--bg-darker);
        border-radius: 3px;
        overflow: hidden;
        margin-top: 8px;
    }
    
    .progress-fill {
        height: 100%;
        background: var(--primary);
        transition: width 0.3s;
    }
    
    .progress-text {
        font-size: 12px;
        color: var(--text-light);
        margin-bottom: 5px;
    }
    
    /* Evaluation Detail View */
    .eval-detail {
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 24px;
    }
    
    .eval-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 24px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--border);
    }
    
    .eval-detail-title {
        font-size: 24px;
        font-weight: 900;
        color: #fff;
        margin-bottom: 10px;
    }
    
    .eval-detail-meta {
        font-size: 14px;
        color: var(--text-light);
    }
    
    .eval-actions {
        display: flex;
        gap: 10px;
    }
    
    .btn-complete, .btn-archive, .btn-share {
        padding: 10px 20px;
        border-radius: 6px;
        border: none;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-complete {
        background: var(--success);
        color: #fff;
    }
    
    .btn-archive {
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text-light);
    }
    
    .btn-share {
        background: var(--primary);
        color: #fff;
    }
    
    /* Skills Grid */
    .skills-category {
        background: var(--bg-darker);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 24px;
    }
    
    .category-header {
        font-size: 20px;
        font-weight: 900;
        color: var(--primary);
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--primary);
    }
    
    .skill-item {
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .skill-item:last-child {
        margin-bottom: 0;
    }
    
    .skill-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 12px;
    }
    
    .skill-name {
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 5px;
    }
    
    .skill-description {
        font-size: 14px;
        color: var(--text-light);
        line-height: 1.5;
    }
    
    .skill-criteria {
        font-size: 12px;
        color: var(--text-light);
        background: var(--bg-darker);
        padding: 10px;
        border-radius: 4px;
        margin-top: 10px;
        border-left: 3px solid var(--primary);
    }
    
    .score-input-wrapper {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .score-input {
        width: 80px;
        padding: 10px;
        background: var(--bg-dark);
        border: 2px solid var(--border);
        border-radius: 6px;
        color: #fff;
        font-size: 18px;
        font-weight: 700;
        text-align: center;
        transition: all 0.2s;
    }
    
    .score-input:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    .score-input.has-score {
        border-color: var(--primary);
        background: rgba(112, 0, 164, 0.1);
    }
    
    .score-scale {
        font-size: 12px;
        color: var(--text-light);
    }
    
    .skill-body {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-top: 12px;
    }
    
    .notes-section {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .note-group {
        position: relative;
    }
    
    .note-label {
        font-size: 12px;
        font-weight: 700;
        color: var(--text-light);
        margin-bottom: 8px;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .note-textarea {
        width: 100%;
        min-height: 80px;
        padding: 12px;
        background: var(--bg-darker);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        resize: vertical;
        font-family: inherit;
    }
    
    .note-textarea:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    .media-section {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .media-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
    }
    
    .media-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid var(--border);
    }
    
    .media-item img, .media-item video {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .media-delete {
        position: absolute;
        top: 5px;
        right: 5px;
        background: rgba(239, 68, 68, 0.9);
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 12px;
        cursor: pointer;
        opacity: 0;
        transition: opacity 0.2s;
    }
    
    .media-item:hover .media-delete {
        opacity: 1;
    }
    
    .upload-button {
        padding: 10px;
        background: var(--bg-darker);
        border: 1px dashed var(--border);
        border-radius: 6px;
        color: var(--text-light);
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
    }
    
    .upload-button:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .upload-button input {
        display: none;
    }
    
    /* Historical Comparison */
    .comparison-section {
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 24px;
    }
    
    .comparison-header {
        font-size: 20px;
        font-weight: 900;
        color: #fff;
        margin-bottom: 20px;
    }
    
    .comparison-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .comparison-card {
        background: var(--bg-darker);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 16px;
    }
    
    .comparison-date {
        font-size: 14px;
        font-weight: 700;
        color: var(--text-light);
        margin-bottom: 10px;
    }
    
    .score-change {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 4px;
        margin-left: 10px;
    }
    
    .score-change.positive {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
    }
    
    .score-change.negative {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
    }
    
    .score-change.neutral {
        background: rgba(148, 163, 184, 0.2);
        color: var(--text-light);
    }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    
    .modal.active {
        display: flex;
    }
    
    .modal-content {
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 24px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .modal-title {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
    }
    
    .modal-close {
        background: none;
        border: none;
        color: var(--text-light);
        font-size: 24px;
        cursor: pointer;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: var(--text-light);
        margin-bottom: 8px;
        text-transform: uppercase;
    }
    
    .form-input, .form-select {
        width: 100%;
        padding: 12px;
        background: var(--bg-darker);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
    }
    
    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    .btn-submit {
        width: 100%;
        padding: 12px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 14px;
    }
    
    .btn-submit:hover {
        background: var(--primary-hover);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 8px;
    }
    
    .empty-state i {
        font-size: 64px;
        color: #64748b;
        opacity: 0.3;
        margin-bottom: 20px;
    }
    
    .share-link-display {
        background: var(--bg-darker);
        padding: 12px;
        border-radius: 6px;
        border: 1px solid var(--border);
        margin-top: 12px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .share-link-url {
        flex: 1;
        color: var(--primary);
        font-size: 14px;
        font-family: monospace;
        word-break: break-all;
    }
    
    .btn-copy {
        padding: 8px 16px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
    }
    
    @media (max-width: 768px) {
        .skill-body {
            grid-template-columns: 1fr;
        }
        
        .evaluations-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="evaluations-container">
    <?php if ($eval_id && $evaluation): ?>
        <!-- Evaluation Detail View -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-clipboard-check"></i> Skills Evaluation
                    <?php if ($team_mode): ?>
                        <span style="font-size: 14px; font-weight: 600; color: var(--primary); margin-left: 10px;">(Team Mode)</span>
                    <?php endif; ?>
                </h1>
            </div>
            <div class="header-actions">
                <?php if ($isAnyCoach): ?>
                    <!-- Team Mode Toggle -->
                    <label style="display: flex; align-items: center; gap: 8px; padding: 10px 16px; background: var(--bg-dark); border: 1px solid var(--border); border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;">
                        <input 
                            type="checkbox" 
                            <?= $team_mode ? 'checked' : '' ?>
                            onchange="toggleTeamMode(this.checked, <?= json_encode($viewing_athlete_id != $current_user_id ? $viewing_athlete_id : null) ?>, <?= json_encode($eval_id) ?>)"
                            style="width: 18px; height: 18px; cursor: pointer;"
                        >
                        <span>Team Evaluation Mode</span>
                    </label>
                    
                    <div class="athlete-selector" id="eval-detail-athlete-typeahead" style="min-width: 220px;"></div>
                <?php endif; ?>
                <a href="?page=evaluations_skills<?= $isAnyCoach && $viewing_athlete_id != $current_user_id ? '&athlete_id=' . $viewing_athlete_id : '' ?><?= $team_mode ? '&team_mode=1' : '' ?>" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
        
        <div class="eval-detail">
            <div class="eval-detail-header">
                <div>
                    <div class="eval-detail-title">
                        <?= $evaluation['title'] ? htmlspecialchars($evaluation['title']) : 'Skills Evaluation' ?>
                    </div>
                    <div class="eval-detail-meta">
                        <strong><?= htmlspecialchars($athlete_info['first_name'] . ' ' . $athlete_info['last_name']) ?></strong>
                        • <?= date('F j, Y', strtotime($evaluation['evaluation_date'])) ?>
                        • Created by <?= htmlspecialchars(trim(($evaluation['creator_first_name'] ?? '') . ' ' . ($evaluation['creator_last_name'] ?? ''))) ?>
                    </div>
                </div>
                <div class="eval-actions">
                    <?php if ($isAnyCoach && $evaluation['status'] === 'draft'): ?>
                        <button class="btn-complete" onclick="completeEvaluation(<?= $eval_id ?>)">
                            <i class="fas fa-check"></i> Mark Complete
                        </button>
                    <?php endif; ?>
                    <?php if ($isAnyCoach): ?>
                        <button class="btn-share" onclick="generateShareLink(<?= $eval_id ?>)">
                            <i class="fas fa-share-alt"></i> Share
                        </button>
                        <?php if ($evaluation['status'] === 'completed'): ?>
                            <button class="btn-archive" onclick="archiveEvaluation(<?= $eval_id ?>)">
                                <i class="fas fa-archive"></i> Archive
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Skills Grid by Category -->
            <?php if ($team_mode): ?>
                <!-- Team Mode: All categories on one page, no tabs -->
                <?php foreach ($all_categories as $cat_id => $category): ?>
                    <div class="skills-category">
                        <div class="category-header">
                            <i class="fas fa-folder"></i> <?= htmlspecialchars($category['name']) ?>
                            <?php if ($category['description']): ?>
                                <span style="font-size: 14px; font-weight: 400; margin-left: 10px; color: var(--text-light);">
                                    <?= htmlspecialchars($category['description']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php foreach ($category['skills'] as $skill): ?>
                            <div class="skill-item" data-skill-id="<?= $skill['id'] ?>">
                                <div class="skill-header">
                                    <div style="flex: 1;">
                                        <div class="skill-name"><?= htmlspecialchars($skill['name']) ?></div>
                                        <div class="skill-description"><?= htmlspecialchars($skill['description']) ?></div>
                                        <?php if ($skill['criteria']): ?>
                                            <div class="skill-criteria">
                                                <strong>Criteria:</strong> <?= htmlspecialchars($skill['criteria']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="score-input-wrapper">
                                        <input 
                                            type="number" 
                                            class="score-input team-score-input" 
                                            min="1" 
                                            max="10" 
                                            placeholder="—"
                                            data-skill-id="<?= $skill['id'] ?>"
                                            data-athlete-id="<?= $viewing_athlete_id ?>"
                                        >
                                        <span class="score-scale">/ 10</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                
                <!-- Save Button for Team Mode -->
                <?php if ($isAnyCoach): ?>
                    <div style="margin-top: 24px; padding: 20px; background: var(--bg-dark); border: 1px solid var(--border); border-radius: 8px;">
                        <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 600;">
                            <input 
                                type="checkbox" 
                                id="addAthleteToSystem" 
                                style="width: 18px; height: 18px; cursor: pointer;"
                            >
                            <span>Add <?= htmlspecialchars($athlete_info['first_name'] . ' ' . $athlete_info['last_name']) ?> to database (if not already)</span>
                        </label>
                        <button class="btn-submit" onclick="saveTeamEvaluation()">
                            <i class="fas fa-save"></i> Save Evaluation for <?= htmlspecialchars($athlete_info['first_name']) ?>
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Regular Mode: Existing tabbed view -->
                <?php foreach ($categories as $category): ?>
                    <div class="skills-category">
                        <div class="category-header">
                            <i class="fas fa-folder"></i> <?= htmlspecialchars($category['name']) ?>
                        </div>
                        
                        <?php foreach ($category['skills'] as $skill): ?>
                            <div class="skill-item" data-score-id="<?= $skill['id'] ?>">
                                <div class="skill-header">
                                    <div style="flex: 1;">
                                        <div class="skill-name"><?= htmlspecialchars($skill['skill_name']) ?></div>
                                        <div class="skill-description"><?= htmlspecialchars($skill['skill_description']) ?></div>
                                        <?php if ($skill['criteria']): ?>
                                            <div class="skill-criteria">
                                                <strong>Criteria:</strong> <?= htmlspecialchars($skill['criteria']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="score-input-wrapper">
                                        <input 
                                            type="number" 
                                            class="score-input <?= $skill['score'] !== null ? 'has-score' : '' ?>" 
                                            min="1" 
                                        max="10" 
                                        value="<?= $skill['score'] ?? '' ?>"
                                        placeholder="—"
                                        data-score-id="<?= $skill['id'] ?>"
                                        <?= $isAnyCoach ? 'onchange="saveScore(' . $skill['id'] . ', this.value)"' : 'readonly' ?>
                                    >
                                    <span class="score-scale">/ 10</span>
                                </div>
                            </div>
                            
                            <div class="skill-body">
                                <div class="notes-section">
                                    <div class="note-group">
                                        <div class="note-label">
                                            <i class="fas fa-eye"></i> Public Notes (Athlete can see)
                                        </div>
                                        <textarea 
                                            class="note-textarea" 
                                            placeholder="Notes visible to athlete..."
                                            data-score-id="<?= $skill['id'] ?>"
                                            data-type="public"
                                            <?= $isAnyCoach ? 'onchange="saveNotes(' . $skill['id'] . ', \'public\', this.value)"' : 'readonly' ?>
                                        ><?= htmlspecialchars($skill['public_notes'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <?php if ($isAnyCoach): ?>
                                        <div class="note-group">
                                            <div class="note-label">
                                                <i class="fas fa-lock"></i> Private Notes (Coach only)
                                            </div>
                                            <textarea 
                                                class="note-textarea" 
                                                placeholder="Private notes for coaching use..."
                                                data-score-id="<?= $skill['id'] ?>"
                                                data-type="private"
                                                onchange="saveNotes(<?= $skill['id'] ?>, 'private', this.value)"
                                            ><?= htmlspecialchars($skill['private_notes'] ?? '') ?></textarea>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="media-section">
                                    <div class="note-label">
                                        <i class="fas fa-photo-video"></i> Media Attachments
                                    </div>
                                    <div class="media-grid" data-score-id="<?= $skill['id'] ?>">
                                        <?php if (isset($media_by_score[$skill['id']])): ?>
                                            <?php foreach ($media_by_score[$skill['id']] as $media): ?>
                                                <div class="media-item" data-media-id="<?= $media['id'] ?>">
                                                    <?php if ($media['media_type'] === 'image'): ?>
                                                        <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $media['media_url'])) ?>" alt="<?= htmlspecialchars($media['caption'] ?? '') ?>">
                                                    <?php else: ?>
                                                        <video src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $media['media_url'])) ?>" controls></video>
                                                    <?php endif; ?>
                                                    <?php if ($isAnyCoach): ?>
                                                        <button class="media-delete" onclick="deleteMedia(<?= $media['id'] ?>)">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isAnyCoach): ?>
                                        <label class="upload-button">
                                            <i class="fas fa-upload"></i> Upload Media
                                            <input type="file" accept="image/*,video/*" onchange="uploadMedia(<?= $skill['id'] ?>, this.files[0])">
                                        </label>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <?php endif; ?> <!-- End team mode conditional -->
        </div>
        
        <!-- Historical Comparison -->
        <?php if (!empty($historical)): ?>
            <div class="comparison-section">
                <div class="comparison-header">
                    <i class="fas fa-chart-line"></i> Historical Comparison
                </div>
                <div class="comparison-grid">
                    <?php foreach ($historical as $hist_id => $hist_eval): ?>
                        <div class="comparison-card">
                            <div class="comparison-date">
                                <?= date('M j, Y', strtotime($hist_eval['date'])) ?>
                                <?php if ($hist_eval['title']): ?>
                                    <br><small style="color: var(--text-light);"><?= htmlspecialchars($hist_eval['title']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 12px; color: var(--text-light); margin-top: 10px;">
                                <?php
                                $comparison_count = 0;
                                foreach ($categories as $category):
                                    foreach ($category['skills'] as $skill):
                                        if (isset($hist_eval['scores'][$skill['skill_id']]) && $skill['score'] !== null):
                                            $old_score = $hist_eval['scores'][$skill['skill_id']];
                                            $new_score = $skill['score'];
                                            $diff = $new_score - $old_score;
                                            $comparison_count++;
                                            
                                            if ($comparison_count <= 5): ?>
                                                <div style="margin: 5px 0;">
                                                    <?= htmlspecialchars($skill['skill_name']) ?>: <?= $old_score ?>
                                                    <?php if ($diff > 0): ?>
                                                        <span class="score-change positive">
                                                            <i class="fas fa-arrow-up"></i> +<?= $diff ?>
                                                        </span>
                                                    <?php elseif ($diff < 0): ?>
                                                        <span class="score-change negative">
                                                            <i class="fas fa-arrow-down"></i> <?= $diff ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="score-change neutral">—</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif;
                                        endif;
                                    endforeach;
                                endforeach;
                                
                                if ($comparison_count > 5): ?>
                                    <div style="margin-top: 10px; font-style: italic;">
                                        +<?= $comparison_count - 5 ?> more skills
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- List View -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-clipboard-check"></i> Skills Evaluations
                <?php if ($athlete_info): ?>
                    - <?= htmlspecialchars($athlete_info['first_name'] . ' ' . $athlete_info['last_name']) ?>
                <?php endif; ?>
            </h1>
            <div class="header-actions">
                <?php if ($isAnyCoach): ?>
                    <div id="eval-list-athlete-typeahead" style="min-width: 220px;"></div>
                    <button class="btn-create" onclick="openCreateModal()">
                        <i class="fas fa-plus"></i> New Evaluation
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (empty($evaluations_list)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-check"></i>
                <h2 style="font-size: 24px; color: #fff; margin-bottom: 10px;">No Evaluations</h2>
                <p style="color: #64748b;">
                    <?php if ($isAnyCoach): ?>
                        Create your first skills evaluation to get started
                    <?php else: ?>
                        Your coach hasn't created any skills evaluations yet
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="evaluations-grid">
                <?php foreach ($evaluations_list as $eval): ?>
                    <div class="eval-card" onclick="window.location='?page=evaluations_skills&eval_id=<?= $eval['id'] ?><?= $isAnyCoach && $viewing_athlete_id != $current_user_id ? '&athlete_id=' . $viewing_athlete_id : '' ?>'">
                        <div class="eval-card-header">
                            <div>
                                <div class="eval-card-title">
                                    <?= $eval['title'] ? htmlspecialchars($eval['title']) : 'Skills Evaluation' ?>
                                </div>
                                <div class="eval-card-date">
                                    <?= date('F j, Y', strtotime($eval['evaluation_date'])) ?>
                                </div>
                            </div>
                            <span class="eval-status status-<?= $eval['status'] ?>">
                                <?= $eval['status'] ?>
                            </span>
                        </div>
                        <div style="font-size: 14px; color: var(--text-light); margin-top: 10px;">
                            Created by <?= htmlspecialchars(trim(($eval['creator_first_name'] ?? '') . ' ' . ($eval['creator_last_name'] ?? ''))) ?>
                        </div>
                        <div class="eval-card-progress">
                            <div class="progress-text">
                                <?= $eval['completed_scores'] ?> / <?= $eval['total_scores'] ?> skills scored
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $eval['total_scores'] > 0 ? ($eval['completed_scores'] / $eval['total_scores'] * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Create Evaluation Modal -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Create Skills Evaluation</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeCreateModal()">&times;</button>
        </div>
        <form id="createForm" method="POST" action="process_evaluations.php" onsubmit="createEvaluation(event)">
            <?= csrfTokenInput() ?>
            <div class="form-group">
                <label class="form-label">Athlete</label>
                <div id="eval-create-athlete-typeahead"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Evaluation Date</label>
                <input type="date" name="evaluation_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Title (Optional)</label>
                <input type="text" name="title" class="form-input" placeholder="e.g., Mid-Season Assessment">
            </div>
            <button type="submit" class="btn-submit">
                <i class="fas fa-plus"></i> Create Evaluation
            </button>
        </form>
    </div>
</div>

<!-- Share Link Modal -->
<div id="shareModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Share Evaluation</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeShareModal()">&times;</button>
        </div>
        <div id="shareLinkContent"></div>
    </div>
</div>

<script>
function toggleTeamMode(isChecked, athleteId, evalId) {
    const params = new URLSearchParams();
    params.set('page', 'evaluations_skills');
    params.set('team_mode', isChecked ? '1' : '0');
    if (athleteId) params.set('athlete_id', athleteId);
    if (evalId) params.set('eval_id', evalId);
    window.location = '?' + params.toString();
}

function switchAthlete(athleteId, evalId = null) {
    if (!athleteId) return;
    const params = new URLSearchParams();
    params.set('page', 'evaluations_skills');
    params.set('athlete_id', athleteId);
    if (evalId) params.set('eval_id', evalId);
    window.location = '?' + params.toString();
}

function openCreateModal() {
    document.getElementById('createModal').classList.add('active');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.remove('active');
}

function closeShareModal() {
    document.getElementById('shareModal').classList.remove('active');
}

async function createEvaluation(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'create_evaluation');
    
    try {
        const response = await fetch('process_eval_skills.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            window.location = '?page=evaluations_skills&eval_id=' + data.evaluation_id + '&athlete_id=' + formData.get('athlete_id');
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    } catch (error) {
        showToast('Error creating evaluation', 'error');
    }
}

async function saveScore(scoreId, value) {
    const formData = new FormData();
    formData.append('action', 'save_score');
    formData.append('score_id', scoreId);
    formData.append('score', value);
    formData.append('csrf_token', '<?= generateCsrfToken() ?>');
    
    try {
        const response = await fetch('process_eval_skills.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            const input = document.querySelector(`input[data-score-id="${scoreId}"]`);
            if (value) {
                input.classList.add('has-score');
            } else {
                input.classList.remove('has-score');
            }
        } else {
            showToast('Error saving score', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function saveNotes(scoreId, type, value) {
    const formData = new FormData();
    formData.append('action', 'save_notes');
    formData.append('score_id', scoreId);
    formData.append('note_type', type);
    formData.append('notes', value);
    formData.append('csrf_token', '<?= generateCsrfToken() ?>');
    
    try {
        const response = await fetch('process_eval_skills.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (!data.success) {
            showToast('Error saving notes', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function uploadMedia(scoreId, file) {
    if (!file) return;
    
    const formData = new FormData();
    formData.append('action', 'upload_media');
    formData.append('score_id', scoreId);
    formData.append('media', file);
    formData.append('csrf_token', '<?= generateCsrfToken() ?>');
    
    try {
        const response = await fetch('process_eval_skills.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            persistToast(data.message || 'Operation completed successfully', 'success');
            location.reload();
        } else {
            showToast('Error uploading media: ' + data.message, 'error');
        }
    } catch (error) {
        showToast('Error uploading media', 'error');
    }
}

async function deleteMedia(mediaId) {
    if (!await showConfirmModal('Delete this media?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_media');
    formData.append('media_id', mediaId);
    formData.append('csrf_token', '<?= generateCsrfToken() ?>');
    
    try {
        const response = await fetch('process_eval_skills.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            document.querySelector(`[data-media-id="${mediaId}"]`).remove();
        } else {
            showToast('Error deleting media', 'error');
        }
    } catch (error) {
        showToast('Error deleting media', 'error');
    }
}

async function completeEvaluation(evalId) {
    if (!await showConfirmModal('Mark this evaluation as completed?')) return;
    
    const formData = new FormData();
    formData.append('action', 'complete_evaluation');
    formData.append('evaluation_id', evalId);
    formData.append('csrf_token', '<?= generateCsrfToken() ?>');
    
    try {
        const response = await fetch('process_eval_skills.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            persistToast(data.message || 'Operation completed successfully', 'success');
            location.reload();
        } else {
            showToast('Error completing evaluation', 'error');
        }
    } catch (error) {
        showToast('Error completing evaluation', 'error');
    }
}

async function archiveEvaluation(evalId) {
    if (!await showConfirmModal('Archive this evaluation?')) return;
    
    const formData = new FormData();
    formData.append('action', 'archive_evaluation');
    formData.append('evaluation_id', evalId);
    formData.append('csrf_token', '<?= generateCsrfToken() ?>');
    
    try {
        const response = await fetch('process_eval_skills.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            persistToast(data.message || 'Operation completed successfully', 'success');
            location.reload();
        } else {
            showToast('Error archiving evaluation', 'error');
        }
    } catch (error) {
        showToast('Error archiving evaluation', 'error');
    }
}

async function generateShareLink(evalId) {
    const formData = new FormData();
    formData.append('action', 'generate_share_link');
    formData.append('evaluation_id', evalId);
    formData.append('csrf_token', '<?= generateCsrfToken() ?>');
    
    try {
        const response = await fetch('process_eval_skills.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            const content = document.getElementById('shareLinkContent');
            content.innerHTML = `
                <p style="color: var(--text-light); margin-bottom: 12px;">
                    Share this link to allow external viewing of the evaluation (public notes only).
                </p>
                <div class="share-link-display">
                    <div class="share-link-url" id="shareUrl">${data.share_url}</div>
                    <button class="btn-copy" onclick="copyShareLink()">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <button class="btn-submit" style="margin-top: 20px; background: var(--danger);" onclick="revokeShareLink(${evalId})">
                    <i class="fas fa-ban"></i> Revoke Public Access
                </button>
            `;
            document.getElementById('shareModal').classList.add('active');
        } else {
            showToast('Error generating share link', 'error');
        }
    } catch (error) {
        showToast('Error generating share link', 'error');
    }
}

function copyShareLink() {
    const url = document.getElementById('shareUrl').textContent;
    navigator.clipboard.writeText(url).then(() => {
        showToast('Link copied to clipboard!', 'success');
    });
}

async function revokeShareLink(evalId) {
    if (!await showConfirmModal('Revoke public access to this evaluation?')) return;
    
    const formData = new FormData();
    formData.append('action', 'revoke_share_link');
    formData.append('evaluation_id', evalId);
    formData.append('csrf_token', '<?= generateCsrfToken() ?>');
    
    try {
        const response = await fetch('process_eval_skills.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            closeShareModal();
            showToast('Public access revoked', 'success');
        } else {
            showToast('Error revoking access', 'error');
        }
    } catch (error) {
        showToast('Error revoking access', 'error');
    }
}

// Team Mode: Save evaluation for current athlete
async function saveTeamEvaluation() {
    const athleteId = <?= $viewing_athlete_id ?>;
    const athleteName = '<?= htmlspecialchars($athlete_info['first_name'] . ' ' . $athlete_info['last_name']) ?>';
    const addToDatabase = document.getElementById('addAthleteToSystem').checked;
    
    // Collect all scores
    const scores = [];
    document.querySelectorAll('.team-score-input').forEach(input => {
        const scoreValue = input.value;
        if (scoreValue && scoreValue !== '') {
            scores.push({
                skill_id: input.dataset.skillId,
                score: parseInt(scoreValue)
            });
        }
    });
    
    if (scores.length === 0) {
        showToast('Please enter at least one skill score before saving.', 'error');
        return;
    }
    
    if (!await showConfirmModal(`Save ${scores.length} skill scores for ${athleteName}?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'save_team_evaluation');
    formData.append('athlete_id', athleteId);
    formData.append('add_to_database', addToDatabase ? '1' : '0');
    formData.append('scores', JSON.stringify(scores));
    formData.append('csrf_token', '<?= generateCsrfToken() ?>');
    
    try {
        const response = await fetch('process_eval_skills.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            persistToast('Evaluation saved successfully!', 'success');
            // Keep same URL for easy athlete switching
            location.reload();
        } else {
            showToast('Error saving evaluation: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error saving evaluation. Please try again.', 'error');
    }
}
</script>
<script>
<?php if ($isAnyCoach): ?>
// Detail view athlete switcher (with eval_id context)
<?php if (isset($eval_id) && $eval_id): ?>
(function() {
    var container = document.getElementById('eval-detail-athlete-typeahead');
    if (container) {
        new ArcticTypeahead({
            container: container,
            name: 'athlete_id',
            placeholder: 'Quick switch athlete…',
            roles: 'athlete',
            multiple: false,
            onSelect: function(item) { switchAthlete(item.id, <?= json_encode($eval_id) ?>); }
        });
    }
})();
<?php endif; ?>

// List view athlete switcher
(function() {
    var container = document.getElementById('eval-list-athlete-typeahead');
    if (container) {
        new ArcticTypeahead({
            container: container,
            name: 'athlete_id',
            placeholder: 'Search for an athlete…',
            roles: 'athlete',
            multiple: false,
            navigateOnSelect: '?page=evaluations_skills&athlete_id=',
            preSelected: <?= $athlete_info ? json_encode([['id' => (int)$viewing_athlete_id, 'name' => $athlete_info['first_name'] . ' ' . $athlete_info['last_name'], 'role' => 'Athlete']]) : '[]' ?>
        });
    }
})();

// Create evaluation modal athlete selector
(function() {
    var container = document.getElementById('eval-create-athlete-typeahead');
    if (container) {
        new ArcticTypeahead({
            container: container,
            name: 'athlete_id',
            placeholder: 'Search for an athlete…',
            roles: 'athlete',
            multiple: false,
            required: true,
            preSelected: <?= $athlete_info ? json_encode([['id' => (int)$viewing_athlete_id, 'name' => $athlete_info['first_name'] . ' ' . $athlete_info['last_name'], 'role' => 'Athlete']]) : '[]' ?>
        });
    }
})();
<?php endif; ?>
</script>
