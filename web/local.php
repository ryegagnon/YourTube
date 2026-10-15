<?php
// Lets YourTube use video files stored on the Raspberry Pi or on a USB drive as a channel.
//   ?a=ls&p=PATH     list folders you can open (p empty = the list of drives and media folders)
//   ?a=scan&p=PATH   list the video files in a folder (with lengths), probing a few at a time
//   ?a=file&p=PATH   stream one video file (supports seeking)
// Only private-network clients are served, and only files under the media folders below.
header('Cache-Control: no-store');

function jout($o) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($o); exit; }

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
    http_response_code(403);
    jout(['ok' => false, 'error' => 'Local files are only available on the local network.']);
}

const EXTS = ['mp4', 'm4v', 'mov', 'webm', 'mkv', 'ogv'];
const UNSUPPORTED = ['avi', 'mpg', 'mpeg', 'wmv', 'flv', 'm2ts', 'mts', 'ts', '3gp', 'divx', 'vob', 'ogm'];
const MAX_FILES = 500;
$unsup = [];

$home = rtrim(getenv('HOME') ?: '', '/');
$user = $home ? basename($home) : '';
// Folders whose children are drives (USB sticks and disks mount here), and single media folders.
$driveParents = array_filter(["/media/$user", "/run/media/$user", '/mnt'], 'is_dir');
$mediaDirs = array_filter(array_merge($home ? ["$home/Videos", "$home/Movies"] : [], array_filter(explode(':', getenv('YOURTUBE_MEDIA') ?: ''))), 'is_dir');
$allowed = [];
foreach (array_merge($driveParents, $mediaDirs) as $d) { $rp = realpath($d); if ($rp) $allowed[] = $rp; }

function inside($path, $allowed) {
    $rp = realpath($path);
    if (!$rp) return false;
    foreach ($allowed as $a) if ($rp === $a || strpos($rp, $a . '/') === 0) return $rp;
    return false;
}
function isVideo($name) {
    return $name[0] !== '.' && in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), EXTS, true);
}
function countVideos($dir) {
    $n = 0;
    foreach (@scandir($dir) ?: [] as $e) if (is_file("$dir/$e") && isVideo($e)) $n++;
    return $n;
}
function collect($dir, $depth, &$out) {
    global $unsup;
    if ($depth > 4 || count($out) >= MAX_FILES) return;
    $names = @scandir($dir) ?: [];
    natcasesort($names);
    foreach ($names as $e) {
        if ($e[0] === '.') continue;
        $p = "$dir/$e";
        if (is_dir($p)) collect($p, $depth + 1, $out);
        elseif (is_file($p) && isVideo($e)) { $out[] = $p; if (count($out) >= MAX_FILES) return; }
        elseif (is_file($p) && in_array(strtolower(pathinfo($e, PATHINFO_EXTENSION)), UNSUPPORTED, true) && count($unsup) < 20) $unsup[] = $e;
    }
}

$a = $_GET['a'] ?? '';
$p = (string)($_GET['p'] ?? '');

if ($a === 'ls') {
    if ($p === '') {
        $dirs = [];
        foreach ($driveParents as $par) {
            foreach (@scandir($par) ?: [] as $e) {
                if ($e[0] === '.' || !is_dir("$par/$e")) continue;
                $dirs[] = ['n' => (strpos($par, '/mnt') === 0 ? 'Drive: ' : 'USB drive: ') . $e, 'p' => "$par/$e", 'v' => countVideos("$par/$e")];
            }
        }
        foreach ($mediaDirs as $m) $dirs[] = ['n' => 'Folder: ' . basename($m), 'p' => $m, 'v' => countVideos($m)];
        jout(['ok' => true, 'path' => '', 'parent' => null, 'dirs' => $dirs,
              'error' => $dirs ? null : 'No USB drive or media folder found. Plug in a drive, or put videos in the Videos folder.']);
    }
    $rp = inside($p, $allowed);
    if (!$rp || !is_dir($rp)) jout(['ok' => false, 'error' => 'That folder is not available.']);
    $dirs = [];
    $names = @scandir($rp) ?: [];
    natcasesort($names);
    foreach ($names as $e) {
        if ($e[0] === '.' || !is_dir("$rp/$e")) continue;
        $dirs[] = ['n' => $e, 'p' => "$rp/$e", 'v' => countVideos("$rp/$e")];
        if (count($dirs) >= 300) break;
    }
    $parent = inside(dirname($rp), $allowed);
    jout(['ok' => true, 'path' => $rp, 'parent' => $parent ?: '', 'videos' => countVideos($rp), 'dirs' => $dirs]);
}

if ($a === 'scan') {
    $rp = inside($p, $allowed);
    if (!$rp || !is_dir($rp)) jout(['ok' => false, 'error' => 'Folder not found. Is the USB drive plugged in?']);
    $files = [];
    collect($rp, 0, $files);
    if (!empty($_GET['quick'])) jout(['ok' => true, 'count' => count($files)]);
    if (!function_exists('shell_exec') || !trim((string)@shell_exec('command -v ffprobe'))) {
        jout(['ok' => false, 'error' => 'ffprobe is not installed. Run: sudo apt install ffmpeg']);
    }
    $dir = __DIR__ . '/rooms';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755);
        @file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    $cf = "$dir/local_lengths.json";
    $cache = json_decode((string)@file_get_contents($cf), true);
    if (!is_array($cache)) $cache = [];
    $t0 = microtime(true); $probed = 0; $dirty = false;
    $items = []; $pending = 0; $skipped = ['short' => 0, 'files' => []];
    foreach ($files as $f) {
        $key = md5($f) . ':' . filesize($f) . ':' . filemtime($f);
        if (!isset($cache[$key])) {
            if ($probed >= 25 || microtime(true) - $t0 > 18) { $pending++; continue; }
            $o = (string)@shell_exec('timeout 15 ffprobe -v error -show_entries format=duration -of csv=p=0 ' . escapeshellarg($f) . ' 2>/dev/null');
            $cache[$key] = (int)round((float)trim($o));
            $probed++; $dirty = true;
        }
        $d = $cache[$key];
        if ($d < 1) { if (count($skipped['files']) < 20) $skipped['files'][] = ['n' => basename($f), 'why' => 'could not read its length (damaged or not a real video)']; }
        elseif ($d < 5) $skipped['short']++;
        if ($d >= 5) {
            $title = trim(preg_replace('/[._]+/', ' ', pathinfo($f, PATHINFO_FILENAME)));
            $items[] = ['p' => $f, 't' => $title, 'd' => $d];
        }
    }
    if ($dirty) @file_put_contents($cf, json_encode($cache), LOCK_EX);
    foreach ($unsup as $n) $skipped['files'][] = ['n' => $n, 'why' => 'format not supported by the browser (convert it to MP4)'];
    jout(['ok' => true, 'count' => count($files), 'pending' => $pending, 'items' => $items, 'skipped' => $skipped]);
}

if ($a === 'file') {
    $rp = inside($p, $allowed);
    if (!$rp || !is_file($rp) || !isVideo(basename($rp))) { http_response_code(404); exit; }
    $mimes = ['mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/mp4', 'webm' => 'video/webm', 'mkv' => 'video/x-matroska', 'ogv' => 'video/ogg'];
    $size = filesize($rp);
    $start = 0; $end = $size - 1;
    header('Content-Type: ' . $mimes[strtolower(pathinfo($rp, PATHINFO_EXTENSION))]);
    header('Accept-Ranges: bytes');
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] !== '') { $start = (int)$m[1]; if ($m[2] !== '') $end = min((int)$m[2], $end); }
        elseif ($m[2] !== '') $start = max(0, $size - (int)$m[2]);
        if ($start > $end || $start >= $size) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Length: ' . ($end - $start + 1));
    @set_time_limit(0);
    $fh = fopen($rp, 'rb');
    fseek($fh, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fh) && !connection_aborted()) {
        $chunk = fread($fh, min(1048576, $left));
        echo $chunk;
        flush();
        $left -= strlen($chunk);
    }
    fclose($fh);
    exit;
}

jout(['ok' => false, 'error' => 'Unknown action.']);
