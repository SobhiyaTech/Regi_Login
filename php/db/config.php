<?php
// Centralized configuration. You can override values with environment variables.
return [
    // MySQL (core registration data)
    'mysql' => [
        'host' => getenv('MYSQL_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('MYSQL_PORT') ?: 3306),
        'database' => getenv('MYSQL_DB') ?: 'guvi_app',
        'username' => getenv('MYSQL_USER') ?: 'guvi',
        'password' => getenv('MYSQL_PASSWORD') ?: 'Guvi@2024',
        'charset' => 'utf8mb4',
    ],

    // Redis (session tokens)
    'redis' => [
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('REDIS_PORT') ?: 6379),
        'db'   => (int)(getenv('REDIS_DB') ?: 0),
        'password' => getenv('REDIS_PASSWORD') ?: null,
        // Token TTL in seconds (7 days)
        'ttl'  => (int)(getenv('SESSION_TTL') ?: 604800),
    ],

    // MongoDB (additional profile details)
    'mongodb' => [
        // Example: mongodb://localhost:27017
        'uri' => getenv('MONGO_URI') ?: 'mongodb://127.0.0.1:27017',
        'database' => getenv('MONGO_DB') ?: 'guvi_app',
        'collection' => getenv('MONGO_COLLECTION') ?: 'profiles',
    ],
];
