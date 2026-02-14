<?php
/**
 * Game Plan View - Redirect to Standalone Module
 *
 * The Game Plan module is now a standalone dashboard at /gameplan.php.
 * This view redirects users who access it via the old ?page=gameplan route.
 */
header("Location: /gameplan.php");
exit();
?>
