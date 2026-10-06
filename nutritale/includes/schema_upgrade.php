<?php
// Applies sql/db_migrations.php's pending schema migrations.
//
// Used two ways:
// - setup.php, explicitly, as before (install and manual upgrades).
// - db() (config/db_conn.php), automatically, once per request: deploying new
//   code onto an existing database used to need someone to remember to visit
//   setup.php - which DEPLOYMENT.md also says to delete after installing - and
//   until then any page reading a newly added column (users.avatar_path, read
//   by every logged-in page) failed outright. Now the first request after a
//   deploy brings the schema up to date itself.

// Runs every migration not yet recorded in schema_migrations, in order.
// Returns how many were applied. Throws if one fails (it is not recorded,
// so it runs again next time).
function nutritale_apply_pending_migrations(PDO $pdo): int
{
    $migrations = require __DIR__ . '/../sql/db_migrations.php';
    $alreadyApplied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $applied = 0;
    foreach ($migrations as $version => $migration) {
        if (in_array($version, $alreadyApplied, true)) {
            continue;
        }
        // A migration is either a ';'-separated SQL string (the common
        // case) or a callable taking the PDO connection, for the rarer
        // case of seeding structured data with real parameter binding
        // instead of hand-escaped SQL text.
        if (is_callable($migration)) {
            $migration($pdo);
        } else {
            foreach (array_filter(array_map('trim', explode(';', $migration))) as $statement) {
                if ($statement === '') continue;
                $pdo->exec($statement);
            }
        }
        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
        $applied++;
    }
    return $applied;
}

// The per-request check: one small query when nothing is pending (the usual
// case). When something is, a MySQL named lock makes sure only one request
// applies it - two visitors arriving together right after a deploy would
// otherwise both try the same ALTER.
function nutritale_ensure_schema_current(PDO $pdo): void
{
    $migrations = require __DIR__ . '/../sql/db_migrations.php';
    try {
        $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return; // no schema_migrations table: a fresh install that setup.php hasn't built yet
    }
    if (!array_diff(array_keys($migrations), $applied)) {
        return;
    }
    try {
        if ((int)$pdo->query("SELECT GET_LOCK('nutritale_schema_upgrade', 30)")->fetchColumn() !== 1) {
            return; // another request is applying them; this one carries on
        }
        try {
            nutritale_apply_pending_migrations($pdo);
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('nutritale_schema_upgrade')");
        }
    } catch (Throwable $e) {
        // Logged, not shown: the page may still work, and a failed migration
        // is retried on the next request. setup.php reports the same error
        // on screen if someone runs it by hand.
        error_log('NutriTale schema upgrade failed: ' . $e->getMessage());
    }
}
