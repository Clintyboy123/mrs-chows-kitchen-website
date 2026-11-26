<?php
/**
 * Ink & Wok — direct online ordering.
 *
 * Guests build an order on the menu page and place it on checkout.html, which posts to /order.php.
 * Everything that matters is decided here, on the server:
 *   - prices and totals come from the saved menu, never from the browser;
 *   - a signed form token (also rejects instant bot submissions), a hidden honeypot field,
 *     a per-visitor rate limit and, when keys are set, Cloudflare Turnstile;
 *   - orders are saved in content/orders/ (not publicly readable) and emailed to the restaurant.
 */

declare(strict_types=1);

define('ORDERS_DIR', CONTENT_DIR . '/orders');

const ORDER_STATUSES = ['new' => 'New', 'accepted' => 'Accepted', 'ready' => 'Ready', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
const ORDER_MAX_LINES = 40;
const ORDER_MAX_QTY = 20;
const ORDER_MIN_SECONDS = 4;          // a person needs at least this long to fill in the form
const ORDER_TOKEN_TTL = 7200;         // checkout form stays valid for 2 hours
const ORDER_RATE_MAX = 5;             // orders per visitor ...
const ORDER_RATE_WINDOW = 1800;       // ... per 30 minutes
const ORDER_KEEP_DAYS = 90;           // older orders are deleted automatically (privacy)

/* ------------------------------------------------------------------ settings */

function ordering_defaults(): array
{
    return [
        'enabled' => true,
        'email' => '',
        'collection' => true,
        'delivery' => true,
        'delivery_fee' => '',
        'min_delivery' => '',
        'note' => 'Pay by cash or card when you collect, or when your order is delivered.',
        'turnstile_site' => '',
        'turnstile_secret' => '',
        'timezone' => '',
    ];
}

function ordering(array $s): array
{
    $o = array_merge(ordering_defaults(), is_array($s['ordering'] ?? null) ? $s['ordering'] : []);
    foreach (['enabled', 'collection', 'delivery'] as $k) {
        $o[$k] = (bool) $o[$k];
    }
    return $o;
}

/** Order times use the restaurant's own time zone, not the server's (often UTC). */
function apply_timezone(array $s): void
{
    $tz = (string) (ordering($s)['timezone'] ?? '');
    if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
        date_default_timezone_set($tz);
    }
}

/** Dishes that can be ordered online: id => [name, price, category]. Dishes without a numeric price are skipped. */
function priced_menu(array $s): array
{
    $out = [];
    foreach ($s['menu'] as $c) {
        foreach ($c['dishes'] as $d) {
            $p = trim((string) $d['price']);
            if (is_numeric($p) && (float) $p > 0) {
                $out[$d['id']] = ['name' => $d['name'], 'price' => round((float) $p, 2), 'cat' => $c['label']];
            }
        }
    }
    return $out;
}

/** [open?, reason shown to guests when closed] */
function ordering_status(array $s): array
{
    $o = ordering($s);
    if (!$o['enabled']) {
        return [false, 'Online ordering is closed right now. Please call us to order.'];
    }
    if (!$o['collection'] && !$o['delivery']) {
        return [false, 'Online ordering is closed right now. Please call us to order.'];
    }
    if (!priced_menu($s)) {
        return [false, 'Online ordering opens soon. In the meantime, please call us to order.'];
    }
    return [true, ''];
}

/** Where the order slip's Checkout button goes: our checkout page, or an external ordering link. */
function order_target(array $s): string
{
    return ordering($s)['enabled'] ? 'checkout.html' : safe_url(f($s, 'order_url'));
}

function money(array $s, float $n): string
{
    return f($s, 'currency') . number_format($n, 2);
}

/* ------------------------------------------------------------------ form token */

function app_secret(): string
{
    $f = CONTENT_DIR . '/secret.php';
    if (is_file($f)) {
        $v = include $f;
        if (is_string($v) && strlen($v) >= 32) {
            return $v;
        }
    }
    $v = bin2hex(random_bytes(32));
    write_atomic($f, "<?php\n// Random key that signs checkout forms. Safe to delete; a new one is made.\nreturn '" . $v . "';\n");
    return $v;
}

function order_token(): string
{
    $t = (string) time();
    $n = bin2hex(random_bytes(8));
    return $t . '.' . $n . '.' . hash_hmac('sha256', $t . '.' . $n, app_secret());
}

/** null when fine, otherwise a message for the guest. */
function check_order_token(string $token): ?string
{
    $p = explode('.', $token);
    if (count($p) !== 3 || !ctype_digit($p[0]) || !hash_equals(hash_hmac('sha256', $p[0] . '.' . $p[1], app_secret()), $p[2])) {
        return 'This checkout page is out of date. Please reload it and try again.';
    }
    $age = time() - (int) $p[0];
    if ($age < ORDER_MIN_SECONDS) {
        return 'That was very quick. Please check your details and press Place order again.';
    }
    if ($age > ORDER_TOKEN_TTL) {
        return 'This checkout page has expired. Please reload it and try again.';
    }
    return null;
}

/* ------------------------------------------------------------------ bot checks */

function verify_turnstile(string $secret, string $response, string $ip): bool
{
    if ($response === '' || strlen($response) > 2048) {
        return false;
    }
    $body = http_build_query(['secret' => $secret, 'response' => $response, 'remoteip' => $ip]);
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, stream_context_create(['http' => [
            'method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $body, 'timeout' => 8,
        ]]));
    }
    $res = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($res) && !empty($res['success']);
}

/** Allows ORDER_RATE_MAX orders per visitor per ORDER_RATE_WINDOW; records this attempt when allowed. */
function order_rate_ok(string $ip): bool
{
    $f = ORDERS_DIR . '/rate.php';
    $data = is_file($f) ? (json_decode(substr((string) file_get_contents($f), strlen(DATA_GUARD)), true) ?: []) : [];
    $now = time();
    foreach ($data as $k => $times) {
        $data[$k] = array_values(array_filter((array) $times, static fn ($t) => $now - (int) $t < ORDER_RATE_WINDOW));
        if (!$data[$k]) {
            unset($data[$k]);
        }
    }
    $key = hash('sha256', $ip . app_secret());
    if (count($data[$key] ?? []) >= ORDER_RATE_MAX) {
        return false;
    }
    $data[$key][] = $now;
    ensure_orders_dir();
    @file_put_contents($f, DATA_GUARD . json_encode($data), LOCK_EX);
    return true;
}

/* ------------------------------------------------------------------ validation */

/**
 * Turn a guest's submission into a priced order. Returns [order, []] or [null, errors].
 * Only ids and quantities are taken from the browser; names and prices come from the saved menu.
 */
function build_order(array $s, array $in): array
{
    $o = ordering($s);
    $menu = priced_menu($s);
    $err = [];
    $txt = static fn ($v, int $max) => trim(mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $v) ?? '', 0, $max));

    $name = $txt($in['name'] ?? '', 80);
    $phone = $txt($in['phone'] ?? '', 30);
    $email = $txt($in['email'] ?? '', 120);
    $mode = (string) ($in['mode'] ?? '');
    $address = $txt($in['address'] ?? '', 240);
    $when = (string) ($in['when'] ?? 'asap');
    $time = (string) ($in['time'] ?? '');
    $notes = $txt($in['notes'] ?? '', 500);

    if (mb_strlen($name) < 2) {
        $err[] = 'Please enter your name.';
    }
    if (!preg_match('/^[+0-9 ()-]{7,30}$/', $phone) || strlen(preg_replace('/\D/', '', $phone) ?? '') < 7) {
        $err[] = 'Please enter a phone number we can call.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err[] = 'That email address doesn\'t look right (it is optional).';
    }
    if (!in_array($mode, ['collection', 'delivery'], true) || !$o[$mode]) {
        $err[] = 'Please choose collection' . ($o['delivery'] ? ' or delivery' : '') . '.';
    }
    if ($mode === 'delivery' && mb_strlen($address) < 6) {
        $err[] = 'Please enter the delivery address.';
    }
    if ($when === 'later') {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $err[] = 'Please choose a time for your order.';
        }
    } else {
        $when = 'asap';
        $time = '';
    }

    $qty = [];
    $items = is_array($in['items'] ?? null) ? array_slice($in['items'], 0, ORDER_MAX_LINES * 2) : [];
    foreach ($items as $it) {
        $id = is_array($it) ? (string) ($it['id'] ?? '') : '';
        $n = is_array($it) ? (int) ($it['qty'] ?? 0) : 0;
        if ($id === '' || $n < 1) {
            continue;
        }
        if (!isset($menu[$id])) {
            $err[] = 'Something in your order is no longer available online. Please check your order.';
            continue;
        }
        $qty[$id] = min(ORDER_MAX_QTY, ($qty[$id] ?? 0) + $n);
    }
    if (!$qty) {
        $err[] = 'Your order is empty.';
    }
    if (count($qty) > ORDER_MAX_LINES) {
        $err[] = 'That order is too large to place online. Please call us.';
    }

    $lines = [];
    $sub = 0.0;
    foreach ($qty as $id => $n) {
        $line = round($menu[$id]['price'] * $n, 2);
        $sub += $line;
        $lines[] = ['id' => $id, 'name' => $menu[$id]['name'], 'qty' => $n, 'price' => $menu[$id]['price'], 'line' => $line];
    }
    $fee = $mode === 'delivery' && is_numeric($o['delivery_fee']) ? round((float) $o['delivery_fee'], 2) : 0.0;
    $min = is_numeric($o['min_delivery']) ? (float) $o['min_delivery'] : 0.0;
    if ($mode === 'delivery' && $min > 0 && $sub < $min) {
        $err[] = 'The minimum order for delivery is ' . money($s, $min) . '.';
    }
    $err = array_values(array_unique($err));
    if ($err) {
        return [null, $err];
    }
    return [[
        'number' => '',
        'created' => date('c'),
        'status' => 'new',
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'mode' => $mode,
        'address' => $mode === 'delivery' ? $address : '',
        'when' => $when,
        'time' => $time,
        'notes' => $notes,
        'lines' => $lines,
        'subtotal' => round($sub, 2),
        'fee' => $fee,
        'total' => round($sub + $fee, 2),
        'currency' => f($s, 'currency'),
        'emailed' => false,
    ], []];
}

/* ------------------------------------------------------------------ storage */

function ensure_orders_dir(): void
{
    if (!is_dir(ORDERS_DIR) && !@mkdir(ORDERS_DIR, 0755, true)) {
        throw new RuntimeException('Could not create content/orders. Check folder permissions.');
    }
    if (!is_file(ORDERS_DIR . '/index.html')) {
        @file_put_contents(ORDERS_DIR . '/index.html', '');
    }
}

function order_path(string $number): ?string
{
    if (!preg_match('/^[A-Z]\d{4}-\d{4}$/', $number)) {
        return null;
    }
    return ORDERS_DIR . '/' . $number . '.php';
}

function save_order(array $order): array
{
    ensure_orders_dir();
    if ($order['number'] === '') {
        do {
            $order['number'] = 'A' . date('md') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (is_file((string) order_path($order['number'])));
    }
    write_atomic((string) order_path($order['number']), DATA_GUARD . json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $order;
}

function load_order(string $number): ?array
{
    $p = order_path($number);
    if (!$p || !is_file($p)) {
        return null;
    }
    $o = json_decode(substr((string) file_get_contents($p), strlen(DATA_GUARD)), true);
    return is_array($o) ? $o : null;
}

/** Newest first. */
function list_orders(int $limit = 300): array
{
    $files = glob(ORDERS_DIR . '/A*-*.php') ?: [];
    usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
    $out = [];
    foreach (array_slice($files, 0, $limit * 2) as $f) {
        $o = load_order(basename($f, '.php'));
        if ($o) {
            $out[] = $o;
        }
    }
    usort($out, static fn ($a, $b) => strcmp($b['created'], $a['created']));
    return array_slice($out, 0, $limit);
}

function purge_old_orders(): int
{
    $n = 0;
    foreach (glob(ORDERS_DIR . '/A*-*.php') ?: [] as $f) {
        if (filemtime($f) < time() - ORDER_KEEP_DAYS * 86400) {
            @unlink($f);
            $n++;
        }
    }
    return $n;
}

/* ------------------------------------------------------------------ email */

function order_text(array $s, array $o, bool $forGuest): string
{
    $when = $o['when'] === 'later' ? 'at ' . $o['time'] : ($o['mode'] === 'delivery' ? 'as soon as possible (about ' . f($s, 'delivery_time') . ' min)' : 'as soon as possible (about ' . f($s, 'prep_time') . ' min)');
    $t = $forGuest
        ? "Thank you for your order, {$o['name']}!\n\nOrder {$o['number']} — " . ($o['mode'] === 'delivery' ? 'delivery' : 'collection') . " {$when}.\n\n"
        : "New online order {$o['number']}\n\n" . strtoupper($o['mode']) . " {$when}\nName:  {$o['name']}\nPhone: {$o['phone']}\n" . ($o['email'] ? "Email: {$o['email']}\n" : '') . ($o['address'] ? "Address: {$o['address']}\n" : '') . "\n";
    foreach ($o['lines'] as $l) {
        $t .= sprintf("%2d x %s  %s\n", $l['qty'], $l['name'], money($s, (float) $l['line']));
    }
    $t .= "\nSubtotal: " . money($s, (float) $o['subtotal']) . "\n";
    if ($o['fee'] > 0) {
        $t .= 'Delivery: ' . money($s, (float) $o['fee']) . "\n";
    }
    $t .= 'Total:    ' . money($s, (float) $o['total']) . "\n";
    if ($o['notes'] !== '') {
        $t .= "\nNotes: {$o['notes']}\n";
    }
    $t .= "\n" . ordering($s)['note'] . "\n";
    if ($forGuest) {
        $t .= "\nQuestions? Call us on " . f($s, 'phone') . ".\n— " . f($s, 'brand') . "\n";
    }
    return $t;
}

/** Best effort: the order is already saved, so a failed email never loses it. */
function send_order_emails(array $s, array $o): bool
{
    $to = trim(ordering($s)['email']);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || !function_exists('mail')) {
        return false;
    }
    $host = explode(':', strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost')))[0];
    $host = preg_replace('/[^a-z0-9.-]/', '', $host) ?: 'localhost';
    $host = preg_replace('/^www\./', '', $host);
    $from = 'orders@' . $host;
    $brand = str_replace(["\r", "\n", '"'], '', f($s, 'brand'));
    $headers = "From: \"{$brand}\" <{$from}>\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
    $replyTo = $o['email'] !== '' ? "Reply-To: {$o['email']}\r\n" : '';
    $subject = mb_encode_mimeheader('New order ' . $o['number'] . ' — ' . money($s, (float) $o['total']), 'UTF-8');
    $ok = @mail($to, $subject, order_text($s, $o, false), $headers . $replyTo);
    if ($o['email'] !== '') {
        @mail($o['email'], mb_encode_mimeheader('Your order ' . $o['number'] . ' at ' . $brand, 'UTF-8'), order_text($s, $o, true), $headers . "Reply-To: {$to}\r\n");
    }
    return (bool) $ok;
}
