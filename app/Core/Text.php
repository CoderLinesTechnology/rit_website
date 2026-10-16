<?php

declare(strict_types=1);

namespace App\Core;

/**
 * The only way administrator-entered long text becomes HTML.
 *
 * Escape-first: every character is run through htmlspecialchars() before any
 * inline transform, so raw HTML can never pass through. Supported markup:
 * paragraphs, line breaks, "## " / "### " headings, "- " / "* " bullet lists,
 * "1. " numbered lists, "> " quotes, **bold**, *italic* / _italic_ and
 * [text](url) links (http, https, mailto and site-relative paths only).
 * All quantifiers are bounded so hostile input cannot cause quadratic work.
 */
final class Text
{
    private const FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5;

    public static function format(string $markup): string
    {
        $markup = self::normalise($markup);
        if ($markup === '') {
            return '';
        }

        $html = [];
        $para = [];
        $list = null;      // ['ul'|'ol', list<string>]
        $quote = [];

        $flush = static function () use (&$html, &$para, &$list, &$quote): void {
            if ($para !== []) {
                $html[] = '<p>' . implode('<br>', array_map([self::class, 'inline'], $para)) . '</p>';
                $para = [];
            }
            if ($list !== null) {
                [$tag, $items] = $list;
                $html[] = "<{$tag}>" . implode('', array_map(
                    static fn (string $i): string => '<li>' . self::inline($i) . '</li>',
                    $items
                )) . "</{$tag}>";
                $list = null;
            }
            if ($quote !== []) {
                $html[] = '<blockquote><p>' . implode('<br>', array_map([self::class, 'inline'], $quote)) . '</p></blockquote>';
                $quote = [];
            }
        };

        foreach (explode("\n", $markup) as $line) {
            $line = trim($line);
            if ($line === '') {
                $flush();
                continue;
            }
            if (str_starts_with($line, '### ') || str_starts_with($line, '## ')) {
                $flush();
                $level = str_starts_with($line, '### ') ? 3 : 2;
                $html[] = "<h{$level}>" . self::inline(ltrim(substr($line, $level + 1))) . "</h{$level}>";
                continue;
            }
            if (preg_match('/^[-*] +(.*)$/', $line, $m) === 1) {
                if ($list === null || $list[0] !== 'ul') {
                    $flush();
                    $list = ['ul', []];
                }
                $list[1][] = $m[1];
                continue;
            }
            if (preg_match('/^\d{1,3}\. +(.*)$/', $line, $m) === 1) {
                if ($list === null || $list[0] !== 'ol') {
                    $flush();
                    $list = ['ol', []];
                }
                $list[1][] = $m[1];
                continue;
            }
            if (str_starts_with($line, '> ') || $line === '>') {
                if ($quote === []) {
                    $flush();
                }
                $quote[] = ltrim(substr($line, 1));
                continue;
            }
            if ($list !== null || $quote !== []) {
                $flush();
            }
            $para[] = $line;
        }
        $flush();

        return implode("\n", $html);
    }

    /** Markup removed, for excerpts, meta descriptions and emails. Not HTML-escaped. */
    public static function plain(string $markup): string
    {
        $markup = self::normalise($markup);
        $out = [];
        foreach (explode("\n", $markup) as $line) {
            $line = trim($line);
            $line = (string) preg_replace('/^(?:#{2,3} +|[-*] +|\d{1,3}\. +|> ?)/', '', $line);
            $out[] = $line;
        }
        $text = implode("\n", $out);
        $text = (string) preg_replace('/\[([^\]\n]{0,200})\]\([^)\s]{0,2000}\)/', '$1', $text);
        $text = (string) preg_replace(self::BOLD, '$1', $text);
        $text = (string) preg_replace(self::ITALIC_STAR, '$1', $text);
        $text = (string) preg_replace(self::ITALIC_UNDER, '$1', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    public static function excerpt(string $markup, int $length = 160): string
    {
        return Str::limit(self::plain($markup), $length);
    }

    private const BOLD = '/\*\*(?=\S)(.{1,200}?)(?<=\S)\*\*/u';
    private const ITALIC_STAR = '/(?<![*\w])\*(?=[^\s*])([^*\n]{1,200}?)(?<=[^\s*])\*(?!\*)/u';
    private const ITALIC_UNDER = '/(?<![\w_])_(?=[^\s_])([^_\n]{1,200}?)(?<=[^\s_])_(?![\w_])/u';

    private static function normalise(string $markup): string
    {
        if (!mb_check_encoding($markup, 'UTF-8')) {
            $markup = mb_convert_encoding($markup, 'UTF-8', 'UTF-8');
        }
        $markup = str_replace(["\r\n", "\r"], "\n", $markup);
        // Strip control characters except newline and tab.
        $markup = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $markup);
        return trim($markup);
    }

    /** Inline transforms on one line of raw (unescaped) text. */
    private static function inline(string $raw): string
    {
        $escaped = htmlspecialchars($raw, self::FLAGS, 'UTF-8');
        $parts = preg_split('/(\[[^\]\n]{0,200}\]\([^)\s]{0,2000}\))/', $escaped, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $escaped;
        }
        $out = '';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $out .= self::emphasis($part);
                continue;
            }
            if (preg_match('/^\[([^\]\n]*)\]\(([^)\s]*)\)$/', $part, $m) !== 1) {
                $out .= self::emphasis($part);
                continue;
            }
            $href = self::safeUrl(html_entity_decode($m[2], self::FLAGS, 'UTF-8'));
            if ($href === null) {
                // Unsafe or unsupported target: show the label only, as plain text.
                $out .= self::emphasis($m[1]);
                continue;
            }
            $rel = preg_match('#^https?://#i', $href) === 1 ? ' rel="noopener noreferrer"' : '';
            $out .= '<a href="' . htmlspecialchars($href, self::FLAGS, 'UTF-8') . '"' . $rel . '>'
                . self::emphasis($m[1]) . '</a>';
        }
        return $out;
    }

    private static function emphasis(string $escaped): string
    {
        $escaped = (string) preg_replace(self::BOLD, '<strong>$1</strong>', $escaped);
        $escaped = (string) preg_replace(self::ITALIC_STAR, '<em>$1</em>', $escaped);
        return (string) preg_replace(self::ITALIC_UNDER, '<em>$1</em>', $escaped);
    }

    /** Returns the URL when it is an allowed target, otherwise null. Input is already unescaped. */
    private static function safeUrl(string $url): ?string
    {
        if ($url === '' || strlen($url) > 2000) {
            return null;
        }
        // No whitespace, control characters, quotes, angle brackets or backslashes.
        if (preg_match('/[\x00-\x20\x7F-\x9F<>"\'`\\\\]/u', $url) === 1) {
            return null;
        }
        if ($url[0] === '/') {
            return ($url[1] ?? '') === '/' ? null : $url;
        }
        if (preg_match('#^https?://[^/?\#]+#i', $url) === 1) {
            $parts = parse_url($url);
            return ($parts !== false && ($parts['host'] ?? '') !== '') ? $url : null;
        }
        if (preg_match('/^mailto:[^\s@?]{1,100}@[^\s@?]{1,190}(\?[^\s]*)?$/i', $url) === 1) {
            return $url;
        }
        return null;
    }
}
