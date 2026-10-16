<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Text;
use App\Core\View;
use App\Services\Settings;

/*
 * Global helpers used by controllers and templates. Templates must escape
 * every dynamic value with e(); format_text() is the only helper whose
 * output is already-safe HTML.
 */

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function e(mixed $value): string
{
    if ($value === null || is_array($value) || is_object($value)) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** @param array<string, scalar|null> $query */
function url(string $path = '/', array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($v): bool => $v !== null && $v !== '');
    return $query === [] ? $path : $path . '?' . http_build_query($query);
}

function absolute_url(string $path = '/'): string
{
    return config('app.url') . '/' . ltrim($path, '/');
}

/** Cache-busted URL of a file in public/assets. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = PUBLIC_ROOT . '/assets/' . $path;
    // Prefer the minified stylesheet written by scripts/build-assets.php while it is up to date.
    if (str_ends_with($path, '.css') && !str_ends_with($path, '.min.css')) {
        $min = substr($file, 0, -4) . '.min.css';
        if (is_file($min) && is_file($file) && filemtime($min) >= filemtime($file)) {
            $path = substr($path, 0, -4) . '.min.css';
            $file = $min;
        }
    }
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return '/assets/' . $path . '?v=' . $version;
}

/** Public URL of an uploaded file stored as "uploads/...". */
function upload_url(?string $path): string
{
    if ($path === null || $path === '' || !str_starts_with($path, 'uploads/') || str_contains($path, '..')) {
        return '';
    }
    return '/' . $path;
}

function csrf_field(): string
{
    return Csrf::field();
}

function old(string $key, string $default = ''): string
{
    $old = Session::getFlash('_old', []);
    $value = is_array($old) ? ($old[$key] ?? null) : null;
    return is_string($value) ? $value : $default;
}

/** @return list<string> Previous input for a multi-value field like interests[]. */
function old_list(string $key): array
{
    $old = Session::getFlash('_old', []);
    $value = is_array($old) ? ($old[$key] ?? []) : [];
    return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
}

/** @return array<string, string> */
function form_errors(): array
{
    $errors = Session::getFlash('_errors', []);
    return is_array($errors) ? $errors : [];
}

function field_error(string $key): ?string
{
    return form_errors()[$key] ?? null;
}

function flash(string $type): ?string
{
    $value = Session::getFlash('_message_' . $type);
    return is_string($value) ? $value : null;
}

/** @param array<string, mixed> $data */
function view(string $template, array $data = [], int $status = 200): Response
{
    return Response::html(View::make($template, $data), $status);
}

function redirect(string $to): Response
{
    return Response::redirect($to);
}

/**
 * Redirect back to a form with its validation errors and the submitted input
 * (sensitive fields are never flashed).
 *
 * @param array<string, string> $errors
 * @param array<string, mixed> $input
 */
function redirect_with_errors(string $to, array $errors, array $input = []): Response
{
    foreach (['password', 'password_confirmation', 'current_password', '_token', 'website_url'] as $secret) {
        unset($input[$secret]);
    }
    $clean = [];
    foreach ($input as $key => $value) {
        if (is_string($value)) {
            $clean[$key] = mb_substr($value, 0, 100000);
        } elseif (is_array($value)) {
            $clean[$key] = array_slice(array_values(array_filter($value, 'is_string')), 0, 50);
        }
    }
    Session::flash('_errors', $errors);
    Session::flash('_old', $clean);
    Session::flash('_message_error', 'Please correct the highlighted fields and try again.');
    return Response::redirect($to);
}

function flash_message(string $type, string $message): void
{
    Session::flash('_message_' . $type, $message);
}

function setting(string $key, string $default = ''): string
{
    return Settings::get($key, $default);
}

/** @return list<array{platform: string, label: string, url: string}> */
function social_links(): array
{
    return Settings::socialLinks();
}

function icon(string $name, string $class = 'icon', ?string $label = null): string
{
    if (!preg_match('/^[a-z0-9-]+$/', $name)) {
        return '';
    }
    $a11y = $label === null
        ? 'aria-hidden="true" focusable="false"'
        : 'role="img" aria-label="' . e($label) . '" focusable="false"';
    return '<svg class="' . e($class) . '" ' . $a11y . '><use href="' . e(asset('images/icons.svg')) . '#i-' . $name . '"></use></svg>';
}

function format_date(?string $datetime, string $format = 'j F Y'): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }
    try {
        $date = new DateTimeImmutable($datetime, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone((string) config('app.timezone', 'Africa/Accra')))->format($format);
    } catch (Exception) {
        return '';
    }
}

/** Machine-readable date for <time datetime="">. */
function iso_date(?string $datetime): string
{
    return format_date($datetime, 'Y-m-d');
}

function excerpt(string $text, int $length = 160): string
{
    return Text::excerpt($text, $length);
}

/** Safe HTML from the restricted markup administrators can use. */
function format_text(?string $markup): string
{
    return Text::format((string) $markup);
}

function current_path(): string
{
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
    return '/' . trim($path, '/');
}

function nav_active(string $prefix): string
{
    $path = current_path();
    $active = $prefix === '/' ? $path === '/' : ($path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/'));
    return $active ? ' aria-current="page"' : '';
}

/** @return array{id: int, name: string, email: string, role: string}|null */
function auth_user(): ?array
{
    $user = Auth::user();
    if ($user === null) {
        return null;
    }
    return ['id' => (int) $user['id'], 'name' => (string) $user['name'], 'email' => (string) $user['email'], 'role' => (string) $user['role']];
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function selected(bool $condition): string
{
    return $condition ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

function money(int $minor, string $currency = 'GHS'): string
{
    $symbol = $currency === 'GHS' ? 'GH₵' : $currency . ' ';
    return $symbol . number_format($minor / 100, 2);
}

/** Human label for a status/enum value: "under_review" → "Under review". */
function label(string $value): string
{
    return ucfirst(str_replace('_', ' ', $value));
}

/** HMAC of a value with the application key (for rate-limit buckets etc.). */
function app_hash(string $value): string
{
    $key = config('app.key');
    if (!is_string($key) || $key === '') {
        if (config('app.env') === 'production') {
            throw new RuntimeException('APP_KEY is not configured.');
        }
        $key = 'insecure-development-key-' . APP_ROOT;
    }
    return hash_hmac('sha256', $value, $key);
}

/**
 * srcset/sizes attributes for an uploaded image: lists the sizes that exist on
 * disk (thumbnail ≈720px, medium ≈1280px, main ≈1920px) with their real pixel
 * widths, so phones download a right-sized file. Pass any of the variant paths.
 */
function srcset(?string $path, string $sizes = '100vw'): string
{
    if (upload_url($path) === '') {
        return '';
    }
    $main = (string) preg_replace('/-(?:thumb|md)\.(webp|jpg)$/', '.$1', (string) $path);
    $parts = [];
    foreach ([App\Services\ImageUploader::variant($main, 'thumb'), App\Services\ImageUploader::variant($main, 'md'), $main] as $candidate) {
        $width = image_width($candidate);
        if ($width > 0) {
            $parts[$width] = upload_url($candidate) . ' ' . $width . 'w';
        }
    }
    if (count($parts) < 2) {
        return '';
    }
    ksort($parts);
    return ' srcset="' . e(implode(', ', $parts)) . '" sizes="' . e($sizes) . '"';
}

/** Pixel width of an uploaded image file (0 when missing); cached for the request. */
function image_width(string $relative): int
{
    static $cache = [];
    if (!array_key_exists($relative, $cache)) {
        $file = PUBLIC_ROOT . '/' . $relative;
        $size = upload_url($relative) !== '' && is_file($file) ? @getimagesize($file) : false;
        $cache[$relative] = $size === false ? 0 : (int) $size[0];
    }
    return $cache[$relative];
}

/**
 * A safe mailto: URL, or '' when the address is not a plain mailbox address
 * (so a stored value like "a@b.c?bcc=x@y.z" can never add hidden recipients).
 */
function mailto_url(?string $email, ?string $subject = null): string
{
    $email = trim((string) $email);
    if ($email === '' || preg_match(App\Core\Validator::EMAIL_PATTERN, $email) !== 1) {
        return '';
    }
    return 'mailto:' . $email . ($subject !== null ? '?subject=' . rawurlencode($subject) : '');
}
