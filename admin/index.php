<?php
/**
 * Admin login page.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// If already logged in, go straight to the dashboard.
if (dk_is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dk_csrf_check();
    $user = (string) ($_POST['username'] ?? '');
    $pass = (string) ($_POST['password'] ?? '');

    if (dk_try_login($user, $pass)) {
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Anmeldung fehlgeschlagen. Bitte Benutzername und Passwort prüfen.';
}

$pageTitle = 'Anmelden';
include __DIR__ . '/partials/header.php';
?>
<div class="dk-login-wrap">
    <form method="post" class="dk-card dk-login-card">
        <div class="dk-login-logo">
            <img src="../images/logo-new.png" alt="Dokuments Hub" width="200" height="67">
        </div>
        <h1>Admin-Anmeldung</h1>

        <?php if ($error): ?>
            <div class="dk-alert dk-alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="dk-field">
            <label for="username">Benutzername</label>
            <input type="text" id="username" name="username" autocomplete="username" required autofocus>
        </div>
        <div class="dk-field">
            <label for="password">Passwort</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>

        <?php echo dk_csrf_field(); ?>
        <button type="submit" class="dk-btn dk-btn-primary dk-btn-block">Anmelden</button>

        <p class="dk-muted dk-login-hint">
            Standardzugang beim ersten Start: <code>admin</code> / <code>dk-admin-2026</code><br>
            Bitte nach der ersten Anmeldung unter <strong>Einstellungen</strong> ändern.
        </p>
    </form>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
