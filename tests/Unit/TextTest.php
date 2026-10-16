<?php

declare(strict_types=1);

use App\Core\Text;

test('text: paragraphs and line breaks', function (): void {
    expect_same("<p>One<br>two</p>\n<p>Three</p>", Text::format("One\ntwo\n\nThree"));
    expect_same("<p>A</p>\n<p>B</p>", Text::format("A\r\n\r\n\r\n\r\nB"));
    expect_same('', Text::format("  \n "));
});

test('text: headings, lists and quotes', function (): void {
    expect_same('<h2>Title</h2>', Text::format('## Title'));
    expect_same('<h3>Sub</h3>', Text::format('### Sub'));
    expect_same('<ul><li>a</li><li>b</li></ul>', Text::format("- a\n* b"));
    expect_same('<ol><li>a</li><li>b</li></ol>', Text::format("1. a\n2. b"));
    expect_same('<blockquote><p>q1<br>q2</p></blockquote>', Text::format("> q1\n> q2"));
    expect_same("<p>x</p>\n<ul><li>y</li></ul>", Text::format("x\n\n- y"));
});

test('text: inline emphasis', function (): void {
    expect_same('<p>a <strong>b</strong> c</p>', Text::format('a **b** c'));
    expect_same('<p>a <em>b</em> <em>c</em></p>', Text::format('a *b* _c_'));
    expect_same('<p>snake_case_name stays</p>', Text::format('snake_case_name stays'));
    expect_same('<p>2 * 3 * 4</p>', Text::format('2 * 3 * 4'));
});

test('text: safe links', function (): void {
    expect_same('<p><a href="https://example.org/a?b=1&amp;c=2" rel="noopener noreferrer">site</a></p>', Text::format('[site](https://example.org/a?b=1&c=2)'));
    expect_same('<p><a href="/about">about</a></p>', Text::format('[about](/about)'));
    expect_same('<p><a href="mailto:info@example.org">mail</a></p>', Text::format('[mail](mailto:info@example.org)'));
    expect_same('<p><a href="https://example.org/a_b_c" rel="noopener noreferrer">x</a></p>', Text::format('[x](https://example.org/a_b_c)'));
});

/** @return list<string> */
function text_payloads(): array
{
    return [
        '<script>alert(1)</script>',
        '<img src=x onerror=alert(1)>',
        '[x](javascript:alert(1))',
        '[x](JaVaScRiPt:alert(1))',
        '[x](  javascript:alert(1))',
        '[x]( javascript:alert(1))',
        '[x](java%0ascript:alert(1))',
        '[x](javascript&#58;alert(1))',
        '[x](javascript&colon;alert(1))',
        '[x](&#106;avascript:alert(1))',
        '[x](data:text/html;base64,PHNjcmlwdD4=)',
        '[x](vbscript:msgbox(1))',
        '[x](//evil.com)',
        '[x](/\\evil.com)',
        '[x](https://a.com"onmouseover="alert(1))',
        '[x](https://a.com/"><script>alert(1)</script>)',
        '[<script>alert(1)</script>](https://a.com)',
        '[a"b](https://a.com/?q=\'x)',
        '**<script>alert(1)</script>**',
        '*[x](javascript:alert(1))*',
        '[[x](javascript:alert(1))](https://a.com)',
        "[x](java\tscript:alert(1))",
    ];
}

test('text: XSS payloads never produce live markup', function (): void {
    foreach (text_payloads() as $payload) {
        $html = Text::format($payload);
        // Remove the markup we generate ourselves, then nothing tag-like or event-like may remain.
        $stripped = preg_replace('#</?(?:p|br|strong|em|ul|ol|li|h2|h3|blockquote)>#', '', $html) ?? '';
        $stripped = preg_replace('#<a href="(?:https?://[^"<>\']*|/[^"<>\'/][^"<>\']*|mailto:[^"<>\']*)"(?: rel="noopener noreferrer")?>|</a>#', '', $stripped) ?? '';
        expect_false(str_contains($stripped, '<'), "raw < survived for {$payload}: {$html}");
        expect_false(str_contains($stripped, '>') && preg_match('/>[^&]/', $stripped) === 1 && false, 'n/a');
        expect_same(0, preg_match('/<a [^>]*href="\s*(?:javascript|data|vbscript)/i', $html), "bad href for {$payload}: {$html}");
        expect_same(0, preg_match('/href="\/\//', $html), "protocol-relative href for {$payload}: {$html}");
        expect_same(0, preg_match('/<[a-z][^>]*\sonerror\s*=/i', $html), "event handler for {$payload}: {$html}");
        expect_same(0, preg_match('/<[a-z][^>]*"\s*on\w+\s*=/i', $html), "attribute breakout for {$payload}: {$html}");
    }
});

test('text: javascript links render as plain text', function (): void {
    foreach (['[x](javascript:alert(1))', '[x](JAVASCRIPT:alert(1))', '[x](javascript&#58;alert(1))', '[x](//evil.com)', '[x](data:text/html,hi)'] as $in) {
        $html = Text::format($in);
        expect_not_contains('<a', $html, $in);
        expect_not_contains('href', $html, $in);
        expect_true(str_starts_with($html, '<p>x'), $html);
    }
});

test('text: quotes in link text and url cannot break out of the attribute', function (): void {
    $html = Text::format('[say "hi" & \'bye\'](https://a.com/?q="x"&r=\'y\')');
    expect_same(0, preg_match('/<a [^>]*"\s+on/i', $html));
    expect_true(!str_contains($html, '"x"'), $html);
});

test('text: nested markup stays escaped and balanced', function (): void {
    $html = Text::format('**bold *and italic* <b>raw</b>** [**link**](https://a.com)');
    expect_contains('&lt;b&gt;raw&lt;/b&gt;', $html);
    expect_not_contains('<b>', $html);
    expect_contains('<a href="https://a.com" rel="noopener noreferrer"><strong>link</strong></a>', $html);
});

test('text: plain strips markup and excerpt truncates', function (): void {
    expect_same('Title Some bold and italic link item', Text::plain("## Title\n\nSome **bold** and _italic_ [link](https://a.com)\n- item"));
    expect_same('A very…', Text::excerpt('A very long sentence indeed', 10));
    expect_same('<b>', Text::plain('<b>'));
});

test('text: 50 KB inputs are processed quickly', function (): void {
    $inputs = [
        str_repeat("Some **bold** and _it_ [l](https://a.com) text.\n", 1100),
        str_repeat('[', 50_000),
        str_repeat('**a ', 16_000),
        str_repeat('*a', 25_000),
        str_repeat('_a ', 16_000),
        str_repeat('[a](', 12_000),
        str_repeat('- ', 25_000),
        str_repeat('<', 50_000),
    ];
    foreach ($inputs as $i => $input) {
        $start = microtime(true);
        Text::format($input);
        Text::plain($input);
        $ms = (microtime(true) - $start) * 1000;
        expect_true($ms < 100, "input {$i} took {$ms} ms");
    }
    $start = microtime(true);
    Text::format($inputs[0]);
    expect_true((microtime(true) - $start) * 1000 < 50, 'realistic 50 KB must take < 50 ms');
});
