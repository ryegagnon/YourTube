<?php
// Tiny relay between the TV page and the phone remote. Stores one small JSON file per pairing code.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function out($o) { echo json_encode($o); exit; }

$c = strtoupper($_GET['c'] ?? '');
$a = $_GET['a'] ?? '';
if (!preg_match('/^[A-Z0-9]{6}$/', $c)) out(['error' => 'badcode']);

$raw = file_get_contents('php://input');
if (strlen($raw) > 60000) out(['error' => 'toolarge']);
$body = json_decode($raw, true);
if (!is_array($body)) $body = [];

$dir = __DIR__ . '/rooms';
if (!is_dir($dir)) {
    @mkdir($dir, 0755);
    @file_put_contents($dir . '/.htaccess',
        "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
}
$f = "$dir/$c.json";
if ($a !== 'tv' && !file_exists($f)) out(['error' => 'nocode']);

$fh = @fopen($f, 'c+');
if (!$fh) out(['error' => 'storage']);
flock($fh, LOCK_EX);
$R = json_decode(stream_get_contents($fh), true);
if (!is_array($R)) $R = ['state' => null, 'cmds' => [], 'tv' => 0, 'ph' => 0];
$now = time();
$o = ['error' => 'badaction'];

if ($a === 'tv') {
    $R['tv'] = $now;
    if (isset($body['state']) && is_array($body['state'])) $R['state'] = $body['state'];
    $o = ['cmds' => $R['cmds'], 'act' => ($now - $R['ph']) < 120];
    $R['cmds'] = [];
    if (mt_rand(1, 100) === 1) {
        foreach (glob("$dir/*.json") as $g) if ($now - filemtime($g) > 2592000) @unlink($g);
    }
} elseif ($a === 'state') {
    $R['ph'] = $now;
    $o = ['state' => $R['state'], 'tv_age' => $R['tv'] ? $now - $R['tv'] : null];
} elseif ($a === 'cmd') {
    $R['ph'] = $now;
    $cmd = $body['cmd'] ?? null;
    if (is_array($cmd) && count($R['cmds']) < 30) {
        $R['cmds'][] = array_intersect_key($cmd, array_flip(['t', 'i', 'd', 'v', 'n', 'k', 'src', 'name']));
    }
    $o = ['ok' => true];
}

ftruncate($fh, 0);
rewind($fh);
fwrite($fh, json_encode($R));
flock($fh, LOCK_UN);
fclose($fh);
out($o);
