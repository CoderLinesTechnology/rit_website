<?php

declare(strict_types=1);

namespace App\Core;

/** Small string helpers: slugs, public reference codes, truncation, masking. */
final class Str
{
    private const REFERENCE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const ASCII_MAP = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ă' => 'A', 'Ą' => 'A',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'Ç' => 'C', 'Ć' => 'C', 'Č' => 'C',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e', 'ɛ' => 'e',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'Ę' => 'E', 'Ě' => 'E', 'Ɛ' => 'E',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'Ñ' => 'N', 'Ń' => 'N',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o', 'ɔ' => 'o',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ō' => 'O', 'Ő' => 'O', 'Ɔ' => 'O',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ū' => 'U', 'Ů' => 'U', 'Ű' => 'U',
        'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'Y', 'š' => 's', 'ś' => 's', 'Š' => 'S', 'Ś' => 'S',
        'ž' => 'z', 'ź' => 'z', 'ż' => 'z', 'Ž' => 'Z', 'Ź' => 'Z', 'Ż' => 'Z',
        'ł' => 'l', 'Ł' => 'L', 'đ' => 'd', 'Đ' => 'D', 'ð' => 'd', 'ř' => 'r', 'ť' => 't', 'ğ' => 'g', 'ı' => 'i',
        'ß' => 'ss', 'æ' => 'ae', 'Æ' => 'AE', 'œ' => 'oe', 'Œ' => 'OE', 'þ' => 'th',
    ];

    /** URL slug: ASCII lowercase letters, digits and single hyphens. Never empty. */
    public static function slug(string $text, int $max = 160): string
    {
        $text = self::transliterate($text);
        $text = strtolower($text);
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        if ($max > 0 && strlen($text) > $max) {
            $text = trim(substr($text, 0, $max), '-');
        }
        return $text === '' ? 'item' : $text;
    }

    private static function transliterate(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        if (class_exists(\Transliterator::class)) {
            $t = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($t !== null) {
                $out = $t->transliterate($text);
                if (is_string($out)) {
                    return $out;
                }
            }
        }
        // Without intl: map common Latin letters (incl. Ghanaian ɛ/ɔ) ourselves, because
        // iconv //TRANSLIT output varies by libc (macOS yields "caf'e").
        $text = strtr($text, self::ASCII_MAP);
        if (function_exists('iconv')) {
            $out = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($out)) {
                return $out;
            }
        }
        return $text;
    }

    /** Public reference such as "RIT-APP-7K3QX9MD" (unambiguous alphabet, CSPRNG). */
    public static function reference(string $prefix): string
    {
        $max = strlen(self::REFERENCE_ALPHABET) - 1;
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::REFERENCE_ALPHABET[random_int(0, $max)];
        }
        return $prefix . '-' . $code;
    }

    /** Multibyte-safe truncation at a word boundary when possible. */
    public static function limit(string $text, int $length, string $end = '…'): string
    {
        if ($length < 0) {
            $length = 0;
        }
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = rtrim(mb_substr($text, 0, $length));
        $space = mb_strrpos($cut, ' ');
        // Only back up to a word boundary when that keeps a reasonable amount of text.
        if ($space !== false && $space >= (int) ($length * 0.5)) {
            $cut = rtrim(mb_substr($cut, 0, $space), " \t\n\r,;:.-");
        }
        return $cut . $end;
    }

    /** "jane@example.org" becomes "j***@example.org". */
    public static function maskEmail(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false || $at === 0) {
            return '***';
        }
        return mb_substr(substr($email, 0, $at), 0, 1) . '***' . substr($email, $at);
    }
}
