<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\Settings;

/**
 * "Website content": the screen is generated from config/settings.php. Each
 * settings group has its own permission, checked when rendering AND saving.
 */
final class SettingsController extends AdminController
{
    use ContentSupport;

    public function index(Request $request): Response
    {
        $groups = Settings::groups();
        $editable = $this->editableGroups();
        $group = $request->query('group');
        if ($group === '') {
            $group = (string) (array_key_first($editable) ?? '');
        }
        if (!isset($groups[$group])) {
            throw HttpException::notFound('That settings section does not exist.');
        }
        $this->requirePermission((string) $groups[$group]['permission']);

        $hasOld = (Session::getFlash('_old', []) ?: []) !== [];
        $sections = [];
        foreach ($this->fieldsOf($group) as $key => $def) {
            $current = Settings::get($key);
            if (($def['type'] ?? 'text') === 'bool') {
                $value = $hasOld ? (old('s[' . $key . ']', '') === '1' ? '1' : '0') : $current;
            } else {
                $value = old('s[' . $key . ']', $current);
            }
            $sections[(string) ($def['section'] ?? '')][] = [
                'key' => $key,
                'def' => $def,
                'value' => $value,
                'current' => $current,
                'needsConfirmation' => !empty($def['verify']) && trim($current) === '',
                'fileName' => $this->fileName($key),
                'removeName' => $this->removeName($key),
            ];
        }
        return $this->page('admin/settings/index', 'Website content', 'settings', [
            'groups' => $editable,
            'group' => $group,
            'groupLabel' => (string) $groups[$group]['label'],
            'sections' => $sections,
            'maxMb' => $this->maxMb(),
        ]);
    }

    public function update(Request $request, string $group): Response
    {
        $groups = Settings::groups();
        if (!isset($groups[$group])) {
            throw HttpException::notFound('That settings section does not exist.');
        }
        $this->requirePermission((string) $groups[$group]['permission']);

        $fields = $this->fieldsOf($group);
        $post = $request->post();
        $input = is_array($post['s'] ?? null) ? $post['s'] : [];
        $back = url('/admin/settings', ['group' => $group]);

        // Validate everything typed (the form posts s[<key>] because setting keys contain dots).
        $data = [];
        $rules = [];
        $labels = [];
        $flat = [];
        foreach ($fields as $key => $def) {
            $type = (string) ($def['type'] ?? 'text');
            if ($type === 'bool' || $type === 'image') {
                if ($type === 'bool' && ($input[$key] ?? '') === '1') {
                    $flat['s[' . $key . ']'] = '1';
                }
                continue;
            }
            $field = 's[' . $key . ']';
            $raw = $input[$key] ?? '';
            $data[$field] = is_string($raw) ? $raw : '';
            $flat[$field] = $data[$field];
            $labels[$field] = (string) $def['label'];
            $parts = [!empty($def['required']) ? 'required' : 'nullable'];
            $parts[] = match ($type) {
                'email' => 'line|email',
                'url' => 'line|url',
                'tel' => 'line|phone',
                'textarea', 'markup', 'lines' => 'string',
                default => 'line',
            };
            if (!empty($def['max'])) {
                $parts[] = 'max:' . (int) $def['max'];
            }
            $rules[$field] = implode('|', $parts);
        }
        $v = Validator::make($data, $rules, $labels);
        $checked = $v->validated();
        foreach ($fields as $key => $def) {
            $field = 's[' . $key . ']';
            if (($def['type'] ?? '') === 'url' && !empty($checked[$field]) && !$this->isHttps((string) $checked[$field])) {
                $v->addError($field, (string) $def['label'] . ' must start with https://');
            }
        }

        // Final values for text fields and booleans.
        $new = [];
        foreach ($fields as $key => $def) {
            $type = (string) ($def['type'] ?? 'text');
            if ($type === 'bool') {
                $new[$key] = ($input[$key] ?? '') === '1' ? '1' : '0';
            } elseif ($type !== 'image') {
                $new[$key] = isset($checked['s[' . $key . ']']) ? (string) $checked['s[' . $key . ']'] : '';
            }
        }

        // Images: an image needs its description whenever one will be set.
        $imageKeys = array_keys(array_filter($fields, static fn (array $d): bool => ($d['type'] ?? '') === 'image'));
        foreach ($imageKeys as $key) {
            $altKey = (string) ($fields[$key]['alt'] ?? '');
            if ($altKey === '' || !isset($new[$altKey])) {
                continue;
            }
            $cur = Settings::get($key);
            $remove = ($post[$this->removeName($key)] ?? '') === '1';
            $willHave = $request->file($this->fileName($key)) !== null || ($cur !== '' && !$remove);
            if ($willHave && $new[$altKey] === '') {
                $v->addError('s[' . $altKey . ']', 'Describe the image for people who cannot see it (required whenever an image is set).');
            }
        }

        $plans = [];
        foreach ($imageKeys as $key) {
            $plans[$key] = $this->planImage(
                $request, $v, [$key => Settings::get($key)], ['path' => $key, 'thumb' => null, 'alt' => null], 'site', null,
                $this->fileName($key), 'image', $this->removeName($key)
            );
        }
        if ($v->fails()) {
            foreach ($plans as $plan) {
                $this->discardStored($plan['stored'], false);
            }
            return $this->invalid($back, $v->errors(), $flat);
        }
        foreach ($plans as $key => $plan) {
            if (array_key_exists($key, $plan['data'])) {
                $new[$key] = (string) ($plan['data'][$key] ?? '');
            }
        }

        $changed = [];
        foreach ($new as $key => $value) {
            if ($value !== Settings::get($key)) {
                $changed[$key] = $value;
            }
        }
        if ($changed === []) {
            flash_message('info', 'Nothing was changed.');
            return redirect($back);
        }
        try {
            Settings::save($changed, $this->adminId());
        } catch (\Throwable $e) {
            foreach ($plans as $plan) {
                $this->discardStored($plan['stored'], false);
            }
            throw $e;
        }
        foreach ($plans as $plan) {
            $this->discardImages($plan['discard']);
        }
        $this->audit('settings.update', 'settings', null, 'Updated "' . $groups[$group]['label'] . '" settings: ' . implode(', ', array_keys($changed)));
        flash_message('success', 'Saved. ' . count($changed) . (count($changed) === 1 ? ' setting was' : ' settings were') . ' updated.');
        return redirect($back);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /** @return array<string, string> group key => label, only groups the signed-in admin may edit */
    private function editableGroups(): array
    {
        $out = [];
        foreach (Settings::groups() as $key => $group) {
            if (Auth::can((string) $group['permission'])) {
                $out[$key] = (string) $group['label'];
            }
        }
        return $out;
    }

    /** @return array<string, array<string, mixed>> */
    private function fieldsOf(string $group): array
    {
        return array_filter(Settings::definitions(), static fn (array $d): bool => ($d['group'] ?? '') === $group);
    }

    /** Dots are not allowed in PHP field names, so file inputs use a derived name. */
    private function fileName(string $key): string
    {
        return 'img_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($key));
    }

    private function removeName(string $key): string
    {
        return 'rm_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($key));
    }
}
