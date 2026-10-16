<?php

declare(strict_types=1);

namespace App\Models;

final class ContactEnquiry extends Submission
{
    public const TABLE = 'contact_enquiries';
    public const REFERENCE_PREFIX = 'RIT-MSG';
    public const STATUSES = ['new', 'in_progress', 'replied', 'closed'];
    public const FILLABLE = ['reference', 'full_name', 'email', 'phone', 'subject', 'message', 'privacy_accepted_at', 'idempotency_key'];
    public const SEARCH_COLUMNS = ['full_name', 'email', 'subject'];
}
