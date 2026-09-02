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
php tests/test-cashback.php               # cashback rules and balances (42 assertions, no WordPress)
php tests/test-load.php [admin|no-wc]     # loads every file against WP stubs; catches include-time fatals
python3 bin/makepot.py roova roova/languages/roova.pot   # regenerate translations after string changes
php bin/makepot.php roova roova/languages/roova.pot      # identical output, for machines without Python
bin/testenv.sh                            # build a throwaway WP+WooCommerce site with the theme installed
```

`tests/test-availability.php` and `tests/test-cashback.php` are single flat scripts with a
`check( $label, $actual, $expected )` helper — there is no test runner and no per-test filtering. Add
assertions by calling `check()`; they exit non-zero on any failure. Each stubs the handful of
WordPress functions the pure logic touches, so they run anywhere PHP does. `test-cashback.php` stubs
the ledger's storage and `roova_account_stays()` as well, and gives each case its own user id —
`roova_cashback_sync()` guards itself with a static, so a reused id would silently skip the sync.

`bin/build.sh` refuses to package if the lint fails, if `style.css` has lost its theme header, or if
`screenshot.png` is missing.

## Verifying real behaviour

Lint and the three test scripts cover syntax, include-time fatals, the pure booking maths and the
cashback rules. Anything
touching hooks, the database or templates needs a real site: run `bin/testenv.sh`, serve it with
`php -S 127.0.0.1:8099 -t .testenv/site`, and drive it with curl (a cookie jar per "guest" is enough
to simulate several visitors competing for the same room).

On Windows there is usually no PHP or MariaDB at all: `bin/testenv-win.sh` downloads portable copies
into `.testenv/`, installs the site, starts both services and seeds demo content
(`bin/testenv-seed.php`). It also serves the site itself — a plain `php -S … site/index.php` routes
every asset through WordPress and the page arrives unstyled, so the script generates a router that
returns real files first. It junctions the theme rather than copying it, so edits are live — but
**check before trusting that**: the junction does not survive every way the site gets rebuilt, and a
`.testenv/site/wp-content/themes/roova` that is a real directory silently serves a stale copy. If a
change seems to have no effect, `cp -r roova/. .testenv/site/wp-content/themes/roova/` first.

Environment gotchas that cost real time once:

- **WooCommerce "coming soon" mode** is on by default for a fresh install and replaces every store
  page with a placeholder, which looks exactly like a broken theme. `bin/testenv.sh` disables it.
- The **PHP built-in server runs sandboxed** here: it cannot write `debug.log` or any file outside the
  site. To debug a live request, echo diagnostics into the page (e.g. an HTML comment on `wp_footer`)
  rather than logging them.
- The **cart** is the Blocks version, rendered client-side. Server HTML will not show stay details;
  read `?rest_route=/wc/store/v1/cart` instead. Checkout is the theme's own classic template (see
  *Checkout* below) and can be read straight out of the HTML.
- **Nested `<form>` elements are dropped by the browser**, not by the parser you are grepping. Markup
  that looks right in `curl` output can be absent from the DOM. Anything layout- or behaviour-related
  has to be checked with `node .testenv/drive.js <url> <script.js> [out.png]`, which evaluates a
  script in headless Edge and prints what it returns.

## Architecture

### Loading

`roova/functions.php` is the only entry point. It defines `ROOVA_VERSION` / `ROOVA_DIR` / `ROOVA_URI`,
loads WooCommerce-independent files unconditionally, and loads everything else **only when WooCommerce
is active** — then explicitly calls `Roova_Schema::init()`, `Roova_Holds::init()`, `Roova_Cart::init()`,
`Roova_Orders::init()`, `Roova_Blocks::init()`. Admin-only files load behind `is_admin()`. A new class
file needs both a `roova_require()` line and its `::init()` call. The account feature files —
`inc/likes.php`, `inc/vip.php`, `inc/account.php`, `inc/reviews.php`, `inc/cashback.php`, `inc/account-tabs.php` — are
plain function files, so they need only the `roova_require()` line, but their order matters:
`inc/reviews.php` reads `roova_account_stays()`, `inc/vip.php` reads
`roova_account_completed_count()`, and `inc/cashback.php` reads `roova_account_stays()` too. Each of
those reaches into `inc/account.php` only through a function call, never at include time, which is
what lets them load before it.

`inc/auth.php` is in the unconditional group on purpose: `header.php` calls `roova_account_control()`
on every page, and signing in has to keep working on a site whose WooCommerce is switched off. Its
WooCommerce calls are each guarded by `function_exists()`.

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

### Three traps this code has already fallen into

**A CSS rule matching a descendant, not a child, will find WooCommerce's price markup.** A formatted
price is a nest of `<span>`s; `.some-list span { display: block }` puts the currency symbol on a line
of its own. Scope label rules with `>`.

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
hold after WooCommerce settles the line's quantity (the resize path is what a repeat add used to take,
and still does with `roova_single_item_cart` filtered off — see *One booking per cart*). Cart removal,
restore and empty all have listeners in
`Roova_Holds`. Order status then drives the row: paid → `confirmed` with no expiry; cancelled /
refunded / failed → `cancelled`, freeing the dates; unpaid orders carry an expiry so abandoned
checkouts release their rooms.

### One booking per cart

The cart carries a single line. `Roova_Cart::add_cart_item_data()` calls `clear_for_new_booking()`
before it attaches the stay, so every add empties the cart first — whatever is being added, room or
not. Quantity is untouched: several units of *one* room type is a normal stay (the "Rooms" number in
the search bar), several room types is not.

Three things make that work:

- **The clearing hangs off `woocommerce_add_cart_item_data`, not the validation filter.**
  `woocommerce_add_to_cart_validation` also fires for "order again" and for the Store API's own
  pre-flight, neither of which is an item going into the cart; `add_cart_item_data` fires only on a
  real add, on both the classic and Store API paths.
- **Lines are removed one at a time rather than through `empty_cart()`.** Only
  `woocommerce_cart_item_removed` releases the hold behind a line, and `empty_cart()` destroys the
  cart session that the incoming line is about to be written into. The undo store is dropped with
  them, so a replaced booking cannot be restored alongside the new one.
- **Availability on the way in must ignore the visitor's own holds**, because all of them are seconds
  from release — that is `exclude_session_holds` on `Roova_Availability::get_overlapping_rows()`.
  Without it, re-booking the last unit of a room you are already holding fails as "fully booked".

`enforce_single_item()` on `woocommerce_cart_loaded_from_session` (priority 10, before WooCommerce
totals at 20) is the backstop for carts that never went through that path — a session saved before the
rule existed, an "order again", a programmatic add — and keeps the newest line. `roova_single_item_cart`
turns the whole thing off.

**"Book now" then goes straight to checkout**, through `redirect_after_add()` on
`woocommerce_add_to_cart_redirect` — with one booking in the cart and nothing to add to it, the cart
page has nothing left to decide. WooCommerce applies that filter only when the line really went in and
`wc_notice_count( 'error' )` is zero, so a stay refused at validation or a hold that failed at the last
moment leaves the guest on the hotel page reading why, with their previous booking still in the cart.
`roova_checkout_after_add` turns the jump off. This is why the add-to-cart notice suppression on
checkout matters — it now fires on essentially every booking.

**The header has no cart link at all**, and `.roova-nav__cart` is gone from `theme.css` with it. There
is nothing to come back to the cart page for: the cart holds one booking and booking it lands the guest
on checkout. The one count that still means something — how many rooms are held — is the checkout
banner's sub-line, from `roova_checkout_banner_sub()`.

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

`checkout.php` is the one page that is not: it prints its own `<html>` document, because the design
strips the header down to a wordmark and a padlock. See *Checkout*.

`template-signin.php` and `template-signup.php` are page templates that print their own documents too,
for the same reason. See *Accounts*.

`account.php` is the fourth: not a page template but a file `template_include` routes the My account
*dashboard* to, printing its own document as well. Every WooCommerce endpoint underneath My account
still goes through `woocommerce.php`. See *My account*.

`header.php` and `footer.php` are shared by every other page: one header and a cream footer of three
menu columns — `footer`, `footer-2`, `footer-3` — whose headings are Customizer settings. Both print the
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

The right of the header is `roova_account_control()`: one `.roova-btn--nav`, reading **"Sign in"** for a
visitor and **"Manage account"** for a member, so the corner keeps its shape when someone signs in. It
replaced a "Manage booking" button that sent both to the same place — which for a signed-out guest was
a login form with no context. There is still no cart link; see *One booking per cart*.

### Accounts

`inc/auth.php` owns the two pages, `template-signin.php` / `template-signup.php` render them, and
`assets/css/auth.css` + `assets/js/auth.js` load only there. WordPress and WooCommerce both ship login
forms and neither can be laid out to the handoff without fighting its markup, so the theme owns the
pages — but **not the authentication**: that is still `wp_signon()` and `wc_create_new_customer()`.

- **Both pages print their own document**, like `checkout.php`: the design's whole header is the
  wordmark, and nothing on the page should lead anywhere but into the account or the other form.
- **The pages are created on activation** (`roova_create_auth_pages()`, options `roova_signin_page_id` /
  `roova_signup_page_id`), and re-checked on `admin_init` once per release behind `roova_auth_version` —
  the same shape as the checkout page's setup, and for the same reason: `after_switch_theme` never
  fires for a theme updated in place. A page that already exists is adopted, never rewritten.
- **`roova_is_auth_page()` matches the template, not the option**, so a page the client rebuilt by
  hand and assigned the template still counts.
- **Errors and typed values live in `roova_auth_state()`**, a static filled by the handler on
  `template_redirect` (priority 5) and read by the template moments later in the same request. Nothing
  survives a redirect, and nothing should — passwords are never carried back into the form.
- **The nonces on these forms pin the logged-out user to 0**, through `roova_auth_nonce_field()` and
  `roova_auth_verify_nonce()`, and every auth form uses them instead of `wp_nonce_field()` /
  `wp_verify_nonce()` directly. A logged-out nonce is built against "user 0" — but WooCommerce
  swaps that for its **session customer id** (`nonce_user_logged_out`), so a cart session starting
  or ending between a form being drawn and posted changes who the nonce belongs to and the form
  comes back as *"That form had expired."* Adding a room in another tab is enough; on WooCommerce
  before 5.3-ish, which applied the swap to every action rather than its own, simply touching the
  cart was. Pinning at both ends makes the nonce depend on the action and the clock alone, which is
  what WordPress does for anonymous forms anyway. It is not a weakening: a forged or missing nonce
  is still refused.
- **Every rule is checked twice**, in `inc/auth.php` and again in `auth.js`, deliberately word for
  word: a guest must never read one message in the browser and a different one after the round trip.
  The forms work with the script blocked. `novalidate` is on both, so the browser's own bubbles never
  compete with the theme's messages.
- **A signed-out visitor at My account is redirected to sign in** and back afterwards, so the site only
  ever shows one login form. WooCommerce *endpoints* are exempt — lost-password and reset-password have
  to work while signed out. `roova_redirect_account_to_signin` turns it off.
- **`redirect_to` goes through `wp_validate_redirect()`**, or the sign-in page is an open redirect.
- **`roova_registration_open()` reads the theme's own Customizer switch and nothing else**, default on.
  It used to read WooCommerce's "Allow customers to create an account" — **the wrong gate**: that option
  decides whether WooCommerce prints a registration form on *its* account page, and
  `wc_create_new_customer()`, which is what this page calls, never consults it (check
  `wc-user-functions.php` — it validates email, username and password, nothing else). Borrowing it shipped
  the sign-up page closed on every store still at WooCommerce's default, for a reason that had nothing to
  do with the page, and led to a worse second mistake: writing to that store setting on activation to work
  around it. Both are gone — the theme does not touch anyone else's settings, and the page is open unless
  this site says otherwise. When it *is* closed, `roova_registration_closed_reason()` tells an admin which
  of the two things did it, the setting or the `roova_registration_open` filter.
- A field with anything beside its value — the eye toggle, the confirm tick — has to be laid out as a
  flex row (`.roova-field--password`, `.roova-field--match`), or that element wraps under the input and
  the box grows a line taller than every other field on the page.
- **The logo is `auth_logo`, deliberately not `the_custom_logo()`.** The header's logo is chosen to sit
  on the hero photograph, so it is usually the reversed-out light version of a mark — invisible against
  the white form column here. `roova_auth_wordmark()` reads its own setting, falls back to the bundled
  full-colour `assets/images/logo.png`, and only then to the site name as text. Sized by height (72px,
  56px on mobile) because the lockup's own aspect decides how wide it lands.
- **A photo per page**, `auth_signin_image` / `auth_signup_image`, falling back to the bundled
  `auth-signin.jpg` (Petronas Towers) and `auth-signup.jpg` (Batu Caves). Two settings, not one: the
  pages are seen one after the other, and the same picture twice reads as a page that failed to change.
  Both bundled files are **smaller than the panel** (452×678 and 510×601 against the handoff's
  ~700×900), so they scale up and soften — the client's own photography is the fix, not upscaling.
- **Both scrims are measured, not styled by eye.** The handoff's wash (`.86 → .34 → .12`) assumes a
  photograph already dark where the words sit; the towers put lit city blocks straight behind the
  figures and the 12px stat labels measured 4.29:1 — under AA. The panel wash is deeper here
  (`.93 → .46 → .14`), and the ≤1024px band, which squeezes the whole gradient into 180px, gets a
  flatter one again (`.92 → .8 → .62`). Worst-pixel readings with the bundled photos: sign in
  5.98 / 11.68 / 5.14, sign up 7.94 / 11.00 / 5.46, bands 9.65 and 10.64. Same rule as the checkout
  banner — **change either photo and re-check**: hide `.roova-auth__panel-inner`, screenshot, and sample
  the lightest pixel under each text box (`.testenv/ratio.js` does the arithmetic).

One copy departure from the handoff, a Customizer default: sign-up's panel line drops "get a discount
when you register", a claim only the client can make good on. The handoff's "we've sent a
verification link" screen used to be the other one — it was cut for being a banner about something
that had not happened. It is now the truth; see *Email confirmation*.

### Email confirmation

`inc/verification.php`. Signing up creates the account and **does not sign anyone in**: the address
gets a link, and opening it is what proves the inbox is theirs. Until then
`roova_block_unverified_login()` refuses the sign-in outright — on `wp_authenticate_user`, so the
password is still checked but no cookie is ever set, and wp-login.php is covered as well as the
theme's own form. Opening the link marks the address confirmed **and signs them in**, which is the
whole point of confirming it.

The sign-up form's "Confirm email address" field is gone with it: re-typing an address catches a
slip of the fingers, and a link that only arrives at the real inbox catches everything.

- **Only accounts made through this flow are ever held back.** `roova_user_is_verified()` treats an
  account with no pending flag as confirmed, so switching this on cannot lock out a site's existing
  members — they have already proved the address by using it. Turning the feature on for older
  accounts is a filter (`roova_account_email_verified`), never a default.
- **Only the token's hash is stored**, with an expiry, and it is spent on use — a password reset's
  shape, because here the inbox is the credential. `hash_equals`, not `===`.
- **A spent link opened by a confirmed member reads as success, not an error.** That is someone who
  clicked twice or opened it on a second device, and telling them something failed would be a lie.
- **Resending is a POST, never a link.** A GET that sends email is a thing a prefetch triggers. The
  form is a sibling of the sign-in form, never nested inside it — the trap the checkout summary
  avoids for the same reason.
- **Resending answers identically whether or not there was anything to send**, or the form becomes a
  way to find out which addresses have accounts.
- **The store's own welcome email is held back while confirmation is on.** Two emails a second
  apart, one of them saying an account is ready when it is not, is worse than either; the
  confirmation email is the welcome.
- `roova_require_email_verification` turns the whole thing off, and sign-up goes back to signing the
  new member straight in.

### My account

WooCommerce's My account page keeps the URL, the login gate and the endpoint router. What the theme
replaces is the **dashboard view**: `roova_account_template()` sends it to `account.php`, which prints
its own document, for the reason checkout and the auth pages do — the design's whole header is a
wordmark, the member's tier and a way out.

**The page itself is guaranteed.** `roova_ensure_account_page()` adopts, untrashes or creates the My
account page on `after_switch_theme` and again once per release, exactly as the checkout page's own
check does — and for a stronger reason: this page is the header's "Manage account" button, where
signing in returns to, and where the email confirmation link finally lands. Without it all three
are a 404, because **`wc_get_page_permalink()` builds a URL for a trashed page just as happily as
for a live one** (`?page_id=8`). `roova_account_url()` therefore checks the page is *published*
rather than merely pointed at, and falls back to the home page; `roova_auth_page_id()` does the
same for the two auth pages, so a trashed sign-in page cannot end up baked into a confirmation
email as a dead link.

**Only the dashboard.** `roova_is_account_dashboard()` is false for every WooCommerce *endpoint*, so
view-order, edit-address, payment-methods, lost-password and customer-logout keep WooCommerce's own
screens inside `header.php`/`footer.php`. That is what keeps the parts that have to work while signed
out working, and it is why "View voucher" can simply link at `get_view_order_url()`.
`roova_use_account_template` turns the whole thing off.

The six panels are all rendered server-side and shown one at a time by `assets/js/account.js`. Each
tab is a real link to its own `?tab=` URL as well as a button, so every tab is reachable with the
script blocked, and a save can redirect back to the tab it came from. `inc/account.php` holds the
routing, the data and the form handlers; `inc/account-tabs.php` holds the markup.

**The forms reuse `roova_auth_field()` and the auth state.** The field shell is the same one the
sign-in and sign-up pages are built from, down to the borderless input inside its label, so the
account page pushes its errors and typed values into `roova_auth_state()` rather than inventing a
second store. Success is always a redirect (`roova_account_redirect()`) — a reload must never repost
a password change or a review.

- **Profile** prefills from the user record and writes back to the same keys checkout prefills itself
  from (`billing_first_name`, `billing_last_name`, `billing_phone`) — see *Checkout*. A password
  change calls `wp_set_password()`, **which destroys every session including this one**, so the
  handler signs the member straight back in; without that, saving a password throws them out of the
  page they were standing on.
- **Bookings** are one row per *booking line* (`roova_account_stays()`), read from the line's own
  `_roova_booking` meta rather than the bookings table: the meta is what the order was placed on and
  it outlives a cleaned-up booking row. Sorted by check-in date, newest first. The design draws three
  status chips; there is a fourth, **Payment due**, because an unpaid order is not an upcoming stay
  and telling a guest their room is booked when it is one failed payment from being released is the
  one thing this page must not do.
- **VIP** counts *completed* stays only. The hero's "stays booked" figure is the looser count —
  everything that is neither cancelled nor unpaid.
- **Cashback rewards** is the sixth tab, last in the strip, and the hero grew a second stat beside
  "Stays booked" for its available balance. See *Cashback rewards*.

### Reviews

A review is a **WooCommerce product review** — a comment on the hotel product with a `rating` meta —
and nothing here reimplements one. WooCommerce recounts `_wc_average_rating` on approval, its
moderation settings apply, and the client moderates from the Comments screen. The theme adds the
three sub-scores (`roova_score_cleanliness` / `_location` / `_service` comment meta) and the rule
about who may write: `roova_can_review()` allows a hotel only to a member with a **completed stay
there** and no review of it yet.

- **The rating field is named `rating`, not `roova_rating`.** That is the exact key WooCommerce's own
  `preprocess_comment` check and its rating-meta handler read; renaming it makes WooCommerce refuse
  the comment.
- **`verified` is set by hand**, and truthfully: WooCommerce's own check looks for a purchase of
  *this* product, which never happens — a guest buys a room, and the review is on its hotel.
- **The hotel page's score comes from real reviews as soon as there is one.** `roova_review_box()`
  prefers `roova_hotel_review_summary()` and falls back to the Hotel Details numbers only when the
  count is zero, so the metabox fields are a stand-in rather than a second source of truth.
  `roova_hotel_rating()` (used on the saved-stays cards) halves a fallback score typed on a ten-point
  scale, so the star beside it never promises "★ 8.9".
- An unapproved review is still shown to **its own author**, marked *Waiting to be published* — a
  review that vanished on submit reads as one that failed to save.

### Saved stays

One user meta key (`roova_liked_hotels`) holds the list, newest first, because that is exactly how
the Likes tab reads it back. The heart is `roova_like_button()`: a button for a member, a **link to
the sign-in page** for a visitor, so it works with the script blocked and never silently drops a save.

**The heart is a sibling of the card's media link, never a child of it** — a browser drops
interactive markup nested inside an `<a>` the same way it drops a nested `<form>`, so the element
would be in the HTML and missing from the DOM. It is laid over the corner in CSS instead
(`.roova-hotel-card` therefore carries `position: relative`).

The toggle lives in **theme.js, not account.js**, because the same button is on hotel cards all over
the site; it dispatches a `roova:like` event that the account page listens for to drop a card out of
the grid and update the count pill.

### RoovaVIP

Tiers and their benefits are one option (`roova_vip_tiers`), edited under **WooCommerce → Settings →
RoovaVIP** (`inc/admin/vip-settings.php`) — a plain settings tab rather than a `WC_Settings_Page`
subclass, because the screen is one repeatable list inside another and there is nothing to inherit.
Tiers are **display only**: nothing here changes what a guest is charged.

- **`roova_vip_tiers()` distinguishes "no option" from "an empty array".** The defaults are returned
  only when the option is absent; an admin who deletes every row means it, and gets the VIP tab
  switched off rather than the defaults handed back on the next page load.
- **Only Gold ships with benefits** — the only tier the handoff writes them for. The other four are
  deliberately empty rather than invented, and a tier with no benefits leaves that section off the
  page rather than printing an empty grid.
- **The lowest tier is the floor.** `roova_vip_current_index()` returns 0 rather than -1 when no
  threshold is met, so every member is at least Bronze — a site whose first tier sits above zero
  bookings still has somewhere to put a new member instead of an empty card.
- **An admin can pin a member to a tier** from Users → the profile screen
  (`inc/admin/vip-user-profile.php`), stored in the `roova_vip_tier` user meta. It is the tier's
  **name**, not its index: positions shift the moment a tier is added or deleted, and a pin that
  silently became a different tier is worse than one that stops applying — a name matching no tier
  is ignored and the member goes back to being counted. `roova_vip_index_for_user()` is what every
  caller should read; `roova_vip_current_index()` is only the earned half of it.
- **The pin sets the status, never the progress.** `roova_vip_next_tier()` takes the *index* the
  member actually stands at and counts on from there, and returns null when the remainder would be
  zero or less — a member pinned below what they have earned must not read "0 more bookings to
  reach VIP Silver".
- **The field is gated on `edit_users`, not `edit_user`.** The latter is true for a member editing
  their own profile, which would let a customer promote themselves; the save handler checks the
  same thing again, and its own nonce.
- **New rows are numbered from a counter in `admin-vip.js`, not from the row count**, or deleting the
  middle tier of three would make the next "Add tier" reuse an index still on the page and one row
  would overwrite the other on save.
- **The Save button is enabled by hand** when a row is added or removed: WooCommerce's settings script
  binds its "something changed" handler directly to the inputs present at page load, so a field added
  afterwards never reaches it.

### Cashback rewards

Two halves. The **offers** are one option (`roova_cashback_rewards`), edited under
**WooCommerce → Settings → Cashback rewards** (`inc/admin/cashback-settings.php`) — the same plain
settings tab shape as RoovaVIP's, for the same reason. Each offer is a rule: a hotel (`0` for all),
a minimum number of nights, a flat amount, an expiry date, and days-until-cleared. The **ledger** is
one user meta key (`roova_cashback_ledger`), keyed by stay, and it is the source of truth for every
figure the tab shows. `inc/cashback.php` holds both.

Cashback is **display only** — a number the theme keeps and shows, exactly as VIP shows benefits.
Nothing here touches an order total. `roova_cashback_record()` is the door a site uses to write a
redemption once it has honoured one, and the ledger already understands `redeem` entries so the
handoff's fourth Activity row has a shape without anything in the theme inventing a redemption flow.

- **The ledger exists so that editing an offer cannot rewrite history.** The amount and the clearing
  date are frozen into the entry the moment the stay completes. Halving an offer next week changes
  nothing already earned, and deleting it claws nothing back. That is the whole reason the balances
  are not simply recomputed from the rules on each page load.
- **Nothing needs a cron.** An entry stores the date it clears and `roova_cashback_entry_cleared()`
  reads that off the calendar — the same principle as availability excluding expired holds in SQL,
  so a balance is never wrong because a scheduled task did not fire.
- **Earning is idempotent and lazy.** `roova_cashback_sync()` runs from
  `roova_cashback_ledger()`, walks the member's completed stays and writes only the ones the ledger
  is missing, guarded by a static so it runs once per member per request. It also *removes* an
  earning whose stay stopped being completed — an order refunded after checkout — but leaves alone
  one whose order has been deleted outright, because there is nothing left to judge it by.
- **Every rule carries a `created` date it is given automatically**, and a stay must check out on or
  after it. Without that, adding an offer today would silently pay out for stays that finished last
  year. It is a hidden field on the settings form, posted back alongside the equally hidden `id`, so
  **a save must never re-issue either** — a re-dated offer reaches back over stays it had already
  declined, and a re-issued id breaks the link from a ledger entry to its rule.
- **Nights are a minimum and rewards do not stack.** `roova_cashback_rewards()` comes back sorted
  most valuable first precisely so `roova_cashback_best_reward()` can stop at its first match.
- **There are no default offers**, unlike VIP. The handoff's four name a specific hotel and two of
  them describe rules this model cannot express, so shipping them would invent promises on the
  client's behalf. A fresh install reads three honest zeroes.
- The tab shows **even with no offers configured**, because a member who earned from an offer that
  has since ended still needs somewhere to read their balance.
- The validity line repeats the handoff's own gotcha: the icon is `flex: none` and the date sits in
  its own `<span>`, or the anonymous flex item shrinks and the date breaks mid-phrase.
- `wc_price()` is a nest of spans, so every figure is scoped with `>` (`.amount`) rather than a
  descendant rule — the trap CLAUDE.md already documents.

### Checkout

**Taxes are real WooCommerce tax rates, not theme maths.** `roova_ensure_tax_rates()` seeds Tourism
Tax 5% and SST 10% into the tax table and switches tax calculation on — but *only* on a store whose
tax table is empty, because a store with rates has had someone think about them. After that they are
the client's: WooCommerce → Settings → Tax → Standard rates, and the summary, order, emails and
confirmation all follow. Each rate needs its own **priority** — WooCommerce charges one rate per
priority, so two sharing one means only the first is applied. Rates are seeded with a blank country
and `woocommerce_tax_based_on = base`: a stay is taxed where the hotel is, and this checkout collects
no address to tax against.

The summary renders one row per tax from `WC_Cart::get_tax_totals()`, honouring
`woocommerce_tax_total_display`, and falls back to the handoff's "Taxes & fees — Included" when tax is
off or prices already include it. `roova_checkout_tax_label()` reads the percentage back off the rate
so "SST (10%)" cannot drift from what is actually charged.

`roova_ensure_checkout_page()` makes sure there is a Checkout page under Pages at all: without one
`is_checkout()` is never true, the template never runs, and the cart has nowhere to go. It adopts
whatever is already there — an existing published page keeps its content, so a store on the block
checkout keeps the block — untrashes and republishes a page that was removed, and only creates one
(with the `[woocommerce_checkout]` shortcode) when there is nothing to adopt. It runs on
`after_switch_theme` and again on `admin_init`, gated on `roova_setup_version` matching
`ROOVA_VERSION` (the gate `roova_ensure_tax_rates()` shares), so an update uploaded over a live site
gets the check exactly once per release and never fights an admin who deleted the page on purpose.

The site's checkout page holds the **Cart & Checkout blocks**, which render client-side and cannot be
templated in PHP. Rather than rewrite the client's page content, `roova_checkout_template()` routes
every `is_checkout()` view — checkout, order-pay and order-received alike — to `checkout.php`, which
prints its own document and runs `[woocommerce_checkout]`. That means the classic checkout, and so the
overrides in `roova/woocommerce/checkout/`. `roova_use_checkout_template` turns the whole thing off.

Everything on the page is read from WooCommerce at render time: the summary from the cart, the payment
cards from `$available_gateways`, the totals from the cart's own totals. Nothing is transcribed from
the handoff.

Two hooks are moved in `inc/checkout.php`, at include time (WooCommerce registers them on
`plugins_loaded`, before the theme is read): `woocommerce_checkout_payment` off the sidebar so payment
can sit under "Payment options", and `woocommerce_checkout_coupon_form` out of
`woocommerce_before_checkout_form` so the coupon row can sit in the summary.

Fields are cut to `billing_first_name`, `billing_last_name`, `billing_phone`, `billing_email` and
`order_comments`, and the store's base country is stood in for the missing one in
`roova_checkout_posted_data()` — an order with no country breaks taxes, gateways and the admin screen.
`roova_cart_needs_shipping()` tells WooCommerce a cart of rooms never ships, but only when *every*
line is a booking.

**Every field key is one WooCommerce already knows, and that is what fills the form in for a member.**
`WC_Checkout::get_value()` prefills a field by looking for a matching getter on the customer object
(`get_billing_first_name()` and so on). The name used to be a single custom `billing_full_name`, split
back into a pair on post; no such getter existed, so it rendered empty no matter how much the store
knew about the guest. Adding a custom key here costs that prefill — take the pair, not a combined box.

**`roova_checkout_signup_cta()` sits under "Payment options" for a signed-out guest**, offering
membership. It is a **link, not a button**: anything that submits inside `form.checkout` would post the
order, and a nested `<form>` would be dropped by the browser entirely — the same trap the summary
avoids. Its copy is `checkout_signup_text` in the Customizer, and it hides itself for a member and
wherever `roova_registration_open()` is false, so it never offers a door that is locked.

Four things here are load-bearing and easy to undo by accident:

- **The summary is a sibling of `form.checkout`, not a child.** It contains the coupon form, and a
  browser silently drops a `<form>` nested inside another one — the element is in the HTML and never
  in the DOM. Nothing in the summary is posted, so nothing is lost.
- **The totals are their own refresh fragment.** WooCommerce replaces
  `.woocommerce-checkout-review-order-table` and `.woocommerce-checkout-payment` after every update;
  the totals sit below the coupon row, which must stay out of every fragment or it loses the submit
  handler WooCommerce bound to it once. So `review-order.php` renders **only** the line items — one
  root element — and `roova_checkout_totals()` is registered through
  `woocommerce_update_order_review_fragments`.
- **The total in the Place order button is part of its label string, not markup inside it.**
  WooCommerce rewrites the button with `.text()` on every payment-method change, reading the gateway's
  `data-order_button_text` or the button's own `data-value` — so `roova_place_order_label()` puts the
  total in both, and the arrow is a CSS pseudo-element, where `.text()` cannot reach it.
- **WooCommerce's own stylesheet paints `#payment`** as a grey panel with a lilac description box and
  a speech-bubble arrow. The resets in `checkout.css` carry the ID because Woo's selectors do.

**"… has been added to your cart." is suppressed on checkout, and only there.** The summary beside the
form already lists every room, so the notice adds nothing and pushes the form down. Notices are stored
as rendered HTML with the product name interpolated, so matching on the wording would break in every
language but English — instead `roova_tag_add_to_cart_notice()` marks it at the source through
`wc_add_to_cart_message_html` (a filter that fires for that notice and nothing else) with an empty
`.roova-cart-added` span, and `roova_hide_add_to_cart_notice_on_checkout()` drops the marked ones on
`template_redirect`. Only `success` notices are touched, so an "Only 2 rooms of this type are left"
error still reaches the guest, and anything checkout queues while rendering is added after this runs.

Each room in the summary carries a delete button. `roova_ajax_remove_cart_item()` / 
`roova_ajax_restore_cart_item()` follow the `inc/ajax.php` conventions (the `roova_ajax` nonce through
`roova_check_ajax_nonce()`) and work on `WC()->cart`, which belongs to the requester's session — so a
cart item key, which is *not* unique to a visitor, can only ever reach their own line. Freeing the
dates is not done there: `woocommerce_cart_item_removed` already runs
`Roova_Holds::on_cart_item_removed()`. Undo goes through `restore_cart_item()`, whose
`woocommerce_cart_item_restored` listener re-places the hold — and can fail, because the dates may
have gone to someone else, so the handler reads the failure back out of the notice store, drops the
line again and returns the message. The undo slot lives outside `#order_review` for the same reason
the coupon row does. Removing the last room reloads, and WooCommerce sends an empty checkout to the
cart. Nothing adds a `wc_add_notice` on that path: the cart is the Blocks cart, which never prints
stored notices, so the notice would sit in the session and surface on the next classic page.

Radio cards keep WooCommerce's `payment_method_{id}` class names on both the input and the
`div.payment_box`, so its script still slides the chosen gateway's description open. The card icon
comes from `roova_payment_icon()` — matched on gateway ID only, neutral card otherwise, the same rule
the amenity icons follow — and the note and badge are empty until a site fills them in through
`roova_payment_note()` / `roova_payment_badge()`. The design's four Malaysian methods are what the
handoff drew, not what the theme ships.

`assets/js/checkout.js` is **jQuery**, unlike the rest of the front end, because WooCommerce's checkout
events are jQuery custom events that never reach a native listener. It validates on
`checkout_place_order` and returns false to refuse the submit; it never submits the order itself.

`Roova_Holds::session_expiry()` backs the "Rate held for 9:42" countdown — it is the real expiry of
this visitor's earliest hold, not decoration.

The empty-cart panel in `checkout.php` is a fallback: WooCommerce redirects an empty checkout to the
cart page unless `woocommerce_checkout_redirect_empty_cart` is filtered off.

**The banner scrim and crop are measured, not styled by eye.** The photo behind the heading is a
Customizer setting, so no install can be assumed to have a dark corner to write on — and the reception
photo the theme ships is at its brightest exactly there. `object-position` sits at 60% (the handoff
says 38%) and the scrim carries a second wash from the left, together clearing WCAG AA against the
worst pixel behind each line — counting the translucent cream the eyebrow and sub-line are painted in,
which caps their contrast well below the heading's. Changing the photo, the crop or either wash means
re-checking: hide `.roova-checkout__banner-inner`, screenshot the band, and sample the pixels under
each text box.

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
`band-2.jpg` — 2400px JPEGs, ~1.1MB the three of them), and the checkout banner with `checkout.jpg`
(1200px, 98KB — a reception desk, the photo the checkout handoff asks for). The account pages add
`auth-signin.jpg` and `auth-signup.jpg` (452px and 510px, 126KB the pair) plus `logo.png`, the
full-colour wordmark lockup they print instead of the site name (834×353, 184KB — see *Accounts*).
`roova_background_image()` prints the
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

`assets/js/theme.js`, vanilla, no jQuery, wired entirely through `data-roova-*` attributes.
(`assets/js/checkout.js` is the one exception, and only because WooCommerce's checkout events are
jQuery's — see *Checkout*. It loads only on checkout pages, alongside `assets/css/checkout.css`.)
`assets/js/auth.js` is vanilla too, and loads only on the two account pages — see *Accounts*;
`assets/js/account.js` likewise, on the My account dashboard, alongside `assets/css/account.css`.
The saved-stays heart is the exception to that split: it lives in `theme.js`, because the same button
is on hotel cards site-wide, and account.js only listens for the `roova:like` event it fires.
Dates are handled as `Y-m-d` strings and only converted to `Date` at local midnight so a stay never
shifts a day across time zones. Server data arrives via the localized `roovaData` object built in
`roova_script_data()`. Admin JS does use jQuery (WordPress admin convention).

Scroll reveal (`[data-roova-reveal]`, `[data-roova-stagger]`) is deliberately paranoid: an
IntersectionObserver plus a scroll/resize sweep plus a 3s timeout that reveals everything. Anchor
jumps and scroll restoration can otherwise leave a section stuck at `opacity: 0`, which is far worse
than a section that never animated.

## Design system

The look comes from the handoffs in `design_handoff_roova_home/`, `design_handoff_roova_checkout/` and
`design_handoff_roova_auth/` (spec plus HTML prototypes — reference only, never shipped). Its tokens are the `:root` block at the top of `assets/css/theme.css`: cream
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

Each page that prints its own document also carries its own stylesheet with its own token header —
`checkout.css`, `auth.css`, `account.css`. The repetition is the convention here, not an oversight:
the field shell in `account.css` is a deliberate second copy of `auth.css`'s, because the two are
never loaded together and each page has to stand up alone.

## Conventions

- Prefix everything `roova_` / `Roova_`; product meta keys are `_roova_*`; text domain is `roova`.
- Every DB query goes through `$wpdb->prepare()`; every output is escaped; forms and AJAX carry nonces.
- Theme options are Customizer settings read through `roova_option( 'key', $default )`.

## When changing things

- **Strings:** rerun `bin/makepot.py` after adding or editing any translatable string.
- **The bookings table:** bump `Roova_Schema::DB_VERSION`, which triggers `dbDelta` on the next
  `admin_init`. Note `order_item_id` is nullable on purpose — the UNIQUE index has to allow the many
  rows that are still holds and have no order.
- **Releases:** `ROOVA_VERSION` in `functions.php` and `Version:` in `style.css` must match. Bumping it
  also re-runs the once-per-release setup on the next `admin_init` (`roova_setup_version` for the
  checkout page and tax rates, `roova_auth_version` for the two account pages).
- **Documentation:** `roova/README.md` ships to the client (setup, how conflict prevention works);
  the root `README.md` is for developers. Keep client-facing behaviour changes in the theme README.

## Other agent configs

`~/.codex/config.toml` and `~/.gemini/settings.json` exist on this machine. If you want their MCP
servers, commands or instructions available in Claude Code, reply `/import` to see what's importable.
