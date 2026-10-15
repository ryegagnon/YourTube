# Local video files (USB drive or the Pi)

A channel can be built from a folder of video files instead of a YouTube channel. The files play full screen in the same guide, with the same schedule, "what is on now" and phone remote as any other channel.

This works when YourTube runs on a Raspberry Pi (or another computer) with the files attached. It does not work on a web host, because the host cannot see your drive.

## Add a folder

**From the phone remote (easiest):** Settings tab, then **Add a local folder**. Browse to the folder and choose **Use this folder**.

**On the TV:** Settings, then **Browse for a local folder**. Use the remote's arrow keys and OK to open folders, then choose **Use this folder**.

You can also paste a folder path that starts with `/` (for example `/media/pi/MYUSB/Movies`) in the add box.

The list starts with:

- **USB drive:** each drive plugged into the Pi. Raspberry Pi OS mounts them under `/media/<your user>/`.
- **Drive:** anything mounted under `/mnt`.
- **Folder:** `~/Videos` and `~/Movies`.

Subfolders are included, up to four levels deep and 500 files. To use another location, set `YOURTUBE_MEDIA` to one or more folders separated by colons before starting the app.

## How the programming works

- The files air in name order (A to Z, numbers sorted naturally, so `Episode 2` comes before `Episode 10`), looping like any other channel.
- Each file's title in the guide is its file name without the extension.
- Files shorter than 5 seconds, or shorter than the channel's minimum length if you set one, are skipped. In the channel's options you can also choose reverse file order, shuffled or A to Z order, and how many files to use.
- New files are added to the end of the loop, like new uploads on a YouTube channel. The folder is rescanned every 15 minutes while the app runs, and **Refresh guide now** rescans at once.
- Lengths are measured with `ffprobe` (part of ffmpeg), a few files at a time, and remembered. The first scan of a big drive can take a minute.

## What plays

The files play in the browser, so use formats Chromium can decode: **MP4/M4V/MOV with H.264 video and AAC audio**, **WebM**, or **MKV with H.264**. Other files are skipped when they fail to play. Convert them with ffmpeg if needed:

```bash
ffmpeg -i input.avi -c:v libx264 -c:a aac output.mp4
```

On a Pi 4 or 5, 1080p H.264 plays smoothly. 4K and HEVC (H.265) files may stutter or not play.

## Without the internet

Local channels do not need the internet. If it goes down while you are watching a YouTube channel, YourTube switches to your first local channel and says so.

## If the drive is unplugged

The guide keeps the last schedule, the channel's row in Settings shows the problem, and playback skips to the next file that can be read. Plug the drive back in and choose **Refresh guide now**.

## Security

`local.php` only serves clients on your local network, only video files, and only from the media folders above. It cannot read other files on the Pi.
