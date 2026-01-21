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
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    .btn-create {
        background: var(--primary);
        color: #fff;
        padding: 12px 24px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 700;
        font-size: 14px;
        transition: all 0.2s;
    }
    .btn-create:hover {
        background: #e64500;
    }
    .nutrition-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 20px;
        transition: all 0.2s;
    }
    .nutrition-card:hover {
        border-color: var(--primary);
    }
    .nutrition-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 15px;
    }
    .nutrition-title {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 10px;
    }
    .nutrition-meta {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 5px;
    }
    .nutrition-content {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 20px;
        margin-top: 15px;
        color: #94a3b8;
        line-height: 1.8;
        white-space: pre-wrap;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
    }
    .empty-state i {
        font-size: 64px;
        color: #64748b;
        opacity: 0.3;
        margin-bottom: 20px;
    }
    .nutrition-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--primary) 0%, #4a0070 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 36px;
        margin-bottom: 20px;
    }
</style>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-apple-whole"></i> Nutrition Plans
    </h1>
    <?php if ($is_coach): ?>
        <a href="?page=library_nutrition" class="btn-create">
            <i class="fas fa-book"></i> Nutrition Library
        </a>
    <?php endif; ?>
</div>

<?php if (empty($plans)): ?>
    <div class="empty-state">
        <div class="nutrition-icon">
            <i class="fas fa-apple-whole"></i>
        </div>
        <h2 style="font-size: 24px; color: #fff; margin-bottom: 10px;">No Nutrition Plans</h2>
        <p style="color: #64748b;">Your coach will create nutrition plans for you here</p>
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
