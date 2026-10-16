<?php

declare(strict_types=1);

/*
 * All routes. $router is provided by App\Core\Application.
 *
 * Every POST route checks the CSRF token automatically (only the signed
 * payment webhook opts out). Admin routes require a signed-in administrator
 * ("auth") plus the permission named with "can:<permission>"; controllers
 * must still check ownership/scope of the specific record they load.
 */

use App\Controllers\Admin;
use App\Controllers\Site;
use App\Core\Router;

/** @var Router $router */

// ── Public website ───────────────────────────────────────────────────────
$router->get('/', [Site\HomeController::class, 'index']);
$router->get('/about', [Site\PageController::class, 'about']);
$router->get('/impact', [Site\PageController::class, 'impact']);
$router->get('/get-involved', [Site\PageController::class, 'getInvolved']);
$router->get('/privacy', [Site\PageController::class, 'privacy']);
$router->get('/terms', [Site\PageController::class, 'terms']);

$router->get('/programs', [Site\ProgramController::class, 'index']);
$router->get('/programs/{slug}', [Site\ProgramController::class, 'show']);

$router->get('/gallery', [Site\GalleryController::class, 'index']);
$router->get('/gallery/{slug}', [Site\GalleryController::class, 'album']);

$router->get('/news', [Site\NewsController::class, 'index']);
$router->get('/news/category/{slug}', [Site\NewsController::class, 'category']);
$router->get('/news/{slug}', [Site\NewsController::class, 'show']);

$router->get('/apply', [Site\ApplicationController::class, 'create']);
$router->post('/apply', [Site\ApplicationController::class, 'store']);
$router->get('/apply/thank-you', [Site\ApplicationController::class, 'thanks']);

$router->get('/volunteer', [Site\VolunteerController::class, 'create']);
$router->post('/volunteer', [Site\VolunteerController::class, 'store']);
$router->get('/volunteer/thank-you', [Site\VolunteerController::class, 'thanks']);

$router->get('/partner', [Site\PartnerController::class, 'create']);
$router->post('/partner', [Site\PartnerController::class, 'store']);
$router->get('/partner/thank-you', [Site\PartnerController::class, 'thanks']);

$router->get('/contact', [Site\ContactController::class, 'create']);
$router->post('/contact', [Site\ContactController::class, 'store']);
$router->get('/contact/thank-you', [Site\ContactController::class, 'thanks']);

$router->get('/donate', [Site\DonationController::class, 'index']);
$router->post('/donate', [Site\DonationController::class, 'start']);
$router->get('/donate/callback', [Site\DonationController::class, 'callback']);
$router->post('/webhooks/payments/{provider}', [Site\DonationController::class, 'webhook'])->withoutCsrf();

$router->get('/sitemap.xml', [Site\SeoController::class, 'sitemap']);
$router->get('/robots.txt', [Site\SeoController::class, 'robots']);

// One-time first-administrator setup (disabled once an administrator exists).
$router->get('/setup', [Admin\SetupController::class, 'show']);
$router->post('/setup', [Admin\SetupController::class, 'store']);

// ── Administration ───────────────────────────────────────────────────────
$router->group(['prefix' => '/admin', 'middleware' => ['guest']], static function (Router $r): void {
    $r->get('/login', [Admin\AuthController::class, 'showLogin']);
    $r->post('/login', [Admin\AuthController::class, 'login']);
    $r->get('/forgot-password', [Admin\PasswordResetController::class, 'request']);
    $r->post('/forgot-password', [Admin\PasswordResetController::class, 'send']);
    $r->get('/reset-password/{token}', [Admin\PasswordResetController::class, 'showReset']);
    $r->post('/reset-password/{token}', [Admin\PasswordResetController::class, 'reset']);
});

$router->group(['prefix' => '/admin', 'middleware' => ['auth']], static function (Router $r): void {
    $r->post('/logout', [Admin\AuthController::class, 'logout']);
    $r->get('', [Admin\DashboardController::class, 'index']);
    $r->get('/help', [Admin\HelpController::class, 'index']);
    $r->get('/account', [Admin\AccountController::class, 'edit']);
    $r->post('/account', [Admin\AccountController::class, 'update']);
    $r->post('/account/password', [Admin\AccountController::class, 'password']);

    // Content
    $r->group(['middleware' => ['can:content.manage']], static function (Router $r): void {
        $r->get('/programs', [Admin\ProgramController::class, 'index']);
        $r->get('/programs/create', [Admin\ProgramController::class, 'create']);
        $r->post('/programs', [Admin\ProgramController::class, 'store']);
        $r->get('/programs/{id}/edit', [Admin\ProgramController::class, 'edit']);
        $r->post('/programs/{id}', [Admin\ProgramController::class, 'update']);
        $r->post('/programs/{id}/delete', [Admin\ProgramController::class, 'destroy']);
        $r->post('/programs/{id}/images', [Admin\ProgramController::class, 'uploadImages']);
        $r->post('/programs/{id}/images/{image_id}/delete', [Admin\ProgramController::class, 'deleteImage']);

        $r->get('/program-categories', [Admin\ProgramCategoryController::class, 'index']);
        $r->get('/program-categories/create', [Admin\ProgramCategoryController::class, 'create']);
        $r->post('/program-categories', [Admin\ProgramCategoryController::class, 'store']);
        $r->get('/program-categories/{id}/edit', [Admin\ProgramCategoryController::class, 'edit']);
        $r->post('/program-categories/{id}', [Admin\ProgramCategoryController::class, 'update']);
        $r->post('/program-categories/{id}/delete', [Admin\ProgramCategoryController::class, 'destroy']);

        $r->get('/news', [Admin\NewsController::class, 'index']);
        $r->get('/news/create', [Admin\NewsController::class, 'create']);
        $r->post('/news', [Admin\NewsController::class, 'store']);
        $r->get('/news/{id}/edit', [Admin\NewsController::class, 'edit']);
        $r->post('/news/{id}', [Admin\NewsController::class, 'update']);
        $r->post('/news/{id}/publish', [Admin\NewsController::class, 'publish']);
        $r->post('/news/{id}/unpublish', [Admin\NewsController::class, 'unpublish']);
        $r->post('/news/{id}/delete', [Admin\NewsController::class, 'destroy']);

        $r->get('/news-categories', [Admin\NewsCategoryController::class, 'index']);
        $r->get('/news-categories/create', [Admin\NewsCategoryController::class, 'create']);
        $r->post('/news-categories', [Admin\NewsCategoryController::class, 'store']);
        $r->get('/news-categories/{id}/edit', [Admin\NewsCategoryController::class, 'edit']);
        $r->post('/news-categories/{id}', [Admin\NewsCategoryController::class, 'update']);
        $r->post('/news-categories/{id}/delete', [Admin\NewsCategoryController::class, 'destroy']);

        $r->get('/gallery', [Admin\GalleryController::class, 'index']);
        $r->get('/gallery/albums/create', [Admin\GalleryController::class, 'createAlbum']);
        $r->post('/gallery/albums', [Admin\GalleryController::class, 'storeAlbum']);
        $r->get('/gallery/albums/{id}', [Admin\GalleryController::class, 'showAlbum']);
        $r->get('/gallery/albums/{id}/edit', [Admin\GalleryController::class, 'editAlbum']);
        $r->post('/gallery/albums/{id}', [Admin\GalleryController::class, 'updateAlbum']);
        $r->post('/gallery/albums/{id}/delete', [Admin\GalleryController::class, 'destroyAlbum']);
        $r->post('/gallery/albums/{id}/images', [Admin\GalleryController::class, 'uploadImages']);
        $r->get('/gallery/images/{id}/edit', [Admin\GalleryController::class, 'editImage']);
        $r->post('/gallery/images/{id}', [Admin\GalleryController::class, 'updateImage']);
        $r->post('/gallery/images/{id}/delete', [Admin\GalleryController::class, 'destroyImage']);

        $r->get('/impact', [Admin\ImpactController::class, 'index']);
        $r->get('/impact/create', [Admin\ImpactController::class, 'create']);
        $r->post('/impact', [Admin\ImpactController::class, 'store']);
        $r->get('/impact/{id}/edit', [Admin\ImpactController::class, 'edit']);
        $r->post('/impact/{id}', [Admin\ImpactController::class, 'update']);
        $r->post('/impact/{id}/delete', [Admin\ImpactController::class, 'destroy']);

        $r->get('/team', [Admin\TeamController::class, 'index']);
        $r->get('/team/members/create', [Admin\TeamController::class, 'createMember']);
        $r->post('/team/members', [Admin\TeamController::class, 'storeMember']);
        $r->get('/team/members/{id}/edit', [Admin\TeamController::class, 'editMember']);
        $r->post('/team/members/{id}', [Admin\TeamController::class, 'updateMember']);
        $r->post('/team/members/{id}/delete', [Admin\TeamController::class, 'destroyMember']);
        $r->get('/team/milestones/create', [Admin\TeamController::class, 'createMilestone']);
        $r->post('/team/milestones', [Admin\TeamController::class, 'storeMilestone']);
        $r->get('/team/milestones/{id}/edit', [Admin\TeamController::class, 'editMilestone']);
        $r->post('/team/milestones/{id}', [Admin\TeamController::class, 'updateMilestone']);
        $r->post('/team/milestones/{id}/delete', [Admin\TeamController::class, 'destroyMilestone']);
    });

    // Private submissions
    $r->group(['middleware' => ['can:submissions.manage']], static function (Router $r): void {
        $r->get('/applications', [Admin\ApplicationController::class, 'index']);
        $r->get('/applications/{id}', [Admin\ApplicationController::class, 'show']);
        $r->post('/applications/{id}/status', [Admin\ApplicationController::class, 'updateStatus']);
        $r->post('/applications/{id}/notes', [Admin\ApplicationController::class, 'updateNotes']);
        $r->get('/applications/{id}/attachment', [Admin\ApplicationController::class, 'attachment']);
        $r->post('/applications/{id}/delete', [Admin\ApplicationController::class, 'destroy']);

        foreach (['volunteers' => Admin\VolunteerController::class, 'partnerships' => Admin\PartnershipController::class, 'contacts' => Admin\ContactController::class] as $path => $controller) {
            $r->get('/' . $path, [$controller, 'index']);
            $r->get('/' . $path . '/{id}', [$controller, 'show']);
            $r->post('/' . $path . '/{id}/status', [$controller, 'updateStatus']);
            $r->post('/' . $path . '/{id}/notes', [$controller, 'updateNotes']);
            $r->post('/' . $path . '/{id}/delete', [$controller, 'destroy']);
        }
    });

    $r->group(['middleware' => ['can:donations.view']], static function (Router $r): void {
        $r->get('/donations', [Admin\DonationController::class, 'index']);
        $r->get('/donations/{id}', [Admin\DonationController::class, 'show']);
    });

    // Site settings (each settings group also has its own permission, checked in the controller)
    $r->group(['middleware' => ['can:settings.content']], static function (Router $r): void {
        $r->get('/settings', [Admin\SettingsController::class, 'index']);
        $r->post('/settings/{group}', [Admin\SettingsController::class, 'update']);
        $r->get('/social-links', [Admin\SocialLinkController::class, 'index']);
        $r->post('/social-links', [Admin\SocialLinkController::class, 'update']);
        $r->get('/seo', [Admin\SeoController::class, 'index']);
        $r->post('/seo/{id}', [Admin\SeoController::class, 'update']);
    });

    // System
    $r->group(['middleware' => ['can:admins.manage']], static function (Router $r): void {
        $r->get('/admins', [Admin\AdminUserController::class, 'index']);
        $r->get('/admins/create', [Admin\AdminUserController::class, 'create']);
        $r->post('/admins', [Admin\AdminUserController::class, 'store']);
        $r->get('/admins/{id}/edit', [Admin\AdminUserController::class, 'edit']);
        $r->post('/admins/{id}', [Admin\AdminUserController::class, 'update']);
        $r->post('/admins/{id}/password', [Admin\AdminUserController::class, 'password']);
        $r->post('/admins/{id}/status', [Admin\AdminUserController::class, 'status']);
        $r->get('/email-log', [Admin\EmailLogController::class, 'index']);
    });
    $r->get('/audit-log', [Admin\AuditLogController::class, 'index'])->middleware('can:audit.view');
});
