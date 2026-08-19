# Roova — project repository

Source for the **Roova** WordPress theme: a hotel-group booking site built on WooCommerce.

```
roova/                 the theme itself (this is what gets zipped)
bin/build.sh           lints, then builds dist/roova.zip
bin/lint.sh            php -l over every theme file
bin/makepot.py         regenerates roova/languages/roova.pot from the source
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

Theme documentation — setting up hotels, rooms, destinations, amenities and how the conflict
prevention works — is in [roova/README.md](roova/README.md).
