<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Services\Settings;

final class NewsController extends Controller
{
    private const PER_PAGE_DEFAULT = 6;
    private const PER_PAGE_FILTERED = 9;

    public function index(Request $request): Response
    {
        $q = mb_substr(trim($request->query('q')), 0, 100);
        $successId = $this->successStoriesId();

        if ($q !== '') {
            $result = NewsArticle::publishedPaginate($request, null, $q, self::PER_PAGE_FILTERED);
            $meta = $this->meta('news', 'Search results — News', Settings::get('news.hero_text'), '/news', Settings::get('news.hero_image') ?: null);
            $meta['robots'] = 'noindex,follow';
            return $this->listing($result, $meta, null, $q, null);
        }

        $result = NewsArticle::publishedPaginate($request, null, '', self::PER_PAGE_DEFAULT, $successId);
        $page = $result['paginator']->page;
        $title = Settings::get('news.hero_heading') . ($page > 1 ? ' — Page ' . $page : '');
        $meta = $this->meta('news', $title, Settings::get('news.hero_text'), url('/news', ['page' => $page > 1 ? $page : null]), Settings::get('news.hero_image') ?: null);
        return $this->listing($result, $meta, null, '', $successId !== null ? NewsArticle::latest(3, $successId) : []);
    }

    public function category(Request $request, string $slug): Response
    {
        $category = NewsCategory::findBySlug($slug);
        if ($category === null) {
            throw HttpException::notFound('That news category does not exist.');
        }
        $result = NewsArticle::publishedPaginate($request, (int) $category['id'], '', self::PER_PAGE_FILTERED);
        $page = $result['paginator']->page;
        $title = $category['name'] . ' — News' . ($page > 1 ? ' — Page ' . $page : '');
        $path = '/news/category/' . $category['slug'];
        $description = trim((string) ($category['description'] ?? '')) !== '' ? (string) $category['description'] : Settings::get('news.hero_text');
        $meta = $this->meta('news', $title, $description, url($path, ['page' => $page > 1 ? $page : null]), Settings::get('news.hero_image') ?: null);
        $meta['title'] = $title;
        $meta['description'] = \App\Core\Text::excerpt($description, 160);
        return $this->listing($result, $meta, $category, '', null);
    }

    public function show(Request $request, string $slug): Response
    {
        $article = NewsArticle::findPublishedBySlug($slug);
        if ($article === null) {
            throw HttpException::notFound('That article could not be found. It may have been moved or removed.');
        }
        $description = (string) ($article['seo_description'] ?: ($article['excerpt'] ?: $article['content']));
        $meta = $this->meta('news-article', (string) ($article['seo_title'] ?: $article['title']), $description, '/news/' . $article['slug'], $article['featured_image_path'] ?: null);
        // Article pages use their own SEO fields, never the listing's override.
        $meta['title'] = (string) ($article['seo_title'] ?: $article['title']);
        $meta['description'] = \App\Core\Text::excerpt($description, 160);
        $meta['pageKey'] = 'news-article';

        return $this->view('pages/news/show', [
            'article' => $article,
            'more' => NewsArticle::latest(3, $article['category_id'] !== null ? (int) $article['category_id'] : null, (int) $article['id']),
        ] + $meta);
    }

    private function successStoriesId(): ?int
    {
        $category = NewsCategory::findBySlug(NewsCategory::SUCCESS_STORIES_SLUG);
        return $category !== null ? (int) $category['id'] : null;
    }

    /**
     * @param array{items: list<array<string, mixed>>, paginator: \App\Core\Paginator} $result
     * @param array<string, mixed> $meta
     * @param array<string, mixed>|null $category
     * @param list<array<string, mixed>>|null $stories
     */
    private function listing(array $result, array $meta, ?array $category, string $q, ?array $stories): Response
    {
        return $this->view('pages/news/index', [
            'categories' => array_values(array_filter(NewsCategory::allWithCounts(), static fn (array $c): bool => (int) $c['article_count'] > 0)),
            'category' => $category,
            'q' => $q,
            'articles' => $result['items'],
            'paginator' => $result['paginator'],
            'stories' => $stories,
            'latest' => NewsArticle::latest(4),
        ] + $meta);
    }
}
