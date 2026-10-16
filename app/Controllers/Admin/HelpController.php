<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;

/** Staff operating guide (static content in the template). */
final class HelpController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page('admin/help', 'Help', 'help', ['mailDriver' => (string) config('mail.driver')]);
    }
}
