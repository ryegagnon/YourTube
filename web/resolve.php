<?php
// Resolves a YouTube channel URL (@handle, /c/name, /user/name) to a channel ID.
header('Content-Type: application/json; charset=utf-8');
$out = ['id' => null, 'title' => null];
$u = trim($_GET['u'] ?? '');

function done($o) { echo json_encode($o); exit; }
if ($u === '') done($out);

if (preg_match('~^@?([\w.\-]{2,60})$~', $u, $m)) {
    $path = '/@' . $m[1];
} else {
    if (!preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
    $p = parse_url($u);
    $host = strtolower($p['host'] ?? '');
    if (!in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) done($out);
    if (!preg_match('~^/(@[\w.\-]+|(c|user)/[\w.\-%]+)~', $p['path'] ?? '', $m)) done($out);
    $path = $m[0];
}

$url = 'https://www.youtube.com' . $path;
$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
$html = false;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_HTTPHEADER => ['Accept-Language: en-US,en;q=0.9'],
        CURLOPT_COOKIE => 'CONSENT=YES+1; SOCS=CAI',
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
} else {
    $ctx = stream_context_create(['http' => [
        'timeout' => 8,
        'header' => "User-Agent: $ua\r\nAccept-Language: en-US,en;q=0.9\r\nCookie: CONSENT=YES+1; SOCS=CAI\r\n",
    ]]);
    $html = @file_get_contents($url, false, $ctx);
}
if (!$html) done($out);

foreach ([
    '~<link rel="canonical" href="https://www\.youtube\.com/channel/(UC[\w-]{22})"~',
    '~"externalId":"(UC[\w-]{22})"~',
    '~"channelId":"(UC[\w-]{22})"~',
] as $re) {
    if (preg_match($re, $html, $m)) { $out['id'] = $m[1]; break; }
}
if ($out['id'] && preg_match('~<meta property="og:title" content="([^"]+)"~', $html, $t)) {
    $out['title'] = html_entity_decode($t[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
done($out);
