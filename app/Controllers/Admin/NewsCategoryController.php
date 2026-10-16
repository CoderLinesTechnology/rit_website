<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\NewsCategory;

final class NewsCategoryController extends AdminController
{
    use ContentSupport;

    public function index(Request $request): Response
    {
        return $this->page('admin/news-categories/index', 'News categories', 'news-categories', [
            // Total article count per category (including drafts) = what blocks a delete.
            'categories' => $this->withTotals(),
            'protectedSlug' => NewsCategory::SUCCESS_STORIES_SLUG,
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
            return $this->invalid(url('/admin/news-categories/create'), $v->errors(), $request->post());
        }
        $d = $v->validated();
        $id = NewsCategory::create($this->row($d, $slug));
        $this->audit('news_category.create', 'news_category', $id, 'Created news category "' . $d['name'] . '"');
        flash_message('success', 'Category created.');
        return redirect(url('/admin/news-categories'));
    }

    public function edit(Request $request, string $id): Response
    {
        $category = $this->findOr404(NewsCategory::find((int) $id));
        return $this->form($category, $this->values($category));
    }

    public function update(Request $request, string $id): Response
    {
        $category = $this->findOr404(NewsCategory::find((int) $id));
        $cid = (int) $category['id'];
        $v = $this->validate($request, $cid, $slug, (string) $category['slug']);
        if ($v->fails()) {
            return $this->invalid(url('/admin/news-categories/' . $cid . '/edit'), $v->errors(), $request->post());
        }
        $d = $v->validated();
        NewsCategory::update($cid, $this->row($d, $slug));
        $this->audit('news_category.update', 'news_category', $cid, 'Updated news category "' . $d['name'] . '"');
        flash_message('success', 'Category saved.');
        return redirect(url('/admin/news-categories'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $category = $this->findOr404(NewsCategory::find((int) $id));
        $cid = (int) $category['id'];
        if ($category['slug'] === NewsCategory::SUCCESS_STORIES_SLUG) {
            flash_message('error', 'The Success stories category is used by the website and cannot be deleted.');
            return redirect(url('/admin/news-categories'));
        }
        $count = NewsCategory::articleCount($cid);
        if ($count > 0) {
            flash_message('error', 'This category is used by ' . $count . ($count === 1 ? ' article' : ' articles') . '. Move or delete them first, then delete the category.');
            return redirect(url('/admin/news-categories'));
        }
        NewsCategory::delete($cid);
        $this->audit('news_category.delete', 'news_category', $cid, 'Deleted news category "' . $category['name'] . '"');
        flash_message('success', 'Category deleted.');
        return redirect(url('/admin/news-categories'));
    }

    /** @param array<string, mixed>|null $category @param array<string, string> $values */
    private function form(?array $category, array $values): Response
    {
        return $this->page('admin/news-categories/form', $category === null ? 'New category' : 'Edit category', 'news-categories', [
            'category' => $category,
            'v' => $values,
            'action' => $category === null ? '/admin/news-categories' : '/admin/news-categories/' . (int) $category['id'],
            'slugLocked' => $category !== null && $category['slug'] === NewsCategory::SUCCESS_STORIES_SLUG,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function withTotals(): array
    {
        $counts = [];
        foreach (\App\Core\Database::connection()->fetchAll('SELECT category_id, COUNT(*) AS n FROM news_articles WHERE category_id IS NOT NULL GROUP BY category_id') as $row) {
            $counts[(int) $row['category_id']] = (int) $row['n'];
        }
        $rows = NewsCategory::all();
        foreach ($rows as &$row) {
            $row['article_count'] = $counts[(int) $row['id']] ?? 0;
        }
        return $rows;
    }

    private function validate(Request $request, ?int $id, ?string &$slug, ?string $lockedSlug = null): Validator
    {
        $post = $request->post();
        if ($lockedSlug === NewsCategory::SUCCESS_STORIES_SLUG) {
            $post['slug'] = $lockedSlug; // the website looks this category up by slug
        }
        $v = Validator::make($post, [
            'name' => 'required|line|max:120',
            'slug' => 'nullable|slug|max:140',
            'description' => 'nullable|string|max:2000',
            'sort_order' => 'nullable|int|min:0|max:9999',
        ]);
        if ($v->passes()) {
            $d = $v->validated();
            $slug = $this->resolveSlug($v, NewsCategory::class, $d['slug'] ?? null, (string) $d['name'], $id);
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
            'sort_order' => (int) ($d['sort_order'] ?? 0),
        ];
    }
}
