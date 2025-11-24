<?php
/**
 * Router for PHP built-in server
 * Handles all routing for the application
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Remove query string for matching
$path = strtok($uri, '?');

// Root redirect
if ($path === '/' || $path === '') {
    header('Location: /public/index.html');
    exit;
}

// Check if file exists and serve it
$filePath = __DIR__ . $path;

if (file_exists($filePath) && is_file($filePath)) {
    // Get file extension
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    
    // Set content type based on extension
    $mimeTypes = [
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
    ];
    
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    
    // For PHP files, execute them
    if ($ext === 'php') {
        require $filePath;
        exit;
    }
    
    // For other files, output them
    readfile($filePath);
    exit;
}

// 404 for non-existent files
http_response_code(404);
header('Content-Type: text/html');
echo '<!DOCTYPE html>
<html>
<head><title>404 Not Found</title></head>
<body>
<h1>404 Not Found</h1>
<p>The requested URL ' . htmlspecialchars($uri) . ' was not found.</p>
<p><a href="/public/index.html">Go to Home</a></p>
</body>
</html>';
