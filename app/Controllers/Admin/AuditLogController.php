<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Admin;
use App\Models\LogEntry;

final class AuditLogController extends AdminController
{
    public const PREFIXES = [
        'auth.' => 'Sign-ins and passwords',
        'application.' => 'Training applications',
        'submission.' => 'Volunteers, partnerships, messages',
        'program.' => 'Programs',
        'news.' => 'News and stories',
        'gallery.' => 'Gallery',
        'settings.' => 'Settings',
        'admin.' => 'Administrator accounts',
    ];

    public function index(Request $request): Response
    {
        $action = $request->query('action');
        if (!array_key_exists($action, self::PREFIXES)) {
            // Keep the model's own validation but only accept the listed prefixes from the form.
            $action = '';
        }
        $result = LogEntry::auditPaginate($this->withAction($request, $action), 50);
        return $this->page('admin/audit-log', 'Audit log', 'audit-log', [
            'items' => $result['items'],
            'paginator' => $result['paginator'],
            'prefixes' => self::PREFIXES,
            'action' => $action,
            'admins' => Admin::all(),
            'adminFilter' => $request->queryInt('admin'),
        ]);
    }

    private function withAction(Request $request, string $action): Request
    {
        $query = $request->queryAll();
        $query['action'] = $action;
        return new Request('GET', $request->path(), $query, [], [], []);
    }
}
