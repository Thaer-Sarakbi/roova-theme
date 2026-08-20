# Roova — project repository

Source for the **Roova** WordPress theme: a hotel-group booking site built on WooCommerce.

```
roova/                 the theme itself (this is what gets zipped)
bin/build.sh           lints, then builds dist/roova.zip
bin/lint.sh            php -l over every theme file
bin/makepot.py         regenerates roova/languages/roova.pot from the source
bin/testenv.sh         throwaway WP + WooCommerce site (macOS, Homebrew php + mariadb)
bin/testenv-win.sh     the same for Windows, using a portable PHP and MariaDB
bin/testenv-seed.php   demo hotels, rooms, destinations, amenities and facilities
tests/                 standalone checks for the booking maths (no WordPress needed)
dist/roova.zip         the uploadable theme
CLAUDE.md              architecture notes and conventions for future work
```

## Build

```bash
bin/build.sh          # → dist/roova.zip
```

Upload `dist/roova.zip` under **Appearance → Themes → Add New → Upload Theme**.

## Test

```bash
php tests/test-availability.php    # date maths, per-night occupancy, landmark parsing
bin/lint.sh                        # syntax check every PHP file
```

`tests/test-availability.php` stubs the handful of WordPress functions the pure logic touches, so it
runs anywhere PHP does. It covers the rules that keep bookings honest — most importantly that a stay
costs inventory on its busiest night, and that a same-day turnaround (one guest out, one guest in) is
not a conflict.

## Run it on a real site

Anything touching hooks, the database or templates needs WordPress. Both scripts below build a
throwaway site in `.testenv/` (gitignored, disposable) with WooCommerce installed, the theme
activated and demo hotels seeded.

```bash
bin/testenv.sh                  # macOS — needs `brew install php mariadb`
bin/testenv-win.sh              # Windows, from Git Bash — downloads its own PHP + MariaDB
```

On Windows the first run downloads about 170 MB and takes a few minutes; every run after that starts
in under ten seconds. Nothing is installed system-wide and nothing touches PATH.

```bash
bin/testenv-win.sh              # start (default) → http://127.0.0.1:8099, admin / admin
bin/testenv-win.sh stop         # stop the web server and the database
bin/testenv-win.sh status       # what is running, and where
bin/testenv-win.sh restart
bin/testenv-win.sh fresh        # wipe the site + database and reinstall from scratch
bin/testenv-win.sh seed         # re-create the demo hotels, rooms and terms
bin/testenv-win.sh wp plugin list    # run any wp-cli command against the site
```

The theme is linked into the site with a directory junction, so an edit in `roova/` is live on the
next page load — no build, copy or reinstall step. Ports can be overridden with `ROOVA_PORT` and
`ROOVA_DB_PORT`. The database keeps its data between runs; use `fresh` when you want a clean install.

Theme documentation — setting up hotels, rooms, destinations, amenities and how the conflict
prevention works — is in [roova/README.md](roova/README.md).
