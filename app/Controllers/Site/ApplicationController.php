<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Deferred;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Program;
use App\Models\ProgramCategory;
use App\Models\TrainingApplication;
use App\Services\DocumentStore;
use App\Services\FormGuard;
use App\Services\Mail\Outbox;
use App\Services\Notifier;
use App\Services\Settings;
use App\Services\StoredDocument;
use App\Services\UploadException;
use PDOException;

/** Training application form (/apply). */
final class ApplicationController extends Controller
{
    private const FORM = 'apply';

    public function create(Request $request): Response
    {
        $programs = Program::openForApplications();
        $preselect = '';
        $slug = $request->query('program');
        if ($slug !== '') {
            foreach ($programs as $program) {
                if ($program['slug'] === $slug) {
                    $preselect = (string) $program['id'];
                    break;
                }
            }
        }

        return $this->view('pages/apply', [
            'nonce' => FormGuard::nonce(self::FORM),
            'programs' => $programs,
            'preselect' => $preselect,
            'allowAttachment' => Settings::bool('applications.allow_attachment'),
            'categories' => ProgramCategory::all(),
        ] + $this->meta('apply', Settings::get('apply.hero_heading', 'Training Applications'), Settings::get('apply.hero_text'), '/apply'));
    }

    public function store(Request $request): Response
    {
        $key = FormGuard::check($request, self::FORM);
        if (FormGuard::completedReference(self::FORM, $key) !== null) {
            return redirect('/apply/thank-you');
        }

        $ageKeys = implode(',', array_keys(TrainingApplication::AGE_RANGES));
        $v = Validator::make($request->post(), [
            'full_name' => 'required|line|max:150',
            'email' => 'required|email|max:190',
            'phone' => 'required|phone|max:30',
            'age_range' => 'required|in:' . $ageKeys,
            'location' => ['required', 'in:' . implode(',', TrainingApplication::REGIONS)],
            'program_id' => 'required|int',
            'guardian_name' => 'nullable|line|max:150',
            'guardian_phone' => 'nullable|phone|max:30',
            'guardian_consent' => 'boolean',
            'background' => 'nullable|string|max:2000',
            'reason' => 'required|string|min:20|max:2000',
            'availability' => 'required|in:' . implode(',', array_keys(TrainingApplication::AVAILABILITY)),
            'privacy' => 'accepted',
        ], [
            'full_name' => 'Full name',
            'email' => 'Email address',
            'phone' => 'Telephone number',
            'age_range' => 'Age',
            'location' => 'Region',
            'program_id' => 'Preferred program',
            'guardian_name' => 'Parent or guardian name',
            'guardian_phone' => 'Parent or guardian telephone',
            'background' => 'Educational background',
            'reason' => 'Reason for applying',
            'availability' => 'Availability',
            'privacy' => 'Agreement to the Privacy Policy',
        ]);

        $program = null;
        if (!isset($v->errors()['program_id'])) {
            $program = Program::findOpen((int) $v->validated()['program_id']);
            if ($program === null) {
                $v->addError('program_id', 'Applications for this program have closed.');
            }
        }

        $isAdult = true;
        if (!isset($v->errors()['age_range'])) {
            $isAdult = TrainingApplication::AGE_RANGES[(string) $v->validated()['age_range']]['adult'];
            if (!$isAdult) {
                if (($v->validated()['guardian_name'] ?? null) === null) {
                    $v->addError('guardian_name', 'Parent or guardian name is required if you are under 18.');
                }
                if (($v->validated()['guardian_phone'] ?? null) === null) {
                    $v->addError('guardian_phone', 'Parent or guardian telephone is required if you are under 18.');
                }
                if (($v->validated()['guardian_consent'] ?? false) !== true) {
                    $v->addError('guardian_consent', 'A parent or guardian must consent to this application if you are under 18.');
                }
            }
        }

        $document = null;
        $store = new DocumentStore();
        $upload = Settings::bool('applications.allow_attachment') ? $request->file('attachment') : null;
        if ($v->passes() && $upload !== null) {
            try {
                $document = $store->store($upload);
            } catch (UploadException $e) {
                $v->addError('attachment', $e->getMessage());
            }
        }
        if ($v->fails()) {
            return redirect_with_errors('/apply', $v->errors(), $request->post());
        }

        $data = $v->validated();
        /** @var array<string, mixed> $program */
        try {
            [$id, $reference] = Database::connection()->transaction(function () use ($data, $key, $program, $isAdult, $document): array {
                $reference = TrainingApplication::newReference();
                $id = TrainingApplication::create([
                    'reference' => $reference,
                    'program_id' => (int) $program['id'],
                    'program_title' => (string) $program['title'],
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'age_range' => $data['age_range'],
                    'is_adult' => $isAdult ? 1 : 0,
                    'guardian_name' => $isAdult ? null : $data['guardian_name'],
                    'guardian_phone' => $isAdult ? null : $data['guardian_phone'],
                    'guardian_consent' => $isAdult ? 0 : 1,
                    'location' => $data['location'],
                    'background' => $data['background'],
                    'reason' => $data['reason'],
                    'availability' => $data['availability'],
                    'attachment_path' => $document?->path,
                    'attachment_name' => $document?->originalName,
                    'attachment_size' => $document?->size,
                    'attachment_mime' => $document?->mime,
                    'privacy_accepted_at' => gmdate('Y-m-d H:i:s'),
                    'idempotency_key' => $key,
                ]);
                (new Notifier())->trainingApplicationReceived($id, $reference, (string) $program['title'], (string) $data['full_name'], (string) $data['email']);
                return [$id, $reference];
            });
        } catch (PDOException $e) {
            // The stored file belongs to a row that was not (or will not be) created by this request.
            $this->discard($store, $document);
            $existing = TrainingApplication::findByIdempotencyKey($key) ?? throw $e;
            $reference = (string) $existing['reference'];
        } catch (\Throwable $e) {
            $this->discard($store, $document);
            throw $e;
        }

        FormGuard::complete(self::FORM, $key, $reference);
        Deferred::add(static fn () => Outbox::process(Outbox::queuedThisRequest(), 10));
        return redirect('/apply/thank-you');
    }

    public function thanks(Request $request): Response
    {
        $reference = FormGuard::thankYouReference(self::FORM);
        if ($reference === null) {
            return redirect('/apply');
        }
        return $this->view('pages/apply-thanks', [
            'reference' => $reference,
            'robots' => 'noindex',
        ] + $this->meta('apply-thanks', 'Application received', 'Your training application has been received.'));
    }

    private function discard(DocumentStore $store, ?StoredDocument $document): void
    {
        if ($document !== null) {
            $store->delete($document->path);
        }
    }
}
