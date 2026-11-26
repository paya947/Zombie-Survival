<?php
declare(strict_types=1);

define('DATA_DIR', __DIR__ . '/data');

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0775, true);
}

function db(string $name, array $default = []): array {
    $file = DATA_DIR . '/' . $name . '.json';
    if (!is_file($file)) {
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    $fp = fopen($file, 'r');
    if (!$fp) return $default;
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : $default;
}

function saveDB(string $name, array $data): void {
    $file = DATA_DIR . '/' . $name . '.json';
    $tmp = $file . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) {
        throw new RuntimeException('Could not save JSON data');
    }
    if (!rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('Could not replace JSON database');
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireUser(): array {
    if (empty($_SESSION['user']['id'])) {
        jsonResponse(['ok' => false, 'message' => 'ابتدا وارد حساب شوید'], 401);
    }
    return $_SESSION['user'];
}
