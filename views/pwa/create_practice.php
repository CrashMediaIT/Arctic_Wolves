<?php
/**
 * PWA Create Practice - Includes practice view and auto-opens the create modal.
 */
include __DIR__ . "/practice.php";
?>
<script>
(function() {
    // Auto-open the create modal when navigating to create_practice
    if (typeof openCreateModal === 'function') {
        setTimeout(function() { openCreateModal(); }, 100);
    }
})();
</script>