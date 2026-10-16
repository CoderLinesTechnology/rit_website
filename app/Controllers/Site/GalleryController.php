<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use App\Services\Settings;

final class GalleryController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render($request, null);
    }

    public function album(Request $request, string $slug): Response
    {
        $album = GalleryCategory::findPublishedBySlug($slug);
        if ($album === null) {
            throw HttpException::notFound('That photo album could not be found.');
        }
        return $this->render($request, $album);
    }

    /** @param array<string, mixed>|null $album */
    private function render(Request $request, ?array $album): Response
    {
        $result = GalleryImage::publishedPaginate($request, $album !== null ? (int) $album['id'] : null, 12);
        $page = $result['paginator']->page;
        $path = $album !== null ? '/gallery/' . $album['slug'] : '/gallery';

        $title = $album !== null ? (string) $album['name'] . ' — Gallery' : Settings::get('gallery.hero_heading');
        $description = $album !== null && trim((string) ($album['description'] ?? '')) !== '' ? (string) $album['description'] : Settings::get('gallery.hero_text');
        if ($page > 1) {
            $title .= ' — Page ' . $page;
        }

        $meta = $this->meta('gallery', $title, $description, url($path, ['page' => $page > 1 ? $page : null]), Settings::get('gallery.hero_image') ?: null);
        // An unfiltered or filtered gallery shares the admin's "gallery" SEO override only on the main page.
        if ($album !== null) {
            $meta['title'] = $title;
            $meta['description'] = \App\Core\Text::excerpt($description, 160);
        }

        return $this->view('pages/gallery/index', [
            'albums' => GalleryCategory::published(),
            'album' => $album,
            'images' => $result['items'],
            'paginator' => $result['paginator'],
        ] + $meta);
    }
}
