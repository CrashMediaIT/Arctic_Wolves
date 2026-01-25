<?php
// Video Parent Page with Tabs
$tab = $_GET['page'] ?? 'video';
if ($tab === 'video') $tab = 'drill_review'; // Default tab
?>

<div class="page-header">
    <h1><i class="fa-solid fa-video"></i> Video</h1>
    <p>Review your drill videos and upload new footage for coach analysis</p>
</div>

<div class="video-nav-container">
    <div class="tab-navigation" data-component="TabNavigation">
        <a href="?page=drill_review" class="tab-link <?= $tab === 'drill_review' ? 'active' : '' ?>" data-tab="drill_review">
            <i class="fa-solid fa-film"></i> Drill Review
        </a>
        <a href="?page=coaches_reviews" class="tab-link <?= $tab === 'coaches_reviews' ? 'active' : '' ?>" data-tab="coaches_reviews">
            <i class="fa-solid fa-comments"></i> Coaches Reviews
        </a>
    </div>
    <?php if($isAnyCoach): ?>
    <a href="?page=coaches_reviews#upload-tab" class="btn btn-primary btn-upload-nav" onclick="activateUploadTab()">
        <i class="fa-solid fa-upload"></i> Upload Video
    </a>
    <?php endif; ?>
</div>

<div class="page-tab-content">
    <?php
    if ($tab === 'drill_review') {
        include __DIR__ . '/video_drill_review.php';
    } elseif ($tab === 'coaches_reviews') {
        include __DIR__ . '/video_coach_reviews.php';
    }
    ?>
</div>

<style>
.video-nav-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 30px;
    border-bottom: 2px solid var(--border);
    padding-bottom: 0;
}

.video-nav-container .tab-navigation {
    margin-bottom: 0;
    border-bottom: none;
}

.btn-upload-nav {
    margin-bottom: 12px;
}

.btn-upload-nav i {
    color: #fff;
}
</style>

<script>
function activateUploadTab() {
    // Switch to coaches_reviews page and activate upload tab
    setTimeout(function() {
        const uploadBtn = document.querySelector('[data-tab="upload"]');
        if (uploadBtn) uploadBtn.click();
    }, 100);
}
</script>
