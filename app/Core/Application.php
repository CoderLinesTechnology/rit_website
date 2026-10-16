<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Application
{
    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
        $router = $this->router;
        require APP_ROOT . '/routes/web.php';
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function handle(Request $request): Response
    {
        Session::configure($request->isSecure());

        if (config('app.force_https') && !$request->isSecure() && PHP_SAPI !== 'cli-server') {
            return Response::redirect(config('app.url') . $request->fullPath(), 301);
        }

        // One address per page: /about/ → /about
        $rawPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
        if ($request->method() === 'GET' && $rawPath !== '/' && str_ends_with($rawPath, '/')) {
            // Built from the still-encoded path, so "%09" or "%0d" can never become a "//host" redirect.
            $qs = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
            $target = '/' . ltrim(rtrim($rawPath, '/'), '/');
            return Response::redirect($target . ($qs !== '' ? '?' . $qs : ''), 301);
        }

        // Anonymous visitors get a stored copy of public pages without touching the database.
        $cacheable = PageCache::eligible($request);
        if ($cacheable && ($cached = PageCache::fetch($request)) !== null) {
            return $this->finalise($request, $cached);
        }

        try {
            if (config('app.env') === 'production' && config('app.key') === null) {
                Logger::error('APP_KEY is missing or a placeholder; refusing to serve requests.');
                throw new HttpException(503, 'The website is being configured. Please try again shortly.');
            }
            $response = $this->router->dispatch($request);
            if ($cacheable && $response->header('X-Page-Cache') === null) {
                PageCache::store($request, $response);
            }
            // Any successful change made in the admin area (or the one-time setup) can affect public pages.
            if ($request->isPost() && $response->status() < 400
                && (str_starts_with($request->path(), '/admin/') || $request->path() === '/setup')) {
                PageCache::flush();
            }
        } catch (HttpException $e) {
            $response = $this->errorPage($e->status, $e->getMessage());
            foreach ($e->headers as $name => $value) {
                $response->withHeader($name, $value);
            }
        } catch (Throwable $e) {
            ErrorHandler::report($e);
            $response = $this->errorPage(500, '');
        }

        return $this->finalise($request, $response);
    }

    private function finalise(Request $request, Response $response): Response
    {
        $isAdmin = str_starts_with($request->path(), '/admin') || str_starts_with($request->path(), '/setup');
        if ($isAdmin) {
            $response->withHeader('Cache-Control', 'no-store, private');
            $response->withHeader('X-Robots-Tag', 'noindex, nofollow');
        } elseif ($response->header('Cache-Control') === null) {
            // Pages that issued a session (forms) must never be stored by shared caches.
            $response->withHeader('Cache-Control', Session::isStarted() ? 'no-store, private' : 'no-cache');
        }
        if (!config('app.indexable')) {
            $response->withHeader('X-Robots-Tag', 'noindex, nofollow');
        }

        // Conditional GET for cacheable public pages.
        if ($request->method() === 'GET' && $response->status() === 200 && !$isAdmin && !Session::isStarted()) {
            $etag = '"' . substr(hash('sha256', $response->body()), 0, 32) . '"';
            $response->withHeader('ETag', $etag);
            if (trim($request->header('If-None-Match')) === $etag) {
                return (new Response('', 304))->withHeader('ETag', $etag)->withHeader('Cache-Control', 'no-cache');
            }
        }
        if ($request->method() === 'HEAD') {
            return new Response('', $response->status(), $response->headers());
        }
        return $response;
    }

    public function errorPage(int $status, string $message): Response
    {
        $known = [403, 404, 405, 419, 422, 429, 500, 503];
        $template = 'errors/' . (in_array($status, $known, true) ? $status : 500);
        try {
            $body = View::make($template, ['message' => $message, 'status' => $status]);
        } catch (Throwable $e) {
            ErrorHandler::report($e);
            $body = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Error</title></head><body><h1>Sorry, something went wrong.</h1><p><a href="/">Return to the homepage</a></p></body></html>';
        }
        $response = Response::html($body, $status);
        if ($status === 503) {
            $response->withHeader('Retry-After', '600');
        }
        return $response;
    }
}
