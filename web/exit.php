<?php
// Closes the YourTube browser window and stops the local server. Local network only.
header('Content-Type: application/json');
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $public || !function_exists('exec')) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}
$home = getenv('HOME');
$stop = $home ? "$home/.local/bin/yourtube-stop" : '';
if (!$stop || !is_executable($stop)) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit;
}
exec('setsid ' . escapeshellarg($stop) . ' >/dev/null 2>&1 &');
echo json_encode(['ok' => true]);
