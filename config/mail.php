<?php

declare(strict_types=1);

use App\Core\Env;

return [
    /*
     * none  - email is not configured; messages are recorded as "skipped".
     * log   - development only: messages are written to storage/logs/mail.log.
     * mail  - PHP mail(); only if the host has configured it properly.
     * smtp  - authenticated SMTP (recommended), e.g. the cPanel mailbox.
     */
    'driver' => strtolower(Env::get('MAIL_DRIVER', 'none') ?? 'none'),
    'host' => Env::get('MAIL_HOST', ''),
    'port' => (int) (Env::get('MAIL_PORT', '587') ?? 587),
    // tls (STARTTLS, usually port 587) | ssl (implicit TLS, usually 465) | none
    'encryption' => strtolower(Env::get('MAIL_ENCRYPTION', 'tls') ?? 'tls'),
    'username' => Env::get('MAIL_USERNAME', ''),
    'password' => Env::secret('MAIL_PASSWORD'),
    'timeout' => 15,
    'from_address' => Env::get('MAIL_FROM_ADDRESS', ''),
    'from_name' => Env::get('MAIL_FROM_NAME', 'RIT Foundation Ghana'),
    // Fallback recipient for staff notifications when the admin setting is empty.
    'admin_address' => Env::get('MAIL_ADMIN_ADDRESS', ''),
    // Retry limit for scripts/process-email-queue.php.
    'max_attempts' => 3,
];
