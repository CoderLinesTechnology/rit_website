<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Admin;
use App\Models\TrainingApplication;
use App\Services\DocumentStore;

/** Training applications (personal data: every action is audited without names or emails). */
final class ApplicationController extends AdminController
{
    private const DOWNLOAD_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];

    public function index(Request $request): Response
    {
        $result = TrainingApplication::adminPaginate($request, 25);
        $programs = Database::connection()->fetchAll('SELECT id, title FROM programs ORDER BY title LIMIT 300');
        return $this->page('admin/applications/index', 'Training applications', 'applications', [
            'items' => $result['items'],
            'paginator' => $result['paginator'],
            'counts' => TrainingApplication::countByStatus(),
            'programs' => $programs,
            'filters' => [
                'status' => $request->query('status'),
                'program' => $request->queryInt('program'),
                'q' => mb_substr($request->query('q'), 0, 100),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'duplicates_of' => $request->queryInt('duplicates_of'),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $app = $this->findOr404(TrainingApplication::find($id));
        AuditLog::record('application.viewed', 'training_application', $id, (string) $app['reference']);
        $changedBy = $app['status_changed_by'] !== null ? Admin::find((int) $app['status_changed_by']) : null;
        return $this->page('admin/applications/show', 'Application ' . $app['reference'], 'applications', [
            'app' => $app,
            'duplicates' => TrainingApplication::duplicateCount($id, (string) $app['email'], $app['program_id'] !== null ? (int) $app['program_id'] : null),
            'changedBy' => $changedBy['name'] ?? null,
            'statuses' => TrainingApplication::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, int $id): Response
    {
        $app = $this->findOr404(TrainingApplication::find($id));
        $status = $request->input('status');
        if (!in_array($status, TrainingApplication::STATUSES, true)) {
            return redirect_with_errors('/admin/applications/' . $id, ['status' => 'Choose one of the available statuses.']);
        }
        if (TrainingApplication::updateStatus($id, $status, Auth::id())) {
            AuditLog::record('application.status_changed', 'training_application', $id, $app['reference'] . ': ' . $app['status'] . ' → ' . $status);
            flash_message('success', 'Status changed to ' . label($status) . '. The applicant is not notified automatically.');
        } else {
            flash_message('info', 'The status was already ' . label($status) . '.');
        }
        return redirect('/admin/applications/' . $id);
    }

    public function updateNotes(Request $request, int $id): Response
    {
        $app = $this->findOr404(TrainingApplication::find($id));
        $v = Validator::make($request->post(), ['admin_notes' => 'nullable|string|max:5000'], ['admin_notes' => 'Notes']);
        if ($v->fails()) {
            return redirect_with_errors('/admin/applications/' . $id, $v->errors(), $request->post());
        }
        TrainingApplication::updateNotes($id, $v->validated()['admin_notes']);
        AuditLog::record('application.notes_updated', 'training_application', $id, (string) $app['reference']);
        flash_message('success', 'Notes saved.');
        return redirect('/admin/applications/' . $id);
    }

    public function attachment(Request $request, int $id): Response
    {
        $app = $this->findOr404(TrainingApplication::find($id));
        $path = (string) ($app['attachment_path'] ?? '');
        $absolute = $path !== '' ? (new DocumentStore())->absolutePath($path) : null;
        if ($absolute === null) {
            throw HttpException::notFound('There is no attachment for this application.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolute);
        if (!is_string($mime) || !in_array($mime, self::DOWNLOAD_MIMES, true)) {
            throw HttpException::notFound('There is no attachment for this application.');
        }
        $body = file_get_contents($absolute);
        if ($body === false) {
            throw HttpException::notFound('There is no attachment for this application.');
        }
        AuditLog::record('application.attachment_downloaded', 'training_application', $id, (string) $app['reference']);
        return (new Response($body, 200, ['Content-Type' => $mime]))
            ->withHeader('Content-Disposition', self::disposition((string) $app['reference'], (string) ($app['attachment_name'] ?? '')))
            ->withHeader('Content-Length', (string) strlen($body))
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, int $id): Response
    {
        $app = $this->findOr404(TrainingApplication::find($id));
        Database::connection()->transaction(static function (Database $db) use ($id): void {
            $db->execute('DELETE FROM email_outbox WHERE related_type = :t AND related_id = :id', ['t' => 'training_application', 'id' => $id]);
            TrainingApplication::delete($id);
        });
        (new DocumentStore())->delete($app['attachment_path'] !== null ? (string) $app['attachment_path'] : null); // after commit
        AuditLog::record('application.deleted', 'training_application', $id, (string) $app['reference']);
        flash_message('success', 'The application and its attachment were permanently deleted.');
        return redirect('/admin/applications');
    }

    /** Content-Disposition with an ASCII fallback plus an RFC 5987 UTF-8 name. */
    private static function disposition(string $reference, string $original): string
    {
        $name = (string) preg_replace('/[\x00-\x1F\x7F"\\\\\/:*?<>|;]+/u', '', $original);
        $name = trim(mb_substr($name, 0, 120), ". \t");
        $full = $reference . '-' . ($name !== '' ? $name : 'attachment');
        $ascii = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $full);
        return 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($full);
    }
}
