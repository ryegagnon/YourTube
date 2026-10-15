# Web hosting

Everything in `web/` is plain files. Upload the contents of that folder (not the folder itself) to the root of your site, for example `htdocs` or `public_html`.

## What each part needs

| Feature | Needs |
| --- | --- |
| TV screen, guide, playback, saved settings | Static hosting only |
| Adding channels by `@handle` or `/c/` link | PHP (`resolve.php`) that can make outbound requests to youtube.com |
| Video lengths and logos for the guide | PHP (`schedule.php`) with outbound requests, or a YouTube API key |
| Phone remote | PHP (`remote.php`) and a writable folder |
| Exit button | Raspberry Pi only (`exit.php` does nothing on a host) |

Settings are stored in each visitor's browser (localStorage), so they are per device and per address. Always use the same address, including `https` and `www`.

## Uploading to a shared host

1. Sign up with a PHP-capable host and pick a domain or subdomain.
2. In the host's file manager or over FTP, open the web root (`htdocs` or `public_html`), delete any default placeholder page, and upload the files from `web/`.
3. Turn on the host's SSL certificate and use `https://`.
4. Open the site. You should see the Turn on screen.

Notes:

- Some hosts, especially free ones, may block outbound requests from PHP. If so, `@handle` links and the guide's lengths and logos will fail. Use `/channel/UC...` links, or add a YouTube API key in Settings, which is called from the browser instead.
- The phone remote stores a small file per pairing code in a `rooms/` folder that the PHP creates. If it cannot, create an empty `rooms` folder yourself. The folder is protected by an `.htaccess` file the script writes.
- The TV page polls the relay every few seconds while a phone is connected, which can count toward request limits on hosts that have them.

## Running it on your own computer

```bash
php -S 0.0.0.0:8080 -t web
```

Open `http://localhost:8080`. `localhost` counts as a secure context, so full screen and saved settings work without https.

## Security notes

- The pairing code is the only protection for the phone remote. Anyone who has it can control the TV page. Use **New remote code** in Settings to change it.
- `exit.php` only accepts POST requests from private network addresses.
- Do not expose a Raspberry Pi install to the internet.
