# norewp: WordPress (de_DE) on noredock

A complete local WordPress in German, run by [noredock](https://github.com/zulfiismailovdemiri/noredock).
There is no Docker and no MySQL, and nothing gets installed on your machine. One command
downloads, verifies and installs everything.

| | |
|---|---|
| WordPress | latest (7.1.5 at the time of writing), language **de_DE** |
| PHP | 8.5 (noredock `php` image, static build) |
| Database | **SQLite** via the official [SQLite Database Integration](https://wordpress.org/plugins/sqlite-database-integration/) plugin |
| E-mail | Mailpit (noredock `mailpit` image) catches every mail |
| Tools | WP-CLI (noredock `wp-cli` image) |

## Start

```sh
cd norewp
cp .env.example .env        # once: set WP_ADMIN_PASSWORD (and title / e-mail if you like)
noredock compose up -d      # first start: ~15 s (downloads WordPress + plugin, installs the site)
noredock compose ps
```

| what | where |
|---|---|
| Website | http://localhost:8200 (or the port shown by `compose ps`) |
| Admin | http://localhost:8200/wp-admin: user and password from `.env` |
| Mailpit inbox | the `norewp-mail` port in `compose ps` (8025, or 8026 if something else uses 8025) |
| Names instead of ports | `noredock proxy up`, then http://wp.norewp.localhost:7080 |

Stop with `noredock compose down`. `noredock compose down -v` also deletes the database
(a fresh install on the next start, after `rm -rf wordpress`).

## How it works

```
noredock compose up
 ├─ mail  (mailpit image)       SMTP + web inbox, sandboxed, localhost only
 └─ wp    (php + wp-cli images)
     ├─ prepare: bin/setup.sh   download → verify → configure → install (skips what's done)
     └─ php -S … config/router.php   PHP's built-in web server serves wordpress/
```

**`bin/setup.sh`** runs before every start of the `wp` service. Each step is skipped when it's already done:

1. **Download WordPress** for `WP_LOCALE` (`bin/fetch-core.php`, the official zip from
   wordpress.org), then **check every file** with `wp core verify-checksums`. If any file
   doesn't match, the files are removed again and the start stops.
2. **SQLite plugin**: `bin/fetch-plugin.php` fetches it from wordpress.org, then the plugin's
   `db.php` drop-in is installed into `wp-content/`. From then on WordPress talks to SQLite instead of MySQL.
3. **`wp-config.php`** via WP-CLI plus `config/wp-config-noredock.php`:
   - the database file lives in the noredock volume (`~/.noredock/volumes/norewp-wp/database/wordpress.sqlite`)
   - the site URL follows the request (any port, proxy names)
   - debug log goes to the volume
4. **Mail plugin**: `config/noredock-mail.php` is copied to `wp-content/mu-plugins/`. It points WordPress'
   mailer at Mailpit (`SMTP_HOST` / `SMTP_PORT` come from the compose file).
5. **Install the site** once with the data from `.env`:
   - German language and Berlin time zone
   - pretty permalinks (`/%postname%/`)
   - plugin checksum check

**`config/router.php`**: PHP's built-in server has no rewrite rules. The router serves existing
files directly and sends everything else (e.g. `/hallo-welt/`) to WordPress.

**Isolation:**
- `wp` runs sandboxed (`isolation = "sandbox"`): it can't read the rest of your home folder and
  only writes to the project and its volume. It keeps `network = "all"` so the admin can load
  plugins, themes and translations.
- `mail` is limited to localhost.

## Everyday commands

```sh
noredock compose logs -f wp                        # web server log
noredock compose exec wp bin/wp plugin list        # WP-CLI (bin/wp: path + memory preset)
noredock compose exec wp bin/wp user list
noredock compose exec wp bin/wp plugin install contact-form-7 --activate
cp ~/.noredock/volumes/norewp-wp/database/wordpress.sqlite backup.sqlite   # backup: it's one file
tail -f ~/.noredock/volumes/norewp-wp/debug.log   # PHP notices / WP_DEBUG_LOG
```

Tip: `alias wpcli='noredock compose exec wp bin/wp'`

## Files

```
noredock.compose.toml        services: mail + wp
.env.example / .env          admin login, title, language (.env is git-ignored)
bin/setup.sh                 install steps (prepare)
bin/wp                       WP-CLI wrapper (memory, path)
bin/fetch-core.php           WordPress download (zip; see the note inside)
bin/fetch-plugin.php         plugin download from wordpress.org
config/router.php            router for PHP's built-in server
config/noredock-mail.php     mu-plugin: mail → Mailpit
config/wp-config-noredock.php  extra wp-config settings
wordpress/                   WordPress itself (generated, git-ignored)
```

Put your own themes and plugins into `wordpress/wp-content/` (and track them in git as you need).

## Notes and limits

- **Why SQLite, not MySQL?** noredock has no MySQL image, and the PHP image has no `mysqli`
  extension. The SQLite integration is an official WordPress project and works for development,
  but a few plugins with hand-written MySQL queries may not. If you need MySQL, use Docker for that site.
- **Why not `wp core download`?** WP-CLI unpacks the `.tar.gz` with PHP's PharData, which cuts
  file names longer than 100 characters. WordPress 7.1 has such files, so they ended up broken.
  `bin/fetch-core.php` uses the zip instead, and every file is checked afterwards.
- `bin/wp` runs WP-CLI with `memory_limit=512M` (PHP's default 128M is too little for some tasks)
  and hides PHP 8.5 deprecation notices from WP-CLI's own code.
- `wp db export/import` need the MySQL tools and don't work with SQLite. Copy the `.sqlite` file instead.
- PHP's built-in server handles one request at a time. That's fine for local development, not for production.
