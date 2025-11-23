<?php
/**
 * Test endpoint to create a session key in Redis and verify it was written.
 * Usage: /php/test-session-write.php?user=alice
 */
require __DIR__ . '/db/db.php';
require __DIR__ . '/utils.php';

try {
    $user = isset($_GET['user']) ? trim((string)$_GET['user']) : 'testuser';
    $userdata = [ 'id' => rand(1000,9999), 'username' => $user, 'email' => $user . '@example.test' ];
    $token = generate_token(16);

    $r = redis_client();
    $ttl = app_config()['redis']['ttl'] ?? 604800;
    $key = 'session:' . $token;
    $written = $r->setex($key, (int)$ttl, json_encode($userdata));

    $found = $r->get($key);

    ok([ 'token' => $token, 'written' => $written === true, 'stored' => $found, 'redis_keys' => $r->keys('session:*') ]);
} catch (Throwable $e) {
    fail('Test write error: ' . $e->getMessage(), 500);
}
