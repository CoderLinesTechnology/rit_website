# Developer guide — RIT Foundation Ghana website

Framework-free PHP 8.2+ (also runs on 8.5), MySQL 8 / MariaDB 10.4+, plain PHP templates, vanilla JS.
No Composer, no runtime dependencies, nothing loaded from a CDN.

## Layout

```
app/Core/          framework pieces (Router, Request, Response, View, Database, Session, Csrf, Auth,
                   Validator, RateLimiter, AuditLog, Logger, ErrorHandler, Paginator, Deferred, Str, Text)
app/Controllers/   Site\*  public pages     Admin\*  dashboard (extend Admin\AdminController)
app/Models/        table gateways (static methods, prepared statements only)
app/Services/      Settings, FormGuard, Notifier, Mail\*, ImageUploader, DocumentStore, Payments\*
config/            app.php database.php mail.php payments.php settings.php (setting definitions)
routes/web.php     every route (+ middleware)
resources/views/   layouts/ partials/ pages/ admin/ errors/
public/            index.php (front controller), assets/, uploads/ (public images only)
storage/           logs/ sessions/ cache/ private/applications/ (never web-accessible)
database/          schema.sql seed.sql sample-content.sql migrations/
scripts/           CLI tools (create-admin, migrate, purge, import-gallery, email queue, test-mail)
tests/             php tests/run.php [filter]
```

## Request lifecycle

`public/index.php` → `App\Core\Application::handle()` → `Router::dispatch()` → middleware
(`auth`, `can:<permission>`, `guest`) → CSRF check on every POST (unless `->withoutCsrf()`)
→ controller method `(Request $request, ...route params)` returning a `Response`.
Throw `HttpException` (404/403/419/422/429/503…) to show an error page.
`Deferred::add()` work runs after the response is sent (e.g. handing emails to SMTP).

## Rules (security, privacy, CSP)

1. **SQL:** only through `Database` with bound parameters. Identifiers come from code, never input.
   LIKE searches use `Database::likeTerm($q)`. Everything list-shaped is paginated or `LIMIT`ed.
2. **Output:** escape every dynamic value with `e()`. The only exception is `format_text()`
   (safe-markup → HTML). Never echo raw HTML from the database.
3. **CSP is strict** (`script-src 'self'; style-src 'self'`): no inline `<script>`, no `onclick=`,
   no `style=""`. Behaviour goes in `public/assets/js/main.js` / `admin.js` through `data-*` hooks.
4. **Forms:** `csrf_field()` in every POST form. Validate server-side with `Validator`. On failure,
   `redirect_with_errors($url, $v->errors(), $request->post())` (POST-redirect-GET). Field
   partials read `old()` / `field_error()` themselves.
5. **Mass assignment:** models only write `FILLABLE` columns, but controllers must still build the
   data array explicitly from validated values (never pass `$request->post()` straight through).
6. **Authorization:** routes enforce the module permission. Controllers must load records by id
   and 404 when missing; check record-level rules (e.g. you cannot deactivate yourself).
7. **Audit:** every create/update/delete/status change/sign-in-relevant action calls
   `AuditLog::record('module.action', 'entity', $id, 'summary')`. Summaries must not contain
   applicants' personal data (use the reference number, never names or emails).
8. **Uploads:** images through `ImageUploader::store($request->file('x'), '<folder>')`, catch
   `UploadException` and show its message. Private documents through `DocumentStore`. Delete
   files with `ImageUploader::deleteImage($path, $thumb)` when a record or image is replaced/deleted.
   Upload forms need `enctype="multipart/form-data"`.
9. **Destructive actions** are POST forms with `data-confirm="…"` (admin.js asks for confirmation).
10. **Personal data:** collect only what a form needs; no IP addresses or user agents stored with
    submissions; staff emails contain only references (see `Notifier`).
11. **Page cache:** public GET pages are stored and replayed to anonymous visitors
    (`App\Core\PageCache`). A cacheable page must not contain anything specific to one visitor;
    anything that needs a session (forms, flash messages) starts one, which keeps that response out
    of the cache automatically. New query parameters are not cached unless added to the allowlist
    in `PageCache::eligible()`. Admin POSTs flush the cache; CLI scripts that change content must
    call `PageCache::flush()`.
12. **Images in templates:** add `<?= srcset($path, '<sizes>') ?>` to every uploaded `<img>` and give it
    `width`/`height`; use `mailto_url()` for every email link (never build `mailto:` by hand).
13. **Assets:** after editing CSS run `php scripts/build-assets.php` (served automatically while
    newer than the source). Font files are cached as immutable: give a changed font a new file name.
14. **Honest states:** never claim an email was "delivered" (outbox status `handed_off` = accepted
    by the mail server), never show a donation as successful unless `DonationService` verified it,
    never promise program acceptance.

## Templates

```php
<?php $this->layout('layouts/public'); ?>          // meta (title/description/canonical/ogImage/pageKey)
                                                    // comes from the controller via $this->meta()
<?= $this->insert('partials/page-hero', ['breadcrumbs' => [['Home', '/'], ['About Us', null]],
      'title' => setting('about.hero_heading'), 'text' => setting('about.hero_text'),
      'image' => setting('about.hero_image'), 'imageAlt' => setting('about.hero_image_alt')]) ?>
<?php $this->start('scripts') ?>…<?php $this->stop() ?>
```

Partials: `page-hero`, `page-header`, `breadcrumbs`, `cta-band`, `filter-pills`, `pagination`,
`empty-state`, `error-summary`, `flash`, `social-links`, `brand`, form controls
`form/input|textarea|select|checkbox|radios` (vars documented at the top of each file).
Admin partials: `admin/partials/page-header|stat-tile|badge|empty-state`.
CSS component classes are indexed in a comment block at the end of `public/assets/css/main.css`.

Helpers: `e url absolute_url asset upload_url csrf_field old old_list form_errors field_error flash
flash_message view redirect redirect_with_errors setting social_links icon format_date iso_date
excerpt format_text current_path nav_active auth_user can selected checked money label app_hash`.

## Public form pattern

```php
public function create(Request $request): Response {
    return $this->view('pages/contact', ['nonce' => FormGuard::nonce('contact')] + $this->meta('contact', …));
}
public function store(Request $request): Response {
    $key = FormGuard::check($request, 'contact');            // honeypot + rate limit (429)
    if ($ref = FormGuard::completedReference('contact', $key)) { return redirect('/contact/thank-you'); }
    $v = Validator::make($request->post(), [...], [...labels]);
    if ($v->fails()) { return redirect_with_errors('/contact', $v->errors(), $request->post()); }
    $data = $v->validated();
    try {
        [$id, $reference] = Database::connection()->transaction(function () use ($data, $key) {
            $reference = ContactEnquiry::newReference();
            $id = ContactEnquiry::create([... explicit columns ..., 'privacy_accepted_at' => gmdate('Y-m-d H:i:s'), 'idempotency_key' => $key]);
            (new Notifier())->contactReceived($id, $reference, …);   // queued in the same transaction
            return [$id, $reference];
        });
    } catch (PDOException $e) {                                 // concurrent duplicate of the same form
        $existing = ContactEnquiry::findByIdempotencyKey($key) ?? throw $e;
        $reference = $existing['reference'];
    }
    FormGuard::complete('contact', $key, $reference);
    Deferred::add(static fn () => Outbox::process(Outbox::queuedThisRequest(), 10));
    return redirect('/contact/thank-you');
}
```
Templates print `FormGuard::fields($nonce)` inside the form (nonce + honeypot).

## Admin page pattern

```php
final class ImpactController extends AdminController {
    public function index(Request $request): Response {
        return $this->page('admin/impact/index', 'Impact metrics', 'impact', ['metrics' => ImpactMetric::all()]);
    }
}
```
`page()` supplies `$title`, `$activeNav`, `$newSubmissions`, `$roleLabel` to `layouts/admin`.
Admin templates: `<?php $this->layout('layouts/admin'); ?>`.

## Testing

`php tests/run.php` (unit + feature). Feature tests use `.env.testing` (database
`rit_foundation_test`). HTTP tests start `php -S` against `public/index.php` (see tests/http.php).
