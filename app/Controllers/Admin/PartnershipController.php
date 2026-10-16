<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\PartnershipEnquiry;

final class PartnershipController extends SubmissionAdminController
{
    protected function config(): array
    {
        return [
            'model' => PartnershipEnquiry::class,
            'nav' => 'partnerships',
            'path' => '/admin/partnerships',
            'title' => 'Partnership enquiries',
            'singular' => 'Partnership enquiry',
            'related_type' => 'partnership_enquiry',
            'heading' => 'organization_name',
            'search_hint' => 'Reference, organisation, contact or email',
            'reply' => false,
            'columns' => [['Organisation', 'organization_name'], ['Contact', 'contact_name'], ['Type', 'partnership_type', PartnershipEnquiry::TYPES]],
            'fields' => [
                ['Organisation', 'organization_name'], ['Contact person', 'contact_name'], ['Email', 'email', 'email'], ['Phone', 'phone', 'phone'],
                ['Website', 'website', 'url'], ['Type of partnership', 'partnership_type', 'text', PartnershipEnquiry::TYPES], ['Message', 'message', 'long'],
            ],
        ];
    }
}
