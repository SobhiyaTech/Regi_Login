<?php
require __DIR__ . '/db/db.php';
require __DIR__ . '/utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
$email    = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

if (!valid_username($username)) fail('Invalid username. Use 3-32 letters, numbers, dot, dash or underscore.');
if (!valid_email($email)) fail('Invalid email address.');
if (strlen($password) < 8) fail('Password must be at least 8 characters.');

try {
    $pdo = pdo_mysql();

    // Ensure unique username/email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
    $stmt->execute([':u' => $username, ':e' => $email]);
    if ($stmt->fetch()) {
        fail('Username or email already exists.', 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, created_at) VALUES (:u, :e, :p, NOW())');
    $stmt->execute([':u' => $username, ':e' => $email, ':p' => $hash]);

    ok(['message' => 'Registered']);
} catch (Throwable $e) {
    fail('Server error: ' . $e->getMessage(), 500);
}
