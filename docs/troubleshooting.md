# Troubleshooting

## The page shows "Turn on" and nothing happens, or the screen stays black

- Open the site through its web address (`http://...` or `https://...`), not by double-clicking `index.html`. YouTube refuses to play in a page opened from a file.
- Make sure autoplay with sound is allowed. The Pi launcher already passes the right flag.
- Some videos have embedding disabled by their owners. YourTube skips them; if every video on a channel is blocked you will see a message about no playable videos.

## The guide says "Schedule unavailable" or shows no logos

The server could not read the channel's video list. Check, from the machine running YourTube:

```bash
curl -s -m 90 "http://localhost:1984/schedule.php?l=UULA_DiR1FfKNvjuUpBHmylQ" | head -c 400
```

You should see `{"items":[{"id":"...`. If you see `{"items":[],"logo":null}`:

- Install the PHP curl extension: `sudo apt install -y php-curl`, then quit and reopen YourTube.
- Check the Pi can reach YouTube: `curl -sI https://www.youtube.com | head -1`.
- YouTube may have changed its pages, or be rate limiting you after many requests. Wait an hour, or add a YouTube Data API key in Settings, which avoids scraping.

Check the PHP file itself for errors with `php -l web/schedule.php`.

## Where do I see what went wrong

Open Settings (on the TV, or the phone remote's Settings tab) and look at the status line on each channel's row (videos found, loop length, last update, or the problem) and at **Problems and skipped items** for the history: skipped videos and files with the reason, feeds that could not be reached, and links that could not be added. Entries repeat as a count ("seen 3 times"). **Clear log** empties it.

## The app restarted by itself

That is the watchdog. See `~/.cache/yourtube/watchdog.log` for the reason and time. If it keeps restarting, run `~/.local/bin/yourtube-watch` in a terminal to see nothing printed (it logs to the file), check the server with `curl -I http://127.0.0.1:1984/index.html`, and make sure only one copy of the app is running. You can switch the watchdog off in Settings while you investigate.

## YourTube does not start when the Pi boots

Tick **Start YourTube when the Pi boots** in Settings and reboot. The file `~/.config/autostart/yourtube.desktop` should exist. It needs the desktop to be set to log in automatically (Raspberry Pi Configuration, System, Auto login). If the TV is slow to wake, give it a moment; the launcher waits 12 seconds.

## The TV did not turn on

**Turn the TV on and switch to this input** needs HDMI-CEC to be on in the TV and a `/dev/cec0` device on the Pi. Try by hand: `cec-ctl -d /dev/cec0 --to 0 --image-view-on`. Some TVs ignore the switch-input request; the TV's own HDMI input setting may need to be left on the Pi.

## No internet: what still works

Local channels keep playing. YouTube channels show their last saved lineup in the guide but cannot play, and a red banner says so. When the connection comes back the guide refreshes by itself.

## The lineup restarted after I changed an option

Changing a channel's order, minimum length or number of videos rebuilds its programming, so the channel starts a new loop and the one you were watching re-tunes. That is expected. Other changes, like new uploads, never reshuffle a lineup.

## Captions are showing, or not showing

Captions are off by default. Turn them on in Settings (**Show captions**), with `C`, or from the phone. YourTube asks the YouTube player to load or unload its captions module each time a video starts. That is an unofficial player feature, so if a video still shows captions you did not ask for, check whether the TV itself (or a Kodi-style CEC setting) is adding them, and open an issue with the video link.

## The guide does not show a channel's new videos

The guide refreshes every 6 hours while the app runs. To update right now, open Settings and choose **Refresh guide now**. If new videos still do not appear, YouTube may not have listed them yet, or the server could not read YouTube and kept the last good schedule; run the `schedule.php` check above to see what the server returns.

## A large playlist takes a long time to appear

The first time a playlist is added, YourTube reads the whole list (up to 500 videos), which can take up to a minute on a Raspberry Pi. The result is cached for 6 hours, so later loads are quick. If it times out, run the `schedule.php` check above with `-m 180` to see how long it really takes. Installing `php-curl` helps.

## A playlist channel shows a thumbnail instead of a logo

A playlist has no logo of its own. YourTube uses the logo of the channel that posted its first video. If that cannot be found it falls back to a video thumbnail or the first letter of the name. An API key makes this more reliable.

## Port already in use

`Failed to listen on 0.0.0.0:1984 (Address already in use)` means another copy is still running. Stop it with `~/.local/bin/yourtube-stop`, or find what is using the port: `sudo ss -ltnp | grep 1984`.

## The TV remote does nothing

1. CEC must be on in the TV's settings, the TV must be on the Pi's input, and Kodi must be closed.
2. Run the helper by hand to see what it finds: `python3 ~/.local/bin/yourtube-cec`. You should see `listening to vc4-hdmi-0` and a `remote key` line for each press.
3. `Cannot create the virtual keyboard ... Permission denied` means `/dev/uinput` is not usable. Re-run `./pi/install.sh`, or add the rule by hand:
   ```bash
   echo 'KERNEL=="uinput", GROUP="input", MODE="0660"' | sudo tee /etc/udev/rules.d/99-uinput.rules
   echo uinput | sudo tee /etc/modules-load.d/uinput.conf
   sudo modprobe uinput && sudo udevadm control --reload && sudo udevadm trigger
   ```
   Log out and in again if you were just added to the `input` group.
4. If keys arrive but do the wrong thing, tick **Show remote key codes** in Settings to see what the app receives.
5. The helper's log is `/tmp/yourtube-cec.log`.

## A local folder will not add or play

- `ffprobe is not installed`: run `sudo apt install -y ffmpeg`.
- The drive is missing from the list: it may not be mounted. Open it once in the file manager, then try again. Drives appear under `/media/<your user>/`.
- Files show but the channel cannot play them: they are probably in a format Chromium cannot decode. Use MP4 (H.264 and AAC) or WebM; see [local files](local-files.md).
- `Local folders only work when YourTube runs on the Raspberry Pi`: the page is being served from a web host, not from the Pi.
- Check the server directly: `curl -s "http://localhost:1984/local.php?a=ls"`.

## Some phone remote buttons do nothing

Channel up and down work but the D-pad does not: the phone page and the TV page are newer than `remote.php` on the server, which strips fields it does not know. Copy the current `web/remote.php` (and the other files in `web/`) to the server, then reload both pages.

## The phone cannot find the TV

- The phone must be on the same network as the Pi (not mobile data).
- The code must match the one on the TV. Use **New remote code** in Settings if you changed or shared it.
- If `hostname.local` does not resolve on the phone, use the Pi's IP address (`hostname -I`) and give the Pi a fixed address in your router.
- Create a `rooms` folder next to `remote.php` if the server cannot create it.

## The desktop icon asks "Execute File?" every time

`./pi/install.sh` turns this off. By hand: File Manager, Edit, Preferences, General, tick **Don't ask options on launch executable file**.

## Chrome asks for a keyring password

The launcher uses `--password-store=basic`, which avoids it. If you launch Chromium another way, add that flag.

## Channels are gone after changing the address or port

Settings are saved per address and per browser profile. Always use the same address, including the port, and launch from the same shortcut.

## Wrong video on a channel after switching

Reload the app. If it persists, open an issue and include the output of the `schedule.php` check above for that channel.
