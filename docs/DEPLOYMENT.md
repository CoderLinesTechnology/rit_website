# Deployment guide: cPanel hosting

Step-by-step instructions for putting the RIT Foundation Ghana website live on cPanel shared
hosting. Replace `USER` with the cPanel account name and `cpuser_` with the account prefix that
cPanel adds to database names and users. Target domain: `https://www.ritfoundationglobal.com`.

Three layouts are supported. **Use A if the host lets you change the document root.**

| Layout | Document root | When to use |
|---|---|---|
| **A (recommended)** | `public/` | Host allows a custom document root (steps 4 and 6) |
| **B** | Project root (`public_html`) | Host will not change the document root; the root `.htaccess` protects private folders (step 7) |
| **C** | `public_html` holds a copy of `public/`; the app lives outside it | Host will not change the document root and you want private folders outside the web root (step 7) |

Before starting, have ready: the cPanel login, the domain pointing at the hosting account, the
mailbox credentials (step 10), and the project files.

> Not verified yet: the hosting plan, DNS, SSL and every credential. See
> [What is not verified yet](#what-is-not-verified-yet) at the end. Layouts A and B were run under
> Apache 2.4 during development; layout C and the cPanel screens below have not been tried on the
> real host, so follow the verification steps after each layout.

---

## 1. PHP version and extensions

1. cPanel > **Select PHP Version** (CloudLinux) or **MultiPHP Manager**. Choose **PHP 8.2 or newer**
   for the domain.
2. In **Select PHP Version > Extensions** (or the host's equivalent), make sure these are ticked:
   `pdo_mysql` (with `mysqlnd`), `mbstring`, `gd`, `fileinfo`, `openssl`, `json`, `session`.
   `intl` is optional. `curl` is only needed once a payment provider is added.
3. In **MultiPHP INI Editor** (or Select PHP Version > Options) set values that allow photo uploads:

   | Directive | Suggested value | Why |
   |---|---|---|
   | `upload_max_filesize` | `10M` or more | Must be at least `UPLOAD_IMAGE_MAX_MB` (default 8) |
   | `post_max_size` | `64M` or more | A gallery batch is one request of up to 20 photos |
   | `memory_limit` | `256M` or more | Photos are decoded in memory before being resized |
   | `max_execution_time` | `120` or more | The gallery upload allows up to 180 seconds for a batch |

4. Check from **Terminal** (the command-line PHP can differ from the web PHP):

   ```bash
   php -v
   php -m | grep -i -E "pdo_mysql|mbstring|gd|fileinfo|openssl|json|session"
   ```

   If `php -v` shows an older version, use the full path of the right one in every command below,
   for example `/opt/cpanel/ea-php82/root/usr/bin/php` (and `/usr/local/bin/php` in cron lines if
   that is the correct one on your host; confirm with `which php`).

---

## 2. MySQL database and users

cPanel > **MySQL Databases**.

1. **Create New Database:** for example `rit` (becomes `cpuser_rit`). cPanel creates it as UTF-8;
   the import in step 3 sets the exact collation.
2. **Add New User** twice, each with a long random password (save them in a password manager):
   - **Application user**, for example `cpuser_ritapp`: used by the website in `.env`.
   - **Privileged user**, for example `cpuser_ritadmin`: used only for schema imports and
     `scripts/migrate.php`.
3. **Add User To Database** for each user, then set privileges:
   - Application user: tick **only** `SELECT`, `INSERT`, `UPDATE`, `DELETE`. Do not tick ALL PRIVILEGES.
   - Privileged user: **ALL PRIVILEGES**.
4. Note: phpMyAdmin (step 3) signs in with your cPanel account, which already has full rights to
   all your databases, so the import itself does not need the privileged user.

The application never needs `CREATE`, `ALTER` or `DROP`. If a migration is needed later it is run
with the privileged user (step 15).

---

## 3. Import the schema and starting content

cPanel > **phpMyAdmin**.

1. Click the new database (`cpuser_rit`) in the left column.
2. **Import** tab > choose `database/schema.sql` > Character set **utf-8** (utf8mb4 where offered)
   > **Import**. Wait for the green success message.
3. **Import** `database/seed.sql` the same way.
4. Check that the **Structure** tab lists the tables (25 including `schema_migrations`), and that
   `site_settings`, `programs`, `news_categories` and `gallery_categories` contain rows.

**Never import `database/sample-content.sql` on the live site.** It is fictional placeholder content
for local development only. If it was imported by mistake, delete the "[Sample]" rows (or drop all
tables and start again before any real data exists).

Do not run the commented-out reset block at the top of `schema.sql` in production.

---

## 4. Upload the files

Upload the project to a folder **outside** `public_html`, for example `/home/USER/rit-app/`
(layout A and C). For layout B, upload into `public_html` (step 7).

**Option 1: zip and extract (fastest).**

1. On your computer, zip the project folder. Leave out `.env`, `.env.testing`, `.git`, `tests/` (optional)
   and the contents of `storage/logs`, `storage/sessions` and `public/uploads`.
2. cPanel > **File Manager** > open `/home/USER/` > **Upload** the zip > select it > **Extract**.
3. Delete the zip afterwards, and make sure the files ended up directly in `rit-app/` (not in an
   extra nested folder).

**Option 2: SFTP.** Connect with FileZilla or similar (SFTP, port 22 if the host allows, else FTPS)
and upload the folder.

Make sure these folders exist and are writable by PHP (see step 8): `storage/logs`,
`storage/sessions`, `storage/cache`, `storage/private/applications`, `public/uploads`. The
`.htaccess` files inside folders (including dotfiles) must be uploaded; turn on **Show Hidden Files**
in File Manager Settings to check.

---

## 5. Create `.env`

1. In File Manager, copy `.env.example` to `.env` in the project root (`/home/USER/rit-app/.env`).
2. Edit `.env`. Production values:

   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://www.ritfoundationglobal.com
   APP_KEY=<64 hex characters from: php scripts/generate-key.php>
   APP_FORCE_HTTPS=true          # set true only after SSL works (step 11)
   APP_INDEXABLE=true            # see step 12

   DB_HOST=localhost
   DB_PORT=3306
   DB_SOCKET=
   DB_NAME=cpuser_rit
   DB_USER=cpuser_ritapp
   DB_PASS=<application user password>

   SESSION_NAME=rit_session
   SESSION_IDLE_MINUTES=30
   SESSION_ABSOLUTE_HOURS=8

   UPLOAD_IMAGE_MAX_MB=8
   UPLOAD_DOCUMENT_MAX_MB=5

   MAIL_DRIVER=smtp              # see step 10
   MAIL_HOST=mail.ritfoundationglobal.com
   MAIL_PORT=465
   MAIL_ENCRYPTION=ssl
   MAIL_USERNAME=no-reply@ritfoundationglobal.com
   MAIL_PASSWORD=<mailbox password>
   MAIL_FROM_ADDRESS=no-reply@ritfoundationglobal.com
   MAIL_FROM_NAME="RIT Foundation Ghana"
   MAIL_ADMIN_ADDRESS=

   PAYMENT_PROVIDER=none
   PAYMENT_PUBLIC_KEY=
   PAYMENT_SECRET_KEY=
   PAYMENT_WEBHOOK_SECRET=

   SETUP_TOKEN=                  # only during step 9 if you use the browser setup
   TRUSTED_PROXIES=              # blank unless behind a proxy or load balancer
   RETENTION_SUBMISSION_MONTHS=24
   RETENTION_AUDIT_MONTHS=24
   ```

   Generate the key in Terminal: `cd ~/rit-app && php scripts/generate-key.php`.
   The host name, mailbox and database names above are examples; use the real ones from cPanel.
3. Protect the file: File Manager > right-click `.env` > **Change Permissions** > `600`
   (owner read/write only). If PHP runs as a different user than the file owner on your host and the
   site cannot read it, use `640`. Never `644` or `777`.
4. `.env` must never be uploaded to a public place, committed to version control, or emailed.

---

## 6. Point the document root at `public/` (layout A)

cPanel > **Domains** (older versions: **Addon Domains** / **Subdomains**).

1. Find `ritfoundationglobal.com` > **Manage** (or the pencil/edit icon).
2. Set **Document Root** to `rit-app/public` (that is `/home/USER/rit-app/public`). Save.
3. Make sure `www.ritfoundationglobal.com` points to the same document root (it normally follows the
   main domain, or add it as an alias).
4. For an **addon domain or subdomain**, the document root can be set freely when you create it.
   For the account's **primary** domain some hosts lock the document root to `public_html`; if so, ask
   the host to change it, or use layout B or C (step 7).
5. **Verify** (in a browser or `curl`):
   - `https://www.ritfoundationglobal.com/` shows the home page.
   - `https://www.ritfoundationglobal.com/.env`, `/app/bootstrap.php` and `/storage/` do **not** show
     content (403 or 404), because they are outside the document root.
   - `/assets/css/main.css` loads.

---

## 7. When the host will not change the document root

### Option B: whole project inside `public_html`

1. Upload (or extract) the **entire project** directly into `public_html/` so that `public_html/index.php`
   does **not** exist but `public_html/public/index.php` and `public_html/.htaccess` do.
2. The root `.htaccess` (included in the project) does three things:
   - denies requests for `app`, `config`, `database`, `resources`, `routes`, `scripts`, `storage`,
     `tests` and `docs` (HTTP 403);
   - denies dotfiles (such as `.env`) and files ending in `.sql .log .env .md .lock .json .sh`;
   - sends everything else into `public/`.
3. Create `.env` as in step 5 (inside `public_html/`). Set it to `600`.
4. **Verify. This is mandatory**, because protection depends on Apache honouring the `.htaccess` file
   (`AllowOverride` and `mod_rewrite`). Each of these must return **403**:
   - `https://www.ritfoundationglobal.com/.env`
   - `https://www.ritfoundationglobal.com/app/bootstrap.php`
   - `https://www.ritfoundationglobal.com/storage/`
   - `https://www.ritfoundationglobal.com/database/schema.sql`
   - `https://www.ritfoundationglobal.com/README.md`

   ```bash
   for p in .env app/bootstrap.php storage/ database/schema.sql config/app.php README.md; do
     printf '%s -> ' "$p"; curl -s -o /dev/null -w '%{http_code}\n' "https://www.ritfoundationglobal.com/$p"
   done
   ```

   If any returns 200, take the site offline immediately (remove the project files from `public_html`)
   and use layout C or ask the host to change the document root.

### Option C: split layout (application outside `public_html`)

1. Upload the whole project to `/home/USER/rit-app/` (steps 4 and 5). Keep its `public/` folder.
2. Copy the **contents** of `rit-app/public/` into `public_html/`: `index.php`, `.htaccess` and
   `assets/`.
3. Tell the front controller where the application is. `public/index.php` reads the environment
   variable `RIT_APP_ROOT` and falls back to the folder above itself. Choose **one**:
   - Add to `public_html/.htaccess` (at the top):

     ```apache
     SetEnv RIT_APP_ROOT /home/USER/rit-app
     ```

   - Or edit `public_html/index.php` and replace the line
     `$appRoot = getenv('RIT_APP_ROOT') ?: dirname(__DIR__);` with
     `$appRoot = '/home/USER/rit-app';`. This is more reliable on hosts where `SetEnv` is not passed
     through to PHP.
4. Uploads and assets need no extra step: `index.php` tells the application that its web root is
   the folder it runs from (`public_html`), so uploaded photos are written to `public_html/uploads/`
   and asset versions are read from `public_html/assets/`. Copy `public/uploads/.htaccess` into
   `public_html/uploads/` too — it blocks script execution there.
5. Command-line scripts (for example `scripts/import-gallery.php`) do not run through `index.php`, so
   give them the web root explicitly:

   ```bash
   RIT_PUBLIC_ROOT=/home/USER/public_html php /home/USER/rit-app/scripts/import-gallery.php --album=… --dir=…
   ```

   After an update, copy `rit-app/public/assets/` (and `index.php`, `.htaccess`) into `public_html/`
   again.
6. **Verify:** `/` loads; `/.env`, `/app/bootstrap.php` and `/storage/` return 403 or 404.

---

## 8. File permissions

Use the least that works. **Never `777`.**

| Item | Permission |
|---|---|
| Directories | `755` |
| Files | `644` |
| `.env` | `600` (or `640`) |
| `storage/` and everything below, `public/uploads/` | Writable by the PHP user: `755` when PHP runs as your cPanel user (suPHP, suEXEC, PHP-FPM per account; most cPanel hosts), otherwise `775` |

In Terminal, from the project folder:

```bash
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 600 .env
```

If uploading photos or saving sessions fails with "permission denied" and 755 is not enough on your
host, set `775` on `storage` and `public/uploads` (directories only) and try again. Do not go further.

---

## 9. Create the first administrator

No administrator account exists after import. Choose one method.

**Method 1: Terminal (preferred).** cPanel > **Terminal**:

```bash
cd ~/rit-app
php scripts/create-admin.php
```

Enter the full name, email address, then the password twice (typing is hidden). Use a long
passphrase (12 to 72 characters). The account is a **super administrator**. Sign in at
`https://www.ritfoundationglobal.com/admin/login`.

**Method 2: browser setup with `SETUP_TOKEN`.**

1. Put a random string of 32 or more characters in `.env`: `SETUP_TOKEN=...`
2. Open `https://www.ritfoundationglobal.com/setup`, enter the token, name, email and password.
3. **Immediately remove the token** from `.env` (set `SETUP_TOKEN=`). The dashboard checklist
   shows a warning while it is still present. The page already answers 404 once an administrator
   exists, but the token should not be left in the file.

Then create the other staff accounts in **Admin > Administrators** and give each person the lowest
role they need (see [HANDOVER.md](HANDOVER.md)).

---

## 10. Email

1. cPanel > **Email Accounts** > **Create** a mailbox on the domain, for example
   `no-reply@ritfoundationglobal.com`. Save the password.
2. In the same screen click **Connect Devices** for the mailbox to see the exact SMTP host name and
   ports. Typical values: SSL/TLS on port **465**, or STARTTLS on port **587**.
3. Set in `.env`:

   | SMTP choice | Settings |
   |---|---|
   | Port 465, implicit SSL | `MAIL_PORT=465`, `MAIL_ENCRYPTION=ssl` |
   | Port 587, STARTTLS | `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls` |

   Also set `MAIL_DRIVER=smtp`, `MAIL_HOST` (the host name from Connect Devices; its certificate
   must match, as the application verifies certificates), `MAIL_USERNAME` (the full mailbox address),
   `MAIL_PASSWORD`, and `MAIL_FROM_ADDRESS` (an address **on the domain**, for example the mailbox).
4. cPanel > **Email Deliverability**: check the domain and use **Repair** or **Install the suggested
   record** so that **SPF** and **DKIM** are valid. Without them, messages are likely to land in spam.
5. Test from Terminal:

   ```bash
   php scripts/test-mail.php you@example.org
   ```

   `Accepted by the mail server ...` means the server took the message; check the inbox **and spam**.
   It does not prove delivery. `Not sent:` means email is not configured; `Failed:` shows why.
6. Set the staff notification address in **Admin > Website content > Email notifications**
   (super administrators), then submit a test contact message and check the **Email log**.
7. Add the cron job below so queued and failed messages are retried.

---

## 11. HTTPS

1. cPanel > **SSL/TLS Status** > select `ritfoundationglobal.com` and `www.ritfoundationglobal.com` >
   **Run AutoSSL** (Let's Encrypt or cPanel certificate). Wait until both show a valid certificate.
2. Browse to `https://www.ritfoundationglobal.com` and confirm there is no browser warning.
3. In `.env` make sure `APP_URL=https://www.ritfoundationglobal.com` (https and `www`) and
   `APP_FORCE_HTTPS=true`. The application then redirects every `http://` request, and any request
   on another host name (such as the non-`www` address), to `APP_URL` with a permanent redirect.
4. Optional extra layer: `public/.htaccess` contains a commented block that redirects to HTTPS and
   to `www` at the web-server level. Remove the leading `#` from those five lines (three
   `RewriteCond` lines and two `RewriteRule` lines). Test again afterwards.
5. **HSTS.** In production with `APP_FORCE_HTTPS=true` the application sends
   `Strict-Transport-Security: max-age=31536000` (one year, no subdomains, no preload). The
   implication: once a browser has seen it, that browser will refuse plain HTTP for this host for a
   year, and **will block the site entirely if the certificate expires or breaks** (users cannot click
   through the warning). So:
   - turn `APP_FORCE_HTTPS` on only when HTTPS is working on the real domain;
   - keep AutoSSL running and watch for certificate renewal problems;
   - do not enable it on a temporary or staging host name you may reuse without HTTPS.
6. If a proxy or load balancer terminates SSL in front of the site, PHP cannot see HTTPS and you
   would get a redirect loop. Set `TRUSTED_PROXIES` to the proxy's IP address(es) so that
   `X-Forwarded-Proto` is honoured.

---

## 12. Production environment

| Setting | Live site | Staging / test copy |
|---|---|---|
| `APP_ENV` | `production` | `production` (or `staging`) |
| `APP_DEBUG` | `false` | `false` |
| `APP_INDEXABLE` | `true`, only once the site is approved and on the real domain | `false` (adds `noindex` header and `Disallow: /` in robots.txt) |
| `APP_FORCE_HTTPS` | `true` | `true` if it has SSL |
| `MAIL_DRIVER` | `smtp` | `log` or `none` (avoid emailing real people from a test copy) |

Debug output is refused in production in any case. Do not run a staging copy against the live
database.

---

## 13. Test the live domain

Work through this list after every deployment. Tick each item.

**Public pages**

- [ ] Home, About, Programs (and one program page), Impact, Get Involved, Gallery, News, Contact, Donate, Privacy, Terms all load without errors, and the layout and fonts look right.
- [ ] A made-up address such as `/no-such-page` shows the friendly **404 page** (not a server error).
- [ ] `/privacy` and `/terms` show the approved wording (or are still marked draft, see [INCOMPLETE.md](INCOMPLETE.md)).
- [ ] `/donate` shows the "not available yet" notice and no payment form.

**Forms**

- [ ] Contact, volunteer and partnership forms submit and show a reference on the thank-you page.
- [ ] **Real test application:** programs are seeded with applications closed. In **Admin > Programs**,
      edit one program, tick "Accepts applications" and set a suitable opening and closing date; confirm
      Settings > Training applications "Accept training applications" is on. Submit a full application on
      `/apply` (use an under-18 age range once to see the guardian fields, and upload a small PDF).
      Open it in **Admin > Applications**, download the attachment, then **delete the test application**.
      Finally close the program again (untick "Accepts applications") unless it is really open.
- [ ] Delete the test volunteer, partner and contact entries in their admin lists.

**Admin**

- [ ] `https://www.ritfoundationglobal.com/admin/login`: sign-in works; a wrong password is refused.
- [ ] Sign out; use "forgot password" (needs working email).
- [ ] **Upload test:** in Admin > Gallery, upload one (non-sensitive) photo to an album with alt text, leave it unpublished, check it displays in the admin, then delete it. Also confirm that a Settings image upload works.
- [ ] Dashboard checklist items make sense (logo, email, reviewed notices).

**Email**

- [ ] `php scripts/test-mail.php you@example.org` accepted and received (inbox or spam).
- [ ] Admin > Email log shows the contact test as `handed_off` after the cron job or first attempt.

**Search engine files**

- [ ] `https://www.ritfoundationglobal.com/robots.txt` shows `Disallow: /admin`, `Disallow: /setup` and a `Sitemap:` line when `APP_INDEXABLE=true` (or `Disallow: /` when it is false).
- [ ] `https://www.ritfoundationglobal.com/sitemap.xml` lists the public pages with `https://www...` addresses.

**Security headers**

```bash
curl -sI https://www.ritfoundationglobal.com/
curl -sI https://www.ritfoundationglobal.com/admin/login
curl -sI http://www.ritfoundationglobal.com/        # expect a 301 to https://www...
curl -sI https://ritfoundationglobal.com/           # expect a 301 to https://www...
```

Expect on the home page: `Content-Security-Policy`, `X-Content-Type-Options: nosniff`,
`X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security`,
no `X-Powered-By`. Expect on `/admin/login`: `Cache-Control: no-store, private` and
`X-Robots-Tag: noindex, nofollow`.

**Private paths (must be 403 or 404, never 200)**

```bash
for p in .env app/bootstrap.php config/app.php storage/ storage/logs/ database/schema.sql docs/DEVELOPER.md README.md scripts/create-admin.php uploads/; do
  printf '%-32s' "$p"; curl -s -o /dev/null -w '%{http_code}\n' "https://www.ritfoundationglobal.com/$p"
done
```

(Under layout A the application paths may return 404 rather than 403, which is fine: the point is
that no source or configuration is served.) After uploading a photo, also confirm a made-up
`/uploads/x.php` request is not executed.

**Other**

- [ ] Cron jobs exist (see below) and `Admin > Email log` has no `failed` rows.
- [ ] Re-check `storage/logs/` for errors; there should be none from your tests.

---

## 14. Backups

**cPanel tools**

- cPanel > **Backup Wizard** > **Back Up** > choose **Home Directory** and **MySQL Databases**
  (partial backups, downloadable), or a **Full Backup** if the host allows it.
- Check **JetBackup / Backup** on the host: many providers keep automatic copies, but do not rely on
  them alone. Keep your own copy off the server.

**What to back up**

| What | Location |
|---|---|
| Database | `mysqldump` or phpMyAdmin > Export |
| Public uploads | `public/uploads/` |
| Applicants' private attachments | `storage/private/applications/` |
| Configuration | `.env` (store separately and securely) |

The code can be re-uploaded; the data above cannot be rebuilt.

**Database dump (Terminal).** To avoid putting the password on the command line, create
`~/.my.cnf` containing `[client]`, `user=cpuser_ritapp`, `password=...` and run `chmod 600 ~/.my.cnf`; then:

```bash
mysqldump --single-transaction --default-character-set=utf8mb4 cpuser_rit > ~/backups/rit-$(date +%F).sql
tar czf ~/backups/rit-files-$(date +%F).tar.gz -C ~/rit-app public/uploads storage/private/applications
```

(The application user has `SELECT` but may lack `LOCK TABLES`; if the dump complains, add
`--skip-lock-tables`, or use phpMyAdmin Export, or the privileged user.)

**Frequency.** Weekly at minimum; daily while applications are open. Keep at least the last four.
Backups contain personal data: store them privately, restrict who can open them, and delete old
ones in line with the retention period.

**Restore**

1. Restore files: extract the upload and attachment archives back to the same paths; restore `.env`.
2. Restore the database: phpMyAdmin > select the (empty) database > **Import** the `.sql` file with
   character set utf-8; or `mysql cpuser_rit < rit-DATE.sql`.
3. Re-apply permissions (step 8).
4. Run the live-domain checklist (step 13).

Practise one restore into a spare database before you need it.

---

## 15. Updating safely

1. **Back up** the database and the folders in step 14. Put the site in a quiet period if you can.
2. **Upload only the changed files** (or extract the new release over the old one). Do **not**
   overwrite or delete `.env`, `public/uploads/`, `storage/` or the permissions you set. In layout C,
   re-copy `public/assets/` and `public/index.php` into `public_html/` and re-apply the `RIT_APP_ROOT` setting.
3. **Apply database migrations** if the release adds files to `database/migrations/`. Check first:

   ```bash
   cd ~/rit-app
   DB_USER=cpuser_ritadmin DB_PASS='the-privileged-password' php scripts/migrate.php --status
   DB_USER=cpuser_ritadmin DB_PASS='the-privileged-password' php scripts/migrate.php
   ```

   The environment variables apply only to that one command and override `.env`. To keep the password
   out of shell history, run `read -rs DB_PASS; export DB_PASS` first, then `DB_USER=... php scripts/migrate.php`,
   then `unset DB_PASS`. The application user cannot run migrations; that is intentional. If a
   migration fails partway, stop and restore from the backup (MySQL schema changes are not transactional).
4. **Clear nothing.** The application has no template, route or data cache to clear.
   (Browsers re-fetch CSS and JS because asset URLs carry the file's modification time.)
5. **Smoke test:** home page, one form page, admin sign-in, and a look at `storage/logs/` for new errors.

---

## Cron jobs in cPanel

cPanel > **Cron Jobs** > **Add New Cron Job**. Use the PHP binary that matches the site (confirm with
`which php` or use `/opt/cpanel/ea-php82/root/usr/bin/php`).

| Job | Schedule | Command |
|---|---|---|
| Send and retry queued email | Every 5 minutes (`*/5 * * * *`); every 10 is acceptable | `/usr/local/bin/php /home/USER/rit-app/scripts/process-email-queue.php >/dev/null 2>&1` |
| Retention purge | Monthly, 03:15 on the 1st (`15 3 1 * *`) | `/usr/local/bin/php /home/USER/rit-app/scripts/purge-old-records.php >> /home/USER/rit-app/storage/logs/purge.log 2>&1` |

Before adding the purge job, run it by hand in **dry-run** mode and read the counts:

```bash
php /home/USER/rit-app/scripts/purge-old-records.php --dry-run
```

It permanently deletes submissions and their attachments older than `RETENTION_SUBMISSION_MONTHS`.
Confirm the retention period with the Foundation before enabling the live job. Run the email-queue
script once by hand too (`php scripts/process-email-queue.php`); it prints how many messages were
accepted, failed or skipped. Cron output is emailed to the cPanel contact address unless it is
redirected as shown.

---

## What is not verified yet

These are outside the code and have **not** been confirmed. Do not assume they are in place.

| Item | Status |
|---|---|
| Hosting account and plan (PHP 8.2+, MySQL/MariaDB, cron, Terminal or SSH access, `AllowOverride`, `mod_rewrite`) | Not verified |
| Whether the host allows changing the document root of the main domain (decides layout A, B or C) | Not verified |
| DNS for `ritfoundationglobal.com` and `www` pointing at the hosting account | Not verified |
| SSL certificate (AutoSSL) for both host names | Not verified |
| Mailbox, SMTP host name and credentials; SPF and DKIM | Not verified |
| Database and cPanel credentials | Not provided to the developers |
| Payment provider | None chosen |
| Layout C and the cPanel screens described above | Not tried on the real host |

The related decisions and owners are listed in [INCOMPLETE.md](INCOMPLETE.md). Test results for the
code are in [TESTING.md](TESTING.md).
