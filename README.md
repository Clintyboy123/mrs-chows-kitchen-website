# Ink & Wok — packages

Built 2026-10-05 · updated 2026-11-02 · version 2.5.1 (Mrs Chow's site; Ink & Wok template stays 1.6.2)

| Folder | What it is | Who it's for |
|---|---|---|
| `ink-and-wok-html-template/` (+ `.zip`) | Neutral, sellable HTML template (home, menu, checkout) with direct online ordering, (home + menu, 102-dish demo menu) with a built-in admin at `/admin` (PHP 8.0+, no database). Demo brand "Jade Lantern", tinted photo placeholders, demo prices. | Sell it |
| `ink-and-wok-wordpress-theme/inkwok.zip` | Neutral, sellable classic WordPress theme. Customizer fields, Dishes post type, a Menu page and a 102-dish sample menu on activation. | Sell it |
| `mrs-chows-kitchen-website/` (+ `.zip`) | Mrs Chow's Kitchen with logo, photos, address and map, plus the same `/admin`. | The restaurant |

Each package has its own `documentation.html`.

## How to open the admin

The admin is the `admin/` folder inside `mrs-chows-kitchen-website/` (and inside `ink-and-wok-html-template/`). It runs on PHP, so double-clicking the files won't open it.

**On the live site:** go to the site's address followed by `/admin`, e.g. `https://mrschowskitchen.co.za/admin` (use the real domain once it's registered).
1. **The first visit sets the password.** Open `/admin` straight after uploading and choose one.
2. **Log in** with that password every time after.
3. **Forgot it?** Delete `content/config.php` with the host's file manager, then open `/admin` again to choose a new one.

**On this Mac (to demo or test):** needs PHP 8.0+ (`brew install php` if `php -v` says it's missing).
1. Work on a copy of the folder: publishing in the admin rewrites `index.html`, `menu.html` and `checkout.html`.
2. In Terminal, start the server from inside the copied folder:
   ```bash
   cd path/to/copy-of-mrs-chows-kitchen-website
   php -S localhost:8000
   ```
3. Open http://localhost:8000/admin/ and set a password. The site itself is at http://localhost:8000/.
4. Press `Ctrl+C` in Terminal to stop it. That link only works on this Mac while the server is running.

**In Docker:** same as live, `[domain]/admin`. See `techstack.md`, section 5.

**Version 2.5 (Mrs Chow's site, current):** the floating bowl starts about 30% bigger on desktop (about 394 px across at 1440 wide, level with the headline); its chopstick tops may now tuck behind the header. Because it starts bigger, the scroll zoom is gentler (1× → 1.4×). A drag-to-turn version was tried and removed.

**Version 2.4 (Mrs Chow's site):** online ordering is removed — the cart page is now a WhatsApp order form. Guests review their order (change quantities in the order slip), enter their name, choose collection or delivery (delivery asks for an address), when, and kitchen notes, then “Send order on WhatsApp” opens WhatsApp with the whole order typed out to the restaurant's number. No payment, no order server: `order.php` is gone and the admin's online-ordering settings (order email, Turnstile, Orders tab) are no longer used. The Mrs Chow's WordPress version has been dropped; this HTML + admin site is the only Mrs Chow's build. (The generic Ink & Wok WordPress theme is unchanged.)

**Version 2.3 (Mrs Chow's site + child theme):** background music plays automatically — at once where the browser allows it, otherwise on the visitor's first tap, click or key press anywhere (browsers block sound before that). It carries on from page to page: if it was playing, the next page starts it again by itself (Chrome/Edge allow this after the earlier click; Safari, iPhone and Firefox resume it on the first tap of the new page — no browser can keep sound playing through a full page load). A visitor who turns it off with the music seal stays muted on that device (`localStorage` key `mck-music`; `sessionStorage` `mck-music-on` tracks it for the visit). Pages where the seal is hidden (checkout; the menu page on phones) never autoplay. The admin's music switch still removes it completely. Cart page: while online ordering is still closed (it opens automatically once every dish has a price and the order email is set), the page now shows the visitor's order — items, quantities, collection/delivery and a “Change order” link — above the Call and WhatsApp buttons; WhatsApp sends those items. Also since 2.1: bowl placed level with the headline on desktop and centred on phones, fade removed, hours row removed from the hero strip, tighter gap before “More than a meal”.

**Version 2.1 (Mrs Chow's site + child theme):** the chopsticks now rest in the bowl — no noodle pull. As you scroll, the floating bowl turns about 70°, tilts about 24° and rolls about 11° (60 rendered frames, 1600×1600 `lg` / 900×900 `sm`, about 4.8 MB together), zooms from 1× to 1.55× (live CSS driven by `motion.js`) and stays fully visible as it scrolls away with the hero. On desktop the hero holds in place while this plays. The lifting version is kept as `_source/noodle-bowl/noodle_bowl_lift_v2.py`.

**Version 2.0 (Mrs Chow's site + child theme):** the 3D noodle bowl now floats directly on the page — no photo frame, no table or backdrop. The render has a transparent background (1200×1200 `lg`, 800×800 `sm`, about 3 MB together) with a soft shadow under it and a slow idle bob. As you scroll, the bowl turns about 50°, tilts 16° and rolls 10° while the chopsticks lift the noodles; on desktop the hero holds in place for the lift. The 鮮 stamp that sat on the photo frame is gone. The framed v1 scene is kept as `_source/noodle-bowl/noodle_bowl_framed_v1.py`.

**Version 1.9 (Mrs Chow's site + child theme):** the hero photo slideshow is replaced by a photoreal 3D bowl of noodles: 60 frames rendered in Blender (`assets/img/noodles/lg` 1296×1080 for desktop, `sm` 720×600 for phones, about 2.8 MB together) that the page scrubs as you scroll. On desktop the hero holds in place while the chopsticks lift the noodles out of the bowl, then the page carries on; on phones the lift plays as the photo moves up the screen. Visitors who ask for reduced motion see a still of the noodles mid-lift. The admin's hero photo fields no longer appear on the home page. Source: `_source/noodle-bowl/noodle_bowl.py` (rebuilds and re-renders the scene) and `noodle_bowl.blend` beside the packages. WordPress: the child theme now ships its own `front-page.php` (a copy of Ink & Wok's with the hero swapped).

**Version 1.7 (Mrs Chow's site + child theme):** a motion layer in `assets/css/motion.css` and `assets/js/motion.js` (the child theme enqueues its own copies). Layered parallax on photos and the decorative seals, calligraphy and sun (one knob: `PARALLAX_STRENGTH` at the top of `motion.js`, 0 turns it off; calmer on phones). Text and blocks rise and sharpen, photos blur into focus and cards float up, all on one ease-out `cubic-bezier(0.16, 1, 0.3, 1)` over 4–5 s. Buttons zoom to 1.05 on hover in 0.8 s. Phone buttons are 40 px with a 44 px tap area. The family signature line under the story was removed. Everything is off for visitors who ask their device for reduced motion.

**Version 1.6 (all packages):**
- **WhatsApp:** a WhatsApp button in the phone bottom bar, the contact section and the footer, plus "Send order on WhatsApp" on the cart page (the order arrives typed out in the chat). Set the number in the admin (Details → WhatsApp number) or Customize → Restaurant details; empty hides every WhatsApp button. Placeholder number 27000000000 until Mrs Chow's real one is in.
- **Phones:** the cart lives in the ☰ menu ("Cart" with its item count) instead of the header; menu links are centred; no back-to-top button.
- **Home page:** the "Favourites from our kitchen" dishes are a one-at-a-time slider on phones, and the trees and hanging scrolls on the home page no longer move.

**Version 1.5 (all packages):** the hero photo can be a slideshow of up to four photos (slow crossfade every 6 seconds, slide markers, a pause button; it starts paused for visitors who prefer reduced motion). Add photos 2–4 in the admin (Hero) or Customize → Ink & Wok → Hero. Mrs Chow's uses the table spread, dumplings, dim sum table and Singapore noodles.

**WordPress 1.4:** the WordPress theme now has the same cart and online ordering: a Cart page, a cart icon in the navigation, orders under **Orders** in the dashboard and emailed to the restaurant (settings in Customize → Ink & Wok → Online ordering; empty email = the site admin email). It also gets the phone layout updates.

**Version 1.4.1:** a cart icon with an item count sits in the navigation bar on every page and opens the cart page (on phones it moved into the menu in 1.6).

**Version 1.4 (HTML packages):** the checkout is now a proper cart page ("Your cart"): change quantities or remove dishes, then fill in your details and place the order. Each order is emailed to the restaurant address set in the admin (Orders › Online ordering) and saved in the admin Orders tab.

**Version 1.3.2:** smaller phone buttons (44px tall, sized to their label) and everything on phones centred, including menu dishes, hours, takeaway tiles and checkout labels.

**Version 1.3.1:** phones use one centred axis for headings, intros, buttons, cards, reviews and footer; menu dishes, hours, takeaway tiles and forms stay left-aligned for quick scanning.

**Version 1.3 (phone layout pass, all packages):** one spacing scale on phones; the first screen shows headline, buttons and food together; signature dishes and reviews become swipeable rails; compact takeaway, contact and footer; sticky category bar and consistent prices on the menu; the bottom order bar appears once the hero buttons scroll away. Home page on a phone is about half as long (≈20,100px → ≈10,400px).

**Version 1.2 (direct online ordering, HTML packages):** guests check out on the site and pay on collection or delivery; orders arrive in the admin's Orders tab and by email. Protected by server-side pricing, a signed form check, a spam trap, a rate limit and Cloudflare Turnstile (keys in the admin). (WordPress got the same in 1.4.)

**Version 1.1 (speed + mobile ordering):** lighter pages (responsive WebP photos, textures as images, map loads on tap), the order is remembered between pages, a slide-up order panel on phones and tablets, and menu search with Vegetarian / Spicy filters. `_backup-before-speed-update.zip` holds the previous version; delete it once you are happy.

## Before you sell

* **Name and author:** "Ink & Wok" and "Your Studio Name" are placeholders. Change them in
  `style.css` (theme header), `readme.txt`, `assets/css/style.css`, `assets/js/main.js` and both documentation files.
* **Marketplace previews:** the products contain no photos, as marketplaces require. For your
  live demo and screenshots, add photos you have the rights to show, e.g. from Unsplash, and say
  in the listing that photos are not included.
* **Licence:** WordPress themes must be GPL-compatible (this one is GPLv2+). For the HTML template,
  use your marketplace's standard licence (ThemeForest, Creative Market and Gumroad each provide one).
* **Not tested in a live WordPress install or the Theme Check plugin.** Run Theme Check
  and test on a fresh WordPress site before listing.

## Kept out of the sellable packages

* **Spell of Asia font:** personal-use licence only. It's in the Mrs Chow's versions; buy a commercial licence before that site goes live.
* **Unsplash photos:** fine on Mrs Chow's own website, but they can't be resold inside a template.
* **Mrs Chow's logo, name and address:** they belong to the restaurant.
