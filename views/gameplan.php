<?php
/**
 * Game Plan View - Redirect to Integrated Module
 *
 * The Game Plan module is now integrated into the main dashboard.
 * This view redirects users who access it via the old route.
 */
header("Location: /dashboard.php?page=gameplan");
exit();
?>
