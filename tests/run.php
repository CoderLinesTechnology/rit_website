<?php

declare(strict_types=1);

/*
 * Dependency-free test runner.
 *
 *   php tests/run.php            run every tests/Unit and tests/Feature file
 *   php tests/run.php Validator  run only tests whose file or name contains "Validator"
 *
 * Test files register cases with test('name', fn () => ...) and assert with
 * the expect_* helpers below. Feature tests that need HTTP use tests/http.php.
 */

require __DIR__ . '/bootstrap.php';

/** @var list<array{file: string, name: string, fn: callable}> $GLOBALS['__tests'] */
$GLOBALS['__tests'] = [];
$GLOBALS['__current_file'] = '';

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = ['file' => $GLOBALS['__current_file'], 'name' => $name, 'fn' => $fn];
}

final class AssertionFailed extends RuntimeException
{
}

function expect_true(mixed $value, string $message = 'Expected true'): void
{
    if ($value !== true) {
        throw new AssertionFailed($message . ' (got ' . var_export($value, true) . ')');
    }
}

function expect_false(mixed $value, string $message = 'Expected false'): void
{
    if ($value !== false) {
        throw new AssertionFailed($message . ' (got ' . var_export($value, true) . ')');
    }
}

function expect_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(trim($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)));
    }
}

function expect_contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new AssertionFailed(trim($message . ' Expected to find ' . var_export($needle, true) . ' in ' . var_export(mb_substr($haystack, 0, 400), true)));
    }
}

function expect_not_contains(string $needle, string $haystack, string $message = ''): void
{
    if (str_contains($haystack, $needle)) {
        throw new AssertionFailed(trim($message . ' Did not expect ' . var_export($needle, true)));
    }
}

/** @param class-string<Throwable> $class */
function expect_throws(string $class, callable $fn, string $message = ''): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return $e;
        }
        throw new AssertionFailed(trim($message . ' Expected ' . $class . ', got ' . $e::class . ': ' . $e->getMessage()));
    }
    throw new AssertionFailed(trim($message . ' Expected ' . $class . ' to be thrown'));
}

$filter = $argv[1] ?? '';
$files = array_merge(glob(__DIR__ . '/Unit/*Test.php') ?: [], glob(__DIR__ . '/Feature/*Test.php') ?: []);
foreach ($files as $file) {
    $GLOBALS['__current_file'] = basename($file, '.php');
    require $file;
}

$passed = 0;
$failed = 0;
$skipped = 0;
foreach ($GLOBALS['__tests'] as $t) {
    if ($filter !== '' && !str_contains($t['file'], $filter) && !str_contains($t['name'], $filter)) {
        continue;
    }
    $label = $t['file'] . ' › ' . $t['name'];
    try {
        $result = ($t['fn'])();
        if ($result === 'skip') {
            $skipped++;
            echo "  SKIP  {$label}\n";
            continue;
        }
        $passed++;
        echo "  PASS  {$label}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  FAIL  {$label}\n        " . $e::class . ': ' . $e->getMessage() . "\n        at " . basename($e->getFile()) . ':' . $e->getLine() . "\n";
    }
}

echo "\n{$passed} passed, {$failed} failed, {$skipped} skipped\n";
exit($failed > 0 ? 1 : 0);
