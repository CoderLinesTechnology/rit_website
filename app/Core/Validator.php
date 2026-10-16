<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Server-side validation for every form.
 *
 *   $v = Validator::make($request->post(), [
 *       'email'   => 'required|email|max:190',
 *       'reason'  => 'required|string|min:20|max:2000',
 *       'program' => 'required|int',
 *       'phone'   => 'nullable|phone',
 *       'consent' => 'accepted',
 *   ], ['email' => 'Email address']);
 *   if ($v->fails()) { ...$v->errors()... }
 *   $data = $v->validated();
 *
 * Rules: required, nullable, string (multi-line text), line (single line),
 * int, numeric, boolean, accepted, email, url, phone, date (Y-m-d),
 * datetime (Y-m-d\TH:i), slug, in:a,b, min:n, max:n, same:field,
 * required_if:field,value, array (list of strings; in: applies per item).
 * Strings are trimmed; ASCII control characters are rejected.
 */
final class Validator
{
    /**
     * Ordinary mailbox addresses only. FILTER_VALIDATE_EMAIL also accepts characters such as
     * ? & % # / that become hidden parameters (e.g. ?bcc=) when the address is used in a mailto: link.
     */
    public const EMAIL_PATTERN = "/^[A-Za-z0-9._+'-]+@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\\.)+[A-Za-z]{2,63}$/";

    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $labels
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $labels = [],
    ) {
        $this->run();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, string> First error message per field. */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Adds an error found by additional checks in the controller. */
    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;
            $isList = in_array('array', $rules, true);

            if ($isList) {
                $value = is_array($value) ? array_values(array_map('trim', array_filter($value, 'is_string'))) : [];
                $value = array_values(array_filter($value, static fn (string $v): bool => $v !== ''));
            } elseif (is_string($value)) {
                $value = trim(str_replace("\r\n", "\n", $value));
            } elseif ($value !== null && !is_scalar($value)) {
                $this->fail($field, 'is not valid.');
                continue;
            }

            if (in_array('boolean', $rules, true)) {
                $this->validated[$field] = in_array($value, ['1', 'on', 'yes', 'true', 1, true], true);
                continue;
            }

            $empty = $value === null || $value === '' || $value === [];
            $required = in_array('required', $rules, true) || $this->requiredIf($rules);

            if ($empty) {
                if ($required || in_array('accepted', $rules, true)) {
                    $this->fail($field, in_array('accepted', $rules, true) ? 'must be confirmed.' : 'is required.');
                } else {
                    $this->validated[$field] = $isList ? [] : null;
                }
                continue;
            }

            $ok = true;
            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $ok = $this->check($field, (string) $name, $arg, $value, $rules);
                if (!$ok) {
                    break;
                }
            }
            if ($ok) {
                $this->validated[$field] = $this->cast($value, $rules);
            }
        }
    }

    /** @param list<string> $rules */
    private function requiredIf(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'required_if:')) {
                [$other, $expected] = array_pad(explode(',', substr($rule, 12), 2), 2, '');
                $actual = $this->data[$other] ?? '';
                return is_string($actual) && trim($actual) === $expected;
            }
        }
        return false;
    }

    /**
     * @param string|list<string> $value
     * @param list<string> $rules
     */
    private function check(string $field, string $rule, ?string $arg, string|array $value, array $rules): bool
    {
        if (is_array($value)) {
            return match ($rule) {
                'in' => $this->checkListIn($field, $value, (string) $arg),
                'max' => count($value) <= (int) $arg || $this->fail($field, 'has too many selections.'),
                default => true,
            };
        }

        $numeric = in_array('int', $rules, true) || in_array('numeric', $rules, true);

        return match ($rule) {
            'required', 'nullable', 'required_if', 'array', 'boolean' => true,
            'string' => $this->noControlChars($value, true) || $this->fail($field, 'contains characters that are not allowed.'),
            'line' => $this->noControlChars($value, false) || $this->fail($field, 'must be a single line of text.'),
            'int' => (bool) preg_match('/^-?\d{1,9}$/', $value) || $this->fail($field, 'must be a whole number.'),
            'numeric' => (is_numeric($value) && preg_match('/^-?\d{1,12}(\.\d{1,2})?$/', $value) === 1) || $this->fail($field, 'must be a number.'),
            'accepted' => in_array($value, ['1', 'on', 'yes', 'true'], true) || $this->fail($field, 'must be confirmed.'),
            'email' => (filter_var($value, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($value) <= 190 && preg_match(self::EMAIL_PATTERN, $value) === 1)
                || $this->fail($field, 'must be a valid email address.'),
            'url' => $this->isHttpUrl($value) || $this->fail($field, 'must be a full web address starting with https:// or http://.'),
            'phone' => (bool) preg_match('/^\+?[0-9][0-9 ()\-]{6,24}$/', $value) || $this->fail($field, 'must be a valid phone number (digits, spaces, + and - only).'),
            'date' => $this->isDate($value, 'Y-m-d') || $this->fail($field, 'must be a valid date.'),
            'datetime' => $this->isDate($value, 'Y-m-d\TH:i') || $this->fail($field, 'must be a valid date and time.'),
            'slug' => (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) || $this->fail($field, 'may only contain lowercase letters, numbers and hyphens.'),
            'in' => in_array($value, explode(',', (string) $arg), true) || $this->fail($field, 'is not one of the available options.'),
            'min' => $numeric
                ? ((float) $value >= (float) $arg || $this->fail($field, "must be at least {$arg}."))
                : (mb_strlen($value) >= (int) $arg || $this->fail($field, "must be at least {$arg} characters.")),
            'max' => $numeric
                ? ((float) $value <= (float) $arg || $this->fail($field, "must be no more than {$arg}."))
                : (mb_strlen($value) <= (int) $arg || $this->fail($field, "must be no more than {$arg} characters.")),
            'same' => $value === (is_string($this->data[(string) $arg] ?? null) ? trim((string) $this->data[(string) $arg]) : null)
                || $this->fail($field, 'does not match.'),
            default => throw new \LogicException("Unknown validation rule: {$rule}"),
        };
    }

    /** @param list<string> $value */
    private function checkListIn(string $field, array $value, string $arg): bool
    {
        $allowed = explode(',', $arg);
        foreach ($value as $item) {
            if (!in_array($item, $allowed, true)) {
                return $this->fail($field, 'contains an option that is not available.');
            }
        }
        return true;
    }

    private function noControlChars(string $value, bool $allowNewlines): bool
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            return false;
        }
        $pattern = $allowNewlines ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';
        return !preg_match($pattern, $value);
    }

    private function isHttpUrl(string $value): bool
    {
        if (mb_strlen($value) > 500 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) && parse_url($value, PHP_URL_HOST) !== null;
    }

    private function isDate(string $value, string $format): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
        return $date !== false && $date->format($format) === $value;
    }

    /**
     * @param string|list<string> $value
     * @param list<string> $rules
     */
    private function cast(string|array $value, array $rules): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if (in_array('int', $rules, true)) {
            return (int) $value;
        }
        if (in_array('email', $rules, true)) {
            return strtolower($value);
        }
        return $value;
    }

    private function fail(string $field, string $message): bool
    {
        $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $this->errors[$field] ??= $label . ' ' . $message;
        return false;
    }
}
