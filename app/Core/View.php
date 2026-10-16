<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Plain-PHP templates in resources/views. Inside a template $this is the
 * View, so a page can choose its layout and fill named sections:
 *
 *   <?php $this->layout('layouts/public', ['title' => 'About us']) ?>
 *   <?php $this->start('head') ?> ... <?php $this->stop() ?>
 *   <?= $this->insert('partials/page-header', ['title' => 'About us']) ?>
 *
 * Layouts print the page with $this->section('content').
 */
final class View
{
    private ?string $layout = null;

    /** @var array<string, mixed> */
    private array $layoutData = [];

    /** @var array<string, string> */
    private array $sections = [];

    /** @var list<string> */
    private array $sectionStack = [];

    /** @param array<string, mixed> $data */
    public static function make(string $template, array $data = []): string
    {
        return (new self())->render($template, $data);
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $content = $this->renderFile($template, $data);
        while ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            $this->sections['content'] = $content;
            $content = $this->renderFile($layout, array_merge($data, $this->layoutData));
        }
        return $content;
    }

    /** @param array<string, mixed> $data */
    public function layout(string $name, array $data = []): void
    {
        $this->layout = $name;
        $this->layoutData = array_merge($this->layoutData, $data);
    }

    public function start(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function stop(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new \LogicException('View::stop() called without start()');
        }
        $this->sections[$name] = ($this->sections[$name] ?? '') . (string) ob_get_clean();
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    /** @param array<string, mixed> $data */
    public function insert(string $template, array $data = []): string
    {
        return (new self())->renderFile($template, $data);
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        if (!preg_match('#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#', $template)) {
            throw new \InvalidArgumentException('Invalid template name.');
        }
        $file = APP_ROOT . '/resources/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: {$template}");
        }
        $level = ob_get_level();
        ob_start();
        try {
            (function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                include $__file;
            })($file, $data);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
