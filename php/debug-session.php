<?php
/**
 * Debug endpoint to inspect session token and Redis connectivity.
 * Usage:
 *  - Send header: X-Session-Token: <token>
 *  - Or query param: ?token=<token>
 */
require __DIR__ . '/db/db.php';
require __DIR__ . '/utils.php';

try {
    $token = header_token() ?: (isset($_GET['token']) ? trim((string)$_GET['token']) : null);

    $result = [
        'token_provided' => $token ? true : false,
        'token_sample' => $token ? substr($token, 0, 8) : null,
        'php_redis_extension' => extension_loaded('redis'),
    ];

    // Show configured redis settings from app config
    $cfg = app_config()['redis'] ?? null;
    $result['redis_config'] = $cfg;

    // Try connecting to Redis (non-fatal)
    try {
        $r = new Redis();
        $connected = $r->connect($cfg['host'] ?? '127.0.0.1', $cfg['port'] ?? 6379);
        $result['redis_connect'] = $connected ? 'ok' : 'failed';
        if ($connected) {
            if (!empty($cfg['password'])) {
                try { $r->auth($cfg['password']); $result['redis_auth'] = 'ok'; } catch (Throwable $e) { $result['redis_auth'] = 'auth_failed: '.$e->getMessage(); }
            }
            if (isset($cfg['db'])) { $r->select((int)$cfg['db']); }
            if ($token) {
                $val = $r->get('session:' . $token);
                $result['session_value'] = $val ?: null;
            }
        }
    } catch (Throwable $e) {
        $result['redis_error'] = $e->getMessage();
    }

    ok($result);
} catch (Throwable $e) {
    fail('Debug error: ' . $e->getMessage(), 500);
}
