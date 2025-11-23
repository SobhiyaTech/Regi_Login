<?php
/** Utility helpers: JSON IO, token, validation */

function json_response(int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function ok(array $data = []): void { json_response(200, ['success' => true] + $data); }
function fail(string $message, int $status = 400): void { json_response($status, ['success' => false, 'error' => $message]); }

function read_json_body(): array {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function header_token(): ?string {
    // Custom header passed by frontend
    $headers = getallheaders();
    foreach ($headers as $k => $v) {
        if (strtolower($k) === 'x-session-token') {
            $v = is_array($v) ? ($v[0] ?? null) : $v;
            return $v ? trim($v) : null;
        }
    }
    // Fallback to query param or form param
    if (isset($_GET['token'])) return trim((string)$_GET['token']);
    if (isset($_POST['token'])) return trim((string)$_POST['token']);
    return null;
}

function generate_token(int $lengthBytes = 32): string { return bin2hex(random_bytes($lengthBytes)); }

function valid_username(string $u): bool {
    $len = strlen($u);
    if ($len < 3 || $len > 32) return false;
    return (bool)preg_match('/^[a-zA-Z0-9_\.\-]+$/', $u);
}

function valid_email(string $e): bool { return filter_var($e, FILTER_VALIDATE_EMAIL) !== false; }
