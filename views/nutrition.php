<?php
/**
 * Nutrition Plan Builder
 * Create and manage nutrition plans for athletes
 */

require_once __DIR__ . '/../security.php';

$is_coach = ($user_role === 'coach' || $user_role === 'coach_plus' || $user_role === 'admin');
$viewing_user_id = $user_id;

// Allow coaches to view athlete nutrition plans
if ($is_coach && isset($_GET['athlete_id'])) {
    $viewing_user_id = intval($_GET['athlete_id']);
}

// Get nutrition plans
$plans_stmt = $pdo->prepare("
    SELECT np.*, u.first_name, u.last_name, coach.first_name as coach_first, coach.last_name as coach_last
    FROM nutrition_plans np
    INNER JOIN users u ON np.user_id = u.id
    LEFT JOIN users coach ON np.coach_id = coach.id
    WHERE np.user_id = ?
    ORDER BY np.created_at DESC
");
$plans_stmt->execute([$viewing_user_id]);
$plans = $plans_stmt->fetchAll();
$plans = decryptUserRows($plans);
?>

<style>
/* Nutrition Page Header - Financial Reports Hub Style */
.nutrition-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 20px;
}
.nutrition-page-header .page-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}
.nutrition-page-header .page-header-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--primary), #5a0080);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.3);
}
.nutrition-page-header .page-title {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 4px 0;
    letter-spacing: -0.5px;
}
.nutrition-page-header .page-description {
    font-size: 14px;
    color: var(--text-dim);
    margin: 0;
}
.nutrition-page-header .btn-library {
    background: var(--primary);
    color: #fff;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.nutrition-page-header .btn-library:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(107, 70, 193, 0.3);
}

/* Nutrition Card Styles */
.nutrition-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    transition: all 0.3s;
}
.nutrition-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}
.nutrition-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 12px;
}
.nutrition-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}
.nutrition-meta {
    font-size: 13px;
    color: var(--text-dim);
    margin-bottom: 5px;
}
.nutrition-content {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    margin-top: 12px;
    color: var(--text-dim);
    line-height: 1.8;
    white-space: pre-wrap;
}
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
}
.empty-state i {
    font-size: 64px;
    color: var(--text-dim);
    opacity: 0.3;
    margin-bottom: 20px;
}
.nutrition-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary) 0%, #5a0080 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 36px;
    margin: 0 auto 20px auto;
}
</style>

<div class="nutrition-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-apple-whole"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Nutrition Plans</h1>
            <p class="page-description">View your personalized nutrition programs</p>
        </div>
    </div>
    <?php if ($is_coach): ?>
        <a href="?page=library_nutrition" class="btn-library">
            <i class="fas fa-book"></i> Nutrition Library
        </a>
    <?php endif; ?>
</div>

<?php if (empty($plans)): ?>
    <div class="empty-state">
        <div class="nutrition-icon">
            <i class="fas fa-apple-whole"></i>
        </div>
        <h2 style="font-size: 24px; color: var(--text-white); margin-bottom: 10px;">No Nutrition Plans</h2>
        <p style="color: var(--text-dim);">Your coach will create nutrition plans for you here</p>
    </div>
<?php else: ?>
    <?php foreach ($plans as $plan): ?>
        <div class="nutrition-card">
            <div class="nutrition-header">
                <div>
                    <h3 class="nutrition-title"><?= htmlspecialchars($plan['title']) ?></h3>
                    <div class="nutrition-meta">
                        <i class="fas fa-calendar"></i>
                        Created: <?= date('M d, Y', strtotime($plan['created_at'])) ?>
                    </div>
                    <?php if ($plan['coach_first']): ?>
                        <div class="nutrition-meta">
                            <i class="fas fa-user-tie"></i>
                            Coach: <?= htmlspecialchars($plan['coach_first'] . ' ' . $plan['coach_last']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($plan['content']): ?>
                <div class="nutrition-content">
                    <?= nl2br(htmlspecialchars($plan['content'])) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
