<?php
/**
 * Postgres-backed PHP session handler so logins survive container redeploys/restarts
 * (the default file sessions live on Render's ephemeral disk and are wiped each deploy).
 *
 * Registered globally via auto_prepend_file (see api/lib/session_prepend.php +
 * docker/php-session.ini). Self-connects (reuses $GLOBALS['pdo'] when present) so it
 * works regardless of include order. Every operation is wrapped so a DB hiccup degrades
 * gracefully to an empty session rather than fatally breaking the request.
 *
 * Session payloads are base64-encoded before storage (Postgres TEXT can't hold NUL bytes).
 */
class PgSessionHandler implements SessionHandlerInterface
{
    private ?PDO $pdo = null;
    private int $ttl;

    public function __construct(int $ttl = 2592000) { $this->ttl = $ttl; }

    private function db(): ?PDO
    {
        if ($this->pdo instanceof PDO) return $this->pdo;
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return $this->pdo = $GLOBALS['pdo'];
        }
        try {
            $dsn = "pgsql:host=" . (getenv('DB_HOST') ?: '127.0.0.1')
                 . ";port=" . (getenv('DB_PORT') ?: '5432')
                 . ";dbname=" . (getenv('DB_NAME') ?: 'collagen_db')
                 . ";options='--client_encoding=UTF8'";
            $this->pdo = new PDO($dsn, getenv('DB_USER') ?: 'postgres', getenv('DB_PASS') ?: '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (Throwable $e) {
            error_log('[pg_session] connect failed: ' . $e->getMessage());
            $this->pdo = null;
        }
        return $this->pdo;
    }

    #[\ReturnTypeWillChange]
    public function open($savePath, $sessionName): bool { return true; }

    #[\ReturnTypeWillChange]
    public function close(): bool { return true; }

    #[\ReturnTypeWillChange]
    public function read($id): string
    {
        $db = $this->db();
        if (!$db) return '';
        try {
            $st = $db->prepare("SELECT data FROM sessions WHERE id = ? AND expires > ?");
            $st->execute([$id, time()]);
            $enc = $st->fetchColumn();
            return $enc !== false ? (base64_decode((string)$enc) ?: '') : '';
        } catch (Throwable $e) {
            error_log('[pg_session] read failed: ' . $e->getMessage());
            return '';
        }
    }

    #[\ReturnTypeWillChange]
    public function write($id, $data): bool
    {
        $db = $this->db();
        if (!$db) return false;
        try {
            $st = $db->prepare(
                "INSERT INTO sessions (id, data, expires, updated_at)
                 VALUES (?, ?, ?, NOW())
                 ON CONFLICT (id) DO UPDATE
                   SET data = EXCLUDED.data, expires = EXCLUDED.expires, updated_at = NOW()"
            );
            $st->execute([$id, base64_encode($data), time() + $this->ttl]);
            return true;
        } catch (Throwable $e) {
            error_log('[pg_session] write failed: ' . $e->getMessage());
            return false;
        }
    }

    #[\ReturnTypeWillChange]
    public function destroy($id): bool
    {
        $db = $this->db();
        if (!$db) return true;
        try {
            $db->prepare("DELETE FROM sessions WHERE id = ?")->execute([$id]);
        } catch (Throwable $e) {
            error_log('[pg_session] destroy failed: ' . $e->getMessage());
        }
        return true;
    }

    #[\ReturnTypeWillChange]
    public function gc($maxLifetime): int
    {
        $db = $this->db();
        if (!$db) return 0;
        try {
            $st = $db->prepare("DELETE FROM sessions WHERE expires < ?");
            $st->execute([time()]);
            return $st->rowCount();
        } catch (Throwable $e) {
            error_log('[pg_session] gc failed: ' . $e->getMessage());
            return 0;
        }
    }
}
