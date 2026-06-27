<?php /** Shared admin layout header. */ ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo e($pageTitle ?? 'Admin'); ?> · Dokuments Hub Admin</title>
    <link rel="stylesheet" href="assets/admin.css?v=<?php echo date('Ymd'); ?>">
</head>
<body>
<header class="dk-topbar">
    <div class="dk-topbar-inner">
        <a href="dashboard.php" class="dk-brand">
            <img src="../images/logo-new.png" alt="" width="120" height="40">
            <span>Admin</span>
        </a>
        <?php if (dk_is_logged_in()): ?>
        <nav class="dk-topnav">
            <a href="dashboard.php">Produkte</a>
            <a href="blog-dashboard.php">Blog</a>
            <a href="seo.php">SEO</a>
            <a href="product-edit.php">Neues Produkt</a>
            <a href="post-edit.php">Neuer Beitrag</a>
            <a href="settings.php">Einstellungen</a>
            <a href="../index.html" target="_blank">Site ↗</a>
            <a href="logout.php">Abmelden</a>
        </nav>
        <?php endif; ?>
    </div>
</header>
<main class="dk-main">
