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
   * the **Destination** and **Amenity** product attributes,
   * a **Find a room** page using the *Hotel search results* template.
4. **Settings → Reading →** set your homepage to a static page so the hotel homepage template is used.
5. **Appearance → Customize → Roova hotel theme →** brand colours, hero text, Google Maps key, hold times.

## 2. Add your destinations

**Products → Attributes → Destination → Configure terms.**

Add one term per city or area (Ampang, Subang Jaya, Kajang, Serendah…). Each term can have a tile
image and a colour, used by the destinations grid on the homepage. Destinations power the search box,
the homepage grid and search filtering — adding a new one never needs code.

## 3. Add your amenities

**Products → Attributes → Amenity → Configure terms.**

Every amenity can be given an **icon**: pick one from the bundled set (Wi-Fi, parking, pool, breakfast,
air conditioning, and around forty more), or upload your own SVG/PNG. Amenities are assigned to hotels
and rooms from the product's **Attributes** tab — tick *Visible on the product page*.

## 4. Add a hotel

**Products → Add New**, then set **Product data → Hotel**.

| What | Where |
|---|---|
| Photos | Product image + product gallery |
| Description | The main product description |
| Destination | Attributes tab → Destination |
| Amenities | Attributes tab → Amenity |
| Address, latitude, longitude, map zoom | **Hotel Details** tab |
| Check-in / check-out times, phone, star rating | **Hotel Details** tab |
| Guest score and the Cleanliness / Location / Service bars | **Hotel Details** tab |
| Popular landmarks | **Hotel Details** tab — one per line, `Name \| 20.6 km` |
| Nearby landmarks | **Hotel Details** tab — one per line, `Name \| 470 m` |

Hotels are never added to the cart; they are the page guests browse. Their price display is "From …",
taken from the cheapest room.

## 5. Add rooms

**Products → Add New**, then set **Product data → Room (bookable)**.

* **General → Regular price** is the rate for **one room for one night**. Totals are rate × nights.
* **Room Details** tab:
  * **Hotel** — which hotel this room belongs to (required, or guests cannot find it).
  * **Units of this room type** — how many identical rooms exist. This is what prevents double
    bookings: the room stays bookable until every unit is taken on one of the requested nights.
  * **Minimum nights**, **max adults / children per room**, size, beds, view.
* Room photos come from the product image and gallery, shown in the "Room photos and details" modal.
* Rooms do not appear in the shop catalogue — they are booked from their hotel page.

## 6. How the booking system prevents conflicts

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

## 7. Managing bookings

**WooCommerce → Bookings**

* **All bookings** — filter by hotel, status, date range, guest name or order number. Confirm or
  cancel any booking by hand.
* **Availability** — a month grid per hotel showing how many rooms of each type are free every night.

Each order also has a **Bookings** panel on its edit screen.

## 8. Menus and pages

* Create a menu and assign it to **Primary**; a good set is Our hotels (→ Find a room), Destinations,
  Offers, Contact.
* The nav "Manage booking" button points at the WooCommerce **My account** page, where guests can see
  and track their orders.

## 9. Developer notes

* Bookings live in `{prefix}roova_bookings`; `Roova_Availability` is the only thing that reads it for
  availability decisions.
* Useful filters: `roova_available_units`, `roova_hold_minutes`, `roova_pending_order_minutes`,
  `roova_max_nights`, `roova_hide_rooms_from_catalog`, `roova_redirect_rooms_to_hotel`,
  `roova_icon_library`.
* Cart/Checkout Blocks are supported: stay details are exposed through the Store API, and checkout is
  blocked when a stay is no longer available.
* Translations: `languages/roova.pot`.
