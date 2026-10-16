<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Program;
use App\Models\ProgramCategory;
use App\Services\Settings;

final class ProgramController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = ProgramCategory::allWithCounts();
        $activeCategory = null;
        $slug = $request->query('category');
        if ($slug !== '') {
            foreach ($categories as $category) {
                if ($category['slug'] === $slug) {
                    $activeCategory = $category;
                    break;
                }
            }
            if ($activeCategory === null) {
                throw HttpException::notFound('That program area does not exist.');
            }
        }

        $title = $activeCategory !== null ? $activeCategory['name'] . ' programs' : Settings::get('programs.hero_heading');
        $canonical = $activeCategory !== null ? url('/programs', ['category' => $activeCategory['slug']]) : '/programs';

        return $this->view('pages/programs/index', [
            'categories' => $categories,
            'programs' => Program::published($activeCategory !== null ? (int) $activeCategory['id'] : null),
            'activeCategory' => $activeCategory,
            'applicationsOpen' => Program::anyOpen(),
        ] + $this->meta('programs', $title, Settings::get('programs.hero_text'), $canonical, Settings::get('programs.hero_image') ?: null));
    }

    public function show(Request $request, string $slug): Response
    {
        $program = Program::findPublishedBySlug($slug);
        if ($program === null) {
            throw HttpException::notFound('That program could not be found. It may have ended or moved.');
        }

        $related = array_values(array_filter(
            $program['category_id'] !== null ? Program::published((int) $program['category_id']) : [],
            static fn (array $p): bool => (int) $p['id'] !== (int) $program['id']
        ));

        $meta = $this->meta(
            'programs',
            (string) ($program['seo_title'] ?: $program['title']),
            (string) ($program['seo_description'] ?: $program['summary']),
            '/programs/' . $program['slug'],
            $program['featured_image_path'] ?: null
        );
        // Program pages use their own SEO fields, never the listing's override.
        $meta['title'] = (string) ($program['seo_title'] ?: $program['title']);
        $meta['description'] = \App\Core\Text::excerpt((string) ($program['seo_description'] ?: $program['summary']), 160);
        $meta['pageKey'] = 'program';

        return $this->view('pages/programs/show', [
            'program' => $program,
            'images' => Program::images((int) $program['id']),
            'related' => array_slice($related, 0, 3),
        ] + $meta);
    }
}
