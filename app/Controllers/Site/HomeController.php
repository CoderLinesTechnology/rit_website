<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use App\Models\ImpactMetric;
use App\Models\NewsArticle;
use App\Models\Program;
use App\Models\ProgramCategory;
use App\Services\Settings;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $album = GalleryCategory::findPublishedBySlug(GalleryCategory::TECH_SKILLS_SLUG);

        return $this->view('pages/home', [
            'metrics' => array_slice(ImpactMetric::published(true), 0, 4),
            'categories' => ProgramCategory::allWithCounts(),
            'programs' => Program::featured(6),
            'galleryAlbum' => $album,
            'galleryImages' => GalleryImage::forHomepage($album !== null ? (int) $album['id'] : null, 8),
            'articles' => NewsArticle::latest(3),
            'applicationsOpen' => Program::anyOpen(),
        ] + $this->meta('home', Settings::get('org.name'), Settings::get('home.hero_text'), '/', Settings::get('home.hero_image') ?: null));
    }
}
