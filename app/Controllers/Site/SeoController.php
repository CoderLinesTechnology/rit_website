<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\GalleryCategory;
use App\Models\NewsArticle;
use App\Models\Program;

final class SeoController extends Controller
{
    private const STATIC_PAGES = [
        '/', '/about', '/programs', '/impact', '/gallery', '/news', '/get-involved',
        '/volunteer', '/partner', '/apply', '/contact', '/donate', '/privacy', '/terms',
    ];

    public function robots(Request $request): Response
    {
        $body = config('app.indexable')
            ? "User-agent: *\nDisallow: /admin\nDisallow: /setup\nSitemap: " . absolute_url('/sitemap.xml') . "\n"
            : "User-agent: *\nDisallow: /\n";
        return Response::text($body);
    }

    public function sitemap(Request $request): Response
    {
        $entries = [];
        foreach (self::STATIC_PAGES as $path) {
            $entries[] = [$path, null];
        }
        foreach (Program::published() as $program) {
            $entries[] = ['/programs/' . $program['slug'], $program['updated_at'] ?? null];
        }
        foreach (GalleryCategory::published() as $album) {
            $entries[] = ['/gallery/' . $album['slug'], $album['updated_at'] ?? null];
        }
        foreach (NewsArticle::sitemapEntries() as $article) {
            $entries[] = ['/news/' . $article['slug'], $article['updated_at'] ?? null];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($entries as [$path, $updated]) {
            $xml .= '  <url><loc>' . htmlspecialchars(absolute_url($path), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
            if (is_string($updated) && preg_match('/^\d{4}-\d{2}-\d{2}/', $updated)) {
                $xml .= '<lastmod>' . substr($updated, 0, 10) . '</lastmod>';
            }
            $xml .= "</url>\n";
        }
        return Response::text($xml . "</urlset>\n", 'application/xml; charset=UTF-8');
    }
}
