<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Work that runs after the response has been sent (e.g. handing queued
 * notification emails to the mail server), so visitors never wait for it.
 */
final class Deferred
{
    /** @var list<callable> */
    private static array $tasks = [];

    public static function add(callable $task): void
    {
        self::$tasks[] = $task;
    }

    public static function run(): void
    {
        if (self::$tasks === []) {
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
        ignore_user_abort(true);
        set_time_limit(60);
        foreach (self::$tasks as $task) {
            try {
                $task();
            } catch (\Throwable $e) {
                ErrorHandler::report($e);
            }
        }
        self::$tasks = [];
    }
}
