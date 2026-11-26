<?php
/**
 * Every editable text field: key => [section, label, kind, demo value].
 * Generated with the template; add your own fields here and use f($s, 'key') in template.php.
 */

declare(strict_types=1);

function inkwok_fields(): array
{
    return [
        'brand' => ['brand', 'Restaurant name', 'text', 'Jade Lantern'],
        'seal_char' => ['brand', 'Seal character (used when no logo is set)', 'text', '味'],
        'tagline' => ['brand', 'Tagline (footer, script font)', 'text', 'Traditional flavour. Made fresh.'],
        'year' => ['brand', 'Year opened', 'text', '1998'],
        'area' => ['brand', 'Area / suburb, city', 'text', 'Riverside, Your City'],
        'area_country' => ['brand', 'Area, city, country (footer)', 'text', 'Riverside, Your City, Country'],
        'topbar_text' => ['brand', 'Top bar text', 'text', 'Serving Riverside since 1998'],
        'phone' => ['brand', 'Phone number (as shown)', 'text', '(000) 000-0000'],
        'phone_tel' => ['brand', 'Phone number (for tel: links, digits only)', 'tel', '+10000000000'],
        'whatsapp' => ['brand', 'WhatsApp number (country code + number, digits only, e.g. 27821234567; leave empty to hide the WhatsApp buttons)', 'tel', '27000000000'],
        'address_line1' => ['brand', 'Address line 1', 'text', '88 Lantern Lane'],
        'address_line2' => ['brand', 'Address line 2', 'text', 'Riverside, Your City, 0000'],
        'address_short' => ['brand', 'Short address (footer)', 'text', '88 Lantern Lane, Riverside'],
        'map_query' => ['brand', 'Map search (name + address as you would type it into Google Maps)', 'text', 'Chinatown, San Francisco, CA'],
        'directions_url' => ['brand', 'Directions link (Google Maps share link)', 'url', 'https://maps.google.com/?q=Chinatown,+San+Francisco'],
        'social_handle' => ['brand', 'Social handle (without @)', 'text', 'jadelantern'],
        'social_facebook' => ['brand', 'Facebook URL', 'url', '#'],
        'social_instagram' => ['brand', 'Instagram URL', 'url', '#'],
        'social_tiktok' => ['brand', 'TikTok URL', 'url', '#'],
        'copyright_year' => ['brand', 'Copyright year', 'text', '2026'],
        'hours_days' => ['hours', 'Opening days', 'text', 'Monday – Sunday'],
        'hours_short' => ['hours', 'Opening hours', 'text', '11:00 – 22:00'],
        'hours_holidays' => ['hours', 'Public holiday hours', 'text', 'Public holidays: 12:00 – 21:00'],
        'prep_time' => ['hours', 'Collection time (minutes)', 'text', '20–30'],
        'delivery_time' => ['hours', 'Delivery time (minutes)', 'text', '40–50'],
        'order_url' => ['hours', 'Online ordering link (Checkout button)', 'url', '#full-menu'],
        'order_label' => ['hours', 'Online ordering name (e.g. your ordering site)', 'text', 'our online ordering partner'],
        'currency' => ['hours', 'Currency symbol', 'text', '$'],
        'delivery_text' => ['hours', 'Delivery tile', 'textarea', 'Delivering within 5 km. Delivery fee $3 · minimum order $20.'],
        'collection_text' => ['hours', 'Collection tile', 'textarea', 'Order ahead online or by phone and collect from 88 Lantern Lane. Free parking out front.'],
        'prep_text' => ['hours', 'Preparation tile', 'textarea', 'Approx. 20–30 minutes from order. Longer on busy Friday nights.'],
        'prices_note' => ['hours', 'Menu prices note', 'text', 'Prices include tax · updated monthly'],
        'hero_title1' => ['hero', 'Headline (line 1)', 'text', 'Authentic flavour.'],
        'hero_title2' => ['hero', 'Headline (line 2, red)', 'text', 'Timeless tradition.'],
        'hero_sub' => ['hero', 'Intro sentence', 'textarea', 'Traditional Chinese-inspired dishes, freshly prepared and made for sharing.'],
        'img_hero' => ['hero', 'Hero photo 1 (1600 × 1200)', 'image', ''],
        'img_hero2' => ['hero', 'Hero photo 2 (optional: 2–4 photos play as a slideshow)', 'image', ''],
        'img_hero3' => ['hero', 'Hero photo 3 (optional)', 'image', ''],
        'img_hero4' => ['hero', 'Hero photo 4 (optional)', 'image', ''],
        'story_title1' => ['story', 'Heading (line 1)', 'text', 'Tradition'],
        'story_title2' => ['story', 'Heading (line 2, red)', 'text', 'passed down'],
        'story_p1' => ['story', 'Paragraph 1', 'textarea', 'For generations, great food has brought people together. Our kitchen combines traditional Chinese flavours, time-honoured cooking techniques and a passion for freshly prepared food.'],
        'story_p2' => ['story', 'Paragraph 2', 'textarea', 'We opened our doors in 1998 with a handful of family recipes. Today the same wok, the same sauces and the same welcome bring our regulars back every week.'],
        'dish_count' => ['story', 'Dishes on the menu (number)', 'text', '50'],
        'img_story' => ['story', 'Kitchen photo (1200 × 1500)', 'image', ''],
        'special_price' => ['special', 'Price on the seal', 'text', '$49'],
        'special_title1' => ['special', 'Name (line 1)', 'text', 'Family'],
        'special_title2' => ['special', 'Name (line 2, green)', 'text', 'Feast'],
        'special_text' => ['special', 'Description', 'textarea', 'Feed the family with our delicious selection of Chinese favourites.'],
        'special_item1' => ['special', 'Item i.', 'text', '3 mains of your choice'],
        'special_item2' => ['special', 'Item ii.', 'text', 'Large special fried rice or chow mein'],
        'special_item3' => ['special', 'Item iii.', 'text', '6 vegetable spring rolls & prawn crackers'],
        'special_note' => ['special', 'Small print', 'text', 'Feeds 4 · available every day'],
        'special_tab' => ['special', 'Menu category opened by "View Special" (slug)', 'text', 'combos'],
        'img_special' => ['special', 'Special photo (1200 × 1400)', 'image', ''],
        'review1_quote' => ['reviews', 'Review 1 — quote', 'textarea', 'Absolutely delicious and always freshly prepared.'],
        'review1_name' => ['reviews', 'Review 1 — name', 'text', 'Sample Guest'],
        'review1_source' => ['reviews', 'Review 1 — source', 'text', 'Google review'],
        'review2_quote' => ['reviews', 'Review 2 — quote', 'textarea', 'One of our family\'s favourite takeaway spots.'],
        'review2_name' => ['reviews', 'Review 2 — name', 'text', 'Sample Guest'],
        'review2_source' => ['reviews', 'Review 2 — source', 'text', 'Facebook review'],
        'review3_quote' => ['reviews', 'Review 3 — quote', 'textarea', 'Great portions, great flavour and friendly service.'],
        'review3_name' => ['reviews', 'Review 3 — name', 'text', 'Sample Guest'],
        'review3_source' => ['reviews', 'Review 3 — source', 'text', 'Google review']
    ];
}

function inkwok_sections(): array
{
    return [
        'brand' => 'Restaurant details',
        'hours' => 'Hours & ordering',
        'hero' => 'Hero',
        'story' => 'Our story',
        'special' => 'Weekly special',
        'reviews' => 'Reviews'
    ];
}

/** key => [CSS variable, label, default] */
function inkwok_colours(): array
{
    return [
        'red'    => ['--c-red', 'Seal red (buttons, accents)', '#B8291E'],
        'jade'   => ['--c-jade', 'Jade (green accents)', '#2E6A4F'],
        'ink'    => ['--c-ink', 'Ink (text)', '#1E1B18'],
        'paper'  => ['--c-paper', 'Paper (page background)', '#FAF6EE'],
        'forest' => ['--c-forest', 'Forest (footer)', '#1F3D2E'],
    ];
}
