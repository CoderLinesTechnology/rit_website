<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;

/**
 * Protection shared by every public form:
 *
 *  - a honeypot field (bots fill it, people never see it);
 *  - a per-IP rate limit per form;
 *  - a one-time form nonce that turns accidental double submissions
 *    (double click, refresh after POST, back + resubmit) into the SAME
 *    record instead of a duplicate. The nonce becomes the row's
 *    idempotency_key, which is UNIQUE in the database as a final guard.
 *
 * Usage in a controller:
 *   GET:  'nonce' => FormGuard::nonce('apply')   → hidden field FormGuard::FIELD
 *   POST: $key = FormGuard::check($request, 'apply');   (throws 429 when limited)
 *         if ($ref = FormGuard::completedReference('apply', $key)) → redirect to thank-you
 *         ...validate, insert with idempotency_key = $key...
 *         FormGuard::complete('apply', $key, $reference);
 */
final class FormGuard
{
    public const FIELD = '_form_nonce';
    public const HONEYPOT = 'website_url';

    /** Any attempt (valid or not) per IP and form within an hour — a loose flood guard. */
    private const MAX_ATTEMPTS_PER_HOUR = 30;

    private static string $ip = '';

    /** @var array<string, array{0: int, 1: int}> form => [max SUCCESSFUL submissions per IP, window seconds] */
    private const LIMITS = [
        'apply' => [5, 3600],
        'volunteer' => [5, 3600],
        'partner' => [5, 3600],
        'contact' => [5, 900],
        'donate' => [10, 3600],
    ];

    /** Issues a nonce for one rendering of a form. */
    public static function nonce(string $form): string
    {
        $nonce = bin2hex(random_bytes(16));
        $pending = Session::get('_form_nonces', []);
        $pending = is_array($pending) ? $pending : [];
        $pending[$form . ':' . $nonce] = time();
        // Keep the newest 30 so the session cannot grow without bound.
        arsort($pending);
        Session::set('_form_nonces', array_slice($pending, 0, 30, true));
        return $nonce;
    }

    /**
     * Validates the honeypot, applies the rate limit and returns the
     * idempotency key for this submission.
     */
    public static function check(Request $request, string $form): string
    {
        if ($request->input(self::HONEYPOT) !== '') {
            throw new HttpException(422, 'Your submission could not be processed. Please try again.');
        }
        self::$ip = $request->ip();
        // Count first, then compare: concurrent requests cannot slip past the limit together.
        if (RateLimiter::hit('form-try:' . $form . ':' . self::$ip, 3600) > self::MAX_ATTEMPTS_PER_HOUR) {
            throw new HttpException(429, 'You have sent this form several times in a short period. Please try again later.');
        }
        // Successful submissions have a stricter limit; correcting validation errors never counts.
        [$max] = self::LIMITS[$form] ?? [5, 3600];
        if (RateLimiter::tooManyAttempts('form-ok:' . $form . ':' . self::$ip, $max)) {
            throw new HttpException(429, 'You have sent this form several times in a short period. Please try again later.');
        }

        $nonce = $request->input(self::FIELD);
        if (!preg_match('/^[a-f0-9]{32}$/', $nonce)) {
            // No usable nonce (e.g. a very old tab): still accept, but without duplicate protection.
            $nonce = bin2hex(random_bytes(16));
        }
        return hash('sha256', $form . '|' . $nonce);
    }

    /** The reference created earlier in this session for the same form rendering, if any. */
    public static function completedReference(string $form, string $key): ?string
    {
        $done = Session::get('_form_done', []);
        $reference = is_array($done) ? ($done[$form . ':' . $key] ?? null) : null;
        return is_string($reference) ? $reference : null;
    }

    /** Remembers the outcome so a resubmission shows the same reference. */
    public static function complete(string $form, string $key, string $reference): void
    {
        $done = Session::get('_form_done', []);
        $done = is_array($done) ? $done : [];
        $isNew = !isset($done[$form . ':' . $key]);
        $done[$form . ':' . $key] = $reference;
        Session::set('_form_done', array_slice($done, -20, null, true));
        if ($isNew && self::$ip !== '') {
            [, $window] = self::LIMITS[$form] ?? [5, 3600];
            RateLimiter::hit('form-ok:' . $form . ':' . self::$ip, $window);
        }
        self::rememberForThankYou($form, $reference);
    }

    /** The reference shown on the form's thank-you page (survives a reload in this session). */
    public static function thankYouReference(string $form): ?string
    {
        if (!Session::cookiePresent() && !Session::isStarted()) {
            return null;
        }
        $last = Session::get('_last_submission', []);
        $reference = is_array($last) ? ($last[$form] ?? null) : null;
        return is_string($reference) ? $reference : null;
    }

    public static function rememberForThankYou(string $form, string $reference): void
    {
        $last = Session::get('_last_submission', []);
        $last = is_array($last) ? $last : [];
        $last[$form] = $reference;
        Session::set('_last_submission', $last);
    }

    public static function honeypotField(): string
    {
        return '<div class="hp-field" aria-hidden="true"><label for="' . self::HONEYPOT . '">Leave this field empty</label>'
            . '<input type="text" id="' . self::HONEYPOT . '" name="' . self::HONEYPOT . '" value="" tabindex="-1" autocomplete="off"></div>';
    }

    /** Hidden nonce + honeypot markup for a form. */
    public static function fields(string $nonce): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . e($nonce) . '">' . self::honeypotField();
    }
}
