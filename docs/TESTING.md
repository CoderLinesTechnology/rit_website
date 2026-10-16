# Test plan and results

What was tested, how, and what could not be tested. "Passed" below means the test was actually
run and passed on the stated date; nothing is reported as passed that was not executed.

**Environment used (2026-10-16):** macOS, PHP 8.5.7 (CLI and built-in server) and PHP 8.2.4
(XAMPP CLI and Apache 2.4.56 module), MariaDB 10.4.28, headless Chrome 155. Databases:
`rit_foundation_dev` (seed + sample news) and `rit_foundation_test` (schema + seed only).

## 1. Automated tests

Run with `php tests/run.php` (dependency-free runner; feature tests start the real application with
PHP's built-in server against the test database).

| Suite | Cases | What it covers |
|---|---|---|
| Unit/StrTest | 6 | slugs (accents), references, limits, email masking |
| Unit/TextTest | 10 | safe markup → HTML, ~20 XSS payloads, link allowlist, 50 KB performance |
| Unit/ImageUploaderTest | 11 | JPEG/PNG/WebP re-encoding, EXIF/GPS stripped, polyglots, PHP-as-JPG, traversal on delete, size limits |
| Unit/DocumentStoreTest | 6 | private PDF/JPG/PNG storage, fake PDFs, renamed PHP, traversal |
| Unit/MailerTest | 10 | header injection, SMTP session against a fake server (dot-stuffing, CRLF, no AUTH over plaintext, 5xx handling) |
| Unit/OutboxTest | 7 | queue/claim/process, double-claim protection, bodies erased after hand-off |
| Unit/NotifierTest | 5 | staff/confirmation messages, no personal data in staff emails |
| Unit/DonationServiceTest | 11 | fake gateway: verify-before-success, amount/currency mismatch, bad signature 401, replay, no downgrade, retry after unavailable verification (503) |
| Unit/PerformanceTest | 6 | page-cache eligibility/store/flush/degraded, srcset sizes, minified asset selection, CSP hash |
| Unit/SecurityRegressionTest | 9 | one test per finding of the security review (cache flood, mailto injection, redirects, bcrypt cost, greeting, CSRF via query, APP_ENV fail-safe, setup token length, route `$`) |
| Feature/ContentPagesTest | 9 | every public page 200, unknown slugs 404, drafts/unpublished hidden, XSS escaped, search input, sitemap XML, robots, canonical, no inline script/style |
| Feature/FormsTest | 26 (59 checks) | apply/volunteer/partner/contact: valid submission persisted, required fields, invalid email, oversized input, CSRF 419, duplicate nonce → one row, honeypot 422, rate limit 429, XSS not echoed, minors need guardian, closed program refused, attachments (PDF ok, PHP refused, oversized refused, stored outside the web root), donate with no provider, email failure keeps the submission |
| Feature/AdminAuthTest | 15 | login, generic errors (unknown/wrong/inactive), throttling, session fixation, logout, GET logout 405, role-based 403s, anonymous redirects, session expiry and revocation, setup 404, reset token single use/expiry, last super admin, self-deactivation |
| Feature/AdminSubmissionsTest | 8 | status change + audit, CSRF, notes escaped, attachment download auth + headers, delete removes row and file, search injection |
| Feature/AdminContentTest | 33 | programs/news/gallery/impact/team/settings/social/SEO CRUD, slugs, publish rules (consent, verification), permission per settings group, uploads, file clean-up, XSS escaped |

**Result of the final full run:** see section 5.

## 2. Manual and scripted checks that were run

| Check | How | Result |
|---|---|---|
| `.htaccess`, document root = `public/` | separate Apache 2.4.56 instance (XAMPP binary, own config, ports 8090/8091) | pages route; `/.env` 403; trailing slash 301 |
| `.htaccess`, whole project in the web root (layout B) | same Apache, document root = project root | `/app/…`, `/config/…`, `/storage/…`, `/database/schema.sql`, `/.env`, `/README.md` → 403; site works |
| Uploaded PHP never executes | planted `evil.php` and PHP-in-`.webp` files in `public/uploads` under Apache | `.php` → 403; disguised file served as inert `image/webp` |
| Archives/backups not served | `/backup.zip`, `/site.tar.gz`, `/index.php~` | 403 |
| Caching headers | curl under Apache | `/assets/*` 1 year immutable; uploads 1 day; HTML `no-cache` + ETag (304 on revalidation); gzip on |
| Security headers | curl | CSP (no `unsafe-inline`; one hashed flag script), X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy, COOP; HSTS only in production |
| Login throttling, role matrix, CSRF, redirects | independent security review with live probes (see section 4) | as reported in section 4 |
| Photo pipeline at scale | imported the 298 supplied Drive photos (2400 px) with `scripts/import-gallery.php` | 298 imported, 0 failed, all unpublished; 355 MB → 118 MB (3 sizes each) |
| Responsive layout | headless Chrome, `scrollWidth === innerWidth` | no horizontal overflow at 390 px on home, programs, apply, volunteer, partner, contact, donate, about, impact, gallery, news, get involved (checked by the developer); home, programs, apply at 1440 px; admin dashboard, lists and forms at 390/1440 px (checked during development) |
| Visual comparison with the approved mockups | screenshots compared side by side by the developer | layout, typography, colours and components match; differences listed in section 6 |
| Performance | in-process timing, MariaDB statement profiling | see section 3 |

## 3. Performance measurements

| Measurement | Before | After |
|---|---|---|
| Home / News server time (warm, uncached) | 100–600 ms (disk temporary tables in the category-count queries) | ~5–6 ms |
| Page served from the page cache (in-process) | — | 0.2–0.3 ms, no database access |
| Public CSS (gzipped) | 13.4 KB in 3 files | 11.7 KB, 1 minified file |
| Render-blocking JavaScript | 8 KB in `<head>` | none (one hashed inline flag; script deferred) |
| Hero image on a phone (1280 px WebP) | 218 KB (1920 px only) | 118 KB |
| Fonts | — | self-hosted WOFF2; Latin-Extended (Ewe letters, ₵) only downloaded when a page uses them |

Not measured: real-device Lighthouse/Core Web Vitals on the production host (no host yet).

## 4. Security review

An independent review (static reading of all code + live probes against a development server)
found **no Critical or High issues**; 3 Medium and 14 Low findings were reported and **all were fixed**
before handover, each with a regression test in `tests/Unit/SecurityRegressionTest.php` or the
existing suites: page-cache flood, mailto parameter injection, trailing-slash redirect with encoded
control characters, login timing on PHP 8.2/8.3 (bcrypt cost now 12 everywhere), confirmation-email
abuse (name filter + global and per-recipient limits), staff emails free of public text, renaming of
photo files when unpublished/consent withdrawn, role-filtered dashboard feed, webhook retry
semantics, CSP form-action for a future checkout, personal data out of URLs, CSRF token not read from
the query string, fairer form rate limits, setup-token length, admin email-change follow-ups,
APP_ENV fail-safe, session cookie Secure flag and garbage collection, log rotation and file modes.

## 5. Final full run

Run on 2026-10-16 after every fix in this report, on the same machine:

| PHP | Passed | Failed | Skipped | Notes |
|---|---|---|---|---|
| 8.2.4 (XAMPP — the minimum supported version) | 204 | 0 | 1 | skip: "PNG transparency kept in WebP output" — this PHP's GD has no WebP, so the JPEG fallback is used and tested instead |
| 8.5.7 | 202 | 2 | 1 | both failures were infrastructure on a heavily loaded disk ("test HTTP server did not start" / a request with no connection), not assertions; after allowing the test server 30 s to start, the affected suite was re-run: **AdminAuthTest 15 passed, 0 failed**. skip: "JPEG fallback" — this PHP has WebP, so the fallback path is not exercised |

Earlier in the day, an intermittent failure of the "log driver hand-off" form test was traced to the
same disk stalls (single requests taking 14–16 s against a 30 s client timeout); the harness timeouts
were raised and the test passed on re-runs. If you run the suite on a slow machine and see
`status 0` or "did not start", re-run before investigating the code.

## 6. Not tested / known differences

- **Not run:** deployment on the real cPanel host, DNS, SSL certificate, real SMTP delivery (only a
  fake SMTP server and the `log` driver), any payment provider (none chosen), real phones and
  browsers other than headless Chrome, screen-reader testing, load testing.
- **Visual differences from the mockups (by design):** the real logo file and approved photos are
  still needed (a text wordmark and photo placeholders are shown until they are uploaded); the
  impact band shows only the verified 576 figure instead of the mockup's four figures; the News
  sidebar invites people to follow the WhatsApp Channel instead of a newsletter sign-up (no
  subscriber system was in scope); unverified mockup content (statistics, timeline item, testimonial,
  team roles) is not published.
- Form pages are never page-cached (they carry a CSRF token), so they always hit PHP and the database.
