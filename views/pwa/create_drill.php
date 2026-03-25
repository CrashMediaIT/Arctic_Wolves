<?php
/**
 * PWA Create Drill - Includes drills view and auto-opens the create modal.
 */
include __DIR__ . "/drills.php";
?>
<script>
(function() {
    // Auto-open the create modal when navigating to create_drill
    if (typeof mOpenCreateModal === 'function') {
        setTimeout(function() { mOpenCreateModal(); }, 100);
    }
})();
</script>