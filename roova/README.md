# Roova — hotel group theme for WooCommerce

Roova turns a WooCommerce store into a multi-property hotel booking site. Hotels and rooms are
WooCommerce products, stays are priced per night, availability is date based, and every payment runs
through the normal WooCommerce cart, checkout and orders.

* **Requires:** WordPress 6.4+, WooCommerce 8.0+, PHP 7.4+
* **Text domain:** `roova`

---

## 1. Install

1. **Appearance → Themes → Add New → Upload Theme**, choose `roova.zip`, install and activate.
2. Install and activate **WooCommerce** if it is not active yet (the theme shows a notice with a link).
3. On activation the theme creates:
   * the bookings database table,
   * the **Destination**, **Amenity** and **Facilities** product attributes,
   * a **Find a room** page using the *Hotel search results* template,
   * a **Checkout** page, if WooCommerce has not already made one,
   * two tax rates — **Tourism Tax** 5% and **SST** 10% — but only on a store that has no tax rates
     of its own. See section 10.
4. **Settings → Reading →** set your homepage to a static page so the hotel homepage template is used.
5. **Appearance → Customize → Roova hotel theme →** brand colours, hero text, Google Maps key, hold times.

## 2. Set up the homepage

The homepage runs in a fixed order: hero and search → four booking promises → a photo band →
your hotels → the destination mosaic → a second photo band → the map of where you are.

Everything on it is edited from **Appearance → Customize → Roova hotel theme**:

| Section | Where |
|---|---|
| Hero photo, eyebrow, headline, sub-heading | **Homepage hero** |
| The pills under the search bar | **Homepage hero → Popular searches** — one per line, `Malacca old town \| malacca` points a pill at a destination |
| The four promises under the hero | **Booking promises** — clear a title to drop that promise |
| Both full-width photo bands, and the words on the first one | **Homepage sections** |
| Section headings, and switching the hotels, destinations or map sections off | **Homepage sections** |
| The "Support" link beside the account control | **Header** |
| The logo on the account pages, a photo each for their panels, the headlines and the two figures | **Sign in and sign up** |
| Footer tagline, the three column headings, the bottom-right note | **Footer and contact** |

Photography does the heavy lifting here. The theme ships with three stand-in photos — the hero and
both full-width bands — so a fresh install already looks like the design; choosing your own image in
the Customizer replaces the one underneath it. Hotel cards and destination tiles have no stand-in:
they use the hotel's product image and the destination term's tile image.

> The four promises ship with wording taken from the client's reference — best rate, no booking
> fees, instant confirmation, 24/7 support. **Check each one is true for your hotels before you go
> live**, and reword or remove any that is not.

## 3. Add your destinations

**Products → Attributes → Destination → Configure terms.**

Add one term per city or area (Ampang, Subang Jaya, Kajang, Serendah…). Each term can have a tile
image and a colour, used by the destinations mosaic on the homepage. Destinations power the search
box, the homepage mosaic, the coverage map and search filtering — adding a new one never needs code.

Each term also takes a **latitude and longitude**, which is where it is pinned on the homepage map.
The theme already knows the Klang Valley and Malacca towns (Ampang, Kajang, Kota Damansara, Malacca,
Rawang, Subang, Taman Melawati and a few more), so you only need to fill these in for somewhere else.
A destination with no coordinates is simply left off the map — never guessed at.

Put a hotel in a destination from **Product data → Hotel Details → Destination** (start typing to
search). That one choice does three jobs: it is the hotel's location line, it decides which searches
the hotel appears in, and it becomes a link — in the breadcrumb and under the hotel name — that shows
every other hotel in the same destination, keeping the dates and guests the guest already chose.

A hotel can sit in more than one destination if that is useful; the first one is shown as its
location.

## 4. Add your amenities

**Products → Attributes → Amenity → Configure terms.**

Every amenity can be given an **icon**: pick one from the bundled set (Wi-Fi, parking, pool, breakfast,
air conditioning, kettle, mirror, slippers, private bathroom, and around forty more), or upload your
own SVG/PNG.

You usually do not have to pick one. If the term's name matches a bundled icon exactly — *Mirror*,
*Slippers*, *Smoking area*, *kettle*, *Private bathroom*, *Shower*, *Non-smoking*, *Air conditioning*
and so on — that icon is used automatically. Choosing an icon overrides the match, and anything with
no match shows a neutral tick.

Amenities are chosen on a hotel from **Product data → Hotel Details → Amenities**: start typing and
the list narrows, click a name to add it, click the × on a chip to remove it. (They can also be set
from the product's **Attributes** tab — both screens edit the same thing, so use whichever you
prefer.) For rooms, use the Attributes tab.

## 5. Add your facilities

**Products → Attributes → Facilities → Configure terms.**

Facilities are the plain checklist shown in the **Facilities** panel just above "Select your room"
(Free Wi-Fi, Free parking, Check-in [24-hour], Laundry, Daily housekeeping…). They need no icon —
every one is listed with a tick — so adding the term is all it takes. Choose them on a hotel from
**Product data → Hotel Details → Facilities**, the same search-as-you-type field as amenities.

Amenities and facilities overlap on purpose: amenities are the illustrated highlights, facilities are
the full list.

## 6. Add a hotel

**Products → Add New**, then set **Product data → Hotel**.

| What | Where |
|---|---|
| Photos | Product image + product gallery |
| Description | The main product description |
| Destination | **Hotel Details** tab → Destination (or the Attributes tab) |
| Amenities | **Hotel Details** tab → Amenities (or the Attributes tab) |
| Facilities | **Hotel Details** tab → Facilities (or the Attributes tab) |
| Address, latitude, longitude, map zoom | **Hotel Details** tab |
| Check-in / check-out times, phone, star rating | **Hotel Details** tab |
| Guest score and the Cleanliness / Location / Service bars | **Hotel Details** tab |
| Popular landmarks | **Hotel Details** tab — one per line, `Name \| 20.6 km` |
| Nearby landmarks | **Hotel Details** tab — one per line, `Name \| 470 m` |

Hotels are never added to the cart; they are the page guests browse. Their price display is "From …",
taken from the cheapest room.

## 7. Add rooms

**Products → Add New**, then set **Product data → Room (bookable)**.

* **General → Regular price** is the rate for **one room for one night**. Totals are rate × nights.
* **Room Details** tab:
  * **Hotel** — which hotel this room belongs to (required, or guests cannot find it).
  * **Units of this room type** — how many identical rooms exist. This is what prevents double
    bookings: the room stays bookable until every unit is taken on one of the requested nights.
  * **Minimum nights**, **max adults / children per room**, size, beds, view.
* Room photos come from the product image and gallery, shown in the "Room photos and details" modal.
* Rooms do not appear in the shop catalogue — they are booked from their hotel page.

## 8. How the booking system prevents conflicts

**One booking at a time.** The cart holds a single room type: booking a room empties the cart first, so
what a guest pays for is always the stay they just chose, and the dates their old cart was holding go
straight back on sale. Guests are told the previous room was replaced. They can still book several
rooms of the *same* type in one stay — that is the "Rooms" number in the search bar.

**"Book now" goes straight to checkout.** There is nothing to add to the booking, so the cart page is
skipped. If the room cannot be held after all — someone else took the last one in the same second —
the guest stays on the hotel page with the reason, and whatever they had in the cart is still there.

1. **Adding to cart places a hold.** The dates are reserved for 30 minutes (Customizer → Booking).
   Another guest cannot take the last unit while it sits in someone's cart.
2. **The cart is re-checked** every time it or the checkout is viewed, and expired holds are ignored
   immediately — nothing depends on a cron job running on time.
3. **Checkout commits under a lock.** Each room is committed inside a MySQL named lock, so two guests
   racing for the last unit are serialised: one order succeeds, the other gets a clear error and no
   booking row.
4. **Order status drives the booking.** Paid or processing → confirmed. Cancelled, refunded or failed →
   the dates are released. Unpaid orders release their rooms after the "unpaid order hold" period.
5. If a *paid* order ever ends up overbooked (for example a manual admin change), the booking is kept
   and a loud order note tells staff to contact the guest — a paid stay is never silently dropped.

Availability maths: for a requested stay, every overlapping booking is added up **per night**, and the
busiest night decides. A room with 8 units is bookable while fewer than 8 units are taken on each of
those nights. Same-day turnarounds do not collide: a guest checking out on the 5th frees that night
for a guest checking in on the 5th.

## 9. The checkout page

The theme replaces WooCommerce's checkout with one built for room bookings. You do not have to set it
up — it takes over the checkout page whatever that page contains, including the block checkout that
WooCommerce installs by default.

You will find **Checkout** under **Pages**, alongside Cart, Shop and My account. The theme checks it is
there and puts it back if it goes missing, so there is nothing to create by hand. Editing that page
does not change what guests see: the checkout below is drawn by the theme, not by the page's content.
The page it points at is set under **WooCommerce → Settings → Advanced → Checkout page**.

What a guest sees:

* A stripped-back header — just your wordmark and "Secure booking". No menu, nothing to click away
  with.
* A photo banner reading "Checkout" and, under it, how many rooms are being held.
* **Guest information** — first name, last name, phone and email. Nothing else: a stay has no delivery
  address, so every address, company and country field is gone. **A signed-in member finds all four
  already filled in** from their account.
* **Order notes** (optional).
* **Payment options** — one card per payment method you have switched on in **WooCommerce → Settings →
  Payments**. Their titles and descriptions are your own; choosing a card opens its description. Add,
  rename or reorder a gateway there and the cards follow.
* **Booking terms** — a checkbox the guest has to tick. It links to the terms page set in
  **WooCommerce → Settings → Advanced → Terms and conditions**, or to the link in **Customizer →
  Roova → Checkout** if you have not set one.
* **Place order**, showing the live total, and the reassurance line underneath (Customizer).
* Under that, for a guest with no account, an invitation to **sign up and become a member**. It is a
  link, not a step — it never gets between the guest and the booking, and it brings them back to
  checkout afterwards with their rooms still held. Members never see it. Its wording is
  **Customizer → Roova → Checkout → Sign-up invitation**, and it disappears entirely if you switch
  sign-up off.
* On the right, the **order summary**: every room in the cart with its photo, hotel, dates, nights and
  guests, a coupon box, the totals, and a countdown showing how long the rooms stay held.
* Each room has a **×** button to take it out of the booking. The dates it was holding go straight
  back on sale, the totals and the Place order button follow, and an **Undo** link appears in case it
  was a mistake — undo only works while nobody else has taken those dates in the meantime. Removing
  the last room sends the guest to the cart.

The banner photo, the eyebrow, the header reassurance and the line under the Place order button are
all in **Customizer → Roova hotel theme → Checkout**.

Guests are told about a problem next to the field it belongs to, and the order cannot be placed until
the name, phone, email and terms are all filled in.

WooCommerce's "*… has been added to your cart*" message is not shown on this page — the order summary
beside the form already lists every room. It still appears on hotel pages, where it is the
confirmation that the room went in, and anything that actually needs the guest's attention (a room
that has sold out, a payment problem) is still shown here.

## 10. Taxes

A fresh install starts with two rates, added on top of the room rate and shown as their own lines in
the order summary:

| Tax | Rate |
|---|---|
| Tourism Tax | 5% |
| SST | 10% |

**Change them at WooCommerce → Settings → Tax → Standard rates.** Edit a percentage, rename a tax, add
a third or delete one — the checkout summary, the confirmation page, the order and the emails all
follow. The percentage in the label ("SST (10%)") is read from the rate, so it is never out of step
with what is actually charged.

Two things worth knowing:

* Each tax needs its own **Priority**. WooCommerce charges one rate per priority, so two rates sharing
  a priority means only the first is applied. Tourism Tax is priority 1, SST is priority 2; a third
  tax needs priority 3.
* Both are charged on the room rate, not on each other. Tick **Compound** on a rate if it should be
  charged on top of the ones above it.

The theme only ever adds these rates to a store that has **no tax rates at all**. Once they exist they
are yours — theme updates never change them.

Under **Settings → Tax** you can also switch *Display tax totals* to "As a single total" for one
combined line, or turn tax off entirely under **Settings → General**, in which case the summary shows
"Taxes & fees — Included".

## 11. Managing bookings

**WooCommerce → Bookings**

* **All bookings** — filter by hotel, status, date range, guest name or order number. Confirm or
  cancel any booking by hand.
* **Availability** — a month grid per hotel showing how many rooms of each type are free every night.

Each order also has a **Bookings** panel on its edit screen.

## 12. Menus and pages

* Create a menu and assign it to **Primary**; a good set is Hotels, Destinations, Why book direct.
* The footer has three link columns, each its own menu location — **Footer column 1 / 2 / 3**. The
  design fills them with Stay (Our hotels, Destinations, Long stays), Guests (Manage booking, Contact
  us, FAQ) and Company (About, Careers, Privacy). A column with no menu assigned is left out, and the
  headings are set in the Customizer.
* The top right of the header is the **account control**: one button, reading "Sign in" for a visitor
  and "Manage account" — linking to **My account** — once they are signed in. See the next section.

## 13. Sign in and sign up

Activating the theme creates two pages — **Sign in** (`/sign-in/`) and **Sign up** (`/sign-up/`) — and
gives them the matching page templates. They are ordinary pages, so you can rename them or move them
in a menu; keep the template assigned and everything keeps working.

* **Signing out** is WooCommerce's own logout link, on the My account page.
* **"Forgot password?"** goes to WooCommerce's lost-password form.
* Someone who is signed out and opens **My account** is sent to the sign-in page and back again
  afterwards, so the site only ever shows one login form — the designed one.
* Signing up creates a **customer** account with the name, email and phone already filled in, so
  checkout does not ask for them a second time.
* **New accounts confirm their email address before they can be used.** Signing up sends a link to
  the address; opening it confirms the account and signs the member straight in. Until then they
  cannot sign in at all — trying tells them so and offers to send the link again. The link works
  for 48 hours, and asking for a new one replaces the old.
  * There is no "confirm your email address" box on the form any more. Re-typing an address only
    catches a slip of the fingers; a link that has to arrive at the real inbox catches everything.
  * Members who already had an account before this were let in as they were — nobody is locked out
    by it.
  * Only one email is sent. WooCommerce's own "welcome" email is held back until confirmation is
    off, so nobody gets two messages a second apart.
  * If your host cannot send email, nobody can finish signing up. Test it: create an account and
    check the link arrives. An SMTP plugin is the usual fix.
  * If a form ever comes back saying **"That form had expired"**, the usual cause is a page cache
    serving an old copy of the sign-in or sign-up page. Exclude both pages from caching — they are
    forms, and there is nothing on them worth caching.
* Sign-up is **on out of the box** and does not depend on any WooCommerce setting. To close it, untick
  **Let guests create accounts** under **Sign in and sign up** in the Customizer: the sign-up page then
  shows a short note instead of the form, and sign-in keeps working for members who already have an
  account.
* The two pages print your **logo** where every other page prints the site name. It is its own
  Customizer setting because these pages are white: the version of a logo that sits on the homepage
  hero is usually the light one, and it would disappear here. Upload the full-colour version under
  **Sign in and sign up → Logo on these pages**, or leave it empty for the one the theme ships.
* Each page has its **own panel photo**, plus its headline and the two shared figures, under
  **Sign in and sign up** in the Customizer. Clear either figure and its column disappears — do not
  leave a number there that your hotels cannot back up.
* The two photos the theme ships with (the Petronas Towers on sign in, Batu Caves on sign up) are
  small — smaller than the panel they fill, so they look a little soft on a large screen. **Replace
  them with your own at 700 × 900 or larger** and the panel sharpens up. Portrait crops suit it best.
  If you change a photo, check the white text over it is still easy to read — a picture that is bright
  along its bottom edge is the one to watch.

## 14. My account

**My account** is WooCommerce's page, laid out to the Roova design. It has six tabs — Profile,
Bookings, Reviews, Likes, VIP and Cashback rewards — and everything on it is read from your site as
the page loads: orders for the bookings, guest reviews for the reviews, each member's saved list for
the likes, the tiers you set up for VIP, and each member's own cashback ledger. There is nothing to
fill in.

* **Profile** — first name, last name and phone, already filled in from the member's account, plus a
  password panel. The email address is shown but not editable: it is the sign-in ID. Saving writes
  the same fields checkout fills itself in from, so a member who corrects a name here does not have
  to correct it again at checkout.
* **Bookings** — one card per stay, newest first, with a status chip: **Upcoming**, **Completed**,
  **Cancelled**, or **Payment due** for an order that has not been paid for yet. The button changes
  with it — View voucher, Book again, See details, or Pay now.
* **Reviews** — see section 15.
* **Likes** — the stays a member has saved with the heart on any hotel card or hotel page. Tapping
  the heart again removes it.
* **VIP** — see section 16.
* **Cashback rewards** — see section 17.

Everything else under My account — a single order, the address book, lost password — is
WooCommerce's own screen and is unchanged.

The theme keeps the **My account page** itself in place: if it is missing, unpublished or in the
trash, it is restored (or created) the next time you open the dashboard. Deleting that page breaks
the account button, signing in, and the link in the confirmation email — so it is put back rather
than left broken. Its content is never rewritten, so anything you have added to it stays.

## 15. Guest reviews

A review is a normal **WooCommerce product review** on the hotel, so it appears under **Comments** in
the dashboard and is moderated there like any other. What the theme adds is who may write one, and
what a review carries:

* Only a guest who has **completed a stay at that hotel** can review it, and only **once per hotel**.
  Their account offers it on the Reviews tab as a gold "Rate your stay at ..." prompt.
* A review carries a star rating and three sub-scores — **Cleanliness, Location and Service**.
* Until you approve it, the member sees their own review marked *Waiting to be published*. Nobody
  else sees it.
* Once there is at least one approved review, the **score box on the hotel page is calculated from
  real reviews** — the score, the review count and the three bars. The numbers on the Hotel Details
  tab are the stand-in until then, so a brand-new hotel still shows something.
* Reviews follow WooCommerce's own settings: switch reviews off under **WooCommerce → Settings →
  Products**, or close discussion on one hotel, and the prompt disappears for it.

## 16. RoovaVIP

Members climb tiers by **completing bookings** — a stay counts once the guest has checked out and the
order is paid. Nothing else counts: no spend thresholds, no expiry dates.

Set the tiers up under **WooCommerce → Settings → RoovaVIP**:

* **Add tier** gives you a name and the number of completed bookings it needs. The order you add them
  in does not matter — tiers are sorted by that number.
* **Add benefit** adds a row to a tier: an icon, the benefit, and a note under it. These are shown to
  the member on the VIP tab; nothing here changes what anyone is charged, so only promise what your
  front desks will honour.
* **Remove tier**, and the × beside a benefit, delete them. Delete every tier to switch RoovaVIP off
  entirely — the tab disappears from My account and the tier stops showing in the account header. Add
  one back and it returns.

**Bronze is where everyone starts.** The lowest tier is the floor, so a member who has never stayed
is Bronze rather than nothing at all — and if you raise your first tier above zero bookings, a new
member still lands on it.

### Setting a member's status by hand

**Users → All Users →** open a member. Under **RoovaVIP** there is a **Member status** dropdown:

* **Automatic** — the default. The status follows their completed bookings, and the option says
  which tier that currently is, so you can see what you would be overriding.
* **Any tier** — pins the member there. It stops moving with their bookings until you set it back
  to Automatic. Use it for a comped VIP, your own staff, or a guest whose stays predate the site.

The **VIP status** column on the Users list shows where every member stands, and marks the ones set
by hand. A pinned member keeps their real booking count on their VIP tab — the tier is yours to
give, the bookings are what actually happened.

Renaming a tier drops any pin that pointed at it and those members go back to Automatic, so rename
with that in mind.

The theme ships five tiers — Bronze, VIP Silver, VIP Gold, VIP Platinum and VIP Diamond — with
benefits written for Gold only. The other four are deliberately empty: their benefits are yours to
decide, and a tier with none simply leaves that section off the page rather than showing an empty
list.

## 17. Cashback rewards

Members earn cashback by **completing stays**. A stay counts once the guest has checked out and the
order is paid — the same rule RoovaVIP uses.

Set the offers up under **WooCommerce → Settings → Cashback rewards**. A site with no rewards runs no
cashback, and every member's balance reads zero; the tab is still there, so a member can always find
what they earned from an offer that has since ended.

**Add reward** gives you one row with five things to fill in:

* **Hotel** — one of your hotels, or **All Hotels** at the top of the list, which also covers hotels
  you add later.
* **Duration (nights)** — the shortest stay that qualifies. It is a **minimum**: a reward set to 7
  nights also pays out on a 9-night stay, so a longer stay never earns less than a shorter one.
* **Reward amount** — a flat amount in your store's currency, not a percentage.
* **Expiry date** — the last day a stay can *check out* and still qualify. Leave it blank and the
  reward runs until you delete it; the member's card reads "Always on".
* **Clears after (days)** — how long after checkout the money moves from **Pending** to
  **Available**. Set it to 0 and it lands immediately.

There are three optional extras: an **icon** for the card, a **card title** (the hotel's name is used
when you leave it blank) and a **card description** (the rule is described automatically when you
leave it blank).

Four things are worth knowing before you start adding rewards:

* **Rewards do not stack.** A stay that qualifies for more than one reward earns the most valuable
  of them, once.
* **A new reward never pays out for stays that are already over.** It applies to stays that check out
  between the day you add it and the day it expires, so adding one today cannot suddenly credit last
  year's guests. Each row shows the date it started running.
* **Editing or deleting a reward never changes what a member has already earned.** The amount and the
  clearing date are fixed at the moment the stay completes. Cutting an offer in half only affects
  stays from then on; deleting it does not claw anything back.
* **A refunded or cancelled stay gives its cashback back.** If an order is cancelled or refunded
  after the guest has checked out, the entry drops off the member's ledger and their balance falls.

What the member sees on the tab: three balances — **Available**, **Pending** and **Earned all
time** — the offers you are currently running, and an **Activity** list of everything they have
earned, each row marked *Pending* or *Cleared*. Their available balance also appears beside "Stays
booked" at the top of the account page.

**Cashback is a promise, not a discount.** Nothing here changes what a guest is charged at checkout —
the theme keeps the figure and shows it, and your front desk honours it, exactly as RoovaVIP benefits
work. So only offer what you will actually pay out.

## 18. Developer notes

* Bookings live in `{prefix}roova_bookings`; `Roova_Availability` is the only thing that reads it for
  availability decisions.
* Useful filters: `roova_available_units`, `roova_hold_minutes`, `roova_pending_order_minutes`,
  `roova_max_nights`, `roova_hide_rooms_from_catalog`, `roova_redirect_rooms_to_hotel`,
  `roova_icon_library`, `roova_guarantees`, `roova_popular_searches`, `roova_map_places`,
  `roova_destination_gazetteer`, `roova_atlas_url`, `roova_atlas_views`.
* Cashback offers live in the `roova_cashback_rewards` option; each member's ledger is the
  `roova_cashback_ledger` user meta, keyed by stay so earning is idempotent. Whether an amount has
  cleared is read off the calendar rather than a stored flag, so no cron has to fire for a balance to
  be right. Filters: `roova_cashback_enabled`, `roova_cashback_rewards`, `roova_cashback_sync`,
  `roova_cashback_reward_matches`, `roova_cashback_icons`. `roova_cashback_record()` writes a
  redemption into a member's ledger for a site that spends the balance itself; the action
  `roova_cashback_earned` fires when a stay earns.
* The homepage map draws real Natural Earth geometry with d3-geo and topojson, loaded from a CDN
  (pinned versions, checked with subresource integrity) only on the page that shows it. If they do
  not load, the town list beside the map still renders and still links.
* The checkout page is the theme's own (`woocommerce/checkout/*.php` over the classic checkout,
  routed by `roova_checkout_template()`), so it does not matter whether the checkout page holds the
  block or the shortcode. Filter `roova_use_checkout_template` to false to hand the page back to
  WooCommerce.
* Checkout filters: `roova_payment_icon`, `roova_payment_note` and `roova_payment_badge` decide the
  icon, the small grey line and the gold pill on each payment card.
* My account filters: `roova_use_account_template` (false) hands the dashboard back to WooCommerce,
  `roova_account_tabs` adds or removes a tab, `roova_account_completed_count` changes what a VIP tier
  is counted from, `roova_account_email_verified` hides the Verified badge, `roova_show_like_button`
  hides the heart, `roova_reviews_open` and `roova_review_subscores` govern reviews, and
  `roova_vip_tiers` / `roova_vip_enabled` / `roova_vip_benefit_icons` govern the tiers. Actions:
  `roova_account_profile_saved`, `roova_review_submitted`, `roova_like_toggled`.
* Saved stays live in the `roova_liked_hotels` user meta; VIP tiers in the `roova_vip_tiers` option.
* Confirmation filters: `roova_require_email_verification` (false) goes back to signing new members
  in immediately, `roova_verification_lifetime` changes how long a link lasts,
  `roova_verification_email` rewrites the message, and `roova_verification_url` changes where the link
  points. `roova_email_verified` fires with the user ID once an address is confirmed.
* Auth filters: `roova_registration_open` decides whether the sign-up form will create accounts, and
  `roova_redirect_account_to_signin` (false) leaves a signed-out My account showing WooCommerce's own
  login form instead. `roova_member_registered` fires with the new user ID and their details.
* The cart still uses the Blocks version: stay details are exposed through the Store API, and checkout
  is blocked when a stay is no longer available.
* Translations: `languages/roova.pot`.
