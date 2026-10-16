<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\ImpactMetric;
use App\Models\Milestone;
use App\Models\NewsArticle;
use App\Models\TeamMember;
use App\Services\Settings;

/** Static-content pages whose text comes from the admin's site settings. */
final class PageController extends Controller
{
    public function about(Request $request): Response
    {
        return $this->view('pages/about', [
            'milestones' => Milestone::published(),
            'team' => TeamMember::published(),
            'headline' => ImpactMetric::headline(),
        ] + $this->heroMeta('about', '/about'));
    }

    public function impact(Request $request): Response
    {
        return $this->view('pages/impact', [
            'metrics' => ImpactMetric::published(),
            'story' => NewsArticle::latestConsentedSuccessStory(),
        ] + $this->heroMeta('impact', '/impact'));
    }

    public function getInvolved(Request $request): Response
    {
        return $this->view('pages/get-involved', $this->heroMeta('get-involved', '/get-involved'));
    }

    public function privacy(Request $request): Response
    {
        return $this->view('pages/privacy', $this->meta('privacy', 'Privacy Notice', 'How RIT Foundation Ghana collects, uses and protects personal information.', '/privacy'));
    }

    public function terms(Request $request): Response
    {
        return $this->view('pages/terms', $this->meta('terms', 'Terms & Conditions', 'The terms for using the RIT Foundation Ghana website and services.', '/terms'));
    }

    /** @return array{title: string, description: string, canonical: ?string, ogImage: ?string, pageKey: string} */
    private function heroMeta(string $page, string $path): array
    {
        return $this->meta(
            $page,
            Settings::get($page . '.hero_heading'),
            Settings::get($page . '.hero_text'),
            $path,
            Settings::get($page . '.hero_image') ?: null
        );
    }
}
