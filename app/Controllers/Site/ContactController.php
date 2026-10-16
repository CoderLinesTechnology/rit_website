<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Deferred;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ContactEnquiry;
use App\Services\FormGuard;
use App\Services\Mail\Outbox;
use App\Services\Notifier;
use App\Services\Settings;
use PDOException;

/** Contact page and message form (/contact). */
final class ContactController extends Controller
{
    private const FORM = 'contact';
    private const MAP_PREFIX = 'https://www.google.com/maps/embed?';

    public function create(Request $request): Response
    {
        $map = trim(Settings::get('contact.map_embed_url'));
        $mapUrl = str_starts_with($map, self::MAP_PREFIX) && preg_match('/[\s"\'<>]/', $map) !== 1 ? $map : '';

        $response = $this->view('pages/contact', [
            'nonce' => FormGuard::nonce(self::FORM),
            'mapUrl' => $mapUrl,
        ] + $this->meta('contact', Settings::get('contact.hero_heading', 'Contact Us'), Settings::get('contact.hero_text'), '/contact'));
        if ($mapUrl !== '') {
            // Without this the page's CSP (frame-src 'none') would block the map.
            $response->allowFrameSource('https://www.google.com');
        }
        return $response;
    }

    public function store(Request $request): Response
    {
        $key = FormGuard::check($request, self::FORM);
        if (FormGuard::completedReference(self::FORM, $key) !== null) {
            return redirect('/contact/thank-you');
        }

        $v = Validator::make($request->post(), [
            'full_name' => 'required|line|max:150',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|phone|max:30',
            'subject' => 'required|line|max:190',
            'message' => 'required|string|min:10|max:3000',
            'privacy' => 'accepted',
        ], [
            'full_name' => 'Full name',
            'email' => 'Email address',
            'phone' => 'Telephone number',
            'subject' => 'Subject',
            'message' => 'Message',
            'privacy' => 'Agreement to the Privacy Policy',
        ]);
        if ($v->fails()) {
            return redirect_with_errors('/contact', $v->errors(), $request->post());
        }

        $data = $v->validated();
        try {
            [$id, $reference] = Database::connection()->transaction(function () use ($data, $key): array {
                $reference = ContactEnquiry::newReference();
                $id = ContactEnquiry::create([
                    'reference' => $reference,
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'subject' => $data['subject'],
                    'message' => $data['message'],
                    'privacy_accepted_at' => gmdate('Y-m-d H:i:s'),
                    'idempotency_key' => $key,
                ]);
                (new Notifier())->contactReceived($id, $reference, (string) $data['subject'], (string) $data['full_name'], (string) $data['email']);
                return [$id, $reference];
            });
        } catch (PDOException $e) {
            $existing = ContactEnquiry::findByIdempotencyKey($key) ?? throw $e;
            $reference = (string) $existing['reference'];
        }

        FormGuard::complete(self::FORM, $key, $reference);
        Deferred::add(static fn () => Outbox::process(Outbox::queuedThisRequest(), 10));
        return redirect('/contact/thank-you');
    }

    public function thanks(Request $request): Response
    {
        $reference = FormGuard::thankYouReference(self::FORM);
        if ($reference === null) {
            return redirect('/contact');
        }
        return $this->view('pages/form-thanks', [
            'kind' => self::FORM,
            'reference' => $reference,
            'robots' => 'noindex',
        ] + $this->meta('contact-thanks', 'Message received', 'Your message has been received.'));
    }
}
