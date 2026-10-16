<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\ContactEnquiry;

final class ContactController extends SubmissionAdminController
{
    protected function config(): array
    {
        return [
            'model' => ContactEnquiry::class,
            'nav' => 'contacts',
            'path' => '/admin/contacts',
            'title' => 'Contact messages',
            'singular' => 'Message',
            'related_type' => 'contact_enquiry',
            'heading' => 'subject',
            'search_hint' => 'Reference, name, email or subject',
            'reply' => true,
            'columns' => [['Name', 'full_name'], ['Subject', 'subject']],
            'fields' => [
                ['Name', 'full_name'], ['Email', 'email', 'email'], ['Phone', 'phone', 'phone'], ['Subject', 'subject'], ['Message', 'message', 'long'],
            ],
        ];
    }
}
