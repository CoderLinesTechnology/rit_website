<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\NewsArticle;
use App\Models\NewsCategory;

final class NewsController extends AdminController
{
    use ContentSupport;

    private const COLS = ['path' => 'featured_image_path', 'thumb' => 'featured_thumb_path', 'alt' => 'featured_image_alt'];
    private const BOOLS = ['is_featured', 'consent_confirmed'];

    public function index(Request $request): Response
    {
        $result = NewsArticle::adminPaginate($request, 20);
        $now = gmdate('Y-m-d H:i:s');
        foreach ($result['items'] as &$item) {
            $item['is_scheduled'] = $item['status'] === 'published' && (string) $item['published_at'] > $now;
            $item['is_success_story'] = ($item['category_slug'] ?? '') === NewsCategory::SUCCESS_STORIES_SLUG;
        }
        unset($item);
        return $this->page('admin/news/index', 'News & stories', 'news', [
            'articles' => $result['items'],
            'paginator' => $result['paginator'],
            'filters' => [
                'q' => mb_substr($request->query('q'), 0, 100),
                'status' => in_array($request->query('status'), NewsArticle::STATUSES, true) ? $request->query('status') : '',
                'category' => $request->queryInt('category') > 0 ? (string) $request->queryInt('category') : '',
            ],
            'categoryOptions' => $this->categoryOptions(),
            'returnTo' => $request->fullPath(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, $this->values(['status' => 'draft', 'is_featured' => '0', 'consent_confirmed' => '0'], self::BOOLS));
    }

    public function store(Request $request): Response
    {
        $v = $this->validate($request, null, $slug, $when);
        if ($v->fails()) {
            return $this->invalid(url('/admin/news/create'), $v->errors(), $request->post());
        }
        $d = $v->validated();
        $plan = $this->planImage($request, $v, null, self::COLS, 'news', $d['image_alt'] ?? null);
        if ($v->fails()) {
            return $this->invalid(url('/admin/news/create'), $v->errors(), $request->post());
        }
        try {
            $id = NewsArticle::create($this->row($d, $slug, $when) + $plan['data'] + ['created_by' => $this->adminId(), 'updated_by' => $this->adminId()]);
        } catch (\Throwable $e) {
            $this->discardStored($plan['stored']);
            throw $e;
        }
        $this->audit('news.create', 'news_article', $id, 'Created article "' . $d['title'] . '" (' . $d['status'] . ')');
        flash_message('success', $d['status'] === 'published' ? 'Article saved and ' . $this->liveText($when) . '.' : 'Draft saved. It is not visible on the website.');
        return redirect(url('/admin/news/' . $id . '/edit'));
    }

    public function edit(Request $request, string $id): Response
    {
        $article = $this->findOr404(NewsArticle::find((int) $id));
        $record = $article;
        $record['published_at'] = $this->toDatetimeLocal($article['published_at']);
        $record['image_alt'] = (string) ($article['featured_image_alt'] ?? '');
        return $this->form($article, $this->values($record, self::BOOLS));
    }

    public function update(Request $request, string $id): Response
    {
        $article = $this->findOr404(NewsArticle::find((int) $id));
        $aid = (int) $article['id'];
        $back = url('/admin/news/' . $aid . '/edit');
        $v = $this->validate($request, $aid, $slug, $when);
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        $d = $v->validated();
        $plan = $this->planImage($request, $v, $article, self::COLS, 'news', $d['image_alt'] ?? null);
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        try {
            NewsArticle::update($aid, $this->row($d, $slug, $when) + $plan['data'] + ['updated_by' => $this->adminId()]);
        } catch (\Throwable $e) {
            $this->discardStored($plan['stored']);
            throw $e;
        }
        $this->discardImages($plan['discard']);
        $this->audit('news.update', 'news_article', $aid, 'Updated article "' . $d['title'] . '" (' . $d['status'] . ')');
        flash_message('success', 'Article saved.' . ($d['status'] === 'published' ? ' It is ' . $this->liveText($when) . '.' : ''));
        return redirect($back);
    }

    public function publish(Request $request, string $id): Response
    {
        $article = $this->findOr404(NewsArticle::find((int) $id));
        $aid = (int) $article['id'];
        if ($this->needsConsent($article) && (int) $article['consent_confirmed'] !== 1) {
            flash_message('error', 'This success story cannot be published until you confirm the people named or pictured have given permission. Open the article, tick the permission box and save.');
            return $this->backTo($request, url('/admin/news'));
        }
        $when = (string) ($article['published_at'] ?? '') !== '' ? (string) $article['published_at'] : gmdate('Y-m-d H:i:s');
        NewsArticle::update($aid, ['status' => 'published', 'published_at' => $when, 'updated_by' => $this->adminId()]);
        $this->audit('news.publish', 'news_article', $aid, 'Published article "' . $article['title'] . '"');
        flash_message('success', 'Article ' . $this->liveText($when) . '.');
        return $this->backTo($request, url('/admin/news'));
    }

    public function unpublish(Request $request, string $id): Response
    {
        $article = $this->findOr404(NewsArticle::find((int) $id));
        NewsArticle::update((int) $article['id'], ['status' => 'draft', 'updated_by' => $this->adminId()]);
        $this->audit('news.unpublish', 'news_article', (int) $article['id'], 'Unpublished article "' . $article['title'] . '"');
        flash_message('success', 'Article unpublished. It is now a draft and no longer visible on the website.');
        return $this->backTo($request, url('/admin/news'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $article = $this->findOr404(NewsArticle::find((int) $id));
        NewsArticle::delete((int) $article['id']);
        $this->discardImages([[(string) $article['featured_image_path'], (string) $article['featured_thumb_path']]]);
        $this->audit('news.delete', 'news_article', (int) $article['id'], 'Deleted article "' . $article['title'] . '"');
        flash_message('success', 'Article deleted.');
        return $this->backTo($request, url('/admin/news'));
    }

    /** @param array<string, mixed>|null $article @param array<string, string> $values */
    private function form(?array $article, array $values): Response
    {
        $aid = $article !== null ? (int) $article['id'] : null;
        $successId = null;
        foreach (NewsCategory::all() as $c) {
            if ($c['slug'] === NewsCategory::SUCCESS_STORIES_SLUG) {
                $successId = (int) $c['id'];
            }
        }
        return $this->page('admin/news/form', $aid === null ? 'New article' : 'Edit article', 'news', [
            'article' => $article,
            'v' => $values,
            'action' => $aid === null ? '/admin/news' : '/admin/news/' . $aid,
            'categoryOptions' => $this->categoryOptions(),
            'successCategoryId' => $successId,
            'maxMb' => $this->maxMb(),
            'returnTo' => $aid === null ? '' : '/admin/news/' . $aid . '/edit',
        ]);
    }

    /** @return array<int, string> */
    private function categoryOptions(): array
    {
        $options = [];
        foreach (NewsCategory::all() as $c) {
            $options[(int) $c['id']] = (string) $c['name'];
        }
        return $options;
    }

    /** @param array<string, mixed> $article */
    private function needsConsent(array $article): bool
    {
        if ($article['category_id'] === null) {
            return false;
        }
        $category = NewsCategory::find((int) $article['category_id']);
        return $category !== null && $category['slug'] === NewsCategory::SUCCESS_STORIES_SLUG;
    }

    private function liveText(?string $when): string
    {
        return $when !== null && $when > gmdate('Y-m-d H:i:s')
            ? 'scheduled to appear on ' . format_date($when, 'j F Y \a\t H:i')
            : 'live on the website';
    }

    private function validate(Request $request, ?int $id, ?string &$slug, ?string &$when): Validator
    {
        $v = Validator::make($request->post(), [
            'title' => 'required|line|max:200',
            'slug' => 'nullable|slug|max:220',
            'category_id' => 'nullable|int',
            'excerpt' => 'required|string|max:500',
            'content' => 'required|string|max:100000',
            'image_alt' => 'nullable|line|max:255',
            'author_name' => 'nullable|line|max:120',
            'status' => 'required|in:' . implode(',', NewsArticle::STATUSES),
            'published_at' => 'nullable|datetime',
            'is_featured' => 'boolean',
            'consent_confirmed' => 'boolean',
            'seo_title' => 'nullable|line|max:150',
            'seo_description' => 'nullable|line|max:300',
        ], [
            'category_id' => 'Category', 'image_alt' => 'Image description', 'seo_title' => 'Search title',
            'seo_description' => 'Search description', 'published_at' => 'Publication date', 'excerpt' => 'Short summary', 'content' => 'Article text',
        ]);
        if ($v->fails()) {
            return $v;
        }
        $d = $v->validated();
        $category = null;
        if (($d['category_id'] ?? null) !== null) {
            $category = NewsCategory::find((int) $d['category_id']);
            if ($category === null) {
                $v->addError('category_id', 'Category is not one of the available options.');
            }
        }
        if ($d['status'] === 'published' && $category !== null && $category['slug'] === NewsCategory::SUCCESS_STORIES_SLUG && !$d['consent_confirmed']) {
            $v->addError('consent_confirmed', 'Success stories can only be published once you confirm the people named or pictured have given permission.');
        }
        $slug = $this->resolveSlug($v, NewsArticle::class, $d['slug'] ?? null, (string) $d['title'], $id);
        $when = null;
        if (($d['published_at'] ?? null) !== null) {
            $when = str_replace('T', ' ', (string) $d['published_at']) . ':00';
        } elseif ($d['status'] === 'published') {
            // Keep an existing publication date; otherwise publish now.
            $existing = $id !== null ? (NewsArticle::find($id)['published_at'] ?? null) : null;
            $when = $existing !== null ? (string) $existing : gmdate('Y-m-d H:i:s');
        }
        return $v;
    }

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    private function row(array $d, string $slug, ?string $when): array
    {
        return [
            'category_id' => $d['category_id'] ?? null,
            'title' => $d['title'],
            'slug' => $slug,
            'excerpt' => $d['excerpt'],
            'content' => $d['content'],
            'author_name' => $d['author_name'] ?? null,
            'status' => $d['status'],
            'published_at' => $when,
            'is_featured' => $this->bit($d['is_featured']),
            'consent_confirmed' => $this->bit($d['consent_confirmed']),
            'seo_title' => $d['seo_title'] ?? null,
            'seo_description' => $d['seo_description'] ?? null,
        ];
    }
}
