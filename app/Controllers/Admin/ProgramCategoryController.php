<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ProgramCategory;

final class ProgramCategoryController extends AdminController
{
    use ContentSupport;

    private const COLS = ['path' => 'image_path', 'thumb' => 'thumb_path', 'alt' => 'image_alt'];

    public function index(Request $request): Response
    {
        return $this->page('admin/program-categories/index', 'Program categories', 'program-categories', [
            // All programs (not just published) so the count matches what blocks a delete.
            'categories' => ProgramCategory::allWithCounts(false),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, $this->values(['sort_order' => '0']));
    }

    public function store(Request $request): Response
    {
        $v = $this->validate($request, null, $slug);
        if ($v->fails()) {
            return $this->invalid(url('/admin/program-categories/create'), $v->errors(), $request->post());
        }
        $d = $v->validated();
        $plan = $this->planImage($request, $v, null, self::COLS, 'programs', $d['image_alt'] ?? null);
        if ($v->fails()) {
            return $this->invalid(url('/admin/program-categories/create'), $v->errors(), $request->post());
        }
        try {
            $id = ProgramCategory::create($this->row($d, $slug) + $plan['data']);
        } catch (\Throwable $e) {
            $this->discardStored($plan['stored']);
            throw $e;
        }
        $this->audit('program_category.create', 'program_category', $id, 'Created program category "' . $d['name'] . '"');
        flash_message('success', 'Category created.');
        return redirect(url('/admin/program-categories'));
    }

    public function edit(Request $request, string $id): Response
    {
        $category = $this->findOr404(ProgramCategory::find((int) $id));
        return $this->form($category, $this->values($category));
    }

    public function update(Request $request, string $id): Response
    {
        $category = $this->findOr404(ProgramCategory::find((int) $id));
        $cid = (int) $category['id'];
        $back = url('/admin/program-categories/' . $cid . '/edit');
        $v = $this->validate($request, $cid, $slug);
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        $d = $v->validated();
        $plan = $this->planImage($request, $v, $category, self::COLS, 'programs', $d['image_alt'] ?? null);
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        try {
            ProgramCategory::update($cid, $this->row($d, $slug) + $plan['data']);
        } catch (\Throwable $e) {
            $this->discardStored($plan['stored']);
            throw $e;
        }
        $this->discardImages($plan['discard']);
        $this->audit('program_category.update', 'program_category', $cid, 'Updated program category "' . $d['name'] . '"');
        flash_message('success', 'Category saved.');
        return redirect(url('/admin/program-categories'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $category = $this->findOr404(ProgramCategory::find((int) $id));
        $cid = (int) $category['id'];
        $count = ProgramCategory::programCount($cid);
        if ($count > 0) {
            flash_message('error', 'This category still has ' . $count . ($count === 1 ? ' program' : ' programs') . '. Move or delete them first, then delete the category.');
            return redirect(url('/admin/program-categories'));
        }
        ProgramCategory::delete($cid);
        $this->discardImages([[(string) $category['image_path'], (string) $category['thumb_path']]]);
        $this->audit('program_category.delete', 'program_category', $cid, 'Deleted program category "' . $category['name'] . '"');
        flash_message('success', 'Category deleted.');
        return redirect(url('/admin/program-categories'));
    }

    /** @param array<string, mixed>|null $category @param array<string, string> $values */
    private function form(?array $category, array $values): Response
    {
        return $this->page('admin/program-categories/form', $category === null ? 'New category' : 'Edit category', 'program-categories', [
            'category' => $category,
            'v' => $values,
            'action' => $category === null ? '/admin/program-categories' : '/admin/program-categories/' . (int) $category['id'],
            'icons' => ProgramController::ICONS,
            'maxMb' => $this->maxMb(),
        ]);
    }

    private function validate(Request $request, ?int $id, ?string &$slug): Validator
    {
        $v = Validator::make($request->post(), [
            'name' => 'required|line|max:120',
            'slug' => 'nullable|slug|max:140',
            'description' => 'nullable|string|max:2000',
            'icon' => 'nullable|in:' . implode(',', ProgramController::ICONS),
            'image_alt' => 'nullable|line|max:255',
            'sort_order' => 'nullable|int|min:0|max:9999',
        ], ['image_alt' => 'Image description']);
        if ($v->passes()) {
            $d = $v->validated();
            $slug = $this->resolveSlug($v, ProgramCategory::class, $d['slug'] ?? null, (string) $d['name'], $id);
        }
        return $v;
    }

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    private function row(array $d, string $slug): array
    {
        return [
            'name' => $d['name'],
            'slug' => $slug,
            'description' => $d['description'] ?? null,
            'icon' => $d['icon'] ?? null,
            'sort_order' => (int) ($d['sort_order'] ?? 0),
        ];
    }
}
