<?php

declare(strict_types=1);

/*
 * HTTP test helpers: starts the real application with PHP's built-in server
 * (same PHP binary as the test run, test database from .env.testing) and
 * performs requests with a cookie jar, like a browser.
 *
 *   require_once __DIR__ . '/../http.php';
 *   $jar = new CookieJar();
 *   $page = Http::get('/contact', $jar);
 *   $res = Http::post('/contact', ['_token' => csrf_from($page->body), ...], $jar);
 *   expect_same(303, $res->status);
 */

final class HttpResponse
{
    /** @param array<string, list<string>> $headers lower-case names */
    public function __construct(public readonly int $status, public readonly array $headers, public readonly string $body)
    {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }
}

final class CookieJar
{
    /** @var array<string, string> */
    public array $cookies = [];

    public function header(): string
    {
        return implode('; ', array_map(static fn ($k, $v) => $k . '=' . $v, array_keys($this->cookies), $this->cookies));
    }

    /** @param list<string> $setCookies */
    public function store(array $setCookies): void
    {
        foreach ($setCookies as $line) {
            [$pair] = explode(';', $line, 2);
            [$name, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            if ($value === '' || $value === 'deleted' || stripos($line, 'expires=Thu, 01 Jan 1970') !== false || preg_match('/Max-Age=0/i', $line)) {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = $value;
            }
        }
    }
}

final class Http
{
    /** @var resource|null */
    private static $process = null;
    private static int $port = 0;

    public static function start(): void
    {
        if (self::$process !== null) {
            return;
        }
        self::$port = random_int(20000, 40000);
        $env = getenv();
        foreach (file(APP_ROOT . '/.env.testing', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if ($line !== '' && $line[0] !== '#' && str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $env[trim($k)] = trim(trim($v), '"');
            }
        }
        $env['APP_ENV'] = 'testing';
        $env['APP_URL'] = 'http://127.0.0.1:' . self::$port;
        $log = APP_ROOT . '/storage/logs/test-server.log';
        self::$process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, '-t', APP_ROOT . '/public', APP_ROOT . '/public/index.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            APP_ROOT,
            $env
        );
        register_shutdown_function([self::class, 'stop']);
        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.1);
            if ($socket !== false) {
                fclose($socket);
                return;
            }
            usleep(100_000);
        }
        throw new RuntimeException('Test HTTP server did not start');
    }

    public static function stop(): void
    {
        if (self::$process !== null) {
            proc_terminate(self::$process);
            proc_close(self::$process);
            self::$process = null;
        }
    }

    public static function base(): string
    {
        self::start();
        return 'http://127.0.0.1:' . self::$port;
    }

    /** @param array<string, string> $headers */
    public static function get(string $path, ?CookieJar $jar = null, array $headers = []): HttpResponse
    {
        return self::request('GET', $path, null, $jar, $headers);
    }

    /**
     * @param array<string, mixed> $fields Use CURLFile values for uploads.
     * @param array<string, string> $headers
     */
    public static function post(string $path, array $fields, ?CookieJar $jar = null, array $headers = []): HttpResponse
    {
        return self::request('POST', $path, $fields, $jar, $headers);
    }

    /**
     * @param array<string, mixed>|string|null $body
     * @param array<string, string> $headers
     */
    public static function request(string $method, string $path, array|string|null $body, ?CookieJar $jar, array $headers = []): HttpResponse
    {
        $ch = curl_init(self::base() . $path);
        $hasFile = is_array($body) && array_filter($body, static fn ($v) => $v instanceof CURLFile) !== [];
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }
        if ($jar !== null && $jar->cookies !== []) {
            $headerLines[] = 'Cookie: ' . $jar->header();
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headerLines,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile || is_string($body) ? $body : http_build_query($body));
        }
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $rawHeaders = substr($raw, 0, $headerSize);
        $parsed = [];
        foreach (preg_split('/\r\n/', $rawHeaders) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $parsed[strtolower(trim($k))][] = trim($v);
            }
        }
        if ($jar !== null) {
            $jar->store($parsed['set-cookie'] ?? []);
        }
        return new HttpResponse($status, $parsed, substr($raw, $headerSize));
    }
}

function csrf_from(string $html): string
{
    return preg_match('/name="_token" value="([a-f0-9]{64})"/', $html, $m) ? $m[1] : '';
}

function nonce_from(string $html): string
{
    return preg_match('/name="_form_nonce" value="([a-f0-9]{32})"/', $html, $m) ? $m[1] : '';
}

/** Clears rate-limit buckets so repeated test runs are not throttled. */
function reset_rate_limits(): void
{
    App\Core\Database::connection()->execute('DELETE FROM rate_limits');
}
