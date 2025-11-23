<?php
// Quick diagnostics: verifies connections to MySQL, Redis, MongoDB
// Run locally with: php backend/db/diagnostics.php

require __DIR__ . '/db.php';

function json_out($status, $payload) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$results = [
    'mysql' => ['ok' => false],
    'redis' => ['ok' => false],
    'mongodb' => ['ok' => false],
    'config' => app_config(),
];

// MySQL (basic select + users table existence + row count)
try {
    $pdo = pdo_mysql();
    $stmt = $pdo->query('SELECT 1 as one');
    $row = $stmt->fetch();
    $hasUsers = false; $userCount = null;
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'users'");
        $hasUsers = (bool)$chk->fetchColumn();
        if ($hasUsers) {
            $cntStmt = $pdo->query('SELECT COUNT(*) AS c FROM users');
            $userCount = (int)$cntStmt->fetch()['c'];
        }
    } catch (Throwable $inner) {
        // ignore table check failures, keep connectivity status
    }
    $results['mysql'] = [
        'ok' => (isset($row['one']) && (int)$row['one'] === 1),
        'has_users_table' => $hasUsers,
        'user_count' => $userCount,
        'server_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
    ];
} catch (Throwable $e) {
    $results['mysql'] = ['ok' => false, 'error' => $e->getMessage()];
}

// Redis (PING + set/get ephemeral key)
try {
    $r = redis_client();
    $pong = $r->ping(); // returns "+PONG" or true depending on version
    $key = 'diagnostics:test:' . bin2hex(random_bytes(4));
    $r->setex($key, 5, 'ok');
    $val = $r->get($key);
    $results['redis'] = [
        'ok' => (bool)$pong && $val === 'ok',
        'response' => is_string($pong) ? $pong : 'OK',
        'ephemeral_key' => $key,
        'ephemeral_value' => $val,
        'ttl_seconds' => $r->ttl($key),
    ];
} catch (Throwable $e) {
    $results['redis'] = ['ok' => false, 'error' => $e->getMessage()];
}

// MongoDB (ping + collection existence + sample count)
try {
    $mgr = mongo_manager();
    $db = app_config()['mongodb']['database'];
    $cmd = new MongoDB\Driver\Command(['ping' => 1]);
    $res = $mgr->executeCommand($db, $cmd);
    $ok = false;
    foreach ($res as $doc) { $ok = isset($doc->ok) ? ((float)$doc->ok === 1.0) : false; break; }
    $collection = app_config()['mongodb']['collection'];
    $listCmd = new MongoDB\Driver\Command(['listCollections' => 1]);
    $collectionsCursor = $mgr->executeCommand($db, $listCmd);
    $collections = [];
    foreach ($collectionsCursor as $cinfo) { if (isset($cinfo->name)) { $collections[] = $cinfo->name; } }
    $hasCollection = in_array($collection, $collections, true);
    $count = null;
    if ($hasCollection) {
        $countCmd = new MongoDB\Driver\Command(['count' => $collection]);
        $countRes = $mgr->executeCommand($db, $countCmd);
        foreach ($countRes as $cr) { if (isset($cr->n)) { $count = (int)$cr->n; } }
    }
    $results['mongodb'] = [
        'ok' => $ok,
        'has_collection' => $hasCollection,
        'collection' => $collection,
        'document_count' => $count,
    ];
} catch (Throwable $e) {
    $results['mongodb'] = ['ok' => false, 'error' => $e->getMessage()];
}

json_out(200, ['success' => true, 'diagnostics' => $results]);
