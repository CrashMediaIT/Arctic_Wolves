<?php
// Video Parent Page with Tabs
$tab = $_GET['page'] ?? 'video';
if ($tab === 'video') $tab = 'drill_review'; // Default tab
?>

<div class="page-header">
    <h1><i class="fa-solid fa-video"></i> Video</h1>
    <p>Review your drill videos and upload new footage for coach analysis</p>
</div>

<div class="tab-navigation">
    <a href="?page=drill_review" class="tab-link <?= $tab === 'drill_review' ? 'active' : '' ?>">
        <i class="fa-solid fa-film"></i> Drill Review
    </a>
    <a href="?page=coaches_reviews" class="tab-link <?= $tab === 'coaches_reviews' ? 'active' : '' ?>">
        <i class="fa-solid fa-comments"></i> Coaches Reviews
        <?php if($isAnyCoach): ?>
        <span style="margin-left: 8px; font-size: 10px; background: var(--primary); padding: 2px 8px; border-radius: 4px;">[Upload]</span>
        <?php endif; ?>
    </a>
</div>

<div class="tab-content">
    <?php
    if ($tab === 'drill_review') {
        include 'video_drill_review.php';
    } elseif ($tab === 'coaches_reviews') {
        include 'video_coach_reviews.php';
    }
    ?>
</div>
