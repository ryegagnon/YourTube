#!/usr/bin/env bash
# Installs YourTube on Raspberry Pi OS (desktop). Safe to run again to update.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(dirname "$HERE")"
APP="$HOME/yourtube"
BIN="$HOME/.local/bin"
ICONS="$HOME/.local/share/icons"
APPS="$HOME/.local/share/applications"
DESKTOP="$(xdg-user-dir DESKTOP 2>/dev/null || echo "$HOME/Desktop")"

echo "==> Installing packages (asks for your password)"
sudo apt-get update
if apt-cache show chromium >/dev/null 2>&1; then CHROMIUM=chromium; else CHROMIUM=chromium-browser; fi
sudo apt-get install -y php-cli php-curl "$CHROMIUM" v4l-utils evtest python3-evdev ffmpeg

echo "==> Copying the app to $APP"
mkdir -p "$APP" "$BIN" "$ICONS" "$APPS" "$DESKTOP"
cp -r "$REPO/web/." "$APP/"

echo "==> Installing launcher scripts to $BIN"
cp "$HERE/yourtube-start" "$HERE/yourtube-stop" "$HERE/yourtube-cec" "$HERE/yourtube-watch" "$BIN/"
chmod +x "$BIN/yourtube-start" "$BIN/yourtube-stop" "$BIN/yourtube-cec" "$BIN/yourtube-watch"

mkdir -p "$HOME/.config/yourtube" "$HOME/.cache/yourtube"
[ -f "$HOME/.config/yourtube/settings.env" ] || printf 'YT_WATCHDOG=1\nYT_TVON=0\n' > "$HOME/.config/yourtube/settings.env"
cp "$HERE/yourtube.svg" "$ICONS/yourtube.svg"

echo "==> Creating the desktop shortcut"
cat > "$APPS/yourtube.desktop" <<DESK
[Desktop Entry]
Type=Application
Name=YourTube
Comment=YouTube channels as live TV
Exec=$BIN/yourtube-start
Icon=$ICONS/yourtube.svg
Terminal=false
Categories=AudioVideo;Video;
DESK
cp "$APPS/yourtube.desktop" "$DESKTOP/yourtube.desktop"
chmod +x "$DESKTOP/yourtube.desktop"
gio set "$DESKTOP/yourtube.desktop" metadata::trusted true 2>/dev/null || true

echo "==> Letting the desktop launch the shortcut without asking"
mkdir -p "$HOME/.config/libfm"
CONF="$HOME/.config/libfm/libfm.conf"
[ -f "$CONF" ] || printf '[config]\n' > "$CONF"
if grep -q '^quick_exec' "$CONF"; then sed -i 's/^quick_exec.*/quick_exec=1/' "$CONF"
else sed -i '/^\[config\]/a quick_exec=1' "$CONF"; fi
pcmanfm --reconfigure 2>/dev/null || true

echo "==> Allowing the TV remote helper to create a virtual keyboard"
echo 'KERNEL=="uinput", GROUP="input", MODE="0660"' | sudo tee /etc/udev/rules.d/99-uinput.rules >/dev/null
echo uinput | sudo tee /etc/modules-load.d/uinput.conf >/dev/null
sudo modprobe uinput || true
sudo udevadm control --reload && sudo udevadm trigger
if ! id -nG "$USER" | grep -qw input; then
  sudo usermod -aG input "$USER"
  echo "    Added you to the 'input' group. Log out and back in once for it to apply."
fi

echo
echo "Done. Double-click the YourTube icon on the desktop to start."
echo "Phone remote: open  http://$(hostname).local:1984/remote.html  and enter the code shown on the TV."
