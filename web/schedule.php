<?php
// Builds a channel's program list (titles and lengths) and finds its logo from YouTube's public pages.
header('Content-Type: application/json; charset=utf-8');
$l = $_GET['l'] ?? '';
if (!preg_match('/^[\w-]{10,50}$/', $l)) { echo '{"items":[]}'; exit; }

$dir = __DIR__ . '/rooms';
if (!is_dir($dir)) {
    @mkdir($dir, 0755);
    @file_put_contents($dir . '/.htaccess',
        "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
}
define("MIN_LEN", 180);   // skip anything shorter than 3 minutes
define("MAX_LEN", 14400);
define("COUNT_CHANNEL", 50);    // a channel airs its 50 latest videos
define("COUNT_PLAYLIST", 500);  // a playlist airs the whole list (safety cap)
// Per-channel options from the page: minimum length in seconds and how many videos to keep.
$minLen = isset($_GET['min']) ? max(10, min(3600, (int)$_GET['min'])) : MIN_LEN;
$want = isset($_GET['count']) ? max(1, min(500, (int)$_GET['count'])) : (strpos($l, 'UU') === 0 ? COUNT_CHANNEL : COUNT_PLAYLIST);
$f = "$dir/sched6_{$l}_{$minLen}_{$want}.json";
$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

function fetchMany($urls, $ua) {
    $out = [];
    if (function_exists('curl_multi_init')) {
        $mh = curl_multi_init(); $hs = [];
        foreach ($urls as $k => $u) {
            $ch = curl_init($u);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_FOLLOWLOCATION => 1, CURLOPT_TIMEOUT => 12,
                CURLOPT_USERAGENT => $ua, CURLOPT_HTTPHEADER => ['Accept-Language: en-US,en;q=0.9'],
                CURLOPT_COOKIE => 'CONSENT=YES+1; SOCS=CAI']);
            curl_multi_add_handle($mh, $ch); $hs[$k] = $ch;
        }
        do { curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 1); } while ($run);
        foreach ($hs as $k => $ch) {
            $out[$k] = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch); curl_close($ch);
        }
        curl_multi_close($mh);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 12,
            'header' => "User-Agent: $ua\r\nAccept-Language: en-US,en;q=0.9\r\nCookie: CONSENT=YES+1; SOCS=CAI\r\n"]]);
        foreach ($urls as $k => $u) $out[$k] = @file_get_contents($u, false, $ctx);
    }
    return $out;
}

// Turns "12:34" or "1:02:03" into seconds.
function dur($s) {
    if (!is_string($s) || !preg_match('~^(?:(\d+):)?(\d{1,2}):(\d{2})$~', trim($s), $m)) return null;
    return ((int)($m[1] ?? 0)) * 3600 + (int)$m[2] * 60 + (int)$m[3];
}
// Finds a length badge such as "12:34" anywhere inside a block of page data.
function findDur($n, &$d) {
    if ($d !== null || !is_array($n)) return;
    foreach ($n as $k => $c) {
        if (is_string($c) && ($k === 'text' || $k === 'simpleText') && ($x = dur($c))) { $d = $x; return; }
        findDur($c, $d);
        if ($d !== null) return;
    }
}
// Collects every video (with its length) from a YouTube page's embedded data. Handles the old and new page layouts.
function walk($n, &$out) {
    if (!is_array($n)) return;
    if (isset($n['playlistVideoRenderer']) || isset($n['videoRenderer'])) {
        $v = $n['playlistVideoRenderer'] ?? $n['videoRenderer'];
        $d = isset($v['lengthSeconds']) ? (int)$v['lengthSeconds'] : dur($v['lengthText']['simpleText'] ?? '');
        $t = $v['title']['runs'][0]['text'] ?? ($v['title']['simpleText'] ?? '');
        if (!empty($v['videoId']) && $d) $out[] = ['id' => $v['videoId'], 't' => $t, 'd' => $d];
        elseif (!empty($v['videoId'])) $GLOBALS['unavail'] = ($GLOBALS['unavail'] ?? 0) + 1;   // private, deleted, live or upcoming
        return;
    }
    if (isset($n['lockupViewModel'])) {
        $v = $n['lockupViewModel'];
        if (!empty($v['contentId']) && preg_match('~^[\w-]{11}$~', $v['contentId'])) {
            $t = $v['metadata']['lockupMetadataViewModel']['title']['content'] ?? '';
            $d = null;
            findDur($v, $d);
            if ($d) $out[] = ['id' => $v['contentId'], 't' => $t, 'd' => $d];
        }
        return;
    }
    foreach ($n as $c) walk($c, $out);
}

// Finds the "load more" token in a page or continuation response.
function findToken($n) {
    if (!is_array($n)) return null;
    if (isset($n['continuationItemRenderer'])) {
        $t = $n['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'] ?? null;
        if ($t) return $t;
    }
    foreach ($n as $c) {
        $t = findToken($c);
        if ($t) return $t;
    }
    return null;
}
// How many distinct videos in the list are the right length to air.
function countUsable($all) {
    $n = 0; $seen = [];
    foreach ($all as $v) {
        if (isset($seen[$v['id']])) continue;
        $seen[$v['id']] = 1;
        if ($v['d'] >= $GLOBALS['minLen'] && $v['d'] <= MAX_LEN) $n++;
    }
    return $n;
}
function postJson($url, $data, $ua) {
    $body = json_encode($data);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $body, CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => $ua, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept-Language: en-US,en;q=0.9'],
            CURLOPT_COOKIE => 'CONSENT=YES+1; SOCS=CAI']);
        $r = curl_exec($ch);
        curl_close($ch);
        return $r;
    }
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15, 'content' => $body,
        'header' => "Content-Type: application/json\r\nUser-Agent: $ua\r\nCookie: CONSENT=YES+1; SOCS=CAI\r\n"]]);
    return @file_get_contents($url, false, $ctx);
}

// Logo for a channel; for a playlist, the logo of the channel that posted its first video.
function findLogo($l, $items, $ua) {
    if (strpos($l, 'UU') === 0) return channelLogoById('UC' . substr($l, 2), $ua);
    if (!$items) return null;
    $w = fetchMany(['https://www.youtube.com/watch?v=' . $items[0]['id']], $ua)[0];
    return ($w && preg_match('~"channelId":"(UC[\w-]{22})"~', $w, $m)) ? channelLogoById($m[1], $ua) : null;
}

// Finds the channel's round profile picture on its public page.
function channelLogoById($cid, $ua) {
    foreach (["https://www.youtube.com/channel/$cid", "https://www.youtube.com/channel/$cid/about"] as $url) {
        $html = fetchMany([$url], $ua)[0];
        if (!$html) continue;
        foreach ([
            '~<meta\s+property="og:image"\s+content="([^"]+)"~i',
            '~<meta\s+content="([^"]+)"\s+property="og:image"~i',
            '~<link\s+rel="image_src"\s+href="([^"]+)"~i',
            '~"avatar":\{"thumbnails":\[\{"url":"([^"]+)"~',
        ] as $re) {
            if (preg_match($re, $html, $m)) {
                $u = html_entity_decode(str_replace('\u0026', '&', $m[1]));
                if (!preg_match('~(ggpht|googleusercontent)\.com~', $u)) continue;
                return preg_match('~=s\d+~', $u) ? preg_replace('~=s\d+~', '=s240', $u) : $u . '=s240-c-k-c0x00ffffff-no-rj';
            }
        }
    }
    return null;
}

$maxAge = isset($_GET['fresh']) ? 600 : 21600;   // ?fresh=1 skips the cache unless it is under 10 minutes old
if (is_file($f) && time() - filemtime($f) < $maxAge) {
    $c = json_decode(file_get_contents($f), true);
    if (is_array($c)) {
        if (empty($c['logo'])) {
            $c['logo'] = findLogo($l, $c['items'] ?? [], $ua);
            if ($c['logo']) @file_put_contents($f, json_encode($c));
        }
        echo json_encode($c);
        exit;
    }
}

// 1) The playlist page lists the first ~100 videos (newest first for a channel) with their lengths,
//    then more are loaded with continuation tokens until we have enough.
$all = [];
$html = fetchMany(["https://www.youtube.com/playlist?list=$l"], $ua)[0];
if ($html && ($i = strpos($html, 'var ytInitialData = ')) !== false) {
    $i += 20;
    $j = strpos($html, ';</script>', $i);
    if ($j !== false) {
        $d = json_decode(substr($html, $i, $j - $i), true);
        if (is_array($d)) {
            walk($d, $all);
            $target = $want;
            $token = findToken($d);
            $key = preg_match('~"INNERTUBE_API_KEY":"([^"]+)"~', $html, $mk) ? $mk[1] : '';
            $ver = preg_match('~"INNERTUBE_CONTEXT_CLIENT_VERSION":"([^"]+)"~', $html, $mv) ? $mv[1] : '2.20240101.00.00';
            for ($p = 0; $p < 12 && $token && $key && countUsable($all) < $target; $p++) {
                $resp = postJson("https://www.youtube.com/youtubei/v1/browse?key=$key",
                    ['context' => ['client' => ['clientName' => 'WEB', 'clientVersion' => $ver, 'hl' => 'en']], 'continuation' => $token], $ua);
                $j = json_decode((string)$resp, true);
                if (!is_array($j)) break;
                $before = count($all);
                walk($j, $all);
                if (count($all) === $before) break;
                $token = findToken($j);
            }
        }
    }
}

// 2) Fall back to the feed (latest 15 videos) plus each video's own page for its length.
if (!$all) {
    $feeds = strpos($l, 'UU') === 0
        ? ['https://www.youtube.com/feeds/videos.xml?channel_id=UC' . substr($l, 2), "https://www.youtube.com/feeds/videos.xml?playlist_id=$l"]
        : ["https://www.youtube.com/feeds/videos.xml?playlist_id=$l"];
    $xml = null;
    foreach ($feeds as $u) {
        $x = fetchMany([$u], $ua)[0];
        if ($x && strpos($x, '<entry>') !== false) { $xml = $x; break; }
    }
    $vids = [];
    if ($xml && preg_match_all('~<entry>.*?<yt:videoId>([\w-]{11})</yt:videoId>.*?<title>(.*?)</title>~s', $xml, $m, PREG_SET_ORDER)) {
        foreach (array_slice($m, 0, 15) as $e) $vids[$e[1]] = html_entity_decode($e[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
    if ($vids) {
        $urls = [];
        foreach ($vids as $id => $t) $urls[$id] = "https://www.youtube.com/watch?v=$id";
        $pages = fetchMany($urls, $ua);
        foreach ($vids as $id => $t) {
            if (!empty($pages[$id]) && preg_match('~"lengthSeconds":"(\d+)"~', $pages[$id], $m)) {
                $all[] = ['id' => $id, 't' => $t, 'd' => (int)$m[1]];
            }
        }
    }
}

$limit = $want;
$stale = is_file($f) ? json_decode(file_get_contents($f), true) : null;
$items = []; $seen = [];
$skip = ['short' => 0, 'long' => 0, 'unavailable' => $GLOBALS['unavail'] ?? 0];
foreach ($all as $v) {
    if (isset($seen[$v['id']])) continue;
    $seen[$v['id']] = 1;
    if ($v['d'] < $minLen) { $skip['short']++; continue; }
    if ($v['d'] > MAX_LEN) { $skip['long']++; continue; }
    $items[] = $v;
    if (count($items) >= $limit) break;
}
// If YouTube could not be read this time, keep serving the last good schedule.
if (!$items && is_array($stale) && !empty($stale['items'])) { echo json_encode($stale); exit; }
$logo = findLogo($l, $items, $ua);
$json = json_encode(['items' => $items, 'logo' => $logo, 'skipped' => $skip]);
if ($items) @file_put_contents($f, $json);
echo $json;
