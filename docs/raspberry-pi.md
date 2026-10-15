# Raspberry Pi setup

YourTube runs well on a Raspberry Pi 4 or 5 connected to a TV by HDMI. A Pi 3 can play 720p.

## Requirements

- Raspberry Pi OS with the desktop (Bookworm or newer), on the network
- A TV connected by HDMI, with HDMI-CEC turned on if you want to use the TV remote (the setting is called Anynet+, Bravia Sync, Simplink, EasyLink or similar)

## Install

```bash
git clone https://github.com/ryegagnon/yourtube.git
cd yourtube
./pi/install.sh
```

The installer asks for your password once and then:

1. Installs `php-cli`, `php-curl`, Chromium, `ffmpeg` (to measure local video files), `v4l-utils`, `evtest` and `python3-evdev`.
2. Copies `web/` to `~/yourtube`.
3. Copies the launcher scripts to `~/.local/bin`.
4. Creates a **YourTube** desktop shortcut and a menu entry, and turns off the file manager's "Execute File?" prompt for it.
5. Adds a rule so the remote helper can create a virtual keyboard (`/dev/uinput`).

If it adds you to the `input` group, log out and back in once.

## Start and quit

- **Start:** double-click the YourTube icon. It starts the local server if needed and opens the app full screen in its own Chromium profile.
- **Quit:** open Settings (press `S`, or use the Settings button) and choose **Exit YourTube**. It closes the browser and stops the server and the remote helper.
- From a terminal you can run `~/.local/bin/yourtube-stop`.

Nothing starts automatically when the Pi boots. To change that, add the launcher to your desktop's autostart (for labwc, put this line in `~/.config/labwc/autostart`):

```bash
~/.local/bin/yourtube-start &
```

## Start at boot

Nothing starts when the Pi boots unless you ask. In Settings, under **Raspberry Pi**, tick **Start YourTube when the Pi boots**. This adds `~/.config/autostart/yourtube.desktop`, which waits 12 seconds for the desktop and then runs `yourtube-start`. Untick it to remove the file. To make the TV turn on and switch to the Pi's input at that moment, tick **Turn the TV on and switch to this input**; it needs HDMI-CEC.

## Watchdog

`yourtube-start` also starts `yourtube-watch`, which checks every 30 seconds that:

1. the server answers,
2. the browser is running, and
3. the TV page has reported in to the server within the last four minutes (so a frozen page is caught too).

If one check fails twice in a row, it closes the browser and server and starts them again, then waits two minutes before checking again. It never restarts anything after you press **Exit YourTube**. Its log is `~/.cache/yourtube/watchdog.log`. You can switch it off with **Restart automatically if the app crashes or freezes** in Settings. The settings you choose are stored in `~/.config/yourtube/settings.env`.

## Pair a phone

On a phone on the same network, open `http://<pi-hostname>.local:1984/remote.html` and enter the code the TV shows. Use the Pi's IP address if `.local` names do not resolve.

## Change the port

The default port is 1984. To use another, set `YOURTUBE_PORT` for the launcher, for example by editing `Exec=` in the desktop shortcut to `env YOURTUBE_PORT=8080 /home/<you>/.local/bin/yourtube-start`. Settings are saved per address, so changing the port starts with default channels.

## Update

```bash
cd yourtube
git pull
./pi/install.sh
```

Your channels are kept, because they live in the browser profile.

## Uninstall

```bash
./pi/uninstall.sh
```

## Manual install

If you prefer not to use the script:

```bash
sudo apt install -y php-cli php-curl chromium
cp -r web ~/yourtube
php -S 0.0.0.0:1984 -t ~/yourtube
```

Then open `http://localhost:1984` in Chromium.
