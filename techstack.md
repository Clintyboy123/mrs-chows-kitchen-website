# Mrs Chow's Kitchen: tech stack and Docker deployment

Version 1.6.2 · written 2026-11-03

This covers the **HTML + admin** build (`mrs-chows-kitchen-website/`), the only Mrs Chow's build.

> **Update 2.4 (2026-11-26):** online ordering is removed. Orders go to the restaurant on **WhatsApp** from the cart page (built in the browser, sent by the guest), so there is **no `order.php`, no order storage and no order email**. Where this document mentions `order.php`, rate limiting, Turnstile, the Orders tab or monitoring the order endpoint, it no longer applies. The admin is the only part that needs PHP. The Mrs Chow's WordPress child theme has been dropped.

---

## 1. The big idea

**The pages are static, and a small PHP admin rewrites them.** Visitors only ever get plain HTML, CSS and JS, which is fast and cheap to host and has almost nothing to attack. When the owner presses **Save & publish** in `/admin`, PHP renders the templates to `index.html`, `menu.html` and `checkout.html`. Two jobs need a server:

- the admin
- ~~the order endpoint (`order.php`)~~ (removed in 2.4: orders go out on WhatsApp)

The build has:
- no database
- no framework
- no build step
- no `node_modules`

```
Visitor ──▶ index.html / menu.html / checkout.html   (static, cached)
              │  assets/css/style.css · assets/js/main.js
              │
              └─ checkout ──▶ order.php ──▶ content/orders/*.php + email

Owner ──▶ /admin (PHP, session login)
              ├─ edits content/site.php  (JSON behind a PHP exit guard)
              ├─ uploads ──▶ assets/uploads/ (+ WebP variants via GD)
              └─ Publish ──▶ re-renders admin/template*.php ──▶ *.html
```

---

## 2. Front end

| Layer | What we used | Why |
|---|---|---|
| Markup | Hand-written semantic HTML5, rendered from PHP templates | Fast first paint, good SEO, works without JS |
| CSS | One vanilla stylesheet, `assets/css/style.css` (~1,000 lines). Uses custom properties for colour, type and spacing, CSS grid/flex, `clamp()` type, and media queries at 760px (phone) | No framework or build. One spacing scale, so the phone layout stays on rhythm |
| JS | One vanilla script, `assets/js/main.js` (~700 lines, no dependencies) | Progressive enhancement: the page reads and works without it |
| Cache-busting | `?v=1.6.2` on CSS/JS | Safe to cache assets for a long time |

**What `main.js` does:**
- Cart in `localStorage` (`inkwok-order-v1`): every `[data-cart-badge]` on every page reads the same cart, so counts always match.
- Phone ☰ menu with the cart link and count.
- Slide-up order panel on phones and tablets.
- Hero slideshow: 6s crossfade, pause button, honours `prefers-reduced-motion`.
- Favourites slider on phones.
- Menu search plus Vegetarian/Spicy filters.
- Checkout: fetches a signed token, posts the order as JSON, shows the confirmation.
- WhatsApp ordering: `[data-wa-order]` builds a `wa.me` link from the cart and form fields.

**Typography:**
- Google Fonts: Noto Serif SC, Noto Sans SC and Ma Shan Zheng (`display=swap`).
- Spell of Asia: self-hosted in `assets/fonts/`. ⚠ Its licence is personal-use only, so buy a commercial licence before launch or remove the `@font-face`.

**Imagery:**
- Responsive WebP variants (160 / 480 / 960 / 1600 px) with `srcset` and lazy loading.
- The paper texture and ink landscapes (`assets/img/art/`) are WebP images, not live SVG filters.
- The design trees and hanging scrolls are static (no animation), and the story-section bamboo has been removed.

**Third-party services (all optional, all client-side or opt-in):**
- **Google Maps** embed: loads only on tap.
- **WhatsApp**: `wa.me` deep links. The number is set in the admin, and an empty number hides every button.
- **Cloudflare Turnstile**: bot check on checkout, active once keys are added in the admin.

---

## 3. Back end (PHP 8.0+, tested on 8.3)

| File | Role |
|---|---|
| `admin/index.php` | The admin UI. Tabs: Details, Hero, Menu, Hours & ordering, Orders, Backups |
| `admin/lib.php` | Core: storage, rendering, image processing, sessions, CSRF |
| `admin/fields.php` | Every editable field, with its default |
| `admin/orders.php` | Order pricing, tokens, Turnstile, rate limit, storage, email |
| `admin/template.php`, `template-menu.php`, `template-checkout.php` | Page templates rendered on Publish |
| `order.php` | JSON endpoint: `GET ?action=token` returns status plus a signed token; `POST` places an order |

### Storage (flat files, no database)

| Path | Contents |
|---|---|
| `content/site.php` | All site content as JSON behind `<?php exit; ?>`, so it can't be downloaded |
| `content/config.php` | Admin password hash (`password_hash`) |
| `content/secret.php` | Random HMAC key for checkout tokens (auto-created) |
| `content/backups/` | Last 10 versions of the content (restorable in the admin) |
| `content/orders/` | One file per order, plus `rate.php` for the rate limit |
| `assets/uploads/` | Photos and logos added in the admin |
| `index.html`, `menu.html`, `checkout.html` | **Generated output.** Rewritten on every Publish |

Writes are atomic (temp file → `rename`, with `LOCK_EX`). `content/.htaccess` denies all access, and `assets/uploads/.htaccess` blocks script execution.

### Security model

- **Admin:**
  - Password set on first visit, checked with `password_verify`.
  - Session cookie: `HttpOnly`, `SameSite=Strict`, `Secure` on HTTPS, 2-hour lifetime; the session ID is regenerated on login.
  - CSRF token on every form.
- **Orders:**
  - Prices are recalculated on the server from the menu, so the browser's numbers are ignored.
  - An HMAC-signed form token sets a minimum fill time (4s) and an expiry (2h).
  - A honeypot field catches bots.
  - Each IP is rate-limited to 5 orders per 30 minutes.
  - Orders older than 90 days are deleted automatically, for privacy.
  - Cloudflare Turnstile adds a bot check once its keys are set.
- **Uploads:**
  - 8 MB maximum.
  - The MIME type is checked with `getimagesize`.
  - Images are resized to at most 1,800px, and WebP variants are made with **GD**.
  - Scripts can't run in the uploads folder.

### Required PHP extensions

`gd` (with JPEG + WebP), `mbstring`, `json`, `session`, and `curl` (optional; it falls back to `file_get_contents`). Email uses PHP `mail()`, so it **needs a working sendmail** (see 5.4).

---

## 4. Design and tooling we used to build it

- **Design:** a Claude Design canvas (desktop and phone artboards for Home, Menu and Cart) served as the source of truth for layout and spacing, and was then ported into the templates.
- **Local testing:**
  - `php -S localhost:8770` for the admin and ordering.
  - `python3 -m http.server` for static checks.
  - In-app browser checks at 390px and desktop widths.
- **Checks:** `php -l` on every PHP file; a marker grep inside the zips after each rebuild.
- **Packaging:** zips per package (`zip -qrX`, `.DS_Store` excluded).

---

## 5. Docker deployment

### 5.1 Decisions (and why)

1. **`php:8.3-apache` as the single container.** The site relies on `.htaccess` (`content/` deny, the uploads no-exec rule), so Apache is the simplest way to keep that protection intact. Nginx + PHP-FPM would need those rules rewritten by hand, which risks exposing `content/`.
2. **Two persistent volumes:** `content/` and `assets/uploads/`. Everything else comes from the image.
3. **Regenerate the HTML on boot.** The published `*.html` live in the web root, inside the image, so a redeploy would revert them to the versions in the repo. The entrypoint therefore runs `publish(load_site())` on every start, rebuilding the pages from the persisted `content/site.php`. Publishes from the admin survive deploys, and the image stays immutable.
4. **One replica only.** File storage with `flock` isn't safe across several containers. A takeaway site doesn't need horizontal scale anyway: a single small VPS is plenty.
5. **TLS at a reverse proxy** (Caddy or Traefik, whichever your other sites use). Two code facts matter behind a proxy:
   - `is_https()` already reads `X-Forwarded-Proto`, so secure cookies work.
   - `order.php` rate-limits on `REMOTE_ADDR`. Behind a proxy, every customer would share the proxy's IP and could lock each other out, so **`mod_remoteip` must be enabled** to restore the real client IP (it's in the Dockerfile below).
6. **Mail via `msmtp`.** Containers have no MTA, so `mail()` would silently fail and no order emails would arrive. `msmtp` acts as sendmail and relays through an SMTP provider (Brevo, Mailgun, SES or your host's SMTP). Orders still save to the admin even if email fails.

### 5.2 Repo layout

```
mrs-chows/
├─ site/                     ← contents of mrs-chows-kitchen-website/
├─ docker/
│  ├─ apache-site.conf
│  ├─ php.ini
│  └─ entrypoint.sh
├─ Dockerfile
├─ compose.yaml
├─ .dockerignore
└─ .github/workflows/deploy.yml
```

Commit `site/content/site.php` (the seed content), but **never** commit `config.php`, `secret.php`, `orders/` or `backups/*.php`:

```gitignore
site/content/config.php
site/content/secret.php
site/content/orders/
site/content/backups/*.php
site/assets/uploads/*
!site/assets/uploads/index.html
!site/assets/uploads/.htaccess
```

### 5.3 Dockerfile

```dockerfile
FROM php:8.3-apache

# GD with JPEG + WebP (admin image uploads), msmtp as sendmail for mail()
RUN apt-get update \
 && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev msmtp ca-certificates \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd \
 && apt-get purge -y --auto-remove libjpeg62-turbo-dev libpng-dev libwebp-dev \
 && apt-get install -y --no-install-recommends libjpeg62-turbo libpng16-16 libwebp7 \
 && rm -rf /var/lib/apt/lists/*

RUN a2enmod headers remoteip expires \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini          $PHP_INI_DIR/conf.d/zz-site.ini
COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh    /usr/local/bin/site-entrypoint

COPY site/ /var/www/html/
# Seed copy of content/, used to initialise an empty volume on first boot
RUN cp -a /var/www/html/content /opt/content-seed \
 && chown -R www-data:www-data /var/www/html \
 && chmod +x /usr/local/bin/site-entrypoint

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s CMD curl -fsS http://localhost/ >/dev/null || exit 1
ENTRYPOINT ["site-entrypoint"]
CMD ["apache2-foreground"]
```

> The web root itself must be writable by `www-data`: Publish writes a temp file next to `index.html` and renames it. The admin shows a warning if it can't.

### 5.4 Config files

**`docker/php.ini`**
```ini
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 256M          ; GD needs headroom for big photos
expose_php = Off
display_errors = Off
log_errors = On
sendmail_path = "/usr/bin/msmtp -t"
date.timezone = Africa/Johannesburg
```

**`docker/apache-site.conf`**
```apache
<VirtualHost *:80>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All          # .htaccess in content/ and assets/uploads/ must apply
        Require all granted
    </Directory>

    # Real client IP from the reverse proxy (rate limiting depends on it)
    RemoteIPHeader X-Forwarded-For
    RemoteIPInternalProxy 172.16.0.0/12 10.0.0.0/8 192.168.0.0/16

    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set X-Frame-Options "SAMEORIGIN"

    # Assets are cache-busted with ?v=, so cache them hard; HTML stays fresh
    ExpiresActive On
    ExpiresByType text/css   "access plus 1 year"
    ExpiresByType text/javascript "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType font/ttf   "access plus 1 year"
    ExpiresByType text/html  "access plus 0 seconds"
</VirtualHost>
```
If the site sits behind **Cloudflare**, use `RemoteIPHeader CF-Connecting-IP` and list Cloudflare's IP ranges as `RemoteIPTrustedProxy` instead.

**`docker/entrypoint.sh`**
```sh
#!/bin/sh
set -e
ROOT=/var/www/html

# 1. First boot: seed an empty content volume
if [ ! -f "$ROOT/content/site.php" ]; then
  cp -a /opt/content-seed/. "$ROOT/content/"
fi
chown -R www-data:www-data "$ROOT/content" "$ROOT/assets/uploads"

# 2. SMTP relay for PHP mail() (order emails)
if [ -n "$SMTP_HOST" ]; then
  cat > /etc/msmtprc <<EOF
defaults
auth on
tls on
tls_trust_file /etc/ssl/certs/ca-certificates.crt
account default
host $SMTP_HOST
port ${SMTP_PORT:-587}
user $SMTP_USER
password $SMTP_PASS
from $SMTP_FROM
EOF
  chown www-data /etc/msmtprc && chmod 600 /etc/msmtprc
fi

# 3. Rebuild index/menu/checkout.html from the persisted content
su -s /bin/sh www-data -c "php -r 'require \"$ROOT/admin/lib.php\"; publish(load_site());'"

exec docker-php-entrypoint "$@"
```

### 5.5 compose.yaml (on the server)

```yaml
services:
  web:
    image: ghcr.io/<your-github-user>/mrs-chows:${TAG:-latest}
    restart: unless-stopped
    env_file: .env            # SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM
    volumes:
      - content:/var/www/html/content
      - uploads:/var/www/html/assets/uploads
    networks: [proxy]
    labels:                   # Traefik example; drop these if you use Caddy
      - traefik.enable=true
      - traefik.http.routers.mrschows.rule=Host(`mrschowskitchen.co.za`)
      - traefik.http.routers.mrschows.tls.certresolver=le
volumes:
  content:
  uploads:
networks:
  proxy:
    external: true
```

Caddy alternative (one line in your Caddyfile): `mrschowskitchen.co.za { reverse_proxy web:80 }`. Caddy sends `X-Forwarded-For` and `X-Forwarded-Proto` by default.

The domain above is a placeholder: use whatever Mrs Chow's registers.

### 5.6 GitHub Actions CI/CD

`.github/workflows/deploy.yml`: lint, build, smoke-test, push to GHCR, then deploy over SSH.

```yaml
name: Build & deploy
on:
  push:
    branches: [main]
  pull_request:

permissions:
  contents: read
  packages: write

env:
  IMAGE: ghcr.io/${{ github.repository_owner }}/mrs-chows

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: gd, mbstring
      - name: Lint every PHP file
        run: find site -name '*.php' -print0 | xargs -0 -n1 php -l
      - name: Templates render
        run: php -r 'require "site/admin/lib.php"; foreach (["home","menu","checkout"] as $p) { if (strlen(render_site(load_site(), false, $p)) < 1000) exit(1); }'

  build:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: docker/setup-buildx-action@v3
      - uses: docker/login-action@v3
        if: github.event_name == 'push'
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}
      - name: Build (load locally for the smoke test)
        uses: docker/build-push-action@v6
        with:
          context: .
          load: true
          tags: ${{ env.IMAGE }}:${{ github.sha }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
      - name: Smoke test
        run: |
          docker run -d --name t -p 8080:80 ${{ env.IMAGE }}:${{ github.sha }}
          for i in $(seq 1 20); do curl -fsS localhost:8080/ >/dev/null && break; sleep 1; done
          curl -fsS localhost:8080/ | grep -q "Mrs Chow"
          curl -fsS localhost:8080/menu.html >/dev/null
          curl -fsS "localhost:8080/order.php?action=token" | grep -q '{'
          test "$(curl -s -o /dev/null -w '%{http_code}' localhost:8080/content/site.php)" = "403"
          docker rm -f t
      - name: Push
        if: github.event_name == 'push'
        run: |
          docker tag ${{ env.IMAGE }}:${{ github.sha }} ${{ env.IMAGE }}:latest
          docker push ${{ env.IMAGE }}:${{ github.sha }}
          docker push ${{ env.IMAGE }}:latest

  deploy:
    needs: build
    if: github.event_name == 'push'
    runs-on: ubuntu-latest
    environment: production        # add a required reviewer here if you want a manual gate
    steps:
      - uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.DEPLOY_HOST }}
          username: ${{ secrets.DEPLOY_USER }}
          key: ${{ secrets.DEPLOY_SSH_KEY }}
          script: |
            cd /srv/mrs-chows
            echo "${{ secrets.GITHUB_TOKEN }}" | docker login ghcr.io -u ${{ github.actor }} --password-stdin
            TAG=${{ github.sha }} docker compose pull
            TAG=${{ github.sha }} docker compose up -d
            docker image prune -f
```

**Secrets to add** (repo → Settings → Secrets): `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`. The SMTP values live in `.env` on the server, not in GitHub.

The smoke test also proves `content/` returns **403**. If `AllowOverride` ever breaks, the build fails instead of exposing the site's data.

> If your other sites already share a deploy pattern (a reusable workflow, Watchtower, Coolify/Dokploy or Portainer webhooks), swap the `deploy` job for that and keep the `test` and `build` jobs as they are.

### 5.7 Rollback, backups and monitoring

- **Rollback:**
  - Every deploy is tagged with the commit SHA. To roll back, run `TAG=<old-sha> docker compose up -d` on the server, or re-run an older workflow.
  - Content isn't affected, because it lives in the volumes.
- **Backups:**
  - The two volumes are the whole business state: content, the admin password, orders and uploads.
  - Nightly cron on the host:
    ```bash
    docker run --rm -v mrs-chows_content:/c -v mrs-chows_uploads:/u -v /srv/backups:/b alpine \
      tar czf /b/mrs-chows-$(date +%F).tgz -C / c u
    ```
  - Ship those archives off-box (rclone to S3/B2/Drive) and keep 14 days.
  - The admin's own Backups tab only covers content edits.
- **Monitoring:** run an uptime check on `/` and `/order.php?action=token`, for example with UptimeRobot or Uptime Kuma. If ordering is down, they lose money.
- **First-run checklist:**
  1. Open `/admin` immediately and set the password.
  2. Enter prices, the order email and the WhatsApp number.
  3. Add the Turnstile keys.
  4. Place a test order, confirm the email arrives, then cancel the order.

### 5.8 Known limits

- **Single instance by design.** Don't scale above 1 replica.
- **Admin sessions live in the container.** A redeploy logs the owner out, which is harmless. Mount a volume on the session path if that becomes annoying.
- **Email depends on the SMTP relay.** Set SPF/DKIM for the sending domain, or order emails will land in spam.

---

## 6. WordPress variant (if the client prefers it)

`ink-and-wok-wordpress-theme/inkwok` is a classic theme with:
- Customizer fields
- a `dish` post type
- a Cart page template
- an **Orders** post type, with emails sent through `wp_mail`

(The Mrs Chow's child theme `inkwok-mrs-chows` was dropped in 2.4; this section only describes the generic Ink & Wok theme.)

Docker for it is a different shape:
- `wordpress:php8.3-apache` plus `mariadb:11`
- the theme baked into the image under `wp-content/themes/`
- volumes for `wp-content/uploads` and the database
- the same GitHub Actions pattern (lint → build → push → SSH deploy)
- an SMTP plugin, or msmtp as above, for `wp_mail`

**Recommendation: deploy the HTML + admin build.** It's faster and needs no database, plugin updates or security patching cycle, and the admin already covers everything Mrs Chow's needs to edit.
