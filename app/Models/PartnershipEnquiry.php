<?php

declare(strict_types=1);

namespace App\Models;

final class PartnershipEnquiry extends Submission
{
    public const TABLE = 'partnership_enquiries';
    public const REFERENCE_PREFIX = 'RIT-PAR';
    public const STATUSES = ['new', 'in_discussion', 'agreed', 'declined', 'closed'];
    public const FILLABLE = [
        'reference', 'organization_name', 'contact_name', 'email', 'phone', 'website', 'partnership_type', 'message',
        'privacy_accepted_at', 'idempotency_key',
    ];
    public const SEARCH_COLUMNS = ['organization_name', 'contact_name', 'email'];

    public const TYPES = [
        'training' => 'Training and capacity building',
        'resources' => 'Resource and equipment support',
        'placements' => 'Internship and job placement opportunities',
        'projects' => 'Joint projects and community initiatives',
        'expertise' => 'Knowledge and expertise sharing',
        'funding' => 'Funding or sponsorship',
        'other' => 'Other',
    ];
}
