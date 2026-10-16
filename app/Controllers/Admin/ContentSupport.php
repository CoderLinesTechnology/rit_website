<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Str;
use App\Core\Validator;
use App\Services\ImageUploader;
use App\Services\StoredImage;
use App\Services\UploadException;

/**
 * Helpers shared by the content-management controllers (programs, news,
 * gallery, impact, team, settings, SEO): form values with old-input
 * overlay, slugs, image replace/remove planning and audit shortcuts.
 */
trait ContentSupport
{
    /**
     * Template values for a form: the record's fields as strings, overlaid
     * with the input flashed by a failed submission. Unchecked checkboxes
     * are absent from the flashed input, so $bools reset to '0' then.
     *
     * @param array<string, mixed> $record
     * @param list<string> $bools
     * @return array<string, string>
     */
    protected function values(array $record, array $bools = []): array
    {
        $out = [];
        foreach ($record as $key => $value) {
            $out[(string) $key] = $value === null || is_array($value) ? '' : (string) $value;
        }
        $old = Session::getFlash('_old', []);
        if (is_array($old) && $old !== []) {
            foreach ($old as $key => $value) {
                if (is_string($value)) {
                    $out[(string) $key] = $value;
                }
            }
            foreach ($bools as $key) {
                if (!isset($old[$key])) {
                    $out[$key] = '0';
                }
            }
        }
        return $out;
    }

    /**
     * Slug for a record: the typed one (must be unused) or one made from $base.
     *
     * @param class-string<\App\Models\Model> $model
     */
    protected function resolveSlug(Validator $v, string $model, mixed $given, string $base, ?int $exceptId): string
    {
        $given = is_string($given) ? $given : '';
        if ($given !== '') {
            if ($model::slugExists($given, $exceptId)) {
                $v->addError('slug', 'Slug is already used by another record. Choose a different one.');
            }
            return $given;
        }
        return $model::uniqueSlug(Str::slug($base, 150), $exceptId);
    }

    /**
     * Works out what a form submission does to one image slot (upload, replace,
     * remove or keep) and performs the upload. Call it LAST, after every other
     * check: it only stores a file when $v has no errors, so a failed form
     * never leaves orphan files. After the database write succeeds, call
     * discardImages($plan['discard']); if the write fails, discardStored().
     *
     * @param array<string, mixed>|null $record Current row (null on create).
     * @param array{path: string, thumb: ?string, alt: ?string} $cols Column names.
     * @return array{data: array<string, mixed>, discard: list<array{0: ?string, 1: ?string}>, stored: ?StoredImage}
     */
    protected function planImage(
        Request $request,
        Validator $v,
        ?array $record,
        array $cols,
        string $folder,
        ?string $alt,
        string $fileKey = 'image',
        string $altField = 'image_alt',
        string $removeKey = 'remove_image',
    ): array {
        $plan = ['data' => [], 'discard' => [], 'stored' => null];
        $file = $request->file($fileKey);
        $post = $request->post();
        $remove = is_string($post[$removeKey] ?? null) && $post[$removeKey] === '1';
        $curPath = $record !== null ? (string) ($record[$cols['path']] ?? '') : '';
        $curThumb = ($record !== null && $cols['thumb'] !== null) ? (string) ($record[$cols['thumb']] ?? '') : '';
        $has = $curPath !== '';
        $alt = $alt === null ? '' : trim($alt);

        if ($cols['alt'] !== null && ($file !== null || ($has && !$remove)) && $alt === '') {
            $v->addError($altField, 'Photo description is required when a photo is set. Describe the photo for people who cannot see it.');
        }

        if ($file !== null) {
            if ($v->fails()) {
                return $plan;
            }
            try {
                $stored = (new ImageUploader())->store($file, $folder);
            } catch (UploadException $e) {
                $v->addError($fileKey, $e->getMessage());
                return $plan;
            }
            $plan['stored'] = $stored;
            $plan['data'][$cols['path']] = $stored->path;
            if ($cols['thumb'] !== null) {
                $plan['data'][$cols['thumb']] = $stored->thumbPath;
            } else {
                (new ImageUploader())->delete($stored->thumbPath); // this slot keeps only the main file
            }
            if ($cols['alt'] !== null) {
                $plan['data'][$cols['alt']] = $alt;
            }
            if ($has) {
                $plan['discard'][] = [$curPath, $curThumb !== '' ? $curThumb : $this->thumbOf($curPath, $cols)];
            }
        } elseif ($remove && $has) {
            $plan['data'][$cols['path']] = null;
            if ($cols['thumb'] !== null) {
                $plan['data'][$cols['thumb']] = null;
            }
            if ($cols['alt'] !== null) {
                $plan['data'][$cols['alt']] = null;
            }
            $plan['discard'][] = [$curPath, $curThumb !== '' ? $curThumb : $this->thumbOf($curPath, $cols)];
        } elseif ($has && $cols['alt'] !== null) {
            $plan['data'][$cols['alt']] = $alt;
        }
        return $plan;
    }

    /** The "-thumb" sibling of a stored image whose thumb path is not kept in the database. */
    private function thumbOf(string $path, array $cols): ?string
    {
        if ($cols['thumb'] !== null) {
            return null;
        }
        return preg_replace('/(\.(?:webp|jpg))$/', '-thumb$1', $path);
    }

    /** @param list<array{0: ?string, 1: ?string}> $pairs */
    protected function discardImages(array $pairs): void
    {
        $uploader = new ImageUploader();
        foreach ($pairs as [$path, $thumb]) {
            $uploader->deleteImage($path, $thumb);
        }
    }

    /** Removes a just-uploaded image when the database write failed. */
    protected function discardStored(?StoredImage $stored, bool $keepsThumb = true): void
    {
        if ($stored !== null) {
            (new ImageUploader())->deleteImage($stored->path, $keepsThumb ? $stored->thumbPath : null);
        }
    }

    /**
     * Like redirect_with_errors(), but keeps long text (articles, descriptions,
     * legal text) intact: the shared helper cuts flashed input at 10,000
     * characters, which would silently shorten an article on the next save.
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    protected function invalid(string $to, array $errors, array $input = []): Response
    {
        $clean = [];
        foreach ($input as $key => $value) {
            if (in_array($key, ['_token', 'password', 'password_confirmation', 'current_password'], true)) {
                continue;
            }
            if (is_string($value) && mb_strlen($value) <= 120000) {
                $clean[(string) $key] = $value;
            }
        }
        Session::flash('_errors', $errors);
        Session::flash('_old', $clean);
        Session::flash('_message_error', 'Please correct the highlighted fields and try again.');
        return Response::redirect($to);
    }

    protected function audit(string $action, string $entity, ?int $id, string $summary): void
    {
        AuditLog::record($action, $entity, $id, $summary);
    }

    /** "2026-03-01 14:30:00" => "2026-03-01T14:30" for datetime-local inputs. */
    protected function toDatetimeLocal(mixed $dbValue): string
    {
        $dbValue = is_string($dbValue) ? $dbValue : '';
        return $dbValue === '' ? '' : str_replace(' ', 'T', substr($dbValue, 0, 16));
    }

    /** Only https:// web addresses are accepted for outbound links. */
    protected function isHttps(string $url): bool
    {
        return str_starts_with(strtolower($url), 'https://');
    }

    /** Max upload size shown in hints. */
    protected function maxMb(): int
    {
        return max(1, (int) config('app.uploads.image_max_mb', 8));
    }

    /** @return int 0 or 1 */
    protected function bit(mixed $value): int
    {
        return $value ? 1 : 0;
    }

    /** A file name safe to show in a message (no control characters, shortened). */
    protected function safeName(string $name): string
    {
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $name)));
        return mb_strlen($name) > 40 ? mb_substr($name, 0, 37) . '...' : ($name === '' ? 'file' : $name);
    }
}
