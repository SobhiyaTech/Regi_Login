<?php
/**
 * Detailed database diagnostics – checks MySQL, Redis, MongoDB connectivity with verbose output.
 * Usage: php php/db/diagnostics-detailed.php or visit http://localhost:8000/php/db/diagnostics-detailed.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║  GUVI App - WSL Database Connection Diagnostics       ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Display configuration
$config = app_config();
echo "📋 Configuration:\n";
echo "   MySQL    : {$config['mysql']['host']}:{$config['mysql']['port']} (DB: {$config['mysql']['database']})\n";
echo "   Redis    : {$config['redis']['host']}:{$config['redis']['port']} (DB: {$config['redis']['db']})\n";
echo "   MongoDB  : {$config['mongodb']['uri']} (DB: {$config['mongodb']['database']}.{$config['mongodb']['collection']})\n";
echo "\n" . str_repeat("─", 58) . "\n\n";

function test_mysql() {
    echo "🔍 Testing MySQL Connection...\n";
    try {
        $pdo = pdo_mysql();
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        $config = app_config()['mysql'];
        echo "   ✓ Connected to MySQL $version\n";
        echo "   ✓ Host: {$config['host']}:{$config['port']}\n";
        echo "   ✓ Database: {$config['database']}\n";
        echo "   ✓ User: {$config['username']}\n";
        
        // Test users table
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
        $count = $stmt->fetchColumn();
        echo "   ✓ Table 'users': $count row(s)\n";
        
        // Test insert/select/delete
        $testUser = 'test_' . bin2hex(random_bytes(4));
        $testEmail = $testUser . '@test.com';
        $testHash = password_hash('test123', PASSWORD_DEFAULT);
        
        $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)')
            ->execute([$testUser, $testEmail, $testHash]);
        
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$testUser]);
        $testId = $stmt->fetchColumn();
        
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$testId]);
        
        echo "   ✓ CRUD operations: OK\n";
        return true;
    } catch (Throwable $e) {
        echo "   ✗ FAILED: " . $e->getMessage() . "\n";
        return false;
    }
}

function test_redis() {
    echo "\n🔍 Testing Redis Connection...\n";
    try {
        $redis = redis_client();
        $config = app_config()['redis'];
        
        $info = $redis->info('server');
        $version = $info['redis_version'] ?? 'unknown';
        
        echo "   ✓ Connected to Redis $version\n";
        echo "   ✓ Host: {$config['host']}:{$config['port']}\n";
        echo "   ✓ Database: {$config['db']}\n";
        
        // Test PING
        $pong = $redis->ping();
        echo "   ✓ PING: $pong\n";
        
        // Test set/get/expire
        $testKey = 'test:' . bin2hex(random_bytes(4));
        $testValue = json_encode(['test' => true, 'timestamp' => time()]);
        
        $redis->setex($testKey, 60, $testValue);
        $retrieved = $redis->get($testKey);
        $ttl = $redis->ttl($testKey);
        $redis->del($testKey);
        
        $match = $retrieved === $testValue;
        echo "   ✓ SET/GET/TTL: " . ($match ? "OK" : "FAIL") . " (TTL: {$ttl}s)\n";
        
        // Count session keys
        $sessions = $redis->keys('session:*');
        echo "   ✓ Active sessions: " . count($sessions) . "\n";
        
        return true;
    } catch (Throwable $e) {
        echo "   ✗ FAILED: " . $e->getMessage() . "\n";
        return false;
    }
}

function test_mongo() {
    echo "\n🔍 Testing MongoDB Connection...\n";
    try {
        $manager = mongo_manager();
        $ns = mongo_namespace();
        $config = app_config()['mongodb'];
        
        // Test connection with a ping command
        $cmd = new MongoDB\Driver\Command(['ping' => 1]);
        $result = $manager->executeCommand('admin', $cmd);
        $arr = $result->toArray();
        $ok = isset($arr[0]->ok) && $arr[0]->ok == 1;
        
        if (!$ok) {
            throw new Exception("Ping failed");
        }
        
        // Get server info
        $cmd = new MongoDB\Driver\Command(['buildInfo' => 1]);
        $result = $manager->executeCommand('admin', $cmd);
        $info = $result->toArray()[0];
        $version = $info->version ?? 'unknown';
        
        echo "   ✓ Connected to MongoDB $version\n";
        echo "   ✓ URI: {$config['uri']}\n";
        echo "   ✓ Namespace: $ns\n";
        
        // Count documents
        $query = new MongoDB\Driver\Query([]);
        $cursor = $manager->executeQuery($ns, $query);
        $count = count($cursor->toArray());
        echo "   ✓ Collection 'profiles': $count document(s)\n";
        
        // Test insert/update/delete
        $testUserId = rand(999000, 999999);
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->insert([
            'user_id' => $testUserId,
            'age' => 25,
            'contact' => 'test@example.com',
            'test' => true,
        ]);
        $result = $manager->executeBulkWrite($ns, $bulk);
        
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->update(
            ['user_id' => $testUserId],
            ['$set' => ['age' => 26]],
            ['multi' => false]
        );
        $manager->executeBulkWrite($ns, $bulk);
        
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->delete(['user_id' => $testUserId]);
        $manager->executeBulkWrite($ns, $bulk);
        
        echo "   ✓ CRUD operations: OK\n";
        
        // List indexes
        $cmd = new MongoDB\Driver\Command(['listIndexes' => $config['collection']]);
        $cursor = $manager->executeCommand($config['database'], $cmd);
        $indexes = iterator_to_array($cursor);
        echo "   ✓ Indexes: " . count($indexes) . " configured\n";
        
        return true;
    } catch (Throwable $e) {
        echo "   ✗ FAILED: " . $e->getMessage() . "\n";
        return false;
    }
}

$mysqlOk = test_mysql();
$redisOk = test_redis();
$mongoOk = test_mongo();

echo "\n" . str_repeat("═", 58) . "\n";
if ($mysqlOk && $redisOk && $mongoOk) {
    echo "✅ ALL SERVICES OPERATIONAL - Ready for production!\n";
    echo "\nNext steps:\n";
    echo "  • Register a user: POST to /php/register.php\n";
    echo "  • Login: POST to /php/login.php (returns token)\n";
    echo "  • View/Update profile: GET/POST to /php/profile.php\n";
} else {
    echo "❌ SOME SERVICES FAILED - Check errors above\n";
    echo "\nTroubleshooting:\n";
    if (!$mysqlOk) echo "  • MySQL: Verify WSL MySQL is running and credentials are correct\n";
    if (!$redisOk) echo "  • Redis: Verify WSL Redis is running on configured port\n";
    if (!$mongoOk) echo "  • MongoDB: Verify WSL MongoDB is running and initialized\n";
}
echo str_repeat("═", 58) . "\n";
