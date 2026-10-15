YourTube - Turn your favourite YouTube channels, playlists and local video files into a live TV lineup.
<img width="1918" height="1078" alt="Screenshot 2026-10-14 203858" src="https://github.com/user-attachments/assets/45bd7e25-b2c6-40fb-a1fa-8e248ccb00d8" />

Turn your favourite YouTube channels and playlists into a live TV lineup. YourTube plays them full screen like cable channels, with an on-screen TV guide, and remembers your channels next time. It is built for a TV or a Raspberry Pi connected to one, and you can drive it with your TV remote or your phone.

> YourTube is an independent hobby project. It is not affiliated with, endorsed by or sponsored by YouTube or Google. See [Limitations and disclaimer](#limitations-and-disclaimer).

## Features

- **Channels from YouTube.** Paste a channel link (`youtube.com/@name`, `/channel/UC...`) or a playlist link and it becomes a numbered channel.
- **Live-TV style programming.** By default a channel airs its 50 newest videos, and a playlist airs the whole playlist (up to 500 videos), skipping anything shorter than 3 minutes. Each channel can have its own order (newest first, oldest first, shuffled or A to Z), minimum length and number of videos. Everything repeats on a schedule and joins whatever "is on" right now, part-way through.
- **TV guide channel.** Channel 1 is a two-hour guide grid with program blocks sized to each video's real length, channel logos, video thumbnails, a "now" line, and six channels per screen that scroll vertically. Left and Right page ahead through tonight's lineup and beyond, and Now jumps back.
- **Full-screen playback** through YouTube's official embedded player, with a channel banner that fades out.
- **Settings that stick.** Your channel list, last channel, volume and options are saved in the browser.
- **Your own videos too.** Turn a folder of video files on a USB drive or the Pi into a channel that plays alongside the YouTube ones.
- **A problems log.** Settings lists skipped videos and files, guide problems and broken links, with the time and how often each happened.
- **Hide and group channels.** Switch a channel off without deleting it, and tag channels with groups (News, Kids, Music) to filter the guide.
- **Keeps itself running.** A watchdog restarts the app if the server, browser or page stops responding. Optional start at boot, and it can turn the TV on and switch to its input.
- **Works without the internet.** Local channels keep playing, the guide says so plainly, and saved lineups are shown instead of empty ones.
- **Captions are your choice.** Off by default, switch them on in Settings or with `C`.
- **Big, readable text.** Adjustable text size (75% to 300%) for a TV viewed from across the room.
- **TV remote support.** On a Raspberry Pi, your TV's own remote works through HDMI-CEC.
- **Phone remote.** Pair a phone with a 6-character code for a round D-pad, a number pad, channel and volume controls, a live guide, channel management, a sleep timer and more.
- **Launches from a desktop icon** on Raspberry Pi OS and quits cleanly from the Settings screen.

## Install on a Raspberry Pi

You need a Raspberry Pi 4 or 5 (a Pi 3 works at 720p) running Raspberry Pi OS with the desktop, connected to a TV by HDMI and to your network. If you want to use your TV's own remote, turn on HDMI-CEC in the TV's settings (it may be called Anynet+, Bravia Sync, Simplink or EasyLink).

1. Open a terminal on the Pi and make sure git is installed:
   ```bash
   sudo apt update && sudo apt install -y git
   ```
2. Download the project:
   ```bash
   git clone https://github.com/ryegagnon/yourtube.git
   cd yourtube
   ```
3. Run the installer. It asks for your password once:
   ```bash
   ./pi/install.sh
   ```
   It installs the packages YourTube needs (PHP, Chromium and the remote-control tools), copies the app to `~/yourtube`, adds a **YourTube** icon to the desktop and the menu, and sets up TV remote support. If it says it added you to the `input` group, log out and back in once.
4. Double-click the **YourTube** icon. The app opens full screen and plays. It does not start by itself at boot.
5. To use your phone as a remote, open `http://<pi-hostname>.local:1984/remote.html` on a phone on the same network (run `hostname` on the Pi to see its name, or use its IP address from `hostname -I`) and type the 6-character code shown on the TV.
6. To quit, open Settings (press `S`, or use the Settings button at the bottom of the screen) and choose **Exit YourTube**.

To update later, run `git pull` and then `./pi/install.sh` again. Your channels are kept. To remove everything, run `./pi/uninstall.sh`. More detail, and how to change the port or start at boot, is in [docs/raspberry-pi.md](docs/raspberry-pi.md). If something does not work, see [docs/troubleshooting.md](docs/troubleshooting.md).

## Install on a web server

Upload the contents of the `web/` folder to any PHP web host and open the site over `https://`. This gives you the TV screen anywhere, but some features (the Exit button, and the TV remote helper) are Raspberry Pi only. See [docs/web-hosting.md](docs/web-hosting.md) for what works on a host and what does not.

## Using YourTube
<img width="1913" height="922" alt="Screenshot 2026-10-14 203819" src="https://github.com/user-attachments/assets/f875475e-e4b7-455b-811a-19ea2600e512" />

Click or press a key on the first screen ("Turn on"), then use any of these.

| Action | Keyboard | TV remote (Pi) |
| --- | --- | --- |
| Channel up / down | Up / Down, Page Up / Page Down | Up / Down, channel buttons |
| Volume | Left / Right | Left / Right |
| Open or leave the guide | `G`, or Enter while watching | OK, Back, Red |
| Info banner (title, time left, what is next) | `I` | Info |
| Move through time in the guide | Left / Right (`Home` or `N` for now) | Left / Right |
| Filter the guide by group | `T` | - |
| Choose a channel in the guide | Up / Down, then Enter | Up / Down, then OK |
| Jump to a channel | Type its number | Number buttons |
| Mute | `M` | Play/Pause |
| Settings | `S` | Menu, Green |
| Text size | `+` and `-` | Blue (larger), Yellow (smaller) |
| Captions on or off | `C` | Subtitle |
| Full screen | `F` | - |

Channel 1 is always the guide. Your channels start at 2. Moving the mouse shows a control bar at the bottom for the same actions.

## Adding channels

Open **Settings** and paste a link, then choose **Add channel**. Accepted inputs:

- A channel link: `https://www.youtube.com/@name`, `.../channel/UC...`, `.../c/name` or `.../user/name`
- A channel ID that starts with `UC`
- A playlist link or ID

Leave the name blank to use the channel's own name. You can rename and remove channels in the same place. To reorder, type a new number in a channel's number box (on the TV) or, on the phone, type it in the number box to the left of the channel name, or drag the row's handle onto another row. The channel that was in that spot swaps places with it, so moving ESPN to channel 4 sends the old channel 4 to ESPN's former number. Channel 1 is always the guide, and numbers run from 2 up with no gaps. Looking up `@handle` links needs the small PHP helper that ships in `web/` (or an optional YouTube API key).

## Programming options for each channel

In Settings, each channel has an **Options** button (on the phone, **Programming options**) with three choices:

- **Order:** newest first (the default for a channel), oldest first, shuffled, or A to Z by title. For a playlist these are playlist order and reverse playlist order, and for a local folder file name order and reverse file order. Oldest first suits a series, shuffled suits a music folder.
- **Minimum length:** any length, or 1, 3 (the default), 5, 10, 20 or 30 minutes.
- **Number of videos:** 10, 25, 50, 100, 200 or 500.

Changing an option rebuilds that channel's programming. Shuffled and A to Z orders are fixed when the lineup is built, and new videos join the end of the loop.

## Hiding and grouping channels

Open **Options** on a channel (or **Programming options** on the phone) to give it a **Group** such as News or Kids, and to **hide** it. A hidden channel keeps its number but is skipped by channel up and down and left out of the guide; you can still reach it by typing its number. When any channel has a group, the guide shows a **Group** button (or press `T`) to filter by group, and each row shows its group name. **Arrange channels by group** in Settings puts channels of the same group next to each other.

## Running on a Raspberry Pi

In Settings, a **Raspberry Pi** section (also on the phone) has:

- **Start YourTube when the Pi boots:** adds a desktop autostart entry. Off by default.
- **Turn the TV on and switch to this input when YourTube starts:** uses HDMI-CEC, so the Pi works like a cable box. Off by default.
- **Restart automatically if the app crashes or freezes:** on by default. See [docs/raspberry-pi.md](docs/raspberry-pi.md#watchdog).

## Phone remote
<img width="493" height="916" alt="Screenshot 2026-10-14 203618" src="https://github.com/user-attachments/assets/b9464bd8-10a5-4712-a615-516bbc93920d" />

Open `remote.html` on your phone, on the same network as the TV, and enter the 6-character code the TV shows on its start screen and in Settings. It has a Remote tab (round D-pad with a centred OK, channel up and down, number pad, volume), a Guide tab showing what is on and what is next, and a Settings tab for channels, captions, the sleep timer, the API key and quitting. See [docs/remotes.md](docs/remotes.md).

## Local video files
<img width="487" height="930" alt="Screenshot 2026-10-14 203730" src="https://github.com/user-attachments/assets/0e996e64-e41f-4e55-9d62-e701753b5d7b" />

A channel can also be a folder of video files on a USB drive or the Pi. Add it from the phone remote (Settings, then Add a local folder) or from Settings on the TV. See [docs/local-files.md](docs/local-files.md).

## Optional: YouTube API key

Without a key, YourTube reads public YouTube pages to learn video lengths and logos. That works today but can break whenever YouTube changes its pages. A free YouTube Data API key (paste it in Settings) makes the guide use YouTube's official service instead. For a channel it scans up to 300 recent videos to find the newest 50 that are 3 minutes or longer, and a playlist is read in full (up to 500 videos). See [docs/how-it-works.md](docs/how-it-works.md#data-sources).

## Configuration
<img width="485" height="928" alt="Screenshot 2026-10-14 203747" src="https://github.com/user-attachments/assets/14297274-ee12-4ec0-a718-0ebc74888055" />

Most options are in the Settings screen. On a Raspberry Pi the launcher scripts read these environment variables:

| Variable | Default | Meaning |
| --- | --- | --- |
| `YOURTUBE_PORT` | `1984` | Port the local server listens on |
| `YOURTUBE_DIR` | `~/yourtube` | Folder the app is served from |

## Project layout

```
web/    The app itself. Serve this folder from any PHP-capable web server.
  index.html      TV screen: guide, player, settings
  remote.html     Phone remote
  remote.php      Relay between the TV page and the phone
  schedule.php    Builds each channel's programming and finds its logo
  resolve.php     Turns @handle and custom channel links into channel IDs
  exit.php        Quits the app on a Raspberry Pi (local network only)
  local.php       Browses and streams local video files (Raspberry Pi only)
pi/     Raspberry Pi launcher and installer
  install.sh, uninstall.sh
  yourtube-start, yourtube-stop     Launch and quit the app
  yourtube-cec                      Turns TV remote buttons into keystrokes
docs/   Documentation
```

## Documentation

- [Raspberry Pi setup](docs/raspberry-pi.md)
- [Local video files](docs/local-files.md)
- [Web hosting](docs/web-hosting.md)
- [TV remote and phone remote](docs/remotes.md)
- [How it works](docs/how-it-works.md)
- [Troubleshooting](docs/troubleshooting.md)

## Limitations and disclaimer

- Playback uses YouTube's official embedded player. Videos whose owners have disabled embedding are skipped.
- The programming is a repeating schedule built from each channel's recent uploads. It is not a real broadcast and it refreshes in the background every 6 hours while the app is running, so new uploads appear without a restart. **Settings, Refresh guide now** does it immediately.
- Channel logos, video lengths and the video list come from public YouTube pages unless you add an API key. Those pages can change without notice.
- The phone remote needs the TV page and the phone to reach the same server. It uses short polling, so button presses take about one to two seconds.
- YouTube and the YouTube logo are trademarks of Google LLC. Use of the YouTube API or embedded player is subject to the [YouTube Terms of Service](https://www.youtube.com/t/terms) and the [API Services Terms](https://developers.google.com/youtube/terms/api-services-terms-of-service). You are responsible for how you use this software.

## Contributing

Issues and pull requests are welcome. The app has no build step: edit the files in `web/` and refresh. Please keep the app dependency-free (plain HTML, CSS, JavaScript and PHP).

## License

[MIT](LICENSE)
