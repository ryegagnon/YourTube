# How it works

YourTube is a single-page app plus a few small PHP helpers. There is no build step and no database.

## Overview

```
 TV browser (index.html) ──── YouTube IFrame Player  (plays the video)
        │   ▲
        │   └─ schedule.php  (video list, lengths, logos)   resolve.php  (@handle lookup)
        │
        └─ remote.php  ◄───────────────  phone (remote.html)
```

- `index.html` is the whole TV experience: player, guide, banner, settings and keyboard handling.
- The player is YouTube's official IFrame Player. YourTube never downloads or re-streams video.
- Channel data is fetched by the small PHP files, because browsers cannot read YouTube pages directly.

## Channels

Each channel is stored as a name plus a YouTube playlist ID.

- A channel's uploads playlist ID is its channel ID with the leading `UC` replaced by `UU`.
- A playlist is used as it is.
- Settings (channels, last channel, volume, text size, options, remote code) are saved in `localStorage` under the key `channelsurf.v1`. That means they are per browser and per address.

## The schedule

For each channel, `schedule.php` returns a list of videos that are at least 3 minutes and at most 4 hours long, each with its title and length in seconds. A channel gets its 50 newest such videos. A playlist gets every such video in the list, in playlist order, up to a safety cap of 500. `index.html` then turns the list into a looping timeline:

1. Add up the lengths to get the total loop length `T`.
2. A position on the timeline is `(unix time) mod T`. This is the same for every viewer, so a channel is "on" at the same point everywhere.
3. Tuning a channel finds the video airing now and its offset, loads that video and seeks to the offset. When it ends, the next video in the list starts.

### A steady lineup

A new upload could change the loop and make the guide jump. To avoid that, the browser remembers each channel's airing order (`yt.o.*` in localStorage). When a refresh arrives, videos that are gone drop out, new videos are added to the **end** of the loop, and the timeline is re-anchored so the video airing now keeps airing at the same point. A playing channel rejoins the updated schedule when its current video ends.

The guide draws the same timeline for a two-hour window. It starts at the current half hour, and Left and Right (or the Earlier, Later and Now buttons) move it forward in two-hour pages, up to a day ahead. The window is `goff` pages after now, so what the guide shows is what plays.

If a schedule cannot be built (for example YouTube cannot be reached), the channel falls back to shuffled playlist playback and the guide shows "Schedule unavailable".

## Data sources

`schedule.php` tries these, in order:

1. The uploads or playlist page, which lists about 100 videos with their lengths in one request. When more are needed (a long playlist, or a channel with many short videos), `schedule.php` follows YouTube's "load more" tokens, up to 12 extra pages.
2. YouTube's public feed (the latest 15 videos) plus each video's own page for its length.

Logos come from the channel page. For a playlist, the logo is the logo of the channel that posted its first video. If you add a YouTube Data API key in Settings, the browser uses the official API instead (playlist items, video durations, channel thumbnails) and skips the scraping. It scans up to 300 recent videos for a channel and 500 for a playlist. The key is stored only in that browser.

While the app is running, each channel's schedule is reloaded in the background every 6 hours (checked every 15 minutes), and also when the screen wakes up. A failed refresh keeps the last good schedule and tries again later. A channel that is playing rejoins the new schedule when its current video ends. **Settings, Refresh guide now** forces it, and `schedule.php?fresh=1` skips the server cache unless it is under 10 minutes old.

Results are cached for 6 hours, on the server (`web/rooms/sched6_<list>_<min>_<count>.json`) and in the browser (`yt.s7.*` in localStorage); the minimum length and count are part of the cache key, so changing an option fetches fresh data.

## Local channels

A local-folder channel has the id `local:<path>` and no YouTube data. `local.php?a=scan` lists the video files and measures each with `ffprobe` (cached in `web/rooms/local_lengths.json`), and `a=file` streams a file with HTTP range support so seeking works. The schedule is built exactly like a YouTube one, but items carry a `u` (file URL), and playback uses a `<video>` element laid over the YouTube player instead of the player itself.

## Problems log

`logEvent()` in `index.html` records skipped videos and files, guide failures and channels that could not be added. It keeps the newest 200 entries in localStorage (`yt.log`), and an identical entry seen again updates its time and count instead of adding a new line. Entries come from:

- `schedule.php` and `local.php`, which report how many videos were too short (under 3 minutes for YouTube, 5 seconds for local files), too long, or unusable (private, deleted, live), plus unsupported or unreadable local files.
- The YouTube player's error codes (removed, private, embedding turned off).
- The `<video>` element for local files (cannot decode, format not supported, drive error).
- Failed schedule loads and refreshes, including a feed that stopped connecting.

Settings on the TV and the phone's Settings tab show the log; `clearlog` empties it.

## Programming options

Each channel may carry `o: {order, min, count}`; missing values fall back to defaults by kind (channel: newest first, 3 minutes, 50; playlist: playlist order, 3 minutes, 500; local: file name order, any length, 500). `min` and `count` go to `schedule.php` (`?min=&count=`) or the API path, and `local.php` results are filtered in the browser. The order is applied to each fresh list before the lineup is steadied. The steady-lineup state stores a signature of the options, so changing one starts the lineup afresh.

## Info banner

Pressing Info shows the same banner as a channel change, with the title, time left, a progress bar and the next title, for seven seconds.

## Hidden channels and groups

A channel may carry `hide: true` and `g: "News"`. Channel numbers stay equal to position, so hiding never renumbers anything. `step()` skips hidden channels for up and down, and the guide builds its rows from the visible, filtered list (`GV`). `S.gf` is the active group filter.

## Offline behaviour

The TV page checks every 30 seconds whether `youtube.com` answers; two failures in a row mean the internet is down (`net`). It then shows a banner in the guide, logs the event, and, if you are watching a YouTube channel and a local channel exists, switches to it. Local channels need nothing from the internet. When the connection returns, the page refreshes the schedules and reloads the YouTube player script if it never loaded. When YouTube cannot be read and no fresh lineup is available, the last saved copy is used (the channel row says so), instead of an empty guide.

## System page

`web/system.php` (local network only) reads and writes `~/.config/yourtube/settings.env` and the autostart entry. `yourtube-watch` restarts the app when a check fails; `yourtube-stop` leaves a marker file so an intentional stop is never "repaired".

## Channel status

Each channel's row in Settings on the TV shows how many videos were found, the length of the loop, when it was last updated, where the data came from (saved copy, `schedule.php` or the YouTube API) and the last problem, if any. The same details appear next to each channel on the phone.

## Phone remote protocol

`remote.php` takes `c` (the 6-character code) and `a` (the action):

| Action | Who | Purpose |
| --- | --- | --- |
| `tv` (POST) | TV page | Optionally uploads its state; returns and clears queued commands |
| `state` (GET) | Phone | Returns the TV's last state and how long ago the TV checked in |
| `cmd` (POST) | Phone | Queues one command, `{"cmd": {...}}` (at most 30 waiting) |

Commands are small objects with a type `t` and optional fields:

| `t` | Fields | Effect |
| --- | --- | --- |
| `tune` | `i` | Go to channel index `i` (0 is the guide) |
| `up`, `down`, `guide`, `mute` | - | Channel up/down, toggle guide, toggle mute |
| `vol` | `v` | Set volume 0 to 100 |
| `scale` | `d` | Text size larger (`d` > 0) or smaller |
| `add` | `src`, `name` | Add a channel from a link |
| `rm`, `mv`, `ren` | `i`, `d`, `n` | Remove, move up or down one place, rename a channel |
| `to` | `i`, `v` | Move channel index `i` to channel number `v`, swapping with the channel there |
| `seek`, `reset` | `v` | Toggle "join part-way", restore default channels |
| `key` | `k` | Send a navigation key (`up`, `down`, `left`, `right`, `ok`, `back`, `guide`, `set`, `chup`, `chdn`) |
| `pause`, `next` | - | Pause or resume; skip to the next video |
| `sleep` | `v` | Quit after `v` minutes (0 cancels) |
| `gf` | `v` | Set the guide's group filter (empty for all) |
| `grp` | `i`, `v` | Set a channel's group |
| `hide` | `i`, `v` | Hide or show a channel |
| `opt` | `i`, `k`, `v` | Set a channel option: `k` is `order` (`default`, `reverse`, `shuffle`, `alpha`), `min` (seconds) or `count` |
| `cc` | `v` | Captions on (`true`) or off |
| `apikey` | `v` | Save a YouTube API key on the TV |
| `refresh` | - | Reload every channel's schedule now |
| `settings` | - | Open Settings on the TV |
| `exit` | - | Quit the app (Raspberry Pi) |

Each pairing code is a JSON file in `web/rooms/`. Files older than 30 days are removed at random on use. The folder is protected by an `.htaccess` file and ignored by git.

## Launching on a Raspberry Pi

`yourtube-start` starts `php -S` (with four workers so slow lookups do not block the page) if the port is free, starts the remote helper, then opens Chromium in kiosk mode with its own profile, `--autoplay-policy=no-user-gesture-required`, `--password-store=basic` (no keyring prompt) and `#kiosk` in the address, which powers the TV on without a click.
