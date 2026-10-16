<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\SeoMeta;

final class SeoController extends AdminController
{
    use ContentSupport;

    private const COLS = ['path' => 'og_image_path', 'thumb' => null, 'alt' => null];

    public function index(Request $request): Response
    {
        $rows = [];
        foreach (SeoMeta::all() as $row) {
            $row['page_name'] = SeoMeta::PAGES[(string) $row['page_key']] ?? (string) $row['page_key'];
            $rows[] = $row;
        }
        return $this->page('admin/seo/index', 'Search & sharing (SEO)', 'seo', [
            'rows' => $rows,
            'activeRow' => $request->queryInt('row'),
            'maxMb' => $this->maxMb(),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $row = $this->findOr404(SeoMeta::find((int) $id));
        $rid = (int) $row['id'];
        $back = url('/admin/seo', ['row' => $rid]) . '#seo-' . $rid;
        $v = Validator::make($request->post(), [
            'meta_title' => 'nullable|line|max:150',
            'meta_description' => 'nullable|line|max:300',
        ], ['meta_title' => 'Search title', 'meta_description' => 'Search description']);
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        $d = $v->validated();
        $plan = $this->planImage($request, $v, $row, self::COLS, 'seo', null);
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        try {
            SeoMeta::update($rid, [
                'meta_title' => $d['meta_title'] ?? null,
                'meta_description' => $d['meta_description'] ?? null,
                'updated_by' => $this->adminId(),
            ] + $plan['data']);
        } catch (\Throwable $e) {
            $this->discardStored($plan['stored'], false);
            throw $e;
        }
        $this->discardImages($plan['discard']);
        SeoMeta::flush();
        $name = SeoMeta::PAGES[(string) $row['page_key']] ?? (string) $row['page_key'];
        $this->audit('seo.update', 'seo_meta', $rid, 'Updated search and sharing details for "' . $name . '"');
        flash_message('success', 'Saved the search and sharing details for ' . $name . '.');
        return redirect(url('/admin/seo') . '#seo-' . $rid);
    }
}
