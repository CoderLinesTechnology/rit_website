<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionMethod;
use ReflectionNamedType;

/**
 * Small router. Route patterns use {name} placeholders: {id} matches a
 * positive integer, {token} a 64-char hex token and anything else a slug.
 * A custom constraint can be given as {name:regex}.
 *
 * Middleware names: "auth" (signed-in administrator), "can:<permission>",
 * "guest" (not signed in). Every POST route verifies the CSRF token unless
 * the route is marked ->withoutCsrf().
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    private string $prefix = '';

    /** @var list<string> */
    private array $groupMiddleware = [];

    /** @param array{0: class-string, 1: string} $handler */
    public function get(string $pattern, array $handler): Route
    {
        return $this->add('GET', $pattern, $handler);
    }

    /** @param array{0: class-string, 1: string} $handler */
    public function post(string $pattern, array $handler): Route
    {
        return $this->add('POST', $pattern, $handler);
    }

    /**
     * @param array{prefix?: string, middleware?: list<string>} $options
     * @param callable(self): void $routes
     */
    public function group(array $options, callable $routes): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;
        $this->prefix .= $options['prefix'] ?? '';
        $this->groupMiddleware = array_merge($this->groupMiddleware, $options['middleware'] ?? []);
        $routes($this);
        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /** @param array{0: class-string, 1: string} $handler */
    private function add(string $method, string $pattern, array $handler): Route
    {
        $full = rtrim($this->prefix . $pattern, '/');
        $route = new Route($method, $full === '' ? '/' : $full, $handler, $this->groupMiddleware);
        $this->routes[] = $route;
        return $route;
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $allowed = [];

        foreach ($this->routes as $route) {
            $params = $route->match($request->path());
            if ($params === null) {
                continue;
            }
            if ($route->method !== $method) {
                $allowed[] = $route->method;
                continue;
            }
            $request->setRouteParams($params);

            $blocked = $this->runMiddleware($route, $request);
            if ($blocked !== null) {
                return $blocked;
            }
            if ($method === 'POST' && $route->csrf && !Csrf::verify($request)) {
                throw new HttpException(419, 'Your session has expired or the form was out of date. Please go back, reload the page and try again.');
            }
            return $this->call($route, $request, $params);
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'That action is not available here.', ['Allow' => implode(', ', array_unique($allowed))]);
        }
        throw HttpException::notFound();
    }

    private function runMiddleware(Route $route, Request $request): ?Response
    {
        foreach ($route->middleware as $middleware) {
            [$name, $arg] = array_pad(explode(':', $middleware, 2), 2, '');
            switch ($name) {
                case 'auth':
                    if (Auth::user() === null) {
                        if ($request->method() === 'GET') {
                            Session::set('_intended', $request->fullPath());
                        }
                        return Response::redirect('/admin/login');
                    }
                    break;
                case 'guest':
                    if (Auth::user() !== null) {
                        return Response::redirect('/admin');
                    }
                    break;
                case 'can':
                    if (!Auth::can($arg)) {
                        AuditLog::record('auth.forbidden', null, null, 'Denied access to ' . $request->method() . ' ' . $request->path());
                        throw HttpException::forbidden('Your account does not have permission to use this page.');
                    }
                    break;
                default:
                    throw new \LogicException("Unknown middleware: {$name}");
            }
        }
        return null;
    }

    /** @param array<string, string> $params */
    private function call(Route $route, Request $request, array $params): Response
    {
        [$class, $method] = $route->handler;
        $controller = new $class();
        $args = [$request];
        $reflection = new ReflectionMethod($controller, $method);
        foreach (array_slice($reflection->getParameters(), 1) as $parameter) {
            $value = $params[$parameter->getName()] ?? null;
            if ($value === null) {
                throw new \LogicException("Route parameter {$parameter->getName()} missing for {$class}::{$method}");
            }
            $type = $parameter->getType();
            $args[] = ($type instanceof ReflectionNamedType && $type->getName() === 'int') ? (int) $value : $value;
        }
        $response = $controller->{$method}(...$args);
        if (!$response instanceof Response) {
            throw new \LogicException("{$class}::{$method} must return a Response");
        }
        return $response;
    }
}
