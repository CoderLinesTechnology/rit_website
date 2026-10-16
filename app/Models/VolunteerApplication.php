<?php

declare(strict_types=1);

namespace App\Models;

final class VolunteerApplication extends Submission
{
    public const TABLE = 'volunteer_applications';
    public const REFERENCE_PREFIX = 'RIT-VOL';
    public const STATUSES = ['new', 'contacted', 'active', 'declined', 'closed'];
    public const FILLABLE = [
        'reference', 'full_name', 'email', 'phone', 'location', 'interest_area', 'skills', 'availability', 'message',
        'privacy_accepted_at', 'idempotency_key',
    ];

    public const INTEREST_AREAS = [
        'mentoring' => 'Mentoring and coaching trainees',
        'training' => 'Supporting training workshops and events',
        'professional-skills' => 'Sharing professional skills and expertise',
        'outreach' => 'Community outreach and awareness',
        'media' => 'Photography, media and communications',
        'other' => 'Something else',
    ];
}
