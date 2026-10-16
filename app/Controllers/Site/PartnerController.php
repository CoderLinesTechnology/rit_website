<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Deferred;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\PartnershipEnquiry;
use App\Services\FormGuard;
use App\Services\Mail\Outbox;
use App\Services\Notifier;
use App\Services\Settings;
use PDOException;

/** Partnership enquiry form (/partner). */
final class PartnerController extends Controller
{
    private const FORM = 'partner';

    public function create(Request $request): Response
    {
        return $this->view('pages/partner', [
            'nonce' => FormGuard::nonce(self::FORM),
        ] + $this->meta('partner', Settings::get('partner.hero_heading', 'Partner With Us'), Settings::get('partner.hero_text'), '/partner'));
    }

    public function store(Request $request): Response
    {
        $key = FormGuard::check($request, self::FORM);
        if (FormGuard::completedReference(self::FORM, $key) !== null) {
            return redirect('/partner/thank-you');
        }

        $v = Validator::make($request->post(), [
            'organization_name' => 'required|line|max:190',
            'contact_name' => 'required|line|max:150',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|phone|max:30',
            'website' => 'nullable|url|max:255',
            'partnership_type' => 'required|in:' . implode(',', array_keys(PartnershipEnquiry::TYPES)),
            'message' => 'required|string|min:20|max:3000',
            'privacy' => 'accepted',
        ], [
            'organization_name' => 'Organization name',
            'contact_name' => 'Contact person',
            'email' => 'Email address',
            'phone' => 'Telephone number',
            'website' => 'Website',
            'partnership_type' => 'Type of partnership',
            'message' => 'Message',
            'privacy' => 'Agreement to the Privacy Policy',
        ]);
        if ($v->fails()) {
            return redirect_with_errors('/partner', $v->errors(), $request->post());
        }

        $data = $v->validated();
        try {
            [$id, $reference] = Database::connection()->transaction(function () use ($data, $key): array {
                $reference = PartnershipEnquiry::newReference();
                $id = PartnershipEnquiry::create([
                    'reference' => $reference,
                    'organization_name' => $data['organization_name'],
                    'contact_name' => $data['contact_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'website' => $data['website'],
                    'partnership_type' => $data['partnership_type'],
                    'message' => $data['message'],
                    'privacy_accepted_at' => gmdate('Y-m-d H:i:s'),
                    'idempotency_key' => $key,
                ]);
                (new Notifier())->partnershipReceived($id, $reference, (string) $data['organization_name'], (string) $data['contact_name'], (string) $data['email']);
                return [$id, $reference];
            });
        } catch (PDOException $e) {
            $existing = PartnershipEnquiry::findByIdempotencyKey($key) ?? throw $e;
            $reference = (string) $existing['reference'];
        }

        FormGuard::complete(self::FORM, $key, $reference);
        Deferred::add(static fn () => Outbox::process(Outbox::queuedThisRequest(), 10));
        return redirect('/partner/thank-you');
    }

    public function thanks(Request $request): Response
    {
        $reference = FormGuard::thankYouReference(self::FORM);
        if ($reference === null) {
            return redirect('/partner');
        }
        return $this->view('pages/form-thanks', [
            'kind' => self::FORM,
            'reference' => $reference,
            'robots' => 'noindex',
        ] + $this->meta('partner-thanks', 'Partnership enquiry received', 'Your partnership enquiry has been received.'));
    }
}
