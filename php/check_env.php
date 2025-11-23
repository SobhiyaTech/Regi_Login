<?php
/**
 * Diagnostic endpoint to verify PHP extensions and connectivity to MySQL, Redis, and MongoDB.
 * Usage (WSL bash):
 *   php -S 0.0.0.0:8000 index.php &
 *   curl 'http://localhost:8000/php/check_env.php'
 * Optional: pass `?token=<token>` to inspect Redis key `session:<token>`
 */

function json_out($data) {
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$cfg = @include __DIR__ . '/db/config.php';
if (!is_array($cfg)) $cfg = [];

$out = [
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'extensions' => [
        'redis' => extension_loaded('redis'),
        'mongodb' => extension_loaded('mongodb'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
    ],
    'config' => $cfg,
    'checks' => new stdClass(),
];

// Redis check
try {
    $rconf = $cfg['redis'] ?? [];
    $out['checks']->redis = (object) ['available' => false];
    if (extension_loaded('redis')) {
        $redis = new Redis();
        $connected = @$redis->connect($rconf['host'] ?? '127.0.0.1', $rconf['port'] ?? 6379, 1);
        if ($connected) {
            if (!empty($rconf['password'])) {
                @$redis->auth($rconf['password']);
            }
            @$redis->select($rconf['db'] ?? 0);
            $pong = @$redis->ping();
            $out['checks']->redis = (object) [
                'available' => true,
                'ping' => $pong,
                'db' => $rconf['db'] ?? 0,
            ];

            // optional: inspect token value
            if (!empty($_GET['token'])) {
                $key = 'session:' . trim($_GET['token']);
                $val = @$redis->get($key);
                $out['checks']->redis->session_key = $key;
                $out['checks']->redis->session_value = $val === false ? null : $val;
            }
        } else {
            $out['checks']->redis->error = 'connect_failed';
        }
    } else {
        $out['checks']->redis->error = 'extension_missing';
    }
} catch (Throwable $e) {
    $out['checks']->redis->error = $e->getMessage();
}

// MySQL check (PDO)
try {
    $out['checks']->mysql = (object) ['available' => false];
    $m = $cfg['mysql'] ?? [];
    if (extension_loaded('pdo_mysql')) {
        $host = $m['host'] ?? '127.0.0.1';
        $port = $m['port'] ?? 3306;
        $db = $m['database'] ?? '';
        $user = $m['username'] ?? '';
        $pass = $m['password'] ?? '';
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=" . ($m['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->query('SELECT 1 AS ok');
        $one = $stmt->fetch(PDO::FETCH_ASSOC);
        $out['checks']->mysql = (object) [
            'available' => true,
            'server_msg' => $one,
        ];
        // show whether user 'guvi' exists (best-effort)
        try {
            $s2 = $pdo->prepare("SELECT User, Host FROM mysql.user WHERE User = :u LIMIT 1");
            $s2->execute([':u' => 'guvi']);
            $urow = $s2->fetch(PDO::FETCH_ASSOC);
            $out['checks']->mysql->guvi_user = $urow ?: null;
        } catch (Throwable $e) {
            // ignore; may require higher privileges to query mysql.user
            $out['checks']->mysql->guvi_user = 'permission_denied_or_not_root';
        }
    } else {
        $out['checks']->mysql->error = 'extension_missing';
    }
} catch (Throwable $e) {
    $out['checks']->mysql->error = $e->getMessage();
}

// MongoDB check (ext-mongodb)
try {
    $out['checks']->mongodb = (object) ['available' => false];
    if (extension_loaded('mongodb')) {
        $mconf = $cfg['mongodb'] ?? [];
        $uri = $mconf['uri'] ?? 'mongodb://127.0.0.1:27017';
        try {
            $mgr = new MongoDB\Driver\Manager($uri);
            $cmd = new MongoDB\Driver\Command(['ping' => 1]);
            $res = $mgr->executeCommand($mconf['database'] ?? 'admin', $cmd);
            $out['checks']->mongodb = (object) ['available' => true, 'ping' => true, 'uri' => $uri];
        } catch (Throwable $e) {
            $out['checks']->mongodb->error = $e->getMessage();
        }
    } else {
        $out['checks']->mongodb->error = 'extension_missing';
    }
} catch (Throwable $e) {
    $out['checks']->mongodb->error = $e->getMessage();
}

json_out($out);
