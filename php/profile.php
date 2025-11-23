<?php
require __DIR__ . '/db/db.php';
require __DIR__ . '/utils.php';

$method = $_SERVER['REQUEST_METHOD'];

function require_user_from_token(): array {
    $token = header_token();
    if (!$token) fail('Missing token.', 401);
    $redis = redis_client();
    $raw = $redis->get('session:' . $token);
    if (!$raw) fail('Invalid or expired token.', 401);
    $user = json_decode($raw, true);
    if (!is_array($user) || !isset($user['id'])) fail('Invalid session.', 401);

    // Touch TTL (sliding expiration)
    $ttl = app_config()['redis']['ttl'];
    $redis->expire('session:' . $token, (int)$ttl);

    return $user; // [id, username, email]
}

try {
    $user = require_user_from_token();

    if ($method === 'GET') {
        // Fetch profile from MongoDB
        $manager = mongo_manager();
        $ns = mongo_namespace();
        $filter = ['user_id' => (int)$user['id']];
        $query = new MongoDB\Driver\Query($filter, ['limit' => 1]);
        $cursor = $manager->executeQuery($ns, $query);
        $profile = null;
        foreach ($cursor as $doc) { $profile = json_decode(json_encode($doc), true); break; }

        if ($profile) {
            unset($profile['_id']);
        }

        ok([
            'user' => [ 'username' => $user['username'], 'email' => $user['email'] ],
            'profile' => $profile ?: (object)[]
        ]);
    }

    if ($method === 'POST') {
        $body = read_json_body();
        $data = isset($body['profile']) && is_array($body['profile']) ? $body['profile'] : [];

        $doc = [
            'user_id' => (int)$user['id'],
            'age' => isset($data['age']) && $data['age'] !== '' ? (int)$data['age'] : null,
            'dob' => isset($data['dob']) ? (string)$data['dob'] : null,
            'contact' => isset($data['contact']) ? (string)$data['contact'] : null,
            'address' => isset($data['address']) ? (string)$data['address'] : null,
            'updated_at' => new MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000)),
        ];

        $manager = mongo_manager();
        $ns = mongo_namespace();
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->update(['user_id' => (int)$user['id']], ['$set' => $doc], ['upsert' => true]);
        $result = $manager->executeBulkWrite($ns, $bulk);

        ok(['message' => 'Updated']);
    }

    fail('Method not allowed', 405);
} catch (Throwable $e) {
    fail('Server error: ' . $e->getMessage(), 500);
}
