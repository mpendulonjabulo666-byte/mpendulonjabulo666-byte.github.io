<?php
// Why this exists: PHP's default session storage is plain temp files on
// disk (session.save_handler = files). That's fine on a normal host, but
// free shared hosts - InfinityFree specifically - are known to sweep their
// shared tmp/session directories on their own schedule to keep disk usage
// under control across every customer on the box, regardless of what this
// app sets session.gc_maxlifetime to. The practical symptom: a user logs
// in, the session cookie is still sitting in their browser, but the
// session data behind it has been deleted server-side - so every request
// looks logged-out and they're bounced back to login.php, sometimes within
// the same day. This isn't a traffic/scaling problem (that's what Redis is
// for, on a multi-server setup) - it's one shared-hosting account cleaning
// its own disk. The fix is to stop depending on that disk at all and store
// sessions somewhere this app already controls: the MySQL database every
// other table already lives in. No new service, no new cost.
//
// Deliberately self-contained: config.php wires this up and calls
// session_start() before functions_core.php (and its db() singleton /
// schema-upgrade-on-request machinery) has even loaded, so this can't
// borrow db()'s connection or rely on the migrations runner having already
// created nutri_sessions - by the time either exists, session_start()
// already needed this table. open() creates it itself (CREATE TABLE IF NOT
// EXISTS is a cheap no-op once it exists) rather than depending on load
// order. It's also still listed in sql/db_migrations.php, purely so
// schema.sql-reading humans and a fresh setup.php run see it as a normal
// part of the schema, not a connection hidden here.
//
// Every method swallows its own DB errors rather than throwing: a session
// backend that can fatal the page on a transient DB hiccup is worse than
// one that just quietly fails this one user's persistence for this one
// request (same failure mode this app already had before - never worse).
final class DbSessionHandler implements SessionHandlerInterface
{
    private ?PDO $pdo = null;
    private string $dsn;
    private string $user;
    private string $pass;

    public function __construct(string $host, string $port, string $name, string $user, string $pass)
    {
        $this->dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
        $this->user = $user;
        $this->pass = $pass;
    }

    private function connect(): ?PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }
        try {
            $this->pdo = new PDO($this->dsn, $this->user, $this->pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            return $this->pdo;
        } catch (Throwable $e) {
            error_log('DbSessionHandler: connection failed: ' . $e->getMessage());
            return null;
        }
    }

    public function open(string $path, string $name): bool
    {
        $pdo = $this->connect();
        if ($pdo === null) {
            return false;
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS nutri_sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                data MEDIUMTEXT NOT NULL,
                last_activity INT UNSIGNED NOT NULL,
                INDEX idx_last_activity (last_activity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            return true;
        } catch (Throwable $e) {
            error_log('DbSessionHandler: open() failed: ' . $e->getMessage());
            return false;
        }
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $pdo = $this->connect();
        if ($pdo === null) {
            return '';
        }
        try {
            $stmt = $pdo->prepare('SELECT data FROM nutri_sessions WHERE id = ?');
            $stmt->execute([$id]);
            $data = $stmt->fetchColumn();
            return $data === false ? '' : $data;
        } catch (Throwable $e) {
            error_log('DbSessionHandler: read() failed: ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        $pdo = $this->connect();
        if ($pdo === null) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO nutri_sessions (id, data, last_activity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)'
            );
            return $stmt->execute([$id, $data, time()]);
        } catch (Throwable $e) {
            error_log('DbSessionHandler: write() failed: ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        $pdo = $this->connect();
        if ($pdo === null) {
            return false;
        }
        try {
            $pdo->prepare('DELETE FROM nutri_sessions WHERE id = ?')->execute([$id]);
            return true;
        } catch (Throwable $e) {
            error_log('DbSessionHandler: destroy() failed: ' . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        $pdo = $this->connect();
        if ($pdo === null) {
            return false;
        }
        try {
            $stmt = $pdo->prepare('DELETE FROM nutri_sessions WHERE last_activity < ?');
            $stmt->execute([time() - $max_lifetime]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('DbSessionHandler: gc() failed: ' . $e->getMessage());
            return false;
        }
    }
}
