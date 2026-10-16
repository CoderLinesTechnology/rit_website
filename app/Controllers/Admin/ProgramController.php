<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Program;
use App\Models\ProgramCategory;
use App\Services\ImageUploader;
use App\Services\UploadException;

final class ProgramController extends AdminController
{
    use ContentSupport;

    /** Sprite icon names an administrator can pick. */
    public const ICONS = [
        'laptop', 'display', 'wrench', 'tools', 'scissors', 'palette', 'graph-up-arrow', 'briefcase', 'people', 'people-fill',
        'house-heart', 'recycle', 'award', 'mortarboard', 'lightbulb', 'heart', 'globe2',
    ];
    public const MAX_BATCH = 10;
    public const MAX_IMAGES_PER_PROGRAM = 50;

    private const BOOLS = ['accepts_applications', 'is_featured'];

    public function index(Request $request): Response
    {
        $result = Program::adminPaginate($request, 20);
        $filters = [
            'q' => mb_substr($request->query('q'), 0, 100),
            'status' => in_array($request->query('status'), Program::STATUSES, true) ? $request->query('status') : '',
            'category' => $request->queryInt('category') > 0 ? (string) $request->queryInt('category') : '',
        ];
        return $this->page('admin/programs/index', 'Programs', 'programs', [
            'programs' => $result['items'],
            'paginator' => $result['paginator'],
            'filters' => $filters,
            'categoryOptions' => $this->categoryOptions(),
            'returnTo' => $request->fullPath(),
        ]);
    }

    public function create(Request $request): Response
    {
        $values = $this->values([
            'status' => 'draft', 'sort_order' => '0', 'accepts_applications' => '0', 'is_featured' => '0',
        ], self::BOOLS);
        return $this->form(null, $values);
    }

    public function store(Request $request): Response
    {
        $v = $this->validate($request, null, $slug);
        if ($v->fails()) {
            return $this->invalid(url('/admin/programs/create'), $v->errors(), $request->post());
        }
        $d = $v->validated();
        $plan = $this->planImage($request, $v, null, ['path' => 'featured_image_path', 'thumb' => 'featured_thumb_path', 'alt' => 'featured_image_alt'], 'programs', $d['image_alt'] ?? null);
        if ($v->fails()) {
            return $this->invalid(url('/admin/programs/create'), $v->errors(), $request->post());
        }
        try {
            $id = Program::create($this->row($d, $slug) + $plan['data'] + ['created_by' => $this->adminId(), 'updated_by' => $this->adminId()]);
        } catch (\Throwable $e) {
            $this->discardStored($plan['stored']);
            throw $e;
        }
        $this->audit('program.create', 'program', $id, 'Created program "' . $d['title'] . '" (' . $d['status'] . ')');
        flash_message('success', 'Program created.' . ($d['status'] === 'draft' ? ' It is a draft and is not visible on the website yet.' : ''));
        return redirect(url('/admin/programs/' . $id . '/edit'));
    }

    public function edit(Request $request, string $id): Response
    {
        $program = $this->findOr404(Program::find((int) $id));
        $record = $program;
        foreach (['application_opens_on', 'application_closes_on'] as $k) {
            $record[$k] = (string) ($program[$k] ?? '');
        }
        $record['image_alt'] = (string) ($program['featured_image_alt'] ?? '');
        return $this->form($program, $this->values($record, self::BOOLS));
    }

    public function update(Request $request, string $id): Response
    {
        $program = $this->findOr404(Program::find((int) $id));
        $pid = (int) $program['id'];
        $back = url('/admin/programs/' . $pid . '/edit');
        $v = $this->validate($request, $pid, $slug);
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        $d = $v->validated();
        $plan = $this->planImage($request, $v, $program, ['path' => 'featured_image_path', 'thumb' => 'featured_thumb_path', 'alt' => 'featured_image_alt'], 'programs', $d['image_alt'] ?? null);
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        try {
            Program::update($pid, $this->row($d, $slug) + $plan['data'] + ['updated_by' => $this->adminId()]);
        } catch (\Throwable $e) {
            $this->discardStored($plan['stored']);
            throw $e;
        }
        $this->discardImages($plan['discard']);
        $this->audit('program.update', 'program', $pid, 'Updated program "' . $d['title'] . '" (' . $d['status'] . ')');
        flash_message('success', 'Program saved.');
        return redirect($back);
    }

    public function destroy(Request $request, string $id): Response
    {
        $program = $this->findOr404(Program::find((int) $id));
        $pid = (int) $program['id'];
        $images = Program::images($pid);
        Database::connection()->transaction(static function () use ($pid): void {
            Program::delete($pid); // program_images rows cascade; applications keep a NULL program_id
        });
        $this->discardImages([[(string) $program['featured_image_path'], (string) $program['featured_thumb_path']]]);
        $this->discardImages(array_map(static fn (array $i): array => [(string) $i['image_path'], (string) $i['thumb_path']], $images));
        $this->audit('program.delete', 'program', $pid, 'Deleted program "' . $program['title'] . '" and ' . count($images) . ' additional image(s)');
        flash_message('success', 'Program deleted. Applications already received for it have been kept.');
        return $this->backTo($request, url('/admin/programs'));
    }

    public function uploadImages(Request $request, string $id): Response
    {
        $program = $this->findOr404(Program::find((int) $id));
        $pid = (int) $program['id'];
        $back = url('/admin/programs/' . $pid . '/edit') . '#program-images';
        $v = Validator::make($request->post(), ['image_batch_alt' => 'required|line|max:255'], ['image_batch_alt' => 'Image description']);
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        $files = $request->files('images');
        if ($files === []) {
            return $this->invalid($back, ['images' => 'Choose at least one image to upload.'], $request->post());
        }
        if (count($files) > self::MAX_BATCH) {
            return $this->invalid($back, ['images' => 'Upload at most ' . self::MAX_BATCH . ' images at a time.'], $request->post());
        }
        if (count(Program::images($pid)) + count($files) > self::MAX_IMAGES_PER_PROGRAM) {
            return $this->invalid($back, ['images' => 'A program can have at most ' . self::MAX_IMAGES_PER_PROGRAM . ' additional images.'], $request->post());
        }
        $alt = (string) $v->validated()['image_batch_alt'];
        $uploader = new ImageUploader();
        $ok = 0;
        $rejected = [];
        foreach ($files as $file) {
            try {
                $stored = $uploader->store($file, 'programs');
            } catch (UploadException $e) {
                $rejected[] = $this->safeName($file['name']) . ' (' . $e->getMessage() . ')';
                continue;
            }
            try {
                Program::addImage($pid, [
                    'image_path' => $stored->path, 'thumb_path' => $stored->thumbPath,
                    'width' => $stored->width, 'height' => $stored->height, 'alt_text' => $alt,
                ]);
                $ok++;
            } catch (\Throwable $e) {
                $uploader->deleteImage($stored->path, $stored->thumbPath);
                throw $e;
            }
        }
        if ($ok > 0) {
            $this->audit('program.images_add', 'program', $pid, 'Added ' . $ok . ' image(s) to program "' . $program['title'] . '"');
            flash_message('success', $ok . ($ok === 1 ? ' image' : ' images') . ' uploaded.');
        }
        if ($rejected !== []) {
            \App\Core\Session::flash('_message_error', count($rejected) . ' rejected: ' . implode('; ', $rejected));
        }
        return redirect($back);
    }

    public function deleteImage(Request $request, string $id, string $image_id): Response
    {
        $program = $this->findOr404(Program::find((int) $id));
        $image = $this->findOr404(Program::findImage((int) $program['id'], (int) $image_id));
        Program::deleteImage((int) $image['id']);
        $this->discardImages([[(string) $image['image_path'], (string) $image['thumb_path']]]);
        $this->audit('program.image_delete', 'program', (int) $program['id'], 'Removed an image from program "' . $program['title'] . '"');
        flash_message('success', 'Image removed.');
        return redirect(url('/admin/programs/' . (int) $program['id'] . '/edit') . '#program-images');
    }

    /** @param array<string, mixed>|null $program @param array<string, string> $values */
    private function form(?array $program, array $values): Response
    {
        $pid = $program !== null ? (int) $program['id'] : null;
        return $this->page('admin/programs/form', $program === null ? 'New program' : 'Edit program', 'programs', [
            'program' => $program,
            'v' => $values,
            'action' => $pid === null ? '/admin/programs' : '/admin/programs/' . $pid,
            'categoryOptions' => $this->categoryOptions(),
            'icons' => self::ICONS,
            'maxMb' => $this->maxMb(),
            'images' => $pid === null ? [] : Program::images($pid),
            'maxBatch' => self::MAX_BATCH,
        ]);
    }

    /** @return array<int, string> */
    private function categoryOptions(): array
    {
        $options = [];
        foreach (ProgramCategory::all() as $c) {
            $options[(int) $c['id']] = (string) $c['name'];
        }
        return $options;
    }

    private function validate(Request $request, ?int $id, ?string &$slug): Validator
    {
        $v = Validator::make($request->post(), [
            'title' => 'required|line|max:160',
            'slug' => 'nullable|slug|max:180',
            'category_id' => 'nullable|int',
            'icon' => 'nullable|in:' . implode(',', self::ICONS),
            'summary' => 'required|string|max:500',
            'description' => 'required|string|max:50000',
            'image_alt' => 'nullable|line|max:255',
            'target_audience' => 'nullable|string|max:500',
            'eligibility' => 'nullable|string|max:5000',
            'duration' => 'nullable|line|max:120',
            'location' => 'nullable|line|max:160',
            'application_opens_on' => 'nullable|date',
            'application_closes_on' => 'nullable|date',
            'accepts_applications' => 'boolean',
            'application_instructions' => 'nullable|string|max:5000',
            'status' => 'required|in:' . implode(',', Program::STATUSES),
            'is_featured' => 'boolean',
            'sort_order' => 'nullable|int|min:0|max:9999',
            'seo_title' => 'nullable|line|max:150',
            'seo_description' => 'nullable|line|max:300',
        ], [
            'seo_title' => 'Search title', 'seo_description' => 'Search description', 'image_alt' => 'Image description',
            'category_id' => 'Category', 'application_opens_on' => 'Opening date', 'application_closes_on' => 'Closing date',
            'description' => 'Full description', 'summary' => 'Short summary',
        ]);
        $d = $v->validated();
        if ($v->passes()) {
            if (($d['category_id'] ?? null) !== null && ProgramCategory::find((int) $d['category_id']) === null) {
                $v->addError('category_id', 'Category is not one of the available options.');
            }
            if (($d['application_opens_on'] ?? null) !== null && ($d['application_closes_on'] ?? null) !== null
                && $d['application_closes_on'] < $d['application_opens_on']) {
                $v->addError('application_closes_on', 'Closing date cannot be before the opening date.');
            }
            $slug = $this->resolveSlug($v, Program::class, $d['slug'] ?? null, (string) $d['title'], $id);
        }
        return $v;
    }

    /**
     * Columns from validated input (explicit; never the raw POST).
     *
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    private function row(array $d, string $slug): array
    {
        return [
            'category_id' => $d['category_id'] ?? null,
            'icon' => $d['icon'] ?? null,
            'title' => $d['title'],
            'slug' => $slug,
            'summary' => $d['summary'],
            'description' => $d['description'],
            'target_audience' => $d['target_audience'] ?? null,
            'eligibility' => $d['eligibility'] ?? null,
            'duration' => $d['duration'] ?? null,
            'location' => $d['location'] ?? null,
            'application_opens_on' => $d['application_opens_on'] ?? null,
            'application_closes_on' => $d['application_closes_on'] ?? null,
            'accepts_applications' => $this->bit($d['accepts_applications']),
            'application_instructions' => $d['application_instructions'] ?? null,
            'status' => $d['status'],
            'is_featured' => $this->bit($d['is_featured']),
            'sort_order' => (int) ($d['sort_order'] ?? 0),
            'seo_title' => $d['seo_title'] ?? null,
            'seo_description' => $d['seo_description'] ?? null,
        ];
    }
}
