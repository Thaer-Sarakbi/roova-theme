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
   * a **Find a room** page using the *Hotel search results* template.
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
| The "Support" link beside *Manage booking* | **Header** |
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

## 9. Managing bookings

**WooCommerce → Bookings**

* **All bookings** — filter by hotel, status, date range, guest name or order number. Confirm or
  cancel any booking by hand.
* **Availability** — a month grid per hotel showing how many rooms of each type are free every night.

Each order also has a **Bookings** panel on its edit screen.

## 10. Menus and pages

* Create a menu and assign it to **Primary**; a good set is Hotels, Destinations, Why book direct.
* The footer has three link columns, each its own menu location — **Footer column 1 / 2 / 3**. The
  design fills them with Stay (Our hotels, Destinations, Long stays), Guests (Manage booking, Contact
  us, FAQ) and Company (About, Careers, Privacy). A column with no menu assigned is left out, and the
  headings are set in the Customizer.
* The nav "Manage booking" button points at the WooCommerce **My account** page, where guests can see
  and track their orders.

## 11. Developer notes

* Bookings live in `{prefix}roova_bookings`; `Roova_Availability` is the only thing that reads it for
  availability decisions.
* Useful filters: `roova_available_units`, `roova_hold_minutes`, `roova_pending_order_minutes`,
  `roova_max_nights`, `roova_hide_rooms_from_catalog`, `roova_redirect_rooms_to_hotel`,
  `roova_icon_library`, `roova_guarantees`, `roova_popular_searches`, `roova_map_places`,
  `roova_destination_gazetteer`, `roova_atlas_url`, `roova_atlas_views`.
* The homepage map draws real Natural Earth geometry with d3-geo and topojson, loaded from a CDN
  (pinned versions, checked with subresource integrity) only on the page that shows it. If they do
  not load, the town list beside the map still renders and still links.
* Cart/Checkout Blocks are supported: stay details are exposed through the Store API, and checkout is
  blocked when a stay is no longer available.
* Translations: `languages/roova.pot`.
