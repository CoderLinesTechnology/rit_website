<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ImpactMetric;

final class ImpactController extends AdminController
{
    use ContentSupport;

    public const ICONS = ['people-fill', 'mortarboard-fill', 'geo-alt-fill', 'people', 'award', 'graph-up-arrow', 'house-heart', 'briefcase'];

    private const COLS = ['path' => 'image_path', 'thumb' => 'thumb_path', 'alt' => 'image_alt'];
    private const BOOLS = ['is_approximate', 'show_on_homepage', 'verified'];

    public function index(Request $request): Response
    {
        return $this->page('admin/impact/index', 'Impact metrics', 'impact', ['metrics' => ImpactMetric::all()]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, $this->values(['status' => 'draft', 'sort_order' => '0'], self::BOOLS));
    }

    public function store(Request $request): Response
    {
        $v = $this->validate($request);
        if ($v->fails()) {
            return $this->invalid(url('/admin/impact/create'), $v->errors(), $request->post());
        }
        $d = $v->validated();
        $plan = $this->planImage($request, $v, null, self::COLS, 'impact', $d['image_alt'] ?? null);
        if ($v->fails()) {
            return $this->invalid(url('/admin/impact/create'), $v->errors(), $request->post());
        }
        try {
            $id = ImpactMetric::create($this->row($d) + $plan['data'] + ['created_by' => $this->adminId(), 'updated_by' => $this->adminId()]);
        } catch (\Throwable $e) {
            $this->discardStored($plan['stored']);
            throw $e;
        }
        $this->audit('impact.create', 'impact_metric', $id, 'Created impact metric "' . $d['label'] . '" (' . $d['status'] . ')');
        flash_message('success', 'Impact metric saved.');
        return redirect(url('/admin/impact'));
    }

    public function edit(Request $request, string $id): Response
    {
        $metric = $this->findOr404(ImpactMetric::find((int) $id));
        return $this->form($metric, $this->values($metric + ['verified' => '0'], self::BOOLS));
    }

    public function update(Request $request, string $id): Response
    {
        $metric = $this->findOr404(ImpactMetric::find((int) $id));
        $mid = (int) $metric['id'];
        $back = url('/admin/impact/' . $mid . '/edit');
        $v = $this->validate($request);
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        $d = $v->validated();
        $plan = $this->planImage($request, $v, $metric, self::COLS, 'impact', $d['image_alt'] ?? null);
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        try {
            ImpactMetric::update($mid, $this->row($d) + $plan['data'] + ['updated_by' => $this->adminId()]);
        } catch (\Throwable $e) {
            $this->discardStored($plan['stored']);
            throw $e;
        }
        $this->discardImages($plan['discard']);
        $this->audit('impact.update', 'impact_metric', $mid, 'Updated impact metric "' . $d['label'] . '" (' . $d['status'] . ')');
        flash_message('success', 'Impact metric saved.');
        return redirect(url('/admin/impact'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $metric = $this->findOr404(ImpactMetric::find((int) $id));
        ImpactMetric::delete((int) $metric['id']);
        $this->discardImages([[(string) $metric['image_path'], (string) $metric['thumb_path']]]);
        $this->audit('impact.delete', 'impact_metric', (int) $metric['id'], 'Deleted impact metric "' . $metric['label'] . '"');
        flash_message('success', 'Impact metric deleted.');
        return redirect(url('/admin/impact'));
    }

    /** @param array<string, mixed>|null $metric @param array<string, string> $values */
    private function form(?array $metric, array $values): Response
    {
        return $this->page('admin/impact/form', $metric === null ? 'New impact metric' : 'Edit impact metric', 'impact', [
            'metric' => $metric,
            'v' => $values,
            'action' => $metric === null ? '/admin/impact' : '/admin/impact/' . (int) $metric['id'],
            'icons' => self::ICONS,
            'maxMb' => $this->maxMb(),
        ]);
    }

    private function validate(Request $request): Validator
    {
        $v = Validator::make($request->post(), [
            'label' => 'required|line|max:150',
            'value' => 'required|line|max:40',
            'is_approximate' => 'boolean',
            'description' => 'nullable|string|max:2000',
            'icon' => 'nullable|in:' . implode(',', self::ICONS),
            'reporting_period' => 'nullable|line|max:100',
            'image_alt' => 'nullable|line|max:255',
            'status' => 'required|in:' . implode(',', ImpactMetric::STATUSES),
            'show_on_homepage' => 'boolean',
            'sort_order' => 'nullable|int|min:0|max:9999',
            'verified' => 'boolean',
        ], ['image_alt' => 'Image description', 'value' => 'Figure', 'reporting_period' => 'Reporting period']);
        $d = $v->validated();
        if ($v->passes() && $d['status'] === 'published' && !$d['verified']) {
            $v->addError('verified', 'Confirm that this figure has been verified by the Foundation before publishing it.');
        }
        return $v;
    }

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    private function row(array $d): array
    {
        return [
            'label' => $d['label'],
            'value' => $d['value'],
            'is_approximate' => $this->bit($d['is_approximate']),
            'description' => $d['description'] ?? null,
            'icon' => $d['icon'] ?? null,
            'reporting_period' => $d['reporting_period'] ?? null,
            'status' => $d['status'],
            'show_on_homepage' => $this->bit($d['show_on_homepage']),
            'sort_order' => (int) ($d['sort_order'] ?? 0),
        ];
    }
}
