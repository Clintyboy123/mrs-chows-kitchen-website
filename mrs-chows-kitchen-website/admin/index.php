<?php
/**
 * Ink & Wok admin — edit the restaurant's details, menu and photos, then publish.
 * Open https://your-site/admin/ in a browser.
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';

// Never print PHP notices into pages (they would also break redirects); hosts log them instead.
ini_set('display_errors', '0');

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    exit('The admin needs PHP 8.0 or newer. Your host runs PHP ' . e(PHP_VERSION) . '; most hosts let you switch the version in their control panel.');
}

start_session();
$action = (string) ($_GET['action'] ?? '');
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

function go(string $msg, string $type = 'ok', string $tab = ''): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: ./' . ($tab !== '' ? '#' . rawurlencode($tab) : ''));
    exit;
}

/* ------------------------------------------------------------ first run: create the password */
if (admin_config() === null) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password2'] ?? '');
        if (strlen($p1) < 10) {
            $err = 'Use at least 10 characters.';
        } elseif ($p1 !== $p2) {
            $err = 'The two passwords do not match.';
        } else {
            try {
                if (!is_dir(CONTENT_DIR)) {
                    @mkdir(CONTENT_DIR, 0755, true);
                }
                save_password($p1);
                session_regenerate_id(true);
                $_SESSION['auth'] = true;
                go('Password saved. Welcome — edit anything below, then press Save & publish.');
            } catch (RuntimeException $ex) {
                $err = $ex->getMessage();
            }
        }
    }
    shell_page('Set up your admin', function () use ($err) { ?>
        <form method="post" class="card narrow">
            <?= csrf_field() ?>
            <h1>Set up your admin</h1>
            <p>Choose the password you will use to edit this website. Do this straight after uploading the site, so nobody else can claim the admin first.</p>
            <?php if ($err) : ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
            <label>Password <input type="password" name="password" autocomplete="new-password" minlength="10" required></label>
            <label>Repeat password <input type="password" name="password2" autocomplete="new-password" minlength="10" required></label>
            <button class="btn primary" type="submit">Create password</button>
        </form>
    <?php });
    exit;
}

/* ------------------------------------------------------------ login */
if (!is_logged_in()) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $wait = login_locked($ip);
        if ($wait > 0) {
            $err = 'Too many attempts. Try again in ' . (int) ceil($wait / 60) . ' minutes.';
        } elseif (password_verify((string) ($_POST['password'] ?? ''), admin_config()['password_hash'])) {
            record_login($ip, true);
            session_regenerate_id(true);
            $_SESSION['auth'] = true;
            header('Location: ./');
            exit;
        } else {
            record_login($ip, false);
            usleep(400000);
            $err = 'That password is not right.';
        }
    }
    shell_page('Log in', function () use ($err) { ?>
        <form method="post" class="card narrow">
            <?= csrf_field() ?>
            <h1>Log in</h1>
            <?php if ($err) : ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
            <label>Password <input type="password" name="password" autocomplete="current-password" required autofocus></label>
            <button class="btn primary" type="submit">Log in</button>
            <p class="hint">Forgot it? Delete <code>content/config.php</code> with your host's file manager, then open this page again to choose a new one.</p>
        </form>
    <?php });
    exit;
}

/* ------------------------------------------------------------ actions */
if ($action === 'logout' && hash_equals($_SESSION['csrf'], (string) ($_GET['csrf'] ?? ''))) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

if ($action === 'orders-check') {
    header('Content-Type: application/json; charset=utf-8');
    $all = list_orders(100);
    echo json_encode(['latest' => $all[0]['created'] ?? '', 'new' => count(array_filter($all, static fn ($o) => $o['status'] === 'new'))]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if ($action === 'order-status') {
        $num = (string) ($_POST['number'] ?? '');
        $status = (string) ($_POST['status'] ?? '');
        $order = load_order($num);
        if (!$order || !isset(ORDER_STATUSES[$status])) {
            go('That order was not found.', 'err', 'orders');
        }
        $order['status'] = $status;
        $order['updated'] = date('c');
        save_order($order);
        go('Order ' . $num . ' marked ' . strtolower(ORDER_STATUSES[$status]) . '.', 'ok', 'orders');
    }
    if ($action === 'preview') {
        $saveFiles = $_FILES;
        $_FILES = [];                       // previews never store uploads
        [$site] = site_from_post(load_site());
        $_FILES = $saveFiles;
        header('Content-Type: text/html; charset=utf-8');
        $page = (string) ($_GET['page'] ?? 'home');
        echo render_site($site, true, isset(PAGES[$page]) ? $page : 'home');
        exit;
    }
    if ($action === 'save') {
        [$site, $errors] = site_from_post(load_site());
        try {
            save_site($site);
            publish($site);
        } catch (RuntimeException $ex) {
            go($ex->getMessage(), 'err', (string) ($_POST['tab'] ?? ''));
        }
        $msg = 'Saved and published. Your site is up to date.';
        if ($errors) {
            go($msg . ' Please check: ' . implode(' ', $errors), 'warn', (string) ($_POST['tab'] ?? ''));
        }
        go($msg, 'ok', (string) ($_POST['tab'] ?? ''));
    }
    if ($action === 'password') {
        if (!password_verify((string) ($_POST['current'] ?? ''), admin_config()['password_hash'])) {
            go('Your current password is not right.', 'err', 'account');
        }
        $p1 = (string) ($_POST['password'] ?? '');
        if (strlen($p1) < 10 || $p1 !== (string) ($_POST['password2'] ?? '')) {
            go('New password: at least 10 characters, typed the same twice.', 'err', 'account');
        }
        save_password($p1);
        session_regenerate_id(true);
        go('Password changed.', 'ok', 'account');
    }
    if ($action === 'restore') {
        $file = basename((string) ($_POST['backup'] ?? ''));
        $path = CONTENT_DIR . '/backups/' . $file;
        if (!preg_match('/^site-[0-9]{8}-[0-9]{6}-[0-9a-f]{4}\.php$/', $file) || !is_file($path)) {
            go('That backup was not found.', 'err', 'account');
        }
        $raw = (string) file_get_contents($path);
        $data = json_decode(substr($raw, strlen(DATA_GUARD)), true);
        if (!is_array($data)) {
            go('That backup could not be read.', 'err', 'account');
        }
        $site = normalise_site($data);
        save_site($site);
        publish($site);
        go('Restored the version from ' . date('j M Y, H:i', (int) filemtime($path)) . ' and published it.', 'ok', 'account');
    }
    if ($action === 'republish') {
        publish(load_site());
        go('Published.', 'ok', 'account');
    }
}

/* ------------------------------------------------------------ editor */
$site = load_site();
$sections = inkwok_sections();
purge_old_orders();
$orders = list_orders(300);
$ord = ordering($site);
[$ordOpen, $ordReason] = ordering_status($site);
$fields = inkwok_fields();
$tabs = ['orders' => 'Orders', 'brand' => 'Details', 'hours' => 'Hours & ordering', 'hero' => 'Home', 'story' => 'Our story', 'menu' => 'Menu', 'special' => 'Weekly special', 'reviews' => 'Reviews', 'look' => 'Logo & look', 'account' => 'Account'];
$writable = true;
foreach (PAGES as [, $out]) {
    $writable = $writable && (is_writable(SITE_ROOT . '/' . $out) || (!file_exists(SITE_ROOT . '/' . $out) && is_writable(SITE_ROOT)));
}

function field_input(string $k, array $def, array $site): void
{
    [$sec, $label, $kind] = $def;
    $v = $site['fields'][$k] ?? '';
    if ($kind === 'image') {
        image_input($k, $label, $site['images'][$k] ?? '');
        return;
    }
    echo '<label class="field"><span>' . e($label) . '</span>';
    if ($kind === 'textarea') {
        echo '<textarea name="f[' . e($k) . ']" rows="3">' . e($v) . '</textarea>';
    } else {
        echo '<input type="' . ($kind === 'url' ? 'text' : 'text') . '" name="f[' . e($k) . ']" value="' . e($v) . '"' . ($kind === 'url' ? ' inputmode="url" placeholder="https://…"' : '') . '>';
    }
    echo '</label>';
}

function image_input(string $k, string $label, string $current): void
{
    echo '<div class="field image-field"><span>' . e($label) . '</span><div class="image-row">';
    echo $current !== '' ? '<img src="../' . e($current) . '" alt="">' : '<div class="noimg">No photo yet</div>';
    echo '<div><input type="file" name="img[' . e($k) . ']" accept="image/jpeg,image/png,image/webp">';
    if ($current !== '') {
        echo '<label class="check"><input type="checkbox" name="img_remove[' . e($k) . ']" value="1"> Remove photo</label>';
    }
    echo '</div></div></div>';
}

function dish_row(string $cuid, string $duid, array $d): void
{
    $n = 'cat[' . $cuid . '][dish][' . $duid . ']';
    ?>
    <div class="dish-row" data-dish>
        <div class="thumb"><?= $d['img'] !== '' ? '<img src="../' . e($d['img']) . '" alt="">' : '<div class="ph-mini ' . e($d['tone']) . '"></div>' ?></div>
        <div class="dish-main">
            <div class="grid-3">
                <label class="field"><span>Dish name</span><input name="<?= $n ?>[name]" value="<?= e($d['name']) ?>" required></label>
                <label class="field"><span>Price</span><input name="<?= $n ?>[price]" value="<?= e($d['price']) ?>" inputmode="decimal" placeholder="12.50"></label>
                <label class="field"><span>Tag</span><select name="<?= $n ?>[tag]"><?php foreach (['' => 'None', 'Spicy' => 'Spicy', 'Vegetarian' => 'Vegetarian', 'For sharing' => 'For sharing'] as $k => $l) : ?><option value="<?= e($k) ?>"<?= $d['tag'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            </div>
            <label class="field"><span>Short description (menu)</span><input name="<?= $n ?>[desc]" value="<?= e($d['desc']) ?>"></label>
            <details>
                <summary>Photo, signature dish &amp; more</summary>
                <div class="grid-3">
                    <label class="field"><span>Photo</span><input type="file" name="dish_img[<?= e($duid) ?>]" accept="image/jpeg,image/png,image/webp"></label>
                    <label class="field"><span>Signature gallery position <small>(1–10, empty = not shown)</small></span><input type="number" min="0" max="10" name="<?= $n ?>[featured]" value="<?= (int) $d['featured'] ?: '' ?>"></label>
                    <label class="field"><span>Gallery note</span><input name="<?= $n ?>[note]" value="<?= e($d['note']) ?>" placeholder="House favourite"></label>
                </div>
                <label class="field"><span>Longer description (signature gallery)</span><textarea name="<?= $n ?>[long]" rows="2"><?= e($d['long']) ?></textarea></label>
                <div class="grid-3">
                    <label class="field"><span>Placeholder colour (until a photo is added)</span><select name="<?= $n ?>[tone]"><?php foreach (['tone-gold' => 'Gold', 'tone-red' => 'Red', 'tone-jade' => 'Jade', 'tone-smoke' => 'Smoke'] as $k => $l) : ?><option value="<?= e($k) ?>"<?= $d['tone'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                    <?php if ($d['img'] !== '') : ?><label class="check"><input type="checkbox" name="<?= $n ?>[remove_img]" value="1"> Remove photo</label><?php endif; ?>
                </div>
                <input type="hidden" name="<?= $n ?>[id]" value="<?= e($d['id']) ?>">
                <input type="hidden" name="<?= $n ?>[img]" value="<?= e($d['img']) ?>">
            </details>
        </div>
        <div class="row-tools">
            <button type="button" class="icon" data-move="up" aria-label="Move up">↑</button>
            <button type="button" class="icon" data-move="down" aria-label="Move down">↓</button>
            <button type="button" class="icon danger" data-remove aria-label="Delete dish">✕</button>
        </div>
    </div>
    <?php
}

function cat_block(string $cuid, array $c): void
{
    ?>
    <section class="cat card" data-cat data-cuid="<?= e($cuid) ?>">
        <header class="cat-head">
            <label class="field grow"><span>Category name</span><input name="cat[<?= e($cuid) ?>][label]" value="<?= e($c['label']) ?>" required></label>
            <input type="hidden" name="cat[<?= e($cuid) ?>][id]" value="<?= e($c['id']) ?>">
            <div class="row-tools">
                <button type="button" class="icon" data-move="up" aria-label="Move category up">↑</button>
                <button type="button" class="icon" data-move="down" aria-label="Move category down">↓</button>
                <button type="button" class="icon danger" data-remove aria-label="Delete category">✕</button>
            </div>
        </header>
        <div class="dishes" data-dishes>
            <?php foreach ($c['dishes'] as $j => $d) { dish_row($cuid, 'd' . $cuid . '_' . $j, $d); } ?>
        </div>
        <button type="button" class="btn ghost" data-add-dish>+ Add dish</button>
    </section>
    <?php
}

function ordering_settings(array $site): void
{
    $o = ordering($site);
    ?>
    <input type="hidden" name="ordering_present" value="1">
    <h3 id="ordering">Online ordering</h3>
    <div class="card form-grid">
        <label class="check"><input type="checkbox" name="ordering[enabled]" value="1"<?= $o['enabled'] ? ' checked' : '' ?>> Accept orders on the website (switch off to close online ordering, e.g. when you're fully booked)</label>
        <label class="field"><span>Email new orders to</span><input type="email" name="ordering[email]" value="<?= e($o['email']) ?>" placeholder="orders@yourrestaurant.com"></label>
        <div class="grid-3">
            <label class="check"><input type="checkbox" name="ordering[collection]" value="1"<?= $o['collection'] ? ' checked' : '' ?>> Collection</label>
            <label class="check"><input type="checkbox" name="ordering[delivery]" value="1"<?= $o['delivery'] ? ' checked' : '' ?>> Delivery</label>
        </div>
        <div class="grid-3">
            <label class="field"><span>Delivery fee <small>(number, e.g. 25)</small></span><input name="ordering[delivery_fee]" value="<?= e($o['delivery_fee']) ?>" inputmode="decimal"></label>
            <label class="field"><span>Minimum order for delivery</span><input name="ordering[min_delivery]" value="<?= e($o['min_delivery']) ?>" inputmode="decimal"></label>
        </div>
        <label class="field"><span>Payment note shown at checkout</span><input name="ordering[note]" value="<?= e($o['note']) ?>"></label>
        <label class="field"><span>Your time zone <small>(order times are shown in this zone)</small></span><select name="ordering[timezone]"><option value="">Server default (<?= e(date_default_timezone_get()) ?>)</option><?php foreach (timezone_identifiers_list() as $tz) : ?><option value="<?= e($tz) ?>"<?= $o['timezone'] === $tz ? ' selected' : '' ?>><?= e(str_replace('_', ' ', $tz)) ?></option><?php endforeach; ?></select></label>
        <p class="hint"><b>Bot protection.</b> Every order already passes a hidden spam trap, a signed form check and a limit of <?= ORDER_RATE_MAX ?> orders per visitor per half hour. For the strongest protection add free <a href="https://www.cloudflare.com/application-services/products/turnstile/" target="_blank" rel="noopener">Cloudflare Turnstile</a> keys (Cloudflare dashboard → Turnstile → Add site → your domain).</p>
        <div class="grid-3">
            <label class="field"><span>Turnstile site key</span><input name="ordering[turnstile_site]" value="<?= e($o['turnstile_site']) ?>" autocomplete="off"></label>
            <label class="field"><span>Turnstile secret key <small><?= $o['turnstile_secret'] !== '' ? '(saved — leave blank to keep)' : '' ?></small></span><input type="password" name="ordering[turnstile_secret]" value="" autocomplete="new-password"></label>
            <?php if ($o['turnstile_secret'] !== '') : ?><label class="check"><input type="checkbox" name="ordering[turnstile_remove]" value="1"> Remove Turnstile</label><?php endif; ?>
        </div>
    </div>
    <?php
}

/** Small forms that live outside the editor form; buttons inside the tabs point at them with form="…". */
$GLOBALS['side_forms'] = [];
function side_form(string $action, array $fields, string $confirm = ''): string
{
    $id = 'sf' . count($GLOBALS['side_forms']);
    $html = '<form id="' . $id . '" method="post" action="?action=' . e($action) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field();
    foreach ($fields as $k => $v) {
        $html .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    $GLOBALS['side_forms'][] = $html . '</form>';
    return $id;
}

function order_card(array $site, array $o): void
{
    $next = ['new' => ['accepted' => 'Accept'], 'accepted' => ['ready' => 'Mark ready'], 'ready' => ['completed' => 'Complete'], 'completed' => [], 'cancelled' => []][$o['status']] ?? [];
    $when = $o['when'] === 'later' ? 'for ' . $o['time'] : 'ASAP';
    ?>
    <article class="order card status-<?= e($o['status']) ?>" data-order="<?= e($o['number']) ?>">
        <header class="order-head">
            <div><strong class="order-num"><?= e($o['number']) ?></strong> <span class="badge-status"><?= e(ORDER_STATUSES[$o['status']] ?? $o['status']) ?></span>
            <small><?= e(date('D j M, H:i', strtotime($o['created']) ?: time())) ?></small></div>
            <div class="order-total"><?= e(money($site, (float) $o['total'])) ?></div>
        </header>
        <div class="order-body">
            <div class="order-who">
                <b><?= e($o['mode'] === 'delivery' ? 'Delivery' : 'Collection') ?> · <?= e($when) ?></b>
                <span><?= e($o['name']) ?> · <a href="tel:<?= e(preg_replace('/[^+0-9]/', '', $o['phone'])) ?>"><?= e($o['phone']) ?></a><?= $o['email'] ? ' · <a href="mailto:' . e($o['email']) . '">' . e($o['email']) . '</a>' : '' ?></span>
                <?php if ($o['address']) : ?><span><?= e($o['address']) ?></span><?php endif; ?>
                <?php if ($o['notes']) : ?><span class="order-notes"><?= e($o['notes']) ?></span><?php endif; ?>
                <?php if (empty($o['emailed']) && ordering($site)['email'] !== '') : ?><span class="order-warn">Email not sent — check your host's mail settings.</span><?php endif; ?>
            </div>
            <ul class="order-lines"><?php foreach ($o['lines'] as $l) : ?><li><span><?= (int) $l['qty'] ?> × <?= e($l['name']) ?></span><span><?= e(money($site, (float) $l['line'])) ?></span></li><?php endforeach; ?>
                <?php if ($o['fee'] > 0) : ?><li><span>Delivery fee</span><span><?= e(money($site, (float) $o['fee'])) ?></span></li><?php endif; ?></ul>
        </div>
        <?php if ($next || !in_array($o['status'], ['completed', 'cancelled'], true)) : ?>
        <footer class="order-actions">
            <?php foreach ($next as $st => $label) : ?>
                <button class="btn primary" type="submit" form="<?= side_form('order-status', ['number' => $o['number'], 'status' => $st]) ?>"><?= e($label) ?></button>
            <?php endforeach; ?>
            <?php if (!in_array($o['status'], ['completed', 'cancelled'], true)) : ?>
                <button class="btn ghost" type="submit" form="<?= side_form('order-status', ['number' => $o['number'], 'status' => 'cancelled'], 'Cancel order ' . $o['number'] . '? Call the customer to let them know.') ?>">Cancel</button>
            <?php endif; ?>
        </footer>
        <?php endif; ?>
    </article>
    <?php
}

function shell_page(string $title, callable $body): void
{
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($title) ?> · Admin</title><link rel="stylesheet" href="admin.css?v=<?= INKWOK_ADMIN_VERSION ?>-<?= (int) @filemtime(__DIR__ . '/admin.css') ?>"></head>
<body class="auth"><main><?php $body(); ?></main></body></html><?php
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Admin · <?= e(f($site, 'brand')) ?></title>
<link rel="stylesheet" href="admin.css?v=<?= INKWOK_ADMIN_VERSION ?>-<?= (int) @filemtime(__DIR__ . '/admin.css') ?>">
</head>
<body>
<form id="editor" method="post" action="?action=save" enctype="multipart/form-data">
<?= csrf_field() ?>
<input type="hidden" name="tab" value="">
<input type="hidden" name="menu_present" value="1">
<input type="hidden" name="features_present" value="1">

<header class="topbar">
    <div class="brand"><?php $logo = (string) ($site['images']['logo'] ?? ''); ?><?php if ($logo !== '') : ?><img class="logo-sm" src="<?= e(preg_match('#^(https?:)?/#', $logo) ? $logo : '../' . $logo) ?>" alt=""><?php else : ?><span class="seal-sm"><?= e(f($site, 'seal_char')) ?></span><?php endif; ?><div><strong><?= e(f($site, 'brand')) ?></strong><small>Website admin</small></div></div>
    <div class="actions">
        <a class="btn ghost" href="../" target="_blank" rel="noopener">View site</a>
        <button class="btn ghost" type="submit" formaction="?action=preview" formtarget="_blank">Preview home</button>
        <button class="btn ghost" type="submit" formaction="?action=preview&amp;page=menu" formtarget="_blank">Preview menu</button>
        <button class="btn ghost" type="submit" formaction="?action=preview&amp;page=checkout" formtarget="_blank">Preview checkout</button>
        <button class="btn primary" type="submit">Save &amp; publish</button>
    </div>
</header>

<?php if ($flash) : ?><p class="msg <?= e($flash['type']) ?> page-msg" role="status"><?= e($flash['msg']) ?></p><?php endif; ?>
<?php if (!$writable) : ?><p class="msg err page-msg">The admin cannot write <code>index.html</code>, <code>menu.html</code> or <code>checkout.html</code>, so changes will not go live. Give the website folder write permission (see the documentation, "Permissions").</p><?php endif; ?>

<div class="layout">
<nav class="tabs" aria-label="Sections">
    <?php foreach ($tabs as $id => $label) : ?><a href="#<?= e($id) ?>" data-tab-link="<?= e($id) ?>"><?= e($label) ?></a><?php endforeach; ?>
</nav>

<div class="panes">
<?php foreach (['brand', 'hours', 'hero', 'story', 'special', 'reviews'] as $sec) : ?>
    <section class="pane" id="<?= e($sec) ?>" data-pane>
        <h2><?= e($tabs[$sec]) ?></h2>
        <?php if ($sec === 'brand') : ?><p class="hint">Your name, contact details and map. They appear in the top bar, the contact section and the footer.</p><?php endif; ?>
        <?php if ($sec === 'reviews') : ?><p class="hint">Use real reviews from your customers. Leave a quote empty to hide that card.</p><?php endif; ?>
        <div class="card form-grid">
            <?php foreach ($fields as $k => $def) { if ($def[0] === $sec) { field_input($k, $def, $site); } } ?>
        </div>
        <?php if ($sec === 'hours') { ordering_settings($site); } ?>
    </section>
<?php endforeach; ?>

<section class="pane" id="orders" data-pane data-orders data-latest="<?= e($orders[0]['created'] ?? '') ?>">
    <h2>Orders</h2>
    <p class="hint">New orders appear here straight away (this page checks every 20 seconds) and are also emailed to <?= $ord['email'] !== '' ? '<b>' . e($ord['email']) . '</b>' : 'the address you set under Hours &amp; ordering' ?>. Orders older than <?= ORDER_KEEP_DAYS ?> days are deleted automatically.</p>
    <?php if (!$ordOpen) : ?><p class="msg warn">Guests can't order online right now: <?= e($ordReason) ?><?= priced_menu($site) ? '' : ' Add prices to your dishes in the Menu tab (numbers only, e.g. 89.00).' ?></p><?php endif; ?>
    <?php if ($ord['enabled'] && $ord['email'] === '') : ?><p class="msg warn">Add the email address that should receive new orders (Hours &amp; ordering → Online ordering).</p><?php endif; ?>
    <?php if ($ord['enabled'] && $ord['turnstile_secret'] === '') : ?><p class="msg warn">Recommended: add Cloudflare Turnstile keys for the strongest bot protection (Hours &amp; ordering → Online ordering).</p><?php endif; ?>
    <p class="msg ok new-orders" data-new-orders hidden>New order received — <a href="./#orders">refresh</a></p>
    <?php $active = array_values(array_filter($orders, static fn ($o) => in_array($o['status'], ['new', 'accepted', 'ready'], true))); $done = array_values(array_filter($orders, static fn ($o) => !in_array($o['status'], ['new', 'accepted', 'ready'], true))); ?>
    <h3>Active <span class="count"><?= count($active) ?></span></h3>
    <?php if (!$active) : ?><p class="hint">No open orders. New ones will appear here.</p><?php endif; ?>
    <?php foreach ($active as $o) { order_card($site, $o); } ?>
    <?php if ($done) : ?>
        <details class="orders-done"><summary>Completed and cancelled (<?= count($done) ?>)</summary>
            <?php foreach (array_slice($done, 0, 100) as $o) { order_card($site, $o); } ?>
        </details>
    <?php endif; ?>
</section>

<section class="pane" id="menu" data-pane>
    <h2>Menu</h2>
    <p class="hint">Each category becomes a tab on the menu. Use the arrows to reorder; empty categories are hidden on the site. Open "Photo, signature dish &amp; more" to add a photo or show a dish in the "Favourites from our kitchen" gallery (positions 1–10, the first two are shown large).</p>
    <div id="cats">
        <?php foreach ($site['menu'] as $i => $c) { cat_block('c' . $i, $c); } ?>
    </div>
    <button type="button" class="btn ghost" id="add-cat">+ Add category</button>
</section>

<section class="pane" id="look" data-pane>
    <h2>Logo &amp; look</h2>
    <div class="card form-grid">
        <?php image_input('logo', 'Logo for the top bar (square works best, e.g. 300 × 300)', $site['images']['logo']); ?>
        <?php image_input('logo_footer', 'Footer logo (full logo, shown on a paper card)', $site['images']['logo_footer']); ?>
    </div>
    <h3>Colours</h3>
    <div class="card form-grid colours">
        <?php foreach (inkwok_colours() as $k => $c) : $v = $site['colours'][$k] ?: $c[2]; ?>
            <label class="field colour"><span><?= e($c[1]) ?></span><input type="color" name="colour[<?= e($k) ?>]" value="<?= e($v) ?>"><small>Default <?= e($c[2]) ?></small></label>
        <?php endforeach; ?>
        <p class="hint">To go back to a default colour, set it to the default value shown.</p>
    </div>
    <h3>Features</h3>
    <div class="card">
        <?php foreach (['cursor' => 'Chopstick cursor (mouse only)', 'music' => 'Background music button', 'art' => 'Watercolour landscapes behind sections', 'paper' => 'Rice-paper texture'] as $k => $l) : ?>
            <label class="check"><input type="checkbox" name="feature[<?= e($k) ?>]" value="1"<?= !empty($site['features'][$k]) ? ' checked' : '' ?>> <?= e($l) ?></label>
        <?php endforeach; ?>
    </div>
</section>

<section class="pane" id="account" data-pane>
    <h2>Account</h2>
    <p class="hint">Last saved: <?= isset($site['updated']) ? e(date('j M Y, H:i', strtotime((string) $site['updated']) ?: time())) : 'never' ?>.</p>
    <div class="card form-grid">
        <h3>Change password</h3>
        <label class="field"><span>Current password</span><input form="f-password" type="password" name="current" autocomplete="current-password"></label>
        <label class="field"><span>New password (10+ characters)</span><input form="f-password" type="password" name="password" autocomplete="new-password" minlength="10"></label>
        <label class="field"><span>Repeat new password</span><input form="f-password" type="password" name="password2" autocomplete="new-password" minlength="10"></label>
        <button class="btn primary" type="submit" form="f-password">Change password</button>
    </div>
    <div class="card form-grid">
        <h3>Undo changes</h3>
        <?php $backups = list_backups(); if ($backups) : ?>
            <p class="hint">Every save keeps the previous version. Pick one to bring it back (your current version is kept as a backup too).</p>
            <label class="field"><span>Version</span><select form="f-restore" name="backup"><?php foreach ($backups as $b) : ?><option value="<?= e(basename($b)) ?>"><?= e(date('j M Y, H:i:s', (int) filemtime($b))) ?></option><?php endforeach; ?></select></label>
            <button class="btn ghost" type="submit" form="f-restore">Restore this version</button>
        <?php else : ?>
            <p class="hint">No earlier versions yet. They appear here after you save.</p>
        <?php endif; ?>
    </div>
    <div class="card">
        <h3>Publish again</h3>
        <p class="hint">Rebuilds <code>index.html</code>, <code>menu.html</code> and <code>checkout.html</code> from the saved content, e.g. after updating the template files.</p>
        <button class="btn ghost" type="submit" form="f-republish">Publish again</button>
    </div>
    <p><a class="btn ghost" href="?action=logout&amp;csrf=<?= e($_SESSION['csrf']) ?>">Log out</a></p>
</section>
</div>
</div>
</form>

<!-- Small forms used by buttons inside the tabs (status changes, password, restore, publish). -->
<form id="f-password" method="post" action="?action=password"><?= csrf_field() ?></form>
<form id="f-restore" method="post" action="?action=restore"><?= csrf_field() ?></form>
<form id="f-republish" method="post" action="?action=republish"><?= csrf_field() ?></form>
<?= implode("\n", $GLOBALS['side_forms']) ?>

<template id="tpl-cat"><?php cat_block('__C__', ['id' => '', 'label' => '', 'dishes' => []]); ?></template>
<template id="tpl-dish"><?php dish_row('__C__', '__D__', ['id' => '', 'name' => '', 'desc' => '', 'long' => '', 'price' => '', 'tag' => '', 'tone' => 'tone-gold', 'img' => '', 'featured' => 0, 'note' => '']); ?></template>
<script src="admin.js?v=<?= INKWOK_ADMIN_VERSION ?>-<?= (int) @filemtime(__DIR__ . '/admin.js') ?>"></script>
</body>
</html>
