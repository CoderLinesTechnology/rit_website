<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Deferred;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\TrainingApplication;
use App\Models\VolunteerApplication;
use App\Services\FormGuard;
use App\Services\Mail\Outbox;
use App\Services\Notifier;
use App\Services\Settings;
use PDOException;

/** Volunteer application form (/volunteer). */
final class VolunteerController extends Controller
{
    private const FORM = 'volunteer';

    public function create(Request $request): Response
    {
        return $this->view('pages/volunteer', [
            'nonce' => FormGuard::nonce(self::FORM),
        ] + $this->meta('volunteer', Settings::get('volunteer.hero_heading', 'Volunteer With Us'), Settings::get('volunteer.hero_text'), '/volunteer'));
    }

    public function store(Request $request): Response
    {
        $key = FormGuard::check($request, self::FORM);
        if (FormGuard::completedReference(self::FORM, $key) !== null) {
            return redirect('/volunteer/thank-you');
        }

        $v = Validator::make($request->post(), [
            'full_name' => 'required|line|max:150',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|phone|max:30',
            'location' => ['nullable', 'in:' . implode(',', TrainingApplication::REGIONS)],
            'interest_area' => 'required|in:' . implode(',', array_keys(VolunteerApplication::INTEREST_AREAS)),
            'skills' => 'nullable|string|max:2000',
            'availability' => 'nullable|line|max:255',
            'message' => 'nullable|string|max:2000',
            'privacy' => 'accepted',
        ], [
            'full_name' => 'Full name',
            'email' => 'Email address',
            'phone' => 'Telephone number',
            'location' => 'Region',
            'interest_area' => 'Area of interest',
            'skills' => 'Skills and experience',
            'availability' => 'Availability',
            'message' => 'Message',
            'privacy' => 'Agreement to the Privacy Policy',
        ]);
        if ($v->fails()) {
            return redirect_with_errors('/volunteer', $v->errors(), $request->post());
        }

        $data = $v->validated();
        try {
            [$id, $reference] = Database::connection()->transaction(function () use ($data, $key): array {
                $reference = VolunteerApplication::newReference();
                $id = VolunteerApplication::create([
                    'reference' => $reference,
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'location' => $data['location'],
                    'interest_area' => $data['interest_area'],
                    'skills' => $data['skills'],
                    'availability' => $data['availability'],
                    'message' => $data['message'],
                    'privacy_accepted_at' => gmdate('Y-m-d H:i:s'),
                    'idempotency_key' => $key,
                ]);
                (new Notifier())->volunteerReceived($id, $reference, (string) $data['full_name'], (string) $data['email']);
                return [$id, $reference];
            });
        } catch (PDOException $e) {
            $existing = VolunteerApplication::findByIdempotencyKey($key) ?? throw $e;
            $reference = (string) $existing['reference'];
        }

        FormGuard::complete(self::FORM, $key, $reference);
        Deferred::add(static fn () => Outbox::process(Outbox::queuedThisRequest(), 10));
        return redirect('/volunteer/thank-you');
    }

    public function thanks(Request $request): Response
    {
        $reference = FormGuard::thankYouReference(self::FORM);
        if ($reference === null) {
            return redirect('/volunteer');
        }
        return $this->view('pages/form-thanks', [
            'kind' => self::FORM,
            'reference' => $reference,
            'robots' => 'noindex',
        ] + $this->meta('volunteer-thanks', 'Volunteer application received', 'Your volunteer application has been received.'));
    }
}
