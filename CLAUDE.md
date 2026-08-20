# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Instructions for Claude Code

After completing any task, feature, or file change in this project, 
automatically update this CLAUDE.md file to reflect what was built. 
Do this without being asked. Never end a session without syncing 
this file to the current state of the project.

## What this is

A **WordPress theme** (`roova/`) that turns WooCommerce into a multi-property hotel booking site.
Shipped as an uploadable `.zip`. There is no build toolchain, no npm, no Composer — plain PHP, CSS and
vanilla JS that WordPress loads directly.

The booking engine lives **inside the theme** rather than in a companion plugin. That was an explicit
product decision (one upload for the client), not an oversight — don't "fix" it by extracting a plugin
without asking.

## Commands

macOS has no system PHP; this repo assumes Homebrew PHP at `/opt/homebrew/bin/php`.

```bash
bin/build.sh                              # lint, then package dist/roova.zip
bin/lint.sh                               # php -l over every theme file
php tests/test-availability.php           # booking-logic checks (30 assertions, no WordPress)
php tests/test-load.php [admin|no-wc]     # loads every file against WP stubs; catches include-time fatals
python3 bin/makepot.py roova roova/languages/roova.pot   # regenerate translations after string changes
bin/testenv.sh                            # build a throwaway WP+WooCommerce site with the theme installed
```

`tests/test-availability.php` is a single flat script with a `check( $label, $actual, $expected )`
helper — there is no test runner and no per-test filtering. Add assertions by calling `check()`; it
exits non-zero on any failure. It stubs the handful of WordPress functions the pure logic touches, so
it runs anywhere PHP does.

`bin/build.sh` refuses to package if the lint fails, if `style.css` has lost its theme header, or if
`screenshot.png` is missing.

## Verifying real behaviour

Lint and the two test scripts cover syntax, include-time fatals and the pure booking maths. Anything
touching hooks, the database or templates needs a real site: run `bin/testenv.sh`, serve it with
`php -S 127.0.0.1:8099 -t .testenv/site`, and drive it with curl (a cookie jar per "guest" is enough
to simulate several visitors competing for the same room).

Environment gotchas that cost real time once:

- **WooCommerce "coming soon" mode** is on by default for a fresh install and replaces every store
  page with a placeholder, which looks exactly like a broken theme. `bin/testenv.sh` disables it.
- The **PHP built-in server runs sandboxed** here: it cannot write `debug.log` or any file outside the
  site. To debug a live request, echo diagnostics into the page (e.g. an HTML comment on `wp_footer`)
  rather than logging them.
- The cart and checkout are the **Blocks** versions, rendered client-side. Server HTML will not show
  stay details; read `?rest_route=/wc/store/v1/cart` instead.

## Architecture

### Loading

`roova/functions.php` is the only entry point. It defines `ROOVA_VERSION` / `ROOVA_DIR` / `ROOVA_URI`,
loads WooCommerce-independent files unconditionally, and loads everything else **only when WooCommerce
is active** — then explicitly calls `Roova_Schema::init()`, `Roova_Holds::init()`, `Roova_Cart::init()`,
`Roova_Orders::init()`, `Roova_Blocks::init()`. Admin-only files load behind `is_admin()`. A new class
file needs both a `roova_require()` line and its `::init()` call.

### Products

Hotels and rooms are WooCommerce product types, not custom post types:

- `hotel` (`WC_Product_Hotel`) — catalogue-only container, `is_purchasable()` is false, price shows as
  "From {cheapest room}".
- `room` (`WC_Product_Room`) — purchasable, price field is the **nightly rate**, belongs to one hotel
  via `_roova_hotel_id`.

Class names follow WooCommerce's `WC_Product_{Type}` convention so the data store resolves without
registration. Rooms are hidden from the shop catalogue and site search (`pre_get_posts`) and redirect
to their hotel page, because a room can't be booked without hotel context.

**Rooms deliberately bypass WooCommerce stock.** Inventory is the `_roova_units` field, because Woo
stock decrements permanently on order — wrong for date-based inventory. `managing_stock()` returns
false and the room metabox forces `set_manage_stock( false )`.

### Availability

`{$wpdb->prefix}roova_bookings` is the single source of truth. `Roova_Availability` is the only class
that should decide whether something is bookable.

The core rule: for a requested stay, sum the units taken on **each individual night** across every
overlapping booking, and take the busiest night (`peak_units()`). Overlap is `check_in < :out AND
check_out > :in`, so a same-day turnaround is not a conflict. That maths is the part `tests/` covers —
change it and update the tests.

Statuses `hold | pending | confirmed | cancelled`; the first three occupy inventory. Availability
queries exclude anything past `expires_at` **in SQL**, so correctness never depends on the cleanup cron
firing on time — the cron is tidy-up only.

### Race protection

Commits at checkout run inside a **MySQL named lock** (`Roova_Holds::lock()` / `GET_LOCK`), not a
transaction. This is load-bearing: WooCommerce runs its own transaction during checkout, and an inner
`COMMIT` would end theirs early. Don't reintroduce `START TRANSACTION` / `FOR UPDATE` here.

Four layers guard against double-booking, in order: hold at add-to-cart → re-validation on every
cart/checkout view → locked commit at `woocommerce_checkout_order_processed` (and the Store API
equivalent, which rethrows as `RouteException`) → order status mapping. A conflict at commit fails the
order and throws so checkout errors out. A *paid* order that ends up overbooked is never silently
dropped — it stays booked and `flag_overbooking()` adds a loud order note.

### Two traps this code has already fallen into

**Overriding a WooCommerce product method with the wrong signature is a site-wide fatal.** Check the
parent in `includes/abstracts/abstract-wc-product.php` before overriding — `get_price_html()` takes a
legacy `$deprecated = ''` argument, and dropping it takes the whole site down at theme load.

**A cart item key is not unique to a visitor.** WooCommerce hashes the product plus its cart item
data, so two guests booking the same room for the same dates get the *identical* key. Every lookup or
delete that uses `cart_item_key` must also match `session_id`, or one guest reads, resizes or deletes
another's hold. The commit path avoids the problem entirely by carrying the booking row's own ID
(`hold_id`) from the cart through to `_roova_hold_id` on the order line.

### Hold lifecycle

Holds are keyed by `cart_item_key` + `session_id`. `Roova_Cart::sync_hold()` creates or resizes the
hold after WooCommerce settles the line's quantity (a repeat add merges into an existing line, so the
hold is resized rather than duplicated). Cart removal, restore and empty all have listeners in
`Roova_Holds`. Order status then drives the row: paid → `confirmed` with no expiry; cancelled /
refunded / failed → `cancelled`, freeing the dates; unpaid orders carry an expiry so abandoned
checkouts release their rooms.

### Search criteria

`roova_get_criteria()` in `inc/helpers.php` is the one place dates/guests come from: GET parameters →
WooCommerce session → defaults, then clamped by `roova_normalise_criteria()`. Templates, the search
page, the hotel page and add-to-cart all read it, which is why a stay survives navigation. Don't read
`$_GET['checkin']` directly anywhere else.

### Taxonomies

`pa_destination` and `pa_amenity` are real WooCommerce global attributes, created programmatically by
`roova_ensure_attributes()` so the client can add terms in the UI without code. Term meta carries the
amenity icon (`roova_icon`, or `roova_icon_image` for a custom upload) and the destination tile image /
colour. Icons come from the inline SVG library in `inc/icons.php` — `roova_icon_library()` is
filterable; icons are rendered inline so they inherit `currentColor`.

### Templates

`single-hotel.php` is routed by `template_include` (not a WooCommerce template override).
`template-search.php` is a page template; the "Find a room" page is created on activation and its ID
stored in the `roova_search_page_id` option. `woocommerce.php` wraps cart/checkout/account pages.
Reusable markup lives in `inc/template-tags.php` as `roova_*` functions, not in template partials.

### Front-end JS

One file, `assets/js/theme.js`, vanilla, no jQuery, wired entirely through `data-roova-*` attributes.
Dates are handled as `Y-m-d` strings and only converted to `Date` at local midnight so a stay never
shifts a day across time zones. Server data arrives via the localized `roovaData` object built in
`roova_script_data()`. Admin JS does use jQuery (WordPress admin convention).

## Conventions

- Prefix everything `roova_` / `Roova_`; product meta keys are `_roova_*`; text domain is `roova`.
- Every DB query goes through `$wpdb->prepare()`; every output is escaped; forms and AJAX carry nonces.
- Theme options are Customizer settings read through `roova_option( 'key', $default )`.

## When changing things

- **Strings:** rerun `bin/makepot.py` after adding or editing any translatable string.
- **The bookings table:** bump `Roova_Schema::DB_VERSION`, which triggers `dbDelta` on the next
  `admin_init`. Note `order_item_id` is nullable on purpose — the UNIQUE index has to allow the many
  rows that are still holds and have no order.
- **Releases:** `ROOVA_VERSION` in `functions.php` and `Version:` in `style.css` must match.
- **Documentation:** `roova/README.md` ships to the client (setup, how conflict prevention works);
  the root `README.md` is for developers. Keep client-facing behaviour changes in the theme README.

## Other agent configs

`~/.codex/config.toml` and `~/.gemini/settings.json` exist on this machine. If you want their MCP
servers, commands or instructions available in Claude Code, reply `/import` to see what's importable.
