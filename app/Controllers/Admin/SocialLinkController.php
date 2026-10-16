<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\SocialLink;

final class SocialLinkController extends AdminController
{
    use ContentSupport;

    public function index(Request $request): Response
    {
        $old = \App\Core\Session::getFlash('_old', []);
        $hasOld = is_array($old) && $old !== [];
        $rows = [];
        foreach (SocialLink::all() as $row) {
            $id = (int) $row['id'];
            $p = 'links[' . $id . ']';
            $rows[] = [
                'id' => $id,
                'platform' => (string) $row['platform'],
                'platformName' => $this->platformName((string) $row['platform']),
                'label' => old($p . '[label]', (string) $row['label']),
                'url' => old($p . '[url]', (string) $row['url']),
                'sort_order' => old($p . '[sort_order]', (string) $row['sort_order']),
                'is_active' => $hasOld ? old($p . '[is_active]', '') === '1' : (int) $row['is_active'] === 1,
                'prefix' => $p,
            ];
        }
        return $this->page('admin/social-links/index', 'Social links', 'social-links', ['rows' => $rows]);
    }

    public function update(Request $request): Response
    {
        $back = url('/admin/social-links');
        $post = $request->post();
        $in = is_array($post['links'] ?? null) ? $post['links'] : [];
        $rows = SocialLink::all();

        $data = [];
        $rules = [];
        $labels = [];
        $flat = [];
        $active = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $r = is_array($in[$id] ?? null) ? $in[$id] : [];
            $p = 'links[' . $id . ']';
            $name = $this->platformName((string) $row['platform']);
            foreach (['label', 'url', 'sort_order'] as $f) {
                $val = is_string($r[$f] ?? null) ? $r[$f] : '';
                $data[$p . '[' . $f . ']'] = $val;
                $flat[$p . '[' . $f . ']'] = $val;
            }
            $active[$id] = ($r['is_active'] ?? '') === '1';
            if ($active[$id]) {
                $flat[$p . '[is_active]'] = '1';
            }
            $rules[$p . '[label]'] = 'required|line|max:60';
            $rules[$p . '[url]'] = 'nullable|url|max:500';
            $rules[$p . '[sort_order]'] = 'nullable|int|min:0|max:9999';
            $labels[$p . '[label]'] = $name . ' label';
            $labels[$p . '[url]'] = $name . ' address';
            $labels[$p . '[sort_order]'] = $name . ' order';
        }
        $v = Validator::make($data, $rules, $labels);
        $checked = $v->validated();
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $field = 'links[' . $id . '][url]';
            $url = (string) ($checked[$field] ?? '');
            if ($url === '') {
                if ($active[$id] && !isset($v->errors()[$field])) {
                    $v->addError($field, $labels[$field] . ' is required while the link is shown on the website.');
                }
                continue;
            }
            if (!isset($v->errors()[$field]) && !$this->plausibleHttpsUrl($url)) {
                $v->addError($field, $labels[$field] . ' must be a full address starting with https:// on a real website domain.');
            }
        }
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $flat);
        }

        $changedPlatforms = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $p = 'links[' . $id . ']';
            $new = [
                'label' => (string) $checked[$p . '[label]'],
                'url' => (string) ($checked[$p . '[url]'] ?? ''),
                'sort_order' => (int) ($checked[$p . '[sort_order]'] ?? 0),
                'is_active' => $active[$id] ? 1 : 0,
            ];
            $old = [
                'label' => (string) $row['label'], 'url' => (string) $row['url'],
                'sort_order' => (int) $row['sort_order'], 'is_active' => (int) $row['is_active'],
            ];
            if ($new !== $old) {
                SocialLink::update($id, $new); // platform is fixed and never written
                $changedPlatforms[] = (string) $row['platform'];
            }
        }
        if ($changedPlatforms === []) {
            flash_message('info', 'Nothing was changed.');
            return redirect($back);
        }
        $this->audit('social_links.update', 'social_links', null, 'Updated social links: ' . implode(', ', $changedPlatforms));
        flash_message('success', 'Social links saved.');
        return redirect($back);
    }

    private function platformName(string $platform): string
    {
        return $platform === 'x' ? 'X (Twitter)' : ucfirst($platform);
    }

    private function plausibleHttpsUrl(string $url): bool
    {
        if (!$this->isHttps($url)) {
            return false;
        }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        return preg_match('/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host) === 1;
    }
}
