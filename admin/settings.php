<?php
/**
 * Admin settings: change password, set the public site URL.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$saved = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    dk_csrf_check();
    $section = (string) ($_POST['section'] ?? '');

    if ($section === 'credentials') {
        $newUser = dk_clean((string) ($_POST['admin_user'] ?? 'admin'));
        $newPass = (string) ($_POST['new_password'] ?? '');
        $curPass = (string) ($_POST['current_password'] ?? '');

        // Verify current password before allowing changes.
        $storedHash = dk_setting('admin_pass');
        if (!password_verify($curPass, (string) $storedHash)) {
            $errors[] = 'Das aktuelle Passwort ist nicht korrekt.';
        }

        if ($newUser === '') {
            $errors[] = 'Benutzername darf nicht leer sein.';
        }

        if (!$errors) {
            dk_set_setting('admin_user', $newUser);
            if ($newPass !== '') {
                if (strlen($newPass) < 8) {
                    $errors[] = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
                } else {
                    dk_set_setting('admin_pass', password_hash($newPass, PASSWORD_DEFAULT));
                }
            }
            if (!$errors) {
                dk_flash('success', 'Zugangsdaten aktualisiert.');
                header('Location: settings.php');
                exit;
            }
        }
    } elseif ($section === 'site') {
        $url = rtrim(dk_clean((string) ($_POST['site_url'] ?? '')), '/');
        if ($url !== '' && !preg_match('#^https?://[^/]+$#i', $url)) {
            $errors[] = 'Die Site-URL muss mit http:// oder https:// beginnen und keinen Pfad enthalten.';
        }
        if (!$errors) {
            dk_set_setting('site_url', $url);
            dk_flash('success', 'Site-URL gespeichert.');
            header('Location: settings.php');
            exit;
        }
    }
}

$curUser = dk_setting('admin_user', 'admin');
$curUrl  = dk_setting('site_url') ?: dk_site_url();

$pageTitle = 'Einstellungen';
include __DIR__ . '/partials/header.php';
?>
<div class="dk-page-head"><h1>Einstellungen</h1></div>

<?php if ($msg = dk_flash('success')): ?>
    <div class="dk-alert dk-alert-success"><?php echo e($msg); ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="dk-alert dk-alert-error"><?php echo e($err); ?></div>
<?php endforeach; ?>

<div class="dk-cards">
    <form method="post" class="dk-card">
        <?php echo dk_csrf_field(); ?>
        <input type="hidden" name="section" value="credentials">
        <h3>Zugangsdaten</h3>
        <div class="dk-field">
            <label for="admin_user">Benutzername</label>
            <input type="text" id="admin_user" name="admin_user" value="<?php echo e($curUser); ?>" required>
        </div>
        <div class="dk-field">
            <label for="current_password">Aktuelles Passwort <span class="dk-req">*</span></label>
            <input type="password" id="current_password" name="current_password" required>
        </div>
        <div class="dk-field">
            <label for="new_password">Neues Passwort <small>(leer lassen = nicht ändern)</small></label>
            <input type="password" id="new_password" name="new_password" autocomplete="new-password">
            <small class="dk-muted">Mindestens 8 Zeichen.</small>
        </div>
        <button type="submit" class="dk-btn dk-btn-primary">Speichern</button>
    </form>

    <form method="post" class="dk-card">
        <?php echo dk_csrf_field(); ?>
        <input type="hidden" name="section" value="site">
        <h3>Website</h3>
        <div class="dk-field">
            <label for="site_url">Öffentliche Site-URL</label>
            <input type="text" id="site_url" name="site_url" value="<?php echo e($curUrl); ?>" placeholder="https://dokumentenkaufen.eu">
            <small class="dk-muted">Wird für Canonical, Open Graph, Sitemaps und JSON-LD verwendet. Ohne abschließenden Schrägstrich.</small>
        </div>
        <button type="submit" class="dk-btn dk-btn-primary">Speichern</button>
    </form>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
