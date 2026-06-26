<?php
/**
 * Authentication guard. Include at the top of every protected admin page.
 *
 * Enforces a login session with a bcrypt password and login throttling.
 * Boots the session, the DB, and the default password on first run.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    ]);
    session_start();
}

/**
 * Ensure a default admin password exists if none has been set.
 *
 * Default credentials are shown once via dk_first_run_token().
 * The user should change this immediately from Settings.
 */
function dk_ensure_admin_password(): void
{
    if (dk_setting('admin_pass') === null) {
        // Default password — MUST be changed on first login.
        $default = 'dk-admin-2026';
        dk_set_setting('admin_pass', password_hash($default, PASSWORD_DEFAULT));
        dk_set_setting('admin_user', 'admin');
    }
}
dk_ensure_admin_password();

/**
 * Is the current session authenticated?
 */
function dk_is_logged_in(): bool
{
    return !empty($_SESSION['dk_admin_id']);
}

/**
 * Attempt a login. Returns true on success, false on failure.
 * Includes simple per-IP throttling stored in the session.
 */
function dk_try_login(string $username, string $password): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Throttle: max 5 attempts per 5 minutes per IP.
    $attempts = $_SESSION['dk_attempts'][$ip] ?? ['n' => 0, 'first' => time()];
    if ((time() - $attempts['first']) > 300) {
        $attempts = ['n' => 0, 'first' => time()];
    }
    if ($attempts['n'] >= 5) {
        usleep(500000); // slow down brute force
        return false;
    }

    $storedUser = dk_setting('admin_user', 'admin');
    $storedHash = dk_setting('admin_pass');

    $ok = ($username === $storedUser)
        && $storedHash
        && password_verify($password, $storedHash);

    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['dk_admin_id']   = $storedUser;
        $_SESSION['dk_admin_seen'] = time();
        unset($_SESSION['dk_attempts'][$ip]);
        return true;
    }

    $attempts['n']++;
    $_SESSION['dk_attempts'][$ip] = $attempts;
    return false;
}

/**
 * Require a login; if not authenticated, redirect to the login page.
 */
function dk_require_login(): void
{
    if (dk_is_logged_in()) {
        // Idle timeout after 2 hours.
        if ((time() - ($_SESSION['dk_admin_seen'] ?? 0)) > 7200) {
            dk_logout();
            header('Location: index.php');
            exit;
        }
        $_SESSION['dk_admin_seen'] = time();
        return;
    }

    $here = basename($_SERVER['PHP_SELF'] ?? '');
    if ($here !== 'index.php') {
        header('Location: index.php');
        exit;
    }
}

/**
 * Log the current admin out.
 */
function dk_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

dk_require_login();
