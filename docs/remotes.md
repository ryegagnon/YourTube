# Remotes

## TV remote (Raspberry Pi, HDMI-CEC)

Your TV's own remote can control YourTube through HDMI-CEC, the same link Kodi uses.

### How it works

1. `yourtube-start` registers the Pi as a CEC playback device (`cec-ctl --playback`).
2. The Linux kernel exposes the remote's buttons as an input device named like `vc4-hdmi-0`.
3. `yourtube-cec` reads that device and re-emits the buttons as keystrokes from a virtual keyboard. This avoids desktops that ignore remote-control devices.
4. YourTube reacts to the keystrokes.

### Button mapping

| TV remote button | Becomes | In YourTube |
| --- | --- | --- |
| Up / Down / Left / Right | Arrow keys | Channel up/down; volume down/up; in the guide, Up/Down move the highlight and Left/Right move through time; move in settings |
| OK / Select | Enter | Open the guide; pick the highlighted channel |
| Back / Exit | Escape | Open or leave the guide; close settings |
| Channel + / - | Page Up / Page Down | Channel up/down; page of six in the guide |
| Number buttons | Digits | Jump to a channel |
| Red, Guide | `G` | Guide |
| Info | `I` | Info banner: the channel, the current title, time left and what is next |
| Green, Menu | `S` | Settings |
| Yellow / Blue | `-` / `=` | Text smaller / larger |
| Play/Pause | `M` | Mute |

### Requirements

- CEC enabled on the TV, and the TV on the Pi's HDMI input.
- Close Kodi first. Only one program can use the CEC adapter.
- `/dev/uinput` must be usable by your user (the installer sets this up).

### Check it

```bash
cec-ctl -d /dev/cec0 --playback
python3 ~/.local/bin/yourtube-cec
```

The helper prints the devices it finds and every button press. In YourTube's Settings, tick **Show remote key codes** to see how each button arrives in the app. The helper writes to `/tmp/yourtube-cec.log` when started from the launcher.

## Phone remote

Open `remote.html` on a phone on the same network. The first time, enter the 6-character code from the TV (shown on the start screen and in Settings). The phone remembers it.

### Tabs

- **Remote:** channel up and down, Guide, Info (the banner), a round D-pad with OK in the centre (it also moves around the TV's Settings screen), a number pad to jump to a channel (tap the digits, then Go, or wait a moment), a volume slider, text size and a captions switch. A progress bar shows how far through the current video the TV is.
- **Guide:** every channel with its logo, what is on now with the time left, and what is next. Tap one to tune it. Hidden channels are left out, and group buttons filter the list. A red banner appears if the TV has lost its internet connection.
- **Settings:** add a channel by pasting a link; rename or remove channels, set each channel's order, minimum length, number of videos, group and whether it is hidden under **Programming options**, change a channel's number by typing it in the box beside its name, or by dragging the row's handle onto another row (the channel already there swaps into its old spot), and see each channel's status (how many videos were found, when the guide last updated, or the problem); join part-way through; captions; a sleep timer; the optional YouTube API key (so you do not have to type it with a TV remote); **Refresh guide now**; open Settings on the TV; restore default channels; exit the app; pair with a different TV.

### How it communicates

The phone and TV page both talk to `remote.php`, which keeps a small JSON file per code. The TV page pushes its state and polls for commands; the phone reads state and sends commands. There are no websockets, so commands take about one to two seconds, and the TV polls more slowly (every 6 seconds) when no phone has been active recently. The command list is in [how it works](how-it-works.md#phone-remote-protocol).

### Security

The pairing code is the only protection. The API key you send from the phone passes through the relay file on the server for a moment and is stored only in the TV's browser. Use **New remote code** in the TV's Settings to change the code.
