<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Submission;

/**
 * Shared admin behaviour for volunteer applications, partnership enquiries
 * and contact messages. A subclass only describes its table through config().
 *
 * config() keys: model (class-string<Submission>), nav, path, title, singular,
 * related_type (outbox + audit entity), heading (column used as the record's
 * name), search_hint, columns (list of [label, key, map?]), fields (list of
 * [label, key, kind?, map?] with kind text|long|email|phone|url), reply (bool).
 */
abstract class SubmissionAdminController extends AdminController
{
    /** @return array<string, mixed> */
    abstract protected function config(): array;

    public function index(Request $request): Response
    {
        $c = $this->config();
        /** @var class-string<Submission> $model */
        $model = $c['model'];
        $select = 's.id, s.reference, s.status, s.created_at';
        foreach ($c['columns'] as [, $key]) {
            $select .= ', s.`' . preg_replace('/[^a-z_]/', '', (string) $key) . '`';
        }
        $result = $model::adminPaginate($request, 25, $select);
        return $this->page('admin/submissions/index', (string) $c['title'], (string) $c['nav'], [
            'cfg' => $c,
            'statuses' => $model::STATUSES,
            'counts' => $model::countByStatus(),
            'items' => $result['items'],
            'paginator' => $result['paginator'],
            'filters' => [
                'status' => $request->query('status'),
                'q' => mb_substr($request->query('q'), 0, 100),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $c = $this->config();
        /** @var class-string<Submission> $model */
        $model = $c['model'];
        $row = $this->findOr404($model::find($id));
        AuditLog::record('submission.viewed', (string) $c['related_type'], $id, (string) $row['reference']);
        return $this->page('admin/submissions/show', (string) $c['singular'] . ' ' . $row['reference'], (string) $c['nav'], [
            'cfg' => $c,
            'row' => $row,
            'statuses' => $model::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, int $id): Response
    {
        $c = $this->config();
        /** @var class-string<Submission> $model */
        $model = $c['model'];
        $row = $this->findOr404($model::find($id));
        $status = $request->input('status');
        if (!in_array($status, $model::STATUSES, true)) {
            return redirect_with_errors($c['path'] . '/' . $id, ['status' => 'Choose one of the available statuses.']);
        }
        if ($model::updateStatus($id, $status, Auth::id())) {
            AuditLog::record('submission.status_changed', (string) $c['related_type'], $id, $row['reference'] . ': ' . $row['status'] . ' → ' . $status);
            flash_message('success', 'Status changed to ' . label($status) . '.');
        } else {
            flash_message('info', 'The status was already ' . label($status) . '.');
        }
        return redirect($c['path'] . '/' . $id);
    }

    public function updateNotes(Request $request, int $id): Response
    {
        $c = $this->config();
        /** @var class-string<Submission> $model */
        $model = $c['model'];
        $row = $this->findOr404($model::find($id));
        $v = Validator::make($request->post(), ['admin_notes' => 'nullable|string|max:5000'], ['admin_notes' => 'Notes']);
        if ($v->fails()) {
            return redirect_with_errors($c['path'] . '/' . $id, $v->errors(), $request->post());
        }
        $model::updateNotes($id, $v->validated()['admin_notes']);
        AuditLog::record('submission.notes_updated', (string) $c['related_type'], $id, (string) $row['reference']);
        flash_message('success', 'Notes saved.');
        return redirect($c['path'] . '/' . $id);
    }

    public function destroy(Request $request, int $id): Response
    {
        $c = $this->config();
        /** @var class-string<Submission> $model */
        $model = $c['model'];
        $row = $this->findOr404($model::find($id));
        Database::connection()->transaction(static function (Database $db) use ($c, $model, $id): void {
            $db->execute('DELETE FROM email_outbox WHERE related_type = :t AND related_id = :id', ['t' => $c['related_type'], 'id' => $id]);
            $model::delete($id);
        });
        AuditLog::record('submission.deleted', (string) $c['related_type'], $id, (string) $row['reference']);
        flash_message('success', 'The record was permanently deleted.');
        return redirect($c['path']);
    }
}
