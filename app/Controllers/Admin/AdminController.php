<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\ContactEnquiry;
use App\Models\PartnershipEnquiry;
use App\Models\TrainingApplication;
use App\Models\VolunteerApplication;

/**
 * Base for admin controllers. Routes already enforce "auth" and the
 * module permission; controllers re-check record-level rules themselves.
 */
abstract class AdminController
{
    /**
     * Renders an admin page inside layouts/admin with the shell variables.
     *
     * @param array<string, mixed> $data
     */
    protected function page(string $template, string $title, string $activeNav, array $data = [], int $status = 200): Response
    {
        $user = Auth::user();
        $newSubmissions = 0;
        if (Auth::can('submissions.manage')) {
            $newSubmissions = TrainingApplication::countNew() + VolunteerApplication::countNew()
                + PartnershipEnquiry::countNew() + ContactEnquiry::countNew();
        }
        return view($template, $data + [
            'title' => $title,
            'activeNav' => $activeNav,
            'newSubmissions' => $newSubmissions,
            'roleLabel' => Auth::ROLES[(string) ($user['role'] ?? '')] ?? '',
            'adminUser' => $user,
        ], $status);
    }

    /** @return array<string, mixed> The record, or a 404 page. */
    protected function findOr404(?array $record): array
    {
        if ($record === null) {
            throw HttpException::notFound('That record does not exist or has been deleted.');
        }
        return $record;
    }

    protected function requirePermission(string $permission): void
    {
        if (!Auth::can($permission)) {
            throw HttpException::forbidden('Your account does not have permission to do that.');
        }
    }

    protected function adminId(): ?int
    {
        return Auth::id();
    }

    /** Redirect back to a listing, keeping its filters (only same-site admin paths). */
    protected function backTo(Request $request, string $fallback): Response
    {
        $return = $request->input('return_to');
        if ($return !== '' && str_starts_with($return, '/admin') && !str_starts_with($return, '//') && !preg_match('/[\r\n\\\\]/', $return)) {
            return redirect($return);
        }
        return redirect($fallback);
    }
}
