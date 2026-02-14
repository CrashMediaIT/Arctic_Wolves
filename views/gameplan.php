<?php
/**
 * Game Plan View - Redirect to Standalone Module
 *
 * The Game Plan module is a separate standalone dashboard.
 * This view redirects users who access it via the dashboard route.
 */
header("Location: /gameplan.php");
exit();
?>
