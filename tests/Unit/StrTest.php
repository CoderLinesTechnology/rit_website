<?php

declare(strict_types=1);

use App\Core\Str;

test('slug transliterates and normalises', function (): void {
    expect_same('cafe-au-lait', Str::slug('Café au Lait!'));
    expect_same('hello-world', Str::slug('  Hello --- World  '));
    expect_same('a-b', Str::slug('a_b'));
});

test('slug never returns empty and honours max length', function (): void {
    expect_same('item', Str::slug(''));
    expect_same('item', Str::slug('!!!???'));
    $slug = Str::slug(str_repeat('word ', 100), 20);
    expect_true(strlen($slug) <= 20);
    expect_true(!str_ends_with($slug, '-'));
});

test('slug output is only [a-z0-9-]', function (): void {
    expect_same(1, preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Str::slug("Ünï<script>cødé / ../ \0 x")));
});

test('reference has prefix and unambiguous 8 char code', function (): void {
    for ($i = 0; $i < 50; $i++) {
        $ref = Str::reference('RIT-APP');
        expect_same(1, preg_match('/^RIT-APP-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{8}$/', $ref), $ref);
    }
    expect_true(Str::reference('X') !== Str::reference('X'));
});

test('limit is multibyte safe and cuts at word boundary', function (): void {
    expect_same('short', Str::limit('short', 10));
    expect_same('The quick brown…', Str::limit('The quick brown fox jumps', 18));
    expect_same('Åkesson…', Str::limit('Åkessonxyz', 7, '…') === 'Åkesson…' ? 'Åkesson…' : 'bad');
    $cut = Str::limit(str_repeat('é', 50), 10);
    expect_same(10 + 1, mb_strlen($cut));
    expect_true(mb_check_encoding($cut, 'UTF-8'));
    expect_same('abc...', Str::limit('abcdefghij', 3, '...'));
});

test('maskEmail hides the local part', function (): void {
    expect_same('j***@example.org', Str::maskEmail('jane@example.org'));
    expect_same('***', Str::maskEmail('not-an-email'));
    expect_same('é***@example.org', Str::maskEmail('émile@example.org'));
});
