<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\LogEntry;

final class EmailLogController extends AdminController
{
    public function index(Request $request): Response
    {
        $result = LogEntry::emailPaginate($request, 50);
        return $this->page('admin/email-log', 'Email log', 'email-log', [
            'items' => $result['items'],
            'paginator' => $result['paginator'],
            'counts' => LogEntry::emailStatusCounts(),
            'status' => $request->query('status'),
            'driver' => (string) config('mail.driver'),
        ]);
    }
}
