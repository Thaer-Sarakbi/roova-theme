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
php bin/makepot.php roova roova/languages/roova.pot      # identical output, for machines without Python
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

On Windows there is usually no PHP or MariaDB at all: `bin/testenv-win.sh` downloads portable copies
into `.testenv/`, installs the site, starts both services and seeds demo content
(`bin/testenv-seed.php`). It also serves the site itself — a plain `php -S … site/index.php` routes
every asset through WordPress and the page arrives unstyled, so the script generates a router that
returns real files first. The theme is junctioned rather than copied, so edits are live.

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

`pa_destination`, `pa_amenity` and `pa_facilities` are real WooCommerce global attributes, created
programmatically by `roova_ensure_attributes()` so the client can add terms in the UI without code.
Term meta carries the amenity icon (`roova_icon`, or `roova_icon_image` for a custom upload) and the
destination tile image / colour plus its map coordinates (`roova_lat` / `roova_lng`). Icons come from
the inline SVG library in `inc/icons.php` —
`roova_icon_library()` is filterable; icons are rendered inline so they inherit `currentColor`.

`roova_amenity_icon()` resolves in three steps: the icon chosen on the term (`roova_icon_image`, then
`roova_icon`), then an **exact** case-insensitive match of the term name against an icon slug or label
(`roova_icon_slug_for_name()`), then a neutral tick. The name match is exact on purpose — a fuzzy one
would put confident, wrong pictures next to amenities.

Facilities are deliberately icon-less: they are the flat "what this hotel has" checklist rendered with
a tick above "Select your room", so a new term needs no admin work beyond adding it.

Amenities and facilities are also editable from the Hotel Details tab (`roova_attribute_picker()`), as
type-to-search multi-selects handed to WooCommerce's own select2 via the `wc-enhanced-select` class —
which is why `roova-admin-product` depends on that handle. Their selection must be written back
through `roova_set_product_attribute_terms()`, which puts them
on the **product's attribute list** — a plain `wp_set_object_terms()` is undone seconds later, because
WooCommerce's data store deletes the term relationships of any attribute taxonomy the product object
does not carry when it saves. Adding an
attribute to `roova_required_attributes()` only creates it on sites whose stored
`roova_attributes_created` differs from `ROOVA_VERSION` — bump the version or the new attribute never
appears on an existing install.

### Templates

`single-hotel.php` is routed by `template_include` (not a WooCommerce template override).
`template-search.php` is a page template; the "Find a room" page is created on activation and its ID
stored in the `roova_search_page_id` option. `woocommerce.php` wraps cart/checkout/account pages —
its 1180px column is the `.roova-woocommerce` rule, because WooCommerce's own wrapper hooks (and so
`.roova-wc-page`) do not fire on every one of those pages.
Reusable markup lives in `inc/template-tags.php` as `roova_*` functions, not in template partials.

`header.php` and `footer.php` are shared by every page: one header and a cream footer of three menu
columns — `footer`, `footer-2`, `footer-3` — whose headings are Customizer settings. Both print the
site name through `roova_wordmark()`, which picks out a domain suffix in gold ("roova**.my**").

The header has two states, decided by `$roova_over_hero` (front page, not paged):

- **Over the hero** (`.roova-nav--over`) — lifted out of the flow with `position: absolute` and laid
  on the photograph, so the hero runs to the very top of the window. Everything in it turns cream.
- **Everywhere else** — in flow, transparent over the cream page, with a hairline underneath.

It is **never sticky and never has its own background**, and the hero is **full bleed with square
corners**. That is a deliberate departure from the handoff (which draws a sticky translucent cream bar
above a 28px-rounded hero panel inset by 20px) — asked for directly, so don't "restore" it. The panel
carries the header's height in its `min-height` (700px, against the spec's 620px) so the composition
keeps its proportions.

### The homepage

`front-page.php` is a composition of template tags, in the order the design handoff fixes:
`roova_hero()` → `roova_guarantees_row()` → `roova_image_band()` → hotels grid → destinations mosaic →
`roova_image_band()` again → `roova_coverage_map()`. Every section's copy is a Customizer setting.

Two things bite here:

- **`get_theme_mod()` ignores the Customizer's registered default** — it falls back to whatever the
  caller passes. So a default is written twice: once in `inc/customizer.php`, once at the call site.
  They have to match, or the live site shows nothing where the Customizer shows text.
- The mosaic's spans come from `roova_destination_span_class()`, a 7-tile repeating rhythm
  (2×2 anchor, four 1×1, two 2×1) so any number of destinations tiles cleanly.

The hero and both bands ship with stand-in photography in `assets/images/` (`hero.jpg`, `band-1.jpg`,
`band-2.jpg` — 2400px JPEGs, ~1.1MB the three of them). `roova_background_image()` prints the
Customizer attachment when there is one and the bundled file otherwise, so an install looks like the
design before anyone touches the media library. Behind both is a navy gradient, for the case where a
caller passes no fallback; a band with no image, no fallback and no statement renders nothing at all
rather than a bare stripe.

### Coverage map

`roova_coverage_map()` prints the town list server-side and leaves an empty canvas; `theme.js` draws
real Natural Earth geometry into it with **d3-geo + topojson** (pinned URLs with SRI hashes in
`roova_map_libraries()`, enqueued only when `roova_show_coverage_map()` is true, and taken as
dependencies of `roova-theme` so `d3` exists before the script runs). If the CDN fails the list is
still there and still links — the drawing is the enhancement, not the content.

Pin coordinates come from destination term meta (`roova_lat` / `roova_lng`) and fall back to
`roova_destination_gazetteer()`. A destination with neither is left off the map rather than guessed —
same principle as the amenity icons. Unlike the prototype, clicking a pin or a row navigates to that
destination's hotels; the two pills switch the framing.

### Front-end JS

One file, `assets/js/theme.js`, vanilla, no jQuery, wired entirely through `data-roova-*` attributes.
Dates are handled as `Y-m-d` strings and only converted to `Date` at local midnight so a stay never
shifts a day across time zones. Server data arrives via the localized `roovaData` object built in
`roova_script_data()`. Admin JS does use jQuery (WordPress admin convention).

Scroll reveal (`[data-roova-reveal]`, `[data-roova-stagger]`) is deliberately paranoid: an
IntersectionObserver plus a scroll/resize sweep plus a 3s timeout that reveals everything. Anchor
jumps and scroll restoration can otherwise leave a section stuck at `opacity: 0`, which is far worse
than a section that never animated.

## Design system

The look comes from the handoff in `design_handoff_roova_home/` (spec plus HTML prototypes — reference
only, never shipped). Its tokens are the `:root` block at the top of `assets/css/theme.css`: cream
`#fbf8f3`, navy `#0d3a52`, gold `#b4823c`, sand `#f6f1e8`, ink `#16302f`, **Newsreader** for display
and **DM Sans** for UI, `cubic-bezier(.22,.8,.28,1)` as the standard easing. Six of those colours are
Customizer settings re-emitted as custom properties by `roova_inline_brand_css()`, so change the
default in *both* places or an existing site keeps the old palette.

`--roova-page` is what the page is actually painted with, and it is deliberately **not**
`--roova-cream`: cream stays warm because it is also the text colour on every dark panel. It defaults
to cream, and `roova_body_classes()` adds `.roova-page-white` on the homepage and hotel pages, which
flips it to white on `<body>`. Anything that has to disappear into the page — the map's country
strokes, the pin haloes — reads `--roova-page`, never `--roova-cream`.

Prefer a token over a literal; the handful of literals left are the multi-stop scrims and the
`rgba(13,58,82,…)` hairlines.

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
