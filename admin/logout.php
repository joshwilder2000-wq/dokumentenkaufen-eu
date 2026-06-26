<?php
/**
 * Log the admin out and return to the login screen.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

dk_logout();
header('Location: index.php');
exit;
