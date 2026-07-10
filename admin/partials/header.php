<?php /** Shared admin layout header. */ ?>
<!DOCTYPE html>
<html lang="en">
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
            <a href="dashboard.php">Products</a>
            <a href="blog-dashboard.php">Blog</a>
            <a href="reviews.php">Reviews</a>
            <a href="forms.php">Forms</a>
            <a href="chat.php">Chat</a>
            <a href="seo.php">SEO</a>
            <a href="redirects.php">Redirects</a>
            <a href="product-edit.php">+ Product</a>
            <a href="post-edit.php">+ Post</a>
            <a href="settings.php">Settings</a>
            <a href="../index.html" target="_blank">Site ↗</a>
            <a href="logout.php">Logout</a>
        </nav>
        <?php endif; ?>
    </div>
</header>
<main class="dk-main">
