<?php
/**
 * auto_prepend_file — runs before every request (see docker/php-session.ini).
 * Registers the Postgres session handler BEFORE any session_start() anywhere in the
 * app, so all sessions are DB-backed and survive redeploys. Must never fatal: on any
 * error it silently falls back to PHP's default (file) session handler.
 */
if (PHP_SAPI !== 'cli') {
    try {
        ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 30)); // 30 days
        ini_set('session.cookie_lifetime', (string)(60 * 60 * 24 * 30));
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        require_once __DIR__ . '/pg_session_handler.php';

        if (session_status() === PHP_SESSION_NONE) {
            session_set_save_handler(new PgSessionHandler(60 * 60 * 24 * 30), true);
        }
    } catch (Throwable $e) {
        error_log('[session_prepend] ' . $e->getMessage());
    }
}
