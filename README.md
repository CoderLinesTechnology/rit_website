# RIT Foundation Ghana: website and administration dashboard

The public website and staff dashboard for RIT Foundation Ghana LBG, a Ghanaian nonprofit that
provides skills training for young women, girls and youth. Target domain:
`https://www.ritfoundationglobal.com`.

It is written in plain **PHP 8.2+** with **MySQL / MariaDB**. There is **no framework, no Composer
and no runtime dependency**, and nothing is loaded from a CDN: fonts, icons, CSS and JavaScript are
all served from this project. The site sets no analytics or advertising cookies.

Documentation map:

| Document | For |
|---|---|
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Putting the site live on cPanel hosting, step by step |
| [docs/HANDOVER.md](docs/HANDOVER.md) | Plain-language guide for Foundation staff |
| [docs/MEDIA-IMPORT.md](docs/MEDIA-IMPORT.md) | Getting approved photos onto the site |
| [docs/INCOMPLETE.md](docs/INCOMPLETE.md) | Open items and external dependencies |
| [docs/COMPLIANCE.md](docs/COMPLIANCE.md) | Data protection and other obligations, and where the code meets them |
| [docs/PAYMENTS.md](docs/PAYMENTS.md) | How online donations work and how to add a payment provider |
| [docs/DEVELOPER.md](docs/DEVELOPER.md) | Code conventions for developers |
| [docs/TESTING.md](docs/TESTING.md) | Test report (written separately; this README does not repeat its results) |

---

## 1. Features

### Public website

- **Pages:** Home, About, Programs (list and detail), Impact, Get Involved, Gallery (albums, lightbox),
  News and success stories (categories, detail pages), Contact, Donate, Privacy notice, Terms.
- **Forms:** training application (with optional PDF/JPG/PNG attachment and guardian consent for
  under-18s), volunteer application, partnership enquiry, contact message. Each form has a privacy
  acknowledgement, spam protection (honeypot and rate limit) and a reference number on the thank-you page.
- **Donations:** the page is built, but **no payment provider is configured**. It shows the
  administrator's notice and any offline giving instructions; nothing can be charged.
- **Search engines:** `/sitemap.xml`, `/robots.txt`, per-page titles and descriptions, share images,
  structured data. Only a production site on its real domain is indexable.

### Administration dashboard (`/admin`)

- Dashboard with figures, charts, recent activity and (for super administrators) a setup checklist.
- Programs and program categories; news articles and news categories; gallery albums and photos;
  impact figures; team profiles and history milestones.
- "Website content" settings (page texts, photos, contact details, legal pages), social links, SEO.
- Private submissions: training applications (status workflow, notes, attachments, duplicate
  warning), volunteers, partnership enquiries, contact messages.
- Donations view (empty while payments are off), email log, audit log, administrator accounts.
- Three roles (see [section 8](#8-first-administrator-and-roles)) and an in-dashboard Help page.

---

## 2. Requirements

| Requirement | Detail |
|---|---|
| PHP | 8.2 or newer (tested on 8.2.4 and 8.5) |
| Extensions | `pdo_mysql`, `mbstring`, `gd` (JPEG and PNG; WebP optional, the site falls back to JPEG), `fileinfo`, `openssl`, `session`, `json`. `curl` is only needed once a payment provider is added. `intl` is optional. |
| Database | MySQL 8.0+ or MariaDB 10.4+ (`utf8mb4_unicode_ci`) |
| Web server | Apache 2.4 with `mod_rewrite` (and ideally `mod_headers`, `mod_deflate`). The `.htaccess` files do the routing and protect private folders. |
| Hosting | Document root pointing at `public/` (recommended). Other layouts are covered in [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). |
| Shell access | Needed for the first administrator (or use the browser setup), `scripts/migrate.php`, and cron jobs. cPanel Terminal or SSH is enough. |

---

## 3. Local quick start

### 3.1 Create the databases

With XAMPP (or any MySQL/MariaDB), create two empty databases with `utf8mb4_unicode_ci`: one for
development and one for the automated tests (see [docs/TESTING.md](docs/TESTING.md); the test
database name used by `.env.testing` is `rit_foundation_test`).

```sql
CREATE DATABASE rit_foundation_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE rit_foundation_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3.2 Import the data (local development only)

Import in this order (phpMyAdmin Import tab, or the command line):

```bash
mysql -u root -p rit_foundation_dev < database/schema.sql
mysql -u root -p rit_foundation_dev < database/seed.sql
mysql -u root -p rit_foundation_dev < database/sample-content.sql   # LOCAL PREVIEW ONLY
```

`sample-content.sql` is fictional placeholder content (rows are marked "[Sample]"). **Never import
it on the live site.** The test suite loads its own schema into `rit_foundation_test`; see
[docs/TESTING.md](docs/TESTING.md).

### 3.3 Configure and run

```bash
cp .env.example .env
php scripts/generate-key.php        # prints a value; paste it into .env as APP_KEY=...
```

Edit `.env` for local use:

```
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_FORCE_HTTPS=false
DB_HOST=127.0.0.1
DB_NAME=rit_foundation_dev
DB_USER=root
DB_PASS=
MAIL_DRIVER=log
```

Start the built-in server and create the first administrator:

```bash
php -S localhost:8000 -t public public/index.php
php scripts/create-admin.php
```

Open `http://localhost:8000` and sign in at `http://localhost:8000/admin/login`. With XAMPP you may
instead point an Apache virtual host at the `public/` folder. With `MAIL_DRIVER=log`, outgoing
emails are written to `storage/logs/mail.log` instead of being sent.

---

## 4. Project structure

```
.
├── .env.example          Configuration template (copy to .env; never commit .env)
├── .htaccess             Used only when the whole project sits in the web root (option B)
├── app/
│   ├── bootstrap.php     Autoloader, .env and config loading, error handler
│   ├── helpers.php       Template and utility functions (e(), url(), setting(), ...)
│   ├── Core/             Router, Request, Response, Session, Csrf, Auth, Validator, RateLimiter, Database, ...
│   ├── Controllers/      Site\* (public pages) and Admin\* (dashboard)
│   ├── Models/           Table gateways (prepared statements only)
│   └── Services/         Settings, FormGuard, Notifier, ImageUploader, DocumentStore, Mail\*, Payments\*
├── config/               app.php, database.php, mail.php, payments.php, settings.php
├── database/             schema.sql, seed.sql, sample-content.sql (local only), migrations/
├── docs/                 Documentation (blocked from the web by docs/.htaccess)
├── public/               The only folder meant to be web-accessible
│   ├── index.php         Front controller
│   ├── .htaccess         Routing, blocked file types, caching, security header
│   ├── assets/           Self-hosted CSS, JS, fonts, icons
│   └── uploads/          Public images written by the app (script execution disabled)
├── resources/views/      PHP templates: layouts, partials, pages, admin, errors
├── routes/web.php        Every route and its middleware
├── scripts/              Command-line tools (see section 12)
├── storage/              logs/, sessions/, cache/, private/applications/ (never web-accessible)
└── tests/                Automated tests (php tests/run.php)
```

---

## 5. Configuration (`.env`)

Copy `.env.example` to `.env`. Values already present in the real server environment take
precedence over `.env`. Placeholders such as `REPLACE_ME` or `changeme` are treated as "not set"
for secrets.

| Key | Meaning | Production value |
|---|---|---|
| `APP_ENV` | `production`, `staging`, `local` or `testing` | `production` |
| `APP_DEBUG` | Show error details | `false` (forced off in production regardless) |
| `APP_URL` | Public base URL, used in links, sitemap, emails and redirects | `https://www.ritfoundationglobal.com` |
| `APP_KEY` | Random secret used to hash rate-limit keys. In production the site refuses to serve (503) without it | Output of `php scripts/generate-key.php` |
| `APP_FORCE_HTTPS` | Redirect HTTP to `APP_URL` and send HSTS (HSTS only when `APP_ENV=production`) | `true` once SSL works |
| `APP_INDEXABLE` | Allow search engines (production only). When `false`, `robots.txt` disallows everything and a `noindex` header is sent | `true` on the real domain; `false` on any staging copy |
| `DB_HOST` | Database host | `localhost` on cPanel |
| `DB_PORT` | Database port | `3306` |
| `DB_SOCKET` | Optional Unix socket path (used instead of host/port when set) | Blank unless the host requires it |
| `DB_NAME`, `DB_USER`, `DB_PASS` | Database and the **least-privilege** app user | cPanel names, e.g. `cpuser_rit`, `cpuser_rit_app` |
| `SESSION_NAME` | Session cookie name | `rit_session` |
| `SESSION_IDLE_MINUTES` | Admin sign-out after inactivity | `30` |
| `SESSION_ABSOLUTE_HOURS` | Admin sign-out after this long regardless | `8` |
| `UPLOAD_IMAGE_MAX_MB` | Largest image accepted | `8` (must fit inside PHP `upload_max_filesize` and `post_max_size`) |
| `UPLOAD_DOCUMENT_MAX_MB` | Largest applicant attachment | `5` |
| `MAIL_DRIVER` | `none`, `log`, `mail` or `smtp` | `smtp` (see [section 9](#9-email)) |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION` | SMTP server; `MAIL_ENCRYPTION` is `tls` (STARTTLS, port 587), `ssl` (port 465) or `none` | e.g. `mail.ritfoundationglobal.com`, `465`, `ssl` |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | SMTP login (the full mailbox address on cPanel) | Mailbox credentials |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Sender shown on emails | An address on the site's own domain; `RIT Foundation Ghana` |
| `MAIL_ADMIN_ADDRESS` | Fallback staff-notification recipient if the admin setting is empty | Optional |
| `PAYMENT_PROVIDER` | `none` until a provider is integrated | `none` |
| `PAYMENT_PUBLIC_KEY`, `PAYMENT_SECRET_KEY`, `PAYMENT_WEBHOOK_SECRET` | Provider keys | Blank until a provider exists |
| `SETUP_TOKEN` | Enables the one-time browser setup of the first administrator | Blank (or a 32+ character random string only while setting up; then remove it) |
| `TRUSTED_PROXIES` | Comma-separated proxy IPs whose `X-Forwarded-For` / `X-Forwarded-Proto` headers are trusted | Blank unless the site sits behind a proxy or load balancer |
| `RETENTION_SUBMISSION_MONTHS` | Months before `purge-old-records.php` deletes submissions | `24` (or the period approved by the Foundation) |
| `RETENTION_AUDIT_MONTHS` | Months before audit entries are purged | `24` |

Not in `.env`: the application timezone is fixed to `Africa/Accra`; images are limited to 1920 px
(thumbnails 720 px) and 40 megapixels (`config/app.php`). Everything editable by staff (page texts,
contact details, logo, legal pages) lives in the database through **Admin > Website content**,
whose fields are defined in `config/settings.php`.

---

## 6. Database

Import order on a **new** database (phpMyAdmin or `mysql`), always with `utf8mb4`:

1. `database/schema.sql`: creates all tables and records migration `0001_initial`.
2. `database/seed.sql`: required starting content (program categories, six program records, news
   categories, gallery albums, the one verified impact figure, social links, history milestones,
   the founder profile, default settings).
3. `database/sample-content.sql`: **local development only. Never in production.**

`schema.sql` contains a commented-out destructive reset block; it is never run in production.

**Least-privilege user.** The application user needs only `SELECT, INSERT, UPDATE, DELETE` on its
own database. Create a separate privileged user (all privileges on that database) for imports and
migrations, and use it only for those. The cPanel steps are in [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md), steps 2 and 3.

**Migrations.** Later schema changes are numbered files `NNNN_description.sql` in
`database/migrations/` (the folder currently contains only its `README.md`; no migration files
beyond the initial schema exist yet). Apply pending ones with a privileged user:

```bash
php scripts/migrate.php --status                       # list pending, apply nothing
DB_USER=privileged_user DB_PASS='...' php scripts/migrate.php
```

The runner skips versions already recorded in `schema_migrations`, so running it twice is safe.
Back up the database first.

---

## 7. Logging

Application errors go to `storage/logs/app-YYYY-MM-DD.log` and PHP errors to
`storage/logs/php-errors.log`. Visitors only ever see a generic error page. Logs must not contain
personal data; if you find any, report it as a defect.

---

## 8. First administrator and roles

No administrator account is shipped. Create the first one in either way.

**A. Command line (preferred).** Needs a terminal on the server:

```bash
php scripts/create-admin.php
php scripts/create-admin.php --name="Jane Doe" --email=jane@example.org --role=super_admin
```

The password is always asked for at a hidden prompt (or piped on standard input); it is never
accepted as an argument. Roles: `super_admin` (default), `content_editor`, `applications_officer`.
The password must be 12 to 72 characters, must not contain the email name or the person's name, and
must not be trivially guessable.

**B. One-time browser setup.** Set `SETUP_TOKEN` in `.env` to a long random string (the example
file asks for at least 32 characters), then open `https://your-domain/setup`, enter the token, a
name, an email address and a password. This creates a **super administrator** and signs you in.
The page exists only while a token is configured **and** no administrator exists yet; otherwise
`/setup` returns an ordinary 404. Wrong tokens are audited and limited to 5 attempts per hour per
address. **Remove `SETUP_TOKEN` from `.env` immediately afterwards** (the dashboard checklist warns
while it is still set).

Roles, from `app/Core/Auth.php`:

| Permission | Super administrator | Content editor | Applications officer |
|---|:-:|:-:|:-:|
| Programs, news, gallery, impact, team (`content.manage`) | Yes | Yes | No |
| Website content, social links, SEO (`settings.content`) | Yes | Yes | No |
| Training applications, volunteers, partnerships, contact messages (`submissions.manage`) | Yes | No | Yes |
| Donations view (`donations.view`) | Yes | No | No |
| System settings: Donations, Email notifications, Privacy & terms (`settings.system`) | Yes | No | No |
| Administrator accounts and the email log (`admins.manage`) | Yes | No | No |
| Audit log (`audit.view`) | Yes | No | No |

All roles can use Help and My account.

---

## 9. Email

| `MAIL_DRIVER` | Behaviour |
|---|---|
| `none` | Nothing is sent. Messages are recorded in the email log as **skipped**. Password-reset emails cannot be sent. This is the default. |
| `log` | Development only. Messages are written to `storage/logs/mail.log`. Not delivered. |
| `mail` | PHP `mail()`. Only reliable if the host has configured it properly. |
| `smtp` | Authenticated SMTP with certificate verification (recommended). Refuses to send a password without TLS/SSL. |

**SMTP settings for a cPanel mailbox.** Create a mailbox (for example `no-reply@ritfoundationglobal.com`)
in cPanel > Email Accounts, then:

```
MAIL_DRIVER=smtp
MAIL_HOST=mail.ritfoundationglobal.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=no-reply@ritfoundationglobal.com
MAIL_PASSWORD=the-mailbox-password
MAIL_FROM_ADDRESS=no-reply@ritfoundationglobal.com
MAIL_FROM_NAME="RIT Foundation Ghana"
```

Port 587 with `MAIL_ENCRYPTION=tls` also works. The server certificate must be valid for the
`MAIL_HOST` name (certificate checking is on), so use the exact host name shown under cPanel > Email
Accounts > Connect Devices. These values are examples; the real mail host name and credentials have
not been verified.

**Test it:**

```bash
php scripts/test-mail.php you@example.org
```

| Output | Meaning |
|---|---|
| `Accepted by the mail server (this does not prove delivery; ...)` | The mail server took the message (exit code 0). Check the inbox **and spam folder**. |
| `Not sent: ...` | The driver is `none`, or SMTP is not fully configured (exit code 1). |
| `Failed: ...` | Connection, TLS or login problem. The text says which (exit code 1). Passwords are never printed. |

**Outbox and cron.** Form submissions never wait for email. Messages are saved in the `email_outbox`
table, a first attempt is made after the response is sent, and `scripts/process-email-queue.php`
(run from cron every 5 to 10 minutes) sends anything still queued and retries failures, up to 3
attempts in total. Message bodies are erased from the table once sent, skipped or finally failed.

**"Handed off" is not "delivered".** The email log (Admin > Email log, super administrators)
shows `queued`, `handed_off`, `failed` or `skipped`. `handed_off` only means the mail server
accepted the message. It may still be filtered as spam or bounce later. For better delivery, set up
SPF and DKIM for the domain (cPanel > Email Deliverability) and send from an address on that domain.

Staff notification emails contain only reference numbers, never applicants' details. Accepting an
application does **not** email the applicant; staff contact them directly.

---

## 10. Cron jobs

| Frequency | Command | Purpose |
|---|---|---|
| Every 5 to 10 minutes | `php /path/to/scripts/process-email-queue.php` | Send and retry queued emails |
| Monthly | `php /path/to/scripts/purge-old-records.php` | Delete expired data (retention) |

Always run the purge with `--dry-run` first and read the counts:

```bash
php scripts/purge-old-records.php --dry-run
php scripts/purge-old-records.php
```

The purge deletes training applications, volunteer applications, partnership enquiries and contact
messages older than `RETENTION_SUBMISSION_MONTHS` (and their private attachments and their email
outbox rows), audit entries older than `RETENTION_AUDIT_MONTHS`, finished outbox rows older than 6
months, and password-reset tokens expired for more than a day. Cron lines for cPanel are in
[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

---

## 11. Security summary

Each control and the file that implements it:

| Control | Implemented in |
|---|---|
| Passwords hashed with `password_hash()`, 12 to 72 character policy, rehash on login | `app/Core/Auth.php` |
| Sign-in throttling (5 failures per account and address, 20 per address, 15-minute lockout); same response for unknown or disabled accounts; constant-time dummy hash | `app/Core/Auth.php`, `app/Core/RateLimiter.php` |
| Session id regenerated at sign-in; idle (30 min) and absolute (8 h) expiry; sessions revoked everywhere when the password, role or active status changes | `app/Core/Auth.php`, `app/Core/Session.php` |
| Cookie flags: `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, strict mode, sessions stored in `storage/sessions`; session started only on form and admin pages (public pages set no cookie) | `app/Core/Session.php` |
| CSRF token on every POST (the signed payment webhook is the only exemption); constant-time comparison | `app/Core/Csrf.php`, `app/Core/Router.php`, `routes/web.php` |
| Role-based permission middleware on every admin route; controllers load records by id and return 404 when missing | `app/Core/Auth.php`, `routes/web.php`, `app/Controllers/Admin/` |
| SQL injection: PDO prepared statements only, emulation off | `app/Core/Database.php`, `app/Models/` |
| Output escaping (`e()`); safe-markup renderer for formatted text | `app/helpers.php`, `app/Core/Text.php` |
| Strict Content-Security-Policy (`script-src 'self'; style-src 'self'`, `frame-ancestors 'none'`), `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy` | `app/Core/Response.php` |
| HTTPS redirect to `APP_URL`; HSTS (`max-age=31536000`) when `APP_FORCE_HTTPS=true` in production | `app/Core/Application.php`, `app/Core/Response.php` |
| Admin and setup pages: `no-store` and `noindex`; staging copies kept out of search engines | `app/Core/Application.php`, `app/Controllers/Site/SeoController.php` |
| Form spam and abuse: honeypot, per-form rate limits, one-time form nonce that turns double submits into one record | `app/Services/FormGuard.php`, `app/Core/RateLimiter.php` |
| Image uploads: real type checked with `finfo` and `getimagesize`, fully decoded and re-encoded with GD (drops EXIF/GPS and any embedded payload), random file names, pixel limit, size limit | `app/Services/ImageUploader.php` |
| Applicant attachments: PDF/JPG/PNG only, stored outside the web root, served only to permitted staff with `no-store`, every download audited | `app/Services/DocumentStore.php`, `app/Controllers/Admin/ApplicationController.php` |
| Uploaded files can never execute: scripts disabled in `public/uploads`, only app-generated image names served, restrictive CSP on that folder | `public/uploads/.htaccess` |
| Private folders blocked: `app`, `config`, `database`, `docs`, `resources`, `routes`, `scripts`, `storage` carry deny rules; sensitive file types blocked in `public/` | per-folder `.htaccess`, `public/.htaccess`, root `.htaccess` |
| Audit log of admin actions (no applicant names or emails in entries) | `app/Core/AuditLog.php` |
| Generic error pages; details only in private logs; debug forced off in production | `app/Core/ErrorHandler.php`, `config/app.php` |
| One-time setup page hidden (404) unless configured and unused; token compared in constant time; rate limited | `app/Controllers/Admin/SetupController.php` |
| Forwarded headers trusted only from listed proxies | `app/Core/Request.php`, `TRUSTED_PROXIES` |
| Payments: no card data handled; hosted checkout design, server-side verification, signed webhooks (inactive) | `app/Services/Payments/`, [docs/PAYMENTS.md](docs/PAYMENTS.md) |
| Password reset: single-use, expiring (60 minutes) email links, rate limited, non-revealing responses | `app/Controllers/Admin/PasswordResetController.php` |

This table describes the code; it is not a penetration-test result. Test outcomes are in
[docs/TESTING.md](docs/TESTING.md).

---

## 12. Command-line tools (`scripts/`)

All scripts refuse to run from a web request.

| Script | Usage |
|---|---|
| `generate-key.php` | Prints a random `APP_KEY` |
| `create-admin.php` | `[--name=... --email=... --role=super_admin\|content_editor\|applications_officer]`, hidden password prompt |
| `migrate.php` | `[--status]` applies pending migrations; run with a privileged DB user |
| `process-email-queue.php` | Sends up to 50 queued or retryable emails and prints the counts |
| `test-mail.php` | `you@example.org` sends one test message |
| `purge-old-records.php` | `[--dry-run]` retention purge |
| `import-gallery.php` | `--album=<slug> --dir=<folder> [--alt=... --taken-on=YYYY-MM-DD --publish --consent-confirmed]`; see [docs/MEDIA-IMPORT.md](docs/MEDIA-IMPORT.md) |

---

## 13. Data protection summary

The site collects personal data only through its forms: training applications (including under-18s
and their guardians), volunteer and partnership enquiries, contact messages, and later donations. It
stores an age **range** (not a date of birth) and a region (not a full address), keeps no IP address
or browser string with submissions, and uses one essential session cookie only. Staff emails carry
references, not personal details. Retention, deletion and photo-consent controls exist in the code
(consent ticks on gallery photos, success stories and team profiles; deletion of any submission with
its attachment; the retention purge).

Legal decisions the Foundation must still make (Data Protection Commission registration, review of
the privacy notice and terms, named processors, photo licences and consent) are listed in
[docs/INCOMPLETE.md](docs/INCOMPLETE.md). The full register is [docs/COMPLIANCE.md](docs/COMPLIANCE.md).
It is engineering documentation, not legal advice.

---

## 14. Backups and restore

Back up regularly (at least weekly; daily if applications are open):

| What | Why |
|---|---|
| The database | All content, submissions and settings |
| `public/uploads/` | Uploaded public images |
| `storage/private/applications/` | Applicants' private attachments (treat as personal data) |
| `.env` | Configuration and secrets (store the copy securely, separate from the site) |

Database dump:

```bash
mysqldump --single-transaction --default-character-set=utf8mb4 -u DBUSER -p DBNAME > rit-$(date +%F).sql
```

Restore: put the files back (same paths and permissions), create an empty database with
`utf8mb4_unicode_ci`, import the dump in phpMyAdmin or with `mysql -u USER -p DBNAME < dump.sql`,
check `.env`, then smoke-test. Backups contain personal data: keep them private and delete old
ones. The cPanel steps are in [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md), step 14.

---

## 15. Updating

1. Back up the database and the folders above.
2. Upload the changed files. Do **not** overwrite `.env`, `public/uploads/` or `storage/`.
3. If the release adds files under `database/migrations/`, run `php scripts/migrate.php` with the privileged database user (see [section 6](#6-database)).
4. There is no cache to clear. Smoke-test the home page, a form, and the admin sign-in.

---

## 16. Troubleshooting

| Symptom | What to check |
|---|---|
| **500 error page** | Read the newest file in `storage/logs/` (`app-YYYY-MM-DD.log`, `php-errors.log`). Common causes: wrong `DB_*` values, missing PHP extension, `storage/` not writable, PHP older than 8.2. |
| **503 "being configured"** | In production `APP_KEY` is missing or a placeholder. Run `php scripts/generate-key.php` and set it. |
| **419 on form or login** | The session or CSRF token did not match. Check that cookies are allowed, `storage/sessions/` is writable, the `APP_URL` host matches the address in the browser (www versus non-www), and HTTPS is consistent. Reload the page and try again. |
| **Redirect loop after enabling HTTPS** | `APP_FORCE_HTTPS=true` but PHP does not see HTTPS (a proxy terminates SSL). Set `TRUSTED_PROXIES` to the proxy address, or fix the server's `HTTPS` variable. |
| **Photo uploads fail** | PHP `upload_max_filesize` or `post_max_size` is smaller than the files (a batch of 20 photos can exceed it). Raise them in cPanel MultiPHP INI Editor, and keep `UPLOAD_IMAGE_MAX_MB` within them. Also check that `public/uploads/` is writable and the `gd` extension is on. |
| **Emails not arriving** | Admin > Email log: `skipped` means the driver is `none`; `failed` shows the error; `handed_off` means the server accepted it (check spam, SPF/DKIM). Run `php scripts/test-mail.php you@example.org`. Make sure the cron job exists. |
| **Apply page says applications are closed** | Three things must all be true: the program is published, today is inside its dates, and Settings > Training applications "Accept training applications" is on. |
| **Private folders readable in a browser** | Visit `/.env`, `/app/bootstrap.php`, `/storage/`. Each must return 403 or 404. If not, fix the document root or `.htaccess` handling immediately ([docs/DEPLOYMENT.md](docs/DEPLOYMENT.md), steps 6 and 7). |
| **Cannot sign in** | Five wrong attempts lock that account and address for 15 minutes. Another super administrator can reset the password (Administrators). |
