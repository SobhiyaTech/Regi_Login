<?php
/** DB + cache clients */

function app_config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
        if (!file_exists($path)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Missing config.php']);
            exit;
        }
        $cfg = require $path;
    }
    return $cfg;
}

function pdo_mysql(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $c = app_config()['mysql'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['database'], $c['charset']);
    try {
        $pdo = new PDO($dsn, $c['username'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        // Error code 1049 = Unknown database
        if (strpos($e->getMessage(), '1049') !== false || str_contains(strtolower($e->getMessage()), 'unknown database')) {
            $hint = "Database '{$c['database']}' not found. Create it with:\n" .
                "CREATE DATABASE {$c['database']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n" .
                "(Then run backend/db/schema.sql) Or set MYSQL_DB env var to an existing database.";
            throw new RuntimeException($hint, 0, $e);
        }
        // Error 1698 / access denied for root with socket auth on Linux/WSL
        $isAccessDenied = (strpos($e->getMessage(), '1698') !== false) || str_contains(strtolower($e->getMessage()), 'access denied');
        $isLinux = PHP_OS_FAMILY === 'Linux';
        $isRootNoPass = ($c['username'] === 'root') && ($c['password'] === '');
        if ($isLinux && $isAccessDenied && $isRootNoPass) {
            // Try Unix socket fallback (WSL/Ubuntu default path)
            $socket = '/var/run/mysqld/mysqld.sock';
            if (is_readable($socket)) {
                $dsnSock = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $c['database'], $c['charset']);
                try {
                    $pdo = new PDO($dsnSock, $c['username'], $c['password'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                    return $pdo;
                } catch (PDOException $e2) {
                    // fall through to hint
                }
            }
            $hint = "MySQL access denied for root (socket auth). Fix by either:\n" .
                "- Create an app user:\n  sudo mysql -e \"CREATE USER 'guvi'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY 'StrongPass123!'; GRANT ALL ON {$c['database']}.* TO 'guvi'@'127.0.0.1'; FLUSH PRIVILEGES;\"\n" .
                "  Then set env vars MYSQL_USER=guvi MYSQL_PASSWORD=StrongPass123!\n" .
                "- Or switch root to password auth: ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'YourPass';";
            throw new RuntimeException($hint, 0, $e);
        }
        throw $e; // rethrow other PDO errors
    }
    return $pdo;
}

function redis_client(): Redis {
    static $r = null;
    if ($r instanceof Redis) return $r;

    $c = app_config()['redis'];
    $r = new Redis();
    try {
        if (!$r->connect($c['host'], $c['port'])) {
            throw new RuntimeException('Failed to connect to Redis ' . $c['host'] . ':' . $c['port']);
        }
        if (!empty($c['password'])) { $r->auth($c['password']); }
        if (isset($c['db'])) { $r->select((int)$c['db']); }
    } catch (Throwable $e) {
        // Surface a simplified error; callers can decide to fail hard or degrade.
        throw new RuntimeException('Redis connection error: ' . $e->getMessage(), 0, $e);
    }
    return $r;
}

/**
 * Returns MongoDB Manager from ext-mongodb without Composer dependency.
 * We'll use low-level driver classes (MongoDB\Driver\Manager, Query, BulkWrite).
 */
function mongo_manager(): MongoDB\Driver\Manager {
    static $m = null;
    if ($m instanceof MongoDB\Driver\Manager) return $m;
    $c = app_config()['mongodb'];
    try {
        $m = new MongoDB\Driver\Manager($c['uri']);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'No suitable servers found') || str_contains($msg, 'connection refused')) {
            $msg .= "\nHint: Ensure MongoDB is running (e.g., 'sudo systemctl start mongod' on Ubuntu/WSL) and listening on 127.0.0.1:27017.";
        }
        throw new RuntimeException('MongoDB connection error: ' . $msg, 0, $e);
    }
    return $m;
}

function mongo_namespace(): string {
    $c = app_config()['mongodb'];
    return $c['database'] . '.' . $c['collection'];
}
