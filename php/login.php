<?php
require __DIR__ . '/db/db.php';
require __DIR__ . '/utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

if ($username === '' || $password === '') fail('Missing credentials.');

try {
    $pdo = pdo_mysql();
    $stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        fail('Invalid username or password.', 401);
    }

    $user = [ 'id' => (int)$row['id'], 'username' => $row['username'], 'email' => $row['email'] ];

    // Create token and store in Redis
    $token = generate_token(32);
    $redis = redis_client();
    $ttl = app_config()['redis']['ttl'];
    $key = 'session:' . $token;
    $redis->setex($key, (int)$ttl, json_encode($user));

    ok(['token' => $token, 'user' => ['username' => $user['username'], 'email' => $user['email']]]);
} catch (Throwable $e) {
    fail('Server error: ' . $e->getMessage(), 500);
}
