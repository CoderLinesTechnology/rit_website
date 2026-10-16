<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\RateLimiter;

use App\Services\Mail\Outbox;

/**
 * Builds the plain-text notification emails and queues them in the outbox
 * (nothing is sent here). Staff messages carry no applicant personal data
 * beyond a reference; confirmations never repeat what the person submitted.
 */
final class Notifier
{
    private const CONFIRM_GLOBAL_PER_HOUR = 100;
    private const CONFIRM_PER_RECIPIENT_PER_DAY = 3;

    private const SUBJECT_PREFIX = '[RIT Foundation Ghana] ';

    /** @var callable(string, string): string */
    private $setting;

    /** @param (callable(string, string): string)|null $setting settings reader, fn(key, default) */
    public function __construct(?callable $setting = null)
    {
        $this->setting = $setting ?? static fn (string $key, string $default): string => Settings::get($key, $default);
    }

    public function trainingApplicationReceived(int $id, string $reference, string $programTitle, string $applicantName, string $applicantEmail): void
    {
        $reference = self::line($reference);
        $program = self::line($programTitle);
        $this->staff(
            'staff.training_application',
            'training_application',
            $id,
            "New training application {$reference}",
            "A new training application ({$reference}) was received for {$program}.\n\nSign in to review it: " . self::adminUrl("/admin/applications/{$id}")
        );
        $this->confirm(
            'confirm.training_application',
            'training_application',
            $id,
            $applicantEmail,
            'We received your training application',
            self::greeting($applicantName)
            . "Thank you for applying for {$program}. We have received your application.\n\n"
            . "Your reference is {$reference}. Please quote it if you contact us.\n\n"
            . "Please note that submitting an application does not guarantee a place. The Foundation will contact you about the next steps.\n"
        );
    }

    public function volunteerReceived(int $id, string $reference, string $name, string $email): void
    {
        $reference = self::line($reference);
        $this->staff(
            'staff.volunteer',
            'volunteer_application',
            $id,
            "New volunteer application {$reference}",
            "A new volunteer application ({$reference}) was received.\n\nSign in to review it: " . self::adminUrl("/admin/volunteers/{$id}")
        );
        $this->confirm(
            'confirm.volunteer',
            'volunteer_application',
            $id,
            $email,
            'We received your volunteer application',
            self::greeting($name)
            . "Thank you for offering to volunteer with the RIT Foundation Ghana. We have received your application.\n\n"
            . "Your reference is {$reference}. Please quote it if you contact us.\n\n"
            . "Submitting an application does not guarantee a volunteering placement. The Foundation will contact you about the next steps.\n"
        );
    }

    public function partnershipReceived(int $id, string $reference, string $organization, string $name, string $email): void
    {
        $reference = self::line($reference);
        // Staff emails carry only the reference: no text typed by the public.
        $this->staff(
            'staff.partnership',
            'partnership_enquiry',
            $id,
            "New partnership enquiry {$reference}",
            "A new partnership enquiry ({$reference}) was received.\n\nSign in to review it: " . self::adminUrl("/admin/partnerships/{$id}")
        );
        $this->confirm(
            'confirm.partnership',
            'partnership_enquiry',
            $id,
            $email,
            'We received your partnership enquiry',
            self::greeting($name)
            . "Thank you for your interest in partnering with the RIT Foundation Ghana. We have received your enquiry.\n\n"
            . "Your reference is {$reference}. Please quote it if you contact us.\n\n"
            . "The Foundation will review it and contact you. Submitting an enquiry does not commit either side to a partnership.\n"
        );
    }

    public function contactReceived(int $id, string $reference, string $subject, string $name, string $email): void
    {
        $reference = self::line($reference);
        $this->staff(
            'staff.contact',
            'contact_enquiry',
            $id,
            "New message {$reference}",
            "A new message ({$reference}) was received.\n\nSign in to read it: " . self::adminUrl("/admin/contacts/{$id}")
        );
        $this->confirm(
            'confirm.contact',
            'contact_enquiry',
            $id,
            $email,
            'We received your message',
            self::greeting($name)
            . "Thank you for contacting the RIT Foundation Ghana. We have received your message.\n\n"
            . "Your reference is {$reference}. Please quote it if you contact us again.\n\n"
            . "The Foundation will reply as soon as it can.\n"
        );
    }

    public function passwordReset(string $email, string $name, string $resetUrl, int $minutesValid): void
    {
        $minutes = max(1, $minutesValid);
        Outbox::queue(
            self::line($email),
            self::SUBJECT_PREFIX . 'Reset your administrator password',
            self::greeting($name)
            . "We received a request to reset the password for your administrator account.\n\n"
            . 'To choose a new password, open this link (valid for ' . $minutes . " minutes, single use):\n"
            . self::line($resetUrl) . "\n\n"
            . "If you did not ask for this, you can ignore this email; your password will not change.\n",
            'admin.password_reset',
            'admin',
            null
        );
    }

    private function staff(string $purpose, string $relatedType, int $id, string $subject, string $body): void
    {
        $subject = self::SUBJECT_PREFIX . $subject;
        $to = trim(($this->setting)('notify.email', ''));
        if ($to === '') {
            $to = trim((string) config('mail.admin_address', ''));
        }
        if ($to === '') {
            Outbox::recordSkipped('(none)', $subject, $purpose, 'No staff notification address configured', $relatedType, $id);
            return;
        }
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            Outbox::recordSkipped(mb_substr($to, 0, 190), $subject, $purpose, 'Staff notification address is not a valid email address', $relatedType, $id);
            return;
        }
        Outbox::queue($to, $subject, $body, $purpose, $relatedType, $id);
    }

    private function confirm(string $purpose, string $relatedType, int $id, string $to, string $subject, string $body): void
    {
        if (($this->setting)('notify.send_confirmations', '1') !== '1') {
            return;
        }
        // Confirmations go to an address typed by the public, so they are capped site-wide
        // and per recipient: the forms can never be used to mass-mail someone from our mailbox.
        $to = self::line($to);
        $global = 'confirm:global';
        $perRecipient = 'confirm:to:' . strtolower($to);
        if (RateLimiter::tooManyAttempts($global, self::CONFIRM_GLOBAL_PER_HOUR)
            || RateLimiter::tooManyAttempts($perRecipient, self::CONFIRM_PER_RECIPIENT_PER_DAY)) {
            Outbox::recordSkipped(mb_substr($to, 0, 190), self::SUBJECT_PREFIX . $subject, $purpose, 'Confirmation limit reached', $relatedType, $id);
            return;
        }
        RateLimiter::hit($global, 3600);
        RateLimiter::hit($perRecipient, 86400);
        Outbox::queue($to, self::SUBJECT_PREFIX . $subject, $body, $purpose, $relatedType, $id);
    }

    private static function adminUrl(string $path): string
    {
        return rtrim((string) config('app.url', ''), '/') . $path;
    }

    /** Single line: CR/LF and control characters removed. */
    private static function line(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value));
    }

    /** "Hello Ama," — only when the first name is plainly a name (letters, apostrophes, hyphens). */
    private static function greeting(string $name): string
    {
        $first = self::line($name);
        $first = $first === '' ? '' : (preg_split('/\s+/', $first)[0] ?? '');
        if (preg_match("/^\\p{L}[\\p{L}'\\x{2019}-]{0,39}$/u", $first) !== 1) {
            $first = '';
        }
        return ($first === '' ? 'Hello,' : "Hello {$first},") . "\n\n";
    }
}
