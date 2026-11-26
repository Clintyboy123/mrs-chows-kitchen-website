<?php
/**
 * Ink & Wok admin — core library.
 *
 * Storage:  content/site.php    the site's content (JSON behind a PHP exit guard)
 *           content/config.php  the admin password hash
 *           content/backups/    the last 10 versions of the content
 * Output:   index.html, menu.html  rewritten on every Publish, so visitors get static pages
 * Uploads:  assets/uploads/     photos and logos added in the admin
 */

declare(strict_types=1);

const INKWOK_ADMIN_VERSION = '1.2.0';
define('SITE_ROOT', dirname(__DIR__));
define('CONTENT_DIR', SITE_ROOT . '/content');
define('UPLOAD_DIR', SITE_ROOT . '/assets/uploads');
const UPLOAD_URL = 'assets/uploads/';
const DATA_GUARD = "<?php exit; ?>\n";
const MAX_UPLOAD_BYTES = 8 * 1024 * 1024;
const MAX_IMAGE_SIDE = 1800;
const SESSION_LIFETIME = 7200;

require __DIR__ . '/fields.php';
require __DIR__ . '/orders.php';

/* ------------------------------------------------------------------ helpers */

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    return $s !== '' ? substr($s, 0, 60) : 'item-' . bin2hex(random_bytes(3));
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/* ------------------------------------------------------------------ content storage */

function data_path(): string
{
    return CONTENT_DIR . '/site.php';
}

function load_site(): array
{
    $raw = @file_get_contents(data_path());
    if ($raw === false) {
        return default_site();
    }
    if (strpos($raw, DATA_GUARD) === 0) {
        $raw = substr($raw, strlen(DATA_GUARD));
    }
    $data = json_decode($raw, true);
    $site = is_array($data) ? normalise_site($data) : default_site();
    apply_timezone($site);
    return $site;
}

function default_site(): array
{
    $f = [];
    foreach (inkwok_fields() as $k => $def) {
        $f[$k] = $def[3];
    }
    return normalise_site(['fields' => $f, 'images' => [], 'colours' => [], 'features' => [], 'menu' => []]);
}

/** Fill in missing keys so templates never hit undefined indexes. */
function normalise_site(array $s): array
{
    $s['fields'] = is_array($s['fields'] ?? null) ? $s['fields'] : [];
    foreach (inkwok_fields() as $k => $def) {
        if ($def[2] === 'image') {
            continue;
        }
        if (!isset($s['fields'][$k])) {
            $s['fields'][$k] = $def[3];
        }
    }
    $s['images'] = array_merge(['img_hero' => '', 'img_hero2' => '', 'img_hero3' => '', 'img_hero4' => '', 'img_story' => '', 'img_special' => '', 'logo' => '', 'logo_footer' => ''], is_array($s['images'] ?? null) ? $s['images'] : []);
    $s['colours'] = array_merge(array_fill_keys(array_keys(inkwok_colours()), ''), is_array($s['colours'] ?? null) ? $s['colours'] : []);
    $s['features'] = array_merge(['cursor' => true, 'music' => true, 'art' => true, 'paper' => true], is_array($s['features'] ?? null) ? $s['features'] : []);
    $menu = [];
    foreach (is_array($s['menu'] ?? null) ? $s['menu'] : [] as $c) {
        if (!is_array($c)) {
            continue;
        }
        $dishes = [];
        foreach (is_array($c['dishes'] ?? null) ? $c['dishes'] : [] as $d) {
            if (!is_array($d) || trim((string) ($d['name'] ?? '')) === '') {
                continue;
            }
            $dishes[] = array_merge(['id' => slugify((string) $d['name']), 'name' => '', 'desc' => '', 'long' => '', 'price' => '', 'tag' => '', 'tone' => 'tone-gold', 'img' => '', 'featured' => 0, 'note' => ''], $d);
        }
        $menu[] = ['id' => (string) ($c['id'] ?? slugify((string) ($c['label'] ?? ''))), 'label' => (string) ($c['label'] ?? ''), 'dishes' => $dishes];
    }
    $s['menu'] = $menu;
    $s['ordering'] = ordering($s);
    return $s;
}

function save_site(array $site): void
{
    if (!is_dir(CONTENT_DIR . '/backups')) {
        @mkdir(CONTENT_DIR . '/backups', 0755, true);
    }
    if (is_file(data_path())) {
        @copy(data_path(), CONTENT_DIR . '/backups/site-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.php');
        $old = glob(CONTENT_DIR . '/backups/site-*.php') ?: [];
        sort($old);
        while (count($old) > 10) {
            @unlink(array_shift($old));
        }
    }
    $site['updated'] = date('c');
    $json = json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    write_atomic(data_path(), DATA_GUARD . $json);
}

function list_backups(): array
{
    $files = glob(CONTENT_DIR . '/backups/site-*.php') ?: [];
    rsort($files);
    return $files;
}

function write_atomic(string $path, string $contents): void
{
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Could not write ' . basename($path) . '. Check that the folder is writable (see documentation: "Admin → Permissions").');
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Could not replace ' . basename($path) . '. Check file permissions.');
    }
}

/* ------------------------------------------------------------------ rendering */

/** Pages the admin publishes: page key => [template, output file]. */
const PAGES = ['home' => ['template.php', 'index.html'], 'menu' => ['template-menu.php', 'menu.html'], 'checkout' => ['template-checkout.php', 'checkout.html']];

function render_site(array $site, bool $preview = false, string $page = 'home'): string
{
    $s = normalise_site($site);
    $tpl = PAGES[$page][0] ?? PAGES['home'][0];
    ob_start();
    include __DIR__ . '/' . $tpl;
    $html = (string) ob_get_clean();
    if ($preview) {
        $html = preg_replace('/<head>/', "<head>\n<base href=\"../\">", $html, 1) ?? $html;
    }
    return $html;
}

function publish(array $site): void
{
    foreach (PAGES as $page => [, $file]) {
        write_atomic(SITE_ROOT . '/' . $file, render_site($site, false, $page));
    }
}

function f(array $s, string $key): string
{
    return (string) ($s['fields'][$key] ?? '');
}

/** WhatsApp number as digits only ('' hides every WhatsApp button). */
function wa_number(array $s): string
{
    return (string) preg_replace('/\D+/', '', f($s, 'whatsapp'));
}

/** wa.me chat link, optionally with a ready-typed message. */
function wa_url(array $s, string $text = ''): string
{
    return 'https://wa.me/' . wa_number($s) . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function map_embed(array $s): string
{
    return 'https://maps.google.com/maps?q=' . rawurlencode(f($s, 'map_query')) . '&z=16&output=embed';
}

function safe_url(string $u): string
{
    $u = trim($u);
    if ($u === '' || $u[0] === '#' || $u[0] === '/') {
        return $u;
    }
    return preg_match('#^(https?:|mailto:|tel:|https://wa\.me/)#i', $u) ? $u : '#';
}

function price_text(array $s, $raw): string
{
    $raw = trim((string) $raw);
    if (is_numeric($raw)) {
        return f($s, 'currency') . number_format((float) $raw, 2);
    }
    // Placeholders like "[00]" are hidden until a real price is entered.
    return preg_match('/^\[.*\]$/', $raw) ? '' : $raw;
}

/** "Dishes on our menu": the admin number if set, else counted from the menu (rounded down to 10s). */
function dish_count_text(array $s): string
{
    $n = trim(f($s, 'dish_count'));
    if (is_numeric($n)) {
        return $n;
    }
    $count = 0;
    foreach ($s['menu'] ?? [] as $c) {
        $count += count($c['dishes'] ?? []);
    }
    return (string) ($count >= 10 ? intdiv($count, 10) * 10 : $count);
}

function price_num($raw): string
{
    $raw = trim((string) $raw);
    return is_numeric($raw) ? (string) (float) $raw : '';
}

/* ------------------------------------------------------------------ responsive images */

/** WebP widths made next to each photo (never larger than the original). */
const IMG_WIDTHS = [160, 480, 960, 1600];

/** Resize $from to $width and save it as WebP at $to. */
function make_webp(string $from, string $to, int $width): bool
{
    $info = @getimagesize($from);
    if (!$info || !function_exists('imagewebp')) {
        return false;
    }
    $src = match ($info['mime'] ?? '') {
        'image/jpeg' => @imagecreatefromjpeg($from),
        'image/png' => @imagecreatefrompng($from),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($from) : false,
        default => false,
    };
    if (!$src) {
        return false;
    }
    $height = (int) round($info[1] * $width / $info[0]);
    $dst = imagecreatetruecolor($width, $height);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
    $ok = imagewebp($dst, $to, 74);
    imagedestroy($src);
    imagedestroy($dst);
    return $ok;
}

/** Size of a local image plus its WebP set (made on demand, refreshed if the original changes). */
function image_variants(string $src): array
{
    if ($src === '' || preg_match('#^([a-z]+:)?//#i', $src) || strpos($src, '..') !== false) {
        return [];
    }
    $file = SITE_ROOT . '/' . $src;
    $size = is_file($file) ? @getimagesize($file) : false;
    if (!$size) {
        return [];
    }
    $out = ['w' => (int) $size[0], 'h' => (int) $size[1], 'set' => []];
    $base = preg_replace('/\.(jpe?g|png|webp)$/i', '', $src);
    foreach (IMG_WIDTHS as $vw) {
        if ($vw > $out['w']) {
            continue;
        }
        $rel = $base . '-' . $vw . '.webp';
        $abs = SITE_ROOT . '/' . $rel;
        if ((!is_file($abs) || filemtime($abs) < filemtime($file)) && !make_webp($file, $abs, $vw)) {
            continue;
        }
        $out['set'][] = $rel . ' ' . $vw . 'w';
    }
    return $out;
}

/**
 * <img> with width/height (no layout jumps) and, where WebP copies exist, a <picture>
 * that lets the browser download only the size it needs.
 */
function img_tag(string $src, string $alt, string $sizes, string $class = 'ph photo', bool $lazy = true): string
{
    $v = image_variants($src);
    $a = ' class="' . e($class) . '" src="' . e($src) . '" alt="' . e($alt) . '"';
    if (!empty($v['w'])) {
        $a .= ' width="' . $v['w'] . '" height="' . $v['h'] . '"';
    }
    $a .= $lazy ? ' loading="lazy" decoding="async"' : ' decoding="async" fetchpriority="high"';
    if (empty($v['set'])) {
        return '<img' . $a . '>';
    }
    return '<picture><source type="image/webp" srcset="' . e(implode(', ', $v['set'])) . '" sizes="' . e($sizes) . '"><img' . $a . '></picture>';
}

const IMG_SIZES = [
    'img_hero' => '(max-width: 900px) 100vw, 56vw',
    'img_hero2' => '(max-width: 900px) 100vw, 56vw',
    'img_hero3' => '(max-width: 900px) 100vw, 56vw',
    'img_hero4' => '(max-width: 900px) 100vw, 56vw',
    'img_story' => '(max-width: 900px) 100vw, 46vw',
    'img_special' => '(max-width: 900px) 100vw, 50vw',
];

function placeholder(string $tone, string $label): string
{
    return '<div class="ph ' . e($tone) . '" role="img" aria-label="Photo placeholder: ' . e($label) . '"><span class="ph-label"><b>Your photo</b>' . e($label) . '</span></div>';
}

function section_image(array $s, string $key, string $tone, string $label, string $alt, bool $lazy = true): string
{
    $src = (string) ($s['images'][$key] ?? '');
    if ($src === '') {
        return placeholder($tone, $label);
    }
    return img_tag($src, $alt, IMG_SIZES[$key] ?? '100vw', 'ph photo', $lazy);
}

/** The hero photos that are set (photo 1 always counts, so an empty site shows one placeholder). */
function hero_slides(array $s): array
{
    $keys = ['img_hero'];
    foreach (['img_hero2', 'img_hero3', 'img_hero4'] as $k) {
        if (!empty($s['images'][$k])) {
            $keys[] = $k;
        }
    }
    return $keys;
}

/** Hero: one photo, or a gentle crossfade slideshow with markers and a pause button when 2–4 are set. */
function hero_slideshow(array $s): string
{
    $keys = hero_slides($s);
    $n = count($keys);
    $alt = 'Signature dishes at ' . f($s, 'brand');
    $out = '<div class="hero-slides"' . ($n > 1 ? ' data-hero-slides role="region" aria-roledescription="carousel" aria-label="Photos from our kitchen"' : '') . '>';
    foreach ($keys as $i => $k) {
        $out .= '<div class="hero-img' . ($i === 0 ? ' is-on' : '') . '"' . ($n > 1 ? ' role="group" aria-roledescription="slide" aria-label="' . ($i + 1) . ' of ' . $n . '"' . ($i ? ' aria-hidden="true"' : '') : '') . '>'
            . section_image($s, $k, 'tone-gold', 'Hero photo · 1600 × 1200', $alt, $i > 0) . '</div>';
    }
    $out .= '</div>';
    if ($n > 1) {
        $out .= '<div class="hero-dots">';
        for ($i = 0; $i < $n; $i++) {
            $out .= '<button type="button" class="hero-dot" data-slide="' . $i . '" aria-label="Show photo ' . ($i + 1) . ' of ' . $n . '"' . ($i === 0 ? ' aria-current="true"' : '') . '></button>';
        }
        $out .= '<button type="button" class="hero-pause" aria-label="Pause the slideshow" aria-pressed="false"><svg class="i-pause" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"></rect><rect x="14" y="5" width="4" height="14" rx="1"></rect></svg><svg class="i-play" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l11-6.5a1 1 0 0 0 0-1.72l-11-6.5A1 1 0 0 0 8 5.5z"></path></svg></button></div>';
    }
    return $out;
}

function brand_mark(array $s): string
{
    if (!empty($s['images']['logo'])) {
        return '<img class="logo-img" src="' . e($s['images']['logo']) . '" alt="">';
    }
    return '<span class="seal">' . e(f($s, 'seal_char')) . '</span>';
}

function footer_logo(array $s): string
{
    if (!empty($s['images']['logo_footer'])) {
        return '<div class="logo-card" style="width: 220px;"><img src="' . e($s['images']['logo_footer']) . '" alt="' . e(f($s, 'brand')) . '"></div>';
    }
    return '<span class="seal" style="width: 52px; height: 52px; font-size: 26px;">' . e(f($s, 'seal_char')) . '</span><div class="f-brand">' . e(f($s, 'brand')) . '</div>';
}

function featured_dishes(array $s): array
{
    $out = [];
    foreach ($s['menu'] as $c) {
        foreach ($c['dishes'] as $d) {
            if ((int) $d['featured'] > 0) {
                $d['_cat'] = $c['label'];
                $d['_catid'] = $c['id'];
                $out[] = $d;
            }
        }
    }
    usort($out, static fn ($a, $b) => (int) $a['featured'] <=> (int) $b['featured']);
    return array_slice($out, 0, 10);
}

const STEAM_SVG = '<svg class="steam" viewBox="0 0 120 120" aria-hidden="true"><path d="M40 110 c-12 -18 12 -30 0 -52"></path><path d="M60 104 c-12 -18 12 -30 0 -52"></path><path d="M80 110 c-12 -18 12 -30 0 -52"></path></svg>';
const STAR_SVG = '<svg width="14" height="14" viewBox="-10 -10 20 20" aria-hidden="true"><g fill="#B8291E"><circle cx="0" cy="-5" r="4"></circle><circle cx="4.8" cy="-1.6" r="4"></circle><circle cx="2.9" cy="4.1" r="4"></circle><circle cx="-2.9" cy="4.1" r="4"></circle><circle cx="-4.8" cy="-1.6" r="4"></circle></g><circle r="1.6" fill="#F2C14E"></circle></svg>';
const TAG_CLASSES = ['Spicy' => 'tag-spicy', 'Vegetarian' => 'tag-veg'];

function render_gallery(array $s): string
{
    $h = '';
    foreach (featured_dishes($s) as $i => $d) {
        $img = $d['img'] !== ''
            ? img_tag($d['img'], $d['name'], $i < 2 ? '(max-width: 640px) 100vw, (max-width: 1100px) 50vw, 600px' : '(max-width: 640px) 100vw, (max-width: 1100px) 50vw, 300px')
            : placeholder($d['tone'], $d['name']);
        $caption = trim($d['_cat'] . ($d['note'] !== '' ? ' · ' . $d['note'] : ''));
        $desc = $d['long'] !== '' ? $d['long'] : $d['desc'];
        $h .= '<article class="work"><div class="frame"><div class="work-img"><div class="ph-zoom">' . $img . '</div>' . STEAM_SVG . '</div>'
            . '<span class="seal work-seal" aria-hidden="true">選</span></div><div class="work-cap">'
            . '<div class="caption" style="margin-top: 0px;"><span class="label" style="color: var(--c-red);">No. ' . sprintf('%02d', $i + 1) . '</span><span class="label">' . e($caption) . '</span></div>'
            . '<div class="work-line"></div><h3 class="work-name">' . e($d['name']) . '</h3><p class="work-desc">' . e($desc) . '</p>'
            . '<div class="work-foot"><span class="price">' . e(price_text($s, $d['price'])) . '</span>'
            . '<a class="btn btn-line btn-sm" href="menu.html#' . e($d['_catid']) . '" aria-label="See ' . e($d['name']) . ' on the menu">View on menu</a></div></div></article>';
    }
    return $h;
}

function render_menu_tabs(array $s): string
{
    $h = '';
    foreach (array_values(array_filter($s['menu'], static fn ($c) => count($c['dishes']) > 0)) as $n => $c) {
        $on = $n === 0;
        $h .= '<button class="tab' . ($on ? ' is-active' : '') . '" type="button" role="tab" id="tab-' . e($c['id']) . '" aria-controls="panel-' . e($c['id']) . '" aria-selected="' . ($on ? 'true' : 'false') . '" tabindex="' . ($on ? '0' : '-1') . '" data-tab="' . e($c['id']) . '"><span class="mono">' . sprintf('%02d', $n + 1) . '</span>' . e($c['label']) . '</button>';
    }
    return $h;
}

function render_menu_panels(array $s): string
{
    $h = '<div>';
    foreach (array_values(array_filter($s['menu'], static fn ($c) => count($c['dishes']) > 0)) as $n => $c) {
        $rows = '';
        foreach ($c['dishes'] as $d) {
            $thumb = $d['img'] !== ''
                ? img_tag($d['img'], '', '84px')
                : '<div class="ph ' . e($d['tone']) . '" aria-hidden="true"></div>';
            $tag = $d['tag'] !== '' ? '<span class="tag ' . e(TAG_CLASSES[$d['tag']] ?? 'tag-share') . '">' . e($d['tag']) . '</span>' : '';
            $rows .= '<div class="dish"><div class="dish-thumb">' . $thumb . '</div><div><div class="dish-row"><h4 class="dish-name">' . e($d['name']) . '</h4><span class="leader"></span><span class="price">' . e(price_text($s, $d['price'])) . '</span></div><p class="dish-desc">' . e($d['desc']) . '</p>' . $tag . '</div>'
                . '<button class="add-round" type="button" data-add="' . e($d['id']) . '" data-name="' . e($d['name']) . '" data-price="' . e(price_num($d['price'])) . '" aria-label="Add ' . e($d['name']) . ' to order">+</button></div>';
        }
        $count = count($c['dishes']);
        $h .= '<div role="tabpanel" id="panel-' . e($c['id']) . '" aria-labelledby="tab-' . e($c['id']) . '"' . ($n ? ' hidden' : '') . '><div class="panel-head"><h3 class="panel-title">' . e($c['label']) . '</h3><span class="label">' . $count . ($count === 1 ? ' dish' : ' dishes') . '</span></div>' . $rows . '</div>';
    }
    return $h . '</div>';
}

function render_reviews(array $s): string
{
    $h = '';
    foreach ([1, 2, 3] as $k) {
        $q = f($s, "review{$k}_quote");
        if (trim($q) === '') {
            continue;
        }
        $h .= '<figure class="note"><span class="q-open brush" aria-hidden="true">「</span><blockquote class="n-quote" style="margin: 0px;">' . e($q) . '</blockquote><span class="q-close brush" aria-hidden="true">」</span>'
            . '<figcaption class="n-foot"><span class="label" style="color: var(--c-ink);">— ' . e(f($s, "review{$k}_name")) . '<span style="display: block; color: var(--c-muted); margin-top: 6px;">' . e(f($s, "review{$k}_source")) . '</span></span>'
            . '<span class="rating" aria-label="Rated 5 out of 5">' . str_repeat(STAR_SVG, 5) . '</span></figcaption></figure>';
    }
    return $h;
}

function colour_css(array $s): string
{
    $css = '';
    foreach (inkwok_colours() as $key => $c) {
        $v = (string) ($s['colours'][$key] ?? '');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
            $css .= $c[0] . ':' . $v . ';';
        }
    }
    return $css !== '' ? '<style>:root{' . $css . '}</style>' : '';
}

function body_classes(array $s): string
{
    $c = [];
    if (empty($s['features']['art'])) {
        $c[] = 'no-art';
    }
    if (empty($s['features']['paper'])) {
        $c[] = 'no-paper';
    }
    return implode(' ', $c);
}

/* ------------------------------------------------------------------ auth */

function config_path(): string
{
    return CONTENT_DIR . '/config.php';
}

function admin_config(): ?array
{
    if (!is_file(config_path())) {
        return null;
    }
    $c = include config_path();
    return is_array($c) && !empty($c['password_hash']) ? $c : null;
}

function save_password(string $password): void
{
    $conf = "<?php\n// Ink & Wok admin password (hashed). Delete this file to set a new password from /admin.\nreturn " . var_export(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'updated' => date('c')], true) . ";\n";
    write_atomic(config_path(), $conf);
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('inkwok_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
    if (!empty($_SESSION['auth']) && (time() - (int) ($_SESSION['seen'] ?? 0)) > SESSION_LIFETIME) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['seen'] = time();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function is_logged_in(): bool
{
    return !empty($_SESSION['auth']);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function check_csrf(): void
{
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('This form expired. Go back, reload the page and try again.');
    }
}

/** Simple brute-force protection: 5 failed logins per IP lock it out for 15 minutes. */
function login_locked(string $ip): int
{
    $f = CONTENT_DIR . '/login-attempts.php';
    $data = is_file($f) ? (json_decode(substr((string) file_get_contents($f), strlen(DATA_GUARD)), true) ?: []) : [];
    $rec = $data[hash('sha256', $ip)] ?? null;
    if ($rec && $rec['n'] >= 5 && time() - $rec['t'] < 900) {
        return 900 - (time() - $rec['t']);
    }
    return 0;
}

function record_login(string $ip, bool $ok): void
{
    $f = CONTENT_DIR . '/login-attempts.php';
    $data = is_file($f) ? (json_decode(substr((string) file_get_contents($f), strlen(DATA_GUARD)), true) ?: []) : [];
    $key = hash('sha256', $ip);
    if ($ok) {
        unset($data[$key]);
    } else {
        $rec = $data[$key] ?? ['n' => 0, 't' => time()];
        if (time() - $rec['t'] >= 900) {
            $rec = ['n' => 0, 't' => time()];
        }
        $rec['n']++;
        $rec['t'] = time();
        $data[$key] = $rec;
    }
    @file_put_contents($f, DATA_GUARD . json_encode($data), LOCK_EX);
}

/* ------------------------------------------------------------------ uploads */

/**
 * Validate and store one uploaded image. Returns its site-relative path, '' when no file
 * was sent, or throws with a message the owner can act on.
 */
function store_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('A photo did not upload (error ' . (int) $file['error'] . '). It may be larger than your host allows.');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('"' . $file['name'] . '" is over 8 MB. Please use a smaller photo.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext || !@getimagesize($file['tmp_name'])) {
        throw new RuntimeException('"' . $file['name'] . '" is not a JPG, PNG or WebP image.');
    }
    if (!is_dir(UPLOAD_DIR) && !@mkdir(UPLOAD_DIR, 0755, true)) {
        throw new RuntimeException('Could not create assets/uploads. Check folder permissions.');
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = UPLOAD_DIR . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save the photo. Check that assets/uploads is writable.');
    }
    shrink_image($dest, $mime);
    return UPLOAD_URL . $name;
}

/** Downscale very large photos when the host has GD; otherwise keep the original. */
function shrink_image(string $path, string $mime): void
{
    if (!function_exists('imagecreatetruecolor')) {
        return;
    }
    [$w, $h] = @getimagesize($path) ?: [0, 0];
    if ($w <= MAX_IMAGE_SIDE && $h <= MAX_IMAGE_SIDE) {
        return;
    }
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
    if (!$src) {
        return;
    }
    $r = MAX_IMAGE_SIDE / max($w, $h);
    $nw = (int) round($w * $r);
    $nh = (int) round($h * $r);
    $dst = imagecreatetruecolor($nw, $nh);
    if ($mime !== 'image/jpeg') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    match ($mime) {
        'image/jpeg' => imagejpeg($dst, $path, 82),
        'image/png' => imagepng($dst, $path, 8),
        'image/webp' => imagewebp($dst, $path, 82),
    };
    imagedestroy($src);
    imagedestroy($dst);
}

/** Map PHP's nested $_FILES structure for one input name to [key => file]. */
function files_for(string $name): array
{
    $out = [];
    if (empty($_FILES[$name]) || !is_array($_FILES[$name]['name'])) {
        return $out;
    }
    foreach ($_FILES[$name]['name'] as $key => $n) {
        $out[$key] = [
            'name' => $n,
            'type' => $_FILES[$name]['type'][$key],
            'tmp_name' => $_FILES[$name]['tmp_name'][$key],
            'error' => $_FILES[$name]['error'][$key],
            'size' => $_FILES[$name]['size'][$key],
        ];
    }
    return $out;
}

/* ------------------------------------------------------------------ form → site */

function site_from_post(array $old): array
{
    $site = $old;
    $errors = [];
    $clean = static fn ($v, int $max = 2000): string => mb_substr(trim(str_replace("\0", '', (string) $v)), 0, $max);

    foreach (inkwok_fields() as $k => $def) {
        if ($def[2] === 'image' || !array_key_exists($k, $_POST['f'] ?? [])) {
            continue;
        }
        $v = $clean($_POST['f'][$k], $def[2] === 'textarea' ? 2000 : 300);
        $site['fields'][$k] = $def[2] === 'url' ? safe_url($v) : $v;
    }

    // Section photos and logos.
    $uploads = files_for('img');
    foreach (array_keys($site['images']) as $k) {
        if (!empty($_POST['img_remove'][$k])) {
            $site['images'][$k] = '';
        }
        if (isset($uploads[$k])) {
            try {
                $p = store_upload($uploads[$k]);
                if ($p !== '') {
                    $site['images'][$k] = $p;
                }
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
        }
    }

    foreach (inkwok_colours() as $k => $c) {
        $v = (string) ($_POST['colour'][$k] ?? '');
        $site['colours'][$k] = preg_match('/^#[0-9a-fA-F]{6}$/', $v) && strtolower($v) !== strtolower($c[2]) ? $v : '';
    }
    if (isset($_POST['features_present'])) {
        foreach (array_keys($site['features']) as $k) {
            $site['features'][$k] = !empty($_POST['feature'][$k]);
        }
    }

    // Online ordering settings.
    if (isset($_POST['ordering_present'])) {
        $op = (array) ($_POST['ordering'] ?? []);
        $cur = ordering($site);
        $num = static fn ($v) => is_numeric(str_replace(',', '.', trim((string) $v))) ? (string) round((float) str_replace(',', '.', trim((string) $v)), 2) : '';
        $email = $clean($op['email'] ?? '', 120);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'The order email address is not valid, so it was not saved.';
            $email = $cur['email'];
        }
        $secret = $clean($op['turnstile_secret'] ?? '', 200);
        $site['ordering'] = [
            'enabled' => !empty($op['enabled']),
            'email' => $email,
            'collection' => !empty($op['collection']),
            'delivery' => !empty($op['delivery']),
            'delivery_fee' => $num($op['delivery_fee'] ?? ''),
            'min_delivery' => $num($op['min_delivery'] ?? ''),
            'note' => $clean($op['note'] ?? '', 300) ?: ordering_defaults()['note'],
            'turnstile_site' => preg_replace('/[^A-Za-z0-9_-]/', '', $clean($op['turnstile_site'] ?? '', 200)) ?? '',
            // a blank secret field keeps the saved one; "remove" clears it
            'timezone' => in_array((string) ($op['timezone'] ?? ''), timezone_identifiers_list(), true) ? (string) $op['timezone'] : '',
            'turnstile_secret' => !empty($op['turnstile_remove']) ? '' : ($secret !== '' ? (preg_replace('/[^A-Za-z0-9_.-]/', '', $secret) ?? '') : $cur['turnstile_secret']),
        ];
    }

    // Menu: categories and dishes arrive in on-screen order.
    if (isset($_POST['menu_present'])) {
        $dishFiles = files_for('dish_img');
        $menu = [];
        $usedCats = [];
        $usedDishes = [];
        foreach ((array) ($_POST['cat'] ?? []) as $cuid => $c) {
            if (!empty($c['delete'])) {
                continue;
            }
            $label = $clean($c['label'] ?? '', 60);
            if ($label === '') {
                continue;
            }
            $cid = slugify($clean($c['id'] ?? '', 60) ?: $label);
            while (isset($usedCats[$cid])) {
                $cid .= '-2';
            }
            $usedCats[$cid] = true;
            $dishes = [];
            foreach ((array) ($c['dish'] ?? []) as $duid => $d) {
                if (!empty($d['delete'])) {
                    continue;
                }
                $name = $clean($d['name'] ?? '', 120);
                if ($name === '') {
                    continue;
                }
                $id = slugify($clean($d['id'] ?? '', 80) ?: $name);
                while (isset($usedDishes[$id])) {
                    $id .= '-2';
                }
                $usedDishes[$id] = true;
                $img = !empty($d['remove_img']) ? '' : $clean($d['img'] ?? '', 300);
                if ($img !== '' && !preg_match('#^assets/[A-Za-z0-9/_.-]+$#', $img)) {
                    $img = '';
                }
                if (isset($dishFiles[$duid])) {
                    try {
                        $p = store_upload($dishFiles[$duid]);
                        if ($p !== '') {
                            $img = $p;
                        }
                    } catch (RuntimeException $ex) {
                        $errors[] = $name . ': ' . $ex->getMessage();
                    }
                }
                $tag = (string) ($d['tag'] ?? '');
                $tag = in_array($tag, ['', 'Spicy', 'Vegetarian', 'For sharing'], true) ? $tag : '';
                $tone = (string) ($d['tone'] ?? '');
                $tone = in_array($tone, ['tone-gold', 'tone-red', 'tone-jade', 'tone-smoke'], true) ? $tone : 'tone-gold';
                $dishes[] = [
                    'id' => $id,
                    'name' => $name,
                    'desc' => $clean($d['desc'] ?? '', 300),
                    'long' => $clean($d['long'] ?? '', 600),
                    'price' => $clean($d['price'] ?? '', 20),
                    'tag' => $tag,
                    'tone' => $tone,
                    'img' => $img,
                    'featured' => max(0, min(99, (int) ($d['featured'] ?? 0))),
                    'note' => $clean($d['note'] ?? '', 60),
                ];
            }
            $menu[] = ['id' => $cid, 'label' => $label, 'dishes' => $dishes];
        }
        $site['menu'] = $menu;
    }
    return [normalise_site($site), $errors];
}
