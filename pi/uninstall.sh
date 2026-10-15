#!/usr/bin/env bash
# Removes YourTube from the Pi. Your channel settings live in the browser profile, removed below.
set -u
DESKTOP="$(xdg-user-dir DESKTOP 2>/dev/null || echo "$HOME/Desktop")"
"$HOME/.local/bin/yourtube-stop" 2>/dev/null
rm -f "$HOME/.local/bin/yourtube-"{start,stop,cec,watch} "$HOME/.config/autostart/yourtube.desktop"
rm -rf "$HOME/.config/yourtube" "$HOME/.cache/yourtube"
rm -f "$HOME/.local/share/icons/yourtube.svg" "$HOME/.local/share/applications/yourtube.desktop" "$DESKTOP/yourtube.desktop"
rm -rf "$HOME/.config/yourtube-browser"
read -r -p "Also delete the app folder $HOME/yourtube? [y/N] " a
[ "$a" = y ] && rm -rf "$HOME/yourtube"
sudo rm -f /etc/udev/rules.d/99-uinput.rules /etc/modules-load.d/uinput.conf
echo "YourTube removed."
