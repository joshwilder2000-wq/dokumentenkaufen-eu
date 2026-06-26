<?php
/**
 * SQLite database connection + schema bootstrap.
 *
 * Auto-creates data/products.sqlite and its tables on first run.
 * The database file lives in /data/ which is protected by .htaccess.
 *
 * Host-agnostic: works on any PHP host with the pdo_sqlite extension.
 */

declare(strict_types=1);

/**
 * Resolve the site root (the folder containing index.html, admin/, data/, ...).
 * admin/lib/db.php is two levels deep under the site root.
 */
function dk_site_root(): string
{
    return dirname(__DIR__, 2);
}

/**
 * Return a single, shared PDO connection to the SQLite database.
 * Creates the database file and schema on first call.
 */
function dk_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dataDir = dk_site_root() . '/data';
    if (!is_dir($dataDir)) {
        if (!@mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
            throw new RuntimeException("Cannot create data directory: {$dataDir}");
        }
    }

    // Defense in depth: drop a .htaccess here too in case the root one ever moves.
    $htaccess = $dataDir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Order allow,deny\nDeny from all\n");
    }

    $dbPath = $dataDir . '/products.sqlite';
    $isNew  = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Better concurrency for SQLite.
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($isNew) {
        dk_create_schema($pdo);
    } else {
        dk_ensure_schema($pdo);
    }

    return $pdo;
}

/**
 * Create the full schema for a brand-new database.
 */
function dk_create_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            slug              TEXT    NOT NULL UNIQUE,
            title             TEXT    NOT NULL,
            meta_description  TEXT    NOT NULL DEFAULT '',
            meta_keywords     TEXT    NOT NULL DEFAULT '',
            category          TEXT    NOT NULL DEFAULT 'universitaetsdokumente',
            og_image          TEXT    NOT NULL DEFAULT '',
            short_description TEXT    NOT NULL DEFAULT '',
            main_description  TEXT    NOT NULL DEFAULT '',
            features          TEXT    NOT NULL DEFAULT '[]',
            process_steps     TEXT    NOT NULL DEFAULT '[]',
            is_published      INTEGER NOT NULL DEFAULT 1,
            sort_order        INTEGER NOT NULL DEFAULT 0,
            created_at        TEXT    NOT NULL DEFAULT (datetime('now')),
            updated_at        TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS images (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id  INTEGER NOT NULL,
            filename    TEXT    NOT NULL,
            uploaded_at TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS admin_settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );

        CREATE INDEX IF NOT EXISTS idx_products_category    ON products(category);
        CREATE INDEX IF NOT EXISTS idx_products_published   ON products(is_published);
        CREATE INDEX IF NOT EXISTS idx_images_product       ON images(product_id);
    ");
}

/**
 * Ensure an existing database has the schema (idempotent — safe if tables exist).
 */
function dk_ensure_schema(PDO $pdo): void
{
    $table = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='products'")->fetch();
    if (!$table) {
        dk_create_schema($pdo);
        return;
    }
    // Make sure indexes/settings exist even on older DBs.
    dk_create_schema($pdo);
}

/**
 * Read a setting value, with a default fallback.
 */
function dk_setting(string $key, ?string $default = null): ?string
{
    $stmt = dk_db()->prepare('SELECT value FROM admin_settings WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string) $row['value'] : $default;
}

/**
 * Write (upsert) a setting value.
 */
function dk_set_setting(string $key, string $value): void
{
    $stmt = dk_db()->prepare(
        'INSERT INTO admin_settings (key, value) VALUES (?, ?)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value'
    );
    $stmt->execute([$key, $value]);
}
