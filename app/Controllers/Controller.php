<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\SeoMeta;
use App\Services\Settings;

/**
 * Base for public controllers.
 */
abstract class Controller
{
    /**
     * Layout variables for a page: title/description from the admin's SEO
     * settings for $pageKey when filled in, otherwise the given defaults.
     *
     * @return array{title: string, description: string, canonical: ?string, ogImage: ?string, pageKey: string}
     */
    protected function meta(string $pageKey, string $title, string $description = '', ?string $canonical = null, ?string $ogImage = null): array
    {
        $seo = SeoMeta::forPage($pageKey);
        $description = $description !== '' ? $description : Settings::get('org.tagline');
        return [
            'title' => trim((string) ($seo['meta_title'] ?? '')) !== '' ? (string) $seo['meta_title'] : $title,
            'description' => trim((string) ($seo['meta_description'] ?? '')) !== '' ? (string) $seo['meta_description'] : \App\Core\Text::excerpt($description, 160),
            'canonical' => $canonical,
            'ogImage' => ($seo['og_image_path'] ?? null) ?: ($ogImage ?: (Settings::get('site.share_image') ?: null)),
            'pageKey' => $pageKey,
        ];
    }

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return view($template, $data, $status);
    }
}
