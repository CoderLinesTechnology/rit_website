<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Donation;
use App\Services\Payments\GatewayFactory;

/** Read-only donation records (no card or wallet credentials are ever stored). */
final class DonationController extends AdminController
{
    public function index(Request $request): Response
    {
        $result = Donation::adminPaginate($request, 25);
        return $this->page('admin/donations/index', 'Donations', 'donations', [
            'items' => $result['items'],
            'paginator' => $result['paginator'],
            'totals' => Donation::successfulTotals(),
            'configured' => GatewayFactory::make()->isConfigured(),
            'statuses' => Donation::STATUSES,
            'status' => $request->query('status'),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $donation = $this->findOr404(Donation::find($id));
        return $this->page('admin/donations/show', 'Donation ' . $donation['reference'], 'donations', [
            'donation' => $donation,
            'events' => Donation::events($id),
        ]);
    }
}
