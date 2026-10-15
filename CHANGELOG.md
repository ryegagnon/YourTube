# Changelog

## 0.2.0

- Hide and group channels: a channel can be hidden (skipped by channel up and down and left out of the guide) and given a group; the guide filters by group (`T` or the Group button), and **Arrange channels by group** orders the list.
- Watchdog (`yourtube-watch`): restarts the server, browser or page if it stops responding; never after an intentional exit. Log in `~/.cache/yourtube/watchdog.log`.
- Start at boot, and turn the TV on and switch to the Pi's input over HDMI-CEC, as options in Settings (new `web/system.php`).
- Offline behaviour: connection checks, a guide banner, automatic switch to a local channel, YouTube player script retried when the internet returns, and saved lineups shown when YouTube cannot be read. Local channels now play even when the YouTube player could not load.
- Per-channel programming options: order (newest, oldest, shuffled, A to Z), minimum video length and number of videos, on the TV (Options button) and the phone (Programming options). `schedule.php` takes `min` and `count`.
- The guide pages through time: Left and Right (or the Earlier, Later and Now buttons) move ahead in two-hour steps. Home or N returns to now.
- Info banner: the Info key (TV remote or `I`) shows the title, time left, progress and the next title without opening the guide. The phone remote has an Info button. The TV remote helper now maps Info to `I` instead of the guide.
- Settings on the TV is much wider, and the separate channel status list is gone: each channel's row now carries its own status (videos found, loop length, last update, source or the problem). Options and the problems log sit side by side below it, so the window is no longer one long column.
- Problems log in Settings (TV and phone): skipped videos and local files with the reason, guide and feed failures, and links that could not be added. Repeats are counted, and the log is kept between runs.
- Fix: the **Refresh guide now** button on the TV did nothing because its handler had been lost; it works again.
- Local video folders as channels: browse a USB drive or the Pi from the phone remote or the TV, and the files air on a looping schedule in the guide. New `web/local.php`; the installer now installs ffmpeg.
- Fix: the phone D-pad did nothing when the server still had an older `remote.php`. The key name now also travels in a field the old version accepts, and the troubleshooting guide covers it.
- Channel numbers can be set directly. On the TV, type a number in the channel's box. On the phone, type it in the box beside the channel name, or drag the row's handle onto another row. The channel that was there swaps into the old spot, and the phone shows the new order straight away.
- Phone remote: a round, remote-style D-pad with OK in the centre, and a number pad that is always visible. The Pause, Next video, Mute, Back and Menu buttons were removed.
- Steady lineup: a refresh no longer reshuffles the guide. The video airing now keeps airing, videos that are gone drop out, and new uploads join the end of the loop.
- New phone remote: D-pad, pause, skip, progress bar, number pad, a Guide tab, channel logos and per-channel status, sleep timer, captions switch, API key entry, refresh, and quit.
- Guide status in Settings (TV and phone): videos found, loop length, last update, source and the last problem for each channel.
- Captions are now an option, off by default. `C` toggles them.
- The guide now refreshes by itself every 6 hours while the app is running, so new uploads show up without restarting. A playing channel rejoins the updated schedule when its current video ends.
- New **Refresh guide now** button in Settings.
- If YouTube cannot be read during a refresh, the last good schedule is kept instead of being replaced by an empty one.
- A channel now airs its 50 newest videos (was 25).
- A playlist now airs the whole playlist, up to 500 videos (was 25). `schedule.php` follows YouTube's "load more" tokens to read long playlists.
- The optional YouTube API key path pages further to match: up to 300 recent videos scanned for a channel, 500 for a playlist.
- Cached schedules are renamed, so every channel rebuilds its programming once after updating.

## 0.1.0

- Channels and playlists as numbered TV channels, with a two-hour guide, logos, thumbnails and full-screen playback.
- Raspberry Pi installer with a desktop shortcut and an Exit button in settings.
- TV remote support over HDMI-CEC, and a phone remote paired by code.
- Adjustable text size, saved settings, optional YouTube API key.
