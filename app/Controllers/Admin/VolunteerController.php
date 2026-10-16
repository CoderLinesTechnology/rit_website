<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\VolunteerApplication;

final class VolunteerController extends SubmissionAdminController
{
    protected function config(): array
    {
        return [
            'model' => VolunteerApplication::class,
            'nav' => 'volunteers',
            'path' => '/admin/volunteers',
            'title' => 'Volunteer applications',
            'singular' => 'Volunteer',
            'related_type' => 'volunteer_application',
            'heading' => 'full_name',
            'search_hint' => 'Reference, name or email',
            'reply' => false,
            'columns' => [['Name', 'full_name'], ['Interest', 'interest_area', VolunteerApplication::INTEREST_AREAS], ['Location', 'location']],
            'fields' => [
                ['Name', 'full_name'], ['Email', 'email', 'email'], ['Phone', 'phone', 'phone'], ['Location', 'location'],
                ['Area of interest', 'interest_area', 'text', VolunteerApplication::INTEREST_AREAS],
                ['Skills', 'skills', 'long'], ['Availability', 'availability'], ['Message', 'message', 'long'],
            ],
        ];
    }
}
