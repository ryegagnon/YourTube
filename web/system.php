<?php
// Raspberry Pi settings for YourTube: start at boot, turn the TV on, and the watchdog. Local network only.
//   ?a=status            what is switched on
//   ?a=set&k=&v=         change one of them (POST): k is boot, tvon or watchdog; v is 1 or 0
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function jout($o) { echo json_encode($o); exit; }

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
    http_response_code(403);
    jout(['ok' => false, 'error' => 'Only available on the local network.']);
}
$home = rtrim(getenv('HOME') ?: '', '/');
if (!$home) jout(['ok' => false, 'error' => 'Not running on a Raspberry Pi install.']);

$a = $_GET['a'] ?? '';
$cfgDir = "$home/.config/yourtube";
$cfg = "$cfgDir/settings.env";
$auto = "$home/.config/autostart/yourtube.desktop";

function cfgRead($cfg) {
    $o = ['YT_TVON' => '0', 'YT_WATCHDOG' => '1'];
    foreach (@file($cfg, FILE_IGNORE_NEW_LINES) ?: [] as $l) {
        if (preg_match('/^(YT_[A-Z]+)=(\d)$/', trim($l), $m)) $o[$m[1]] = $m[2];
    }
    return $o;
}
function cfgWrite($cfgDir, $cfg, $o) {
    @mkdir($cfgDir, 0755, true);
    $t = '';
    foreach ($o as $k => $v) $t .= "$k=$v\n";
    return file_put_contents($cfg, $t) !== false;
}

if ($a === 'status') {
    $o = cfgRead($cfg);
    jout(['ok' => true, 'boot' => is_file($auto), 'tvon' => $o['YT_TVON'] === '1',
          'watchdog' => $o['YT_WATCHDOG'] === '1', 'cec' => (bool)glob('/dev/cec*')]);
}

if ($a === 'set' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $k = $_GET['k'] ?? ''; $v = ($_GET['v'] ?? '0') === '1';
    if ($k === 'boot') {
        if ($v) {
            @mkdir(dirname($auto), 0755, true);
            $d = "[Desktop Entry]\nType=Application\nName=YourTube\nComment=Start YourTube when the desktop loads\nExec=sh -c \"sleep 12; $home/.local/bin/yourtube-start\"\nX-GNOME-Autostart-enabled=true\n";
            jout(['ok' => file_put_contents($auto, $d) !== false]);
        }
        @unlink($auto);
        jout(['ok' => true]);
    }
    if ($k === 'tvon' || $k === 'watchdog') {
        $o = cfgRead($cfg);
        $o[$k === 'tvon' ? 'YT_TVON' : 'YT_WATCHDOG'] = $v ? '1' : '0';
        jout(['ok' => cfgWrite($cfgDir, $cfg, $o)]);
    }
    jout(['ok' => false, 'error' => 'Unknown setting.']);
}

jout(['ok' => false, 'error' => 'Unknown action.']);
