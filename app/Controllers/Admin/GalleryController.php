<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use App\Services\ImageUploader;
use App\Services\UploadException;

final class GalleryController extends AdminController
{
    use ContentSupport;

    public const MAX_BATCH = 20;

    public function index(Request $request): Response
    {
        return $this->page('admin/gallery/index', 'Gallery', 'gallery', ['albums' => GalleryCategory::allWithCounts()]);
    }

    // ── Albums ────────────────────────────────────────────────────────────

    public function createAlbum(Request $request): Response
    {
        return $this->albumForm(null, $this->values(['is_published' => '1', 'sort_order' => '0'], ['is_published']));
    }

    public function storeAlbum(Request $request): Response
    {
        $v = $this->validateAlbum($request, null, $slug);
        if ($v->fails()) {
            return $this->invalid(url('/admin/gallery/albums/create'), $v->errors(), $request->post());
        }
        $d = $v->validated();
        $id = GalleryCategory::create($this->albumRow($d, $slug));
        $this->audit('gallery.album_create', 'gallery_album', $id, 'Created album "' . $d['name'] . '"');
        flash_message('success', 'Album created. Now add photos to it.');
        return redirect(url('/admin/gallery/albums/' . $id));
    }

    public function showAlbum(Request $request, string $id): Response
    {
        $album = $this->findOr404(GalleryCategory::find((int) $id));
        return $this->page('admin/gallery/album', (string) $album['name'], 'gallery', [
            'album' => $album,
            'images' => GalleryImage::forAlbum((int) $album['id']),
            'v' => $this->values([
                'alt_text' => 'Photo from ' . $album['name'], 'caption' => '', 'taken_on' => '',
                'consent_confirmed' => '0', 'is_published' => '0',
            ], ['consent_confirmed', 'is_published']),
            'maxBatch' => self::MAX_BATCH,
            'maxMb' => $this->maxMb(),
        ]);
    }

    public function editAlbum(Request $request, string $id): Response
    {
        $album = $this->findOr404(GalleryCategory::find((int) $id));
        return $this->albumForm($album, $this->values($album, ['is_published']));
    }

    public function updateAlbum(Request $request, string $id): Response
    {
        $album = $this->findOr404(GalleryCategory::find((int) $id));
        $aid = (int) $album['id'];
        $v = $this->validateAlbum($request, $aid, $slug);
        if ($v->fails()) {
            return $this->invalid(url('/admin/gallery/albums/' . $aid . '/edit'), $v->errors(), $request->post());
        }
        $d = $v->validated();
        GalleryCategory::update($aid, $this->albumRow($d, $slug));
        $this->audit('gallery.album_update', 'gallery_album', $aid, 'Updated album "' . $d['name'] . '"' . ($d['is_published'] ? ' (published)' : ' (hidden)'));
        flash_message('success', 'Album saved.');
        return redirect(url('/admin/gallery/albums/' . $aid));
    }

    public function destroyAlbum(Request $request, string $id): Response
    {
        $album = $this->findOr404(GalleryCategory::find((int) $id));
        $aid = (int) $album['id'];
        $count = GalleryCategory::imageCount($aid);
        if ($count > 0) {
            flash_message('error', 'This album still has ' . $count . ($count === 1 ? ' photo' : ' photos') . '. Delete the photos or move them to another album first, then delete the album.');
            return redirect(url('/admin/gallery'));
        }
        GalleryCategory::delete($aid);
        $this->audit('gallery.album_delete', 'gallery_album', $aid, 'Deleted album "' . $album['name'] . '"');
        flash_message('success', 'Album deleted.');
        return redirect(url('/admin/gallery'));
    }

    // ── Photos ────────────────────────────────────────────────────────────

    public function uploadImages(Request $request, string $id): Response
    {
        $album = $this->findOr404(GalleryCategory::find((int) $id));
        $aid = (int) $album['id'];
        $back = url('/admin/gallery/albums/' . $aid);
        $v = Validator::make($request->post(), [
            'alt_text' => 'required|line|max:255',
            'caption' => 'nullable|line|max:500',
            'taken_on' => 'nullable|date',
            'consent_confirmed' => 'boolean',
            'is_published' => 'boolean',
        ], ['alt_text' => 'Photo description', 'taken_on' => 'Date taken']);
        $d = $v->validated();
        if ($v->passes() && $d['is_published'] && !$d['consent_confirmed']) {
            $v->addError('consent_confirmed', 'Photos can only be published once you confirm the Foundation has permission to publish them.');
        }
        $files = $request->files('images');
        if ($files === []) {
            $v->addError('images', 'Choose at least one photo to upload.');
        } elseif (count($files) > self::MAX_BATCH) {
            $v->addError('images', 'Upload at most ' . self::MAX_BATCH . ' photos at a time.');
        }
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }

        @set_time_limit(180); // up to 20 photos are decoded and re-encoded
        $uploader = new ImageUploader();
        $sort = GalleryImage::nextSortOrder($aid);
        $ok = 0;
        $rejected = [];
        foreach ($files as $file) {
            try {
                $stored = $uploader->store($file, 'gallery');
            } catch (UploadException $e) {
                $rejected[] = $this->safeName($file['name']) . ' (' . $e->getMessage() . ')';
                continue;
            }
            try {
                GalleryImage::create([
                    'category_id' => $aid,
                    'image_path' => $stored->path, 'thumb_path' => $stored->thumbPath,
                    'width' => $stored->width, 'height' => $stored->height,
                    'alt_text' => $d['alt_text'], 'caption' => $d['caption'] ?? null, 'taken_on' => $d['taken_on'] ?? null,
                    'is_featured' => 0, 'is_published' => $this->bit($d['is_published']),
                    'consent_confirmed' => $this->bit($d['consent_confirmed']),
                    'sort_order' => $sort++, 'uploaded_by' => $this->adminId(),
                ]);
                $ok++;
            } catch (\Throwable $e) {
                $uploader->deleteImage($stored->path, $stored->thumbPath);
                throw $e;
            }
        }
        if ($ok > 0) {
            $this->audit('gallery.upload', 'gallery_album', $aid, 'Uploaded ' . $ok . ' photo(s) to album "' . $album['name'] . '"' . ($d['is_published'] ? ' (published)' : ' (unpublished)'));
            flash_message('success', $ok . ($ok === 1 ? ' photo' : ' photos') . ' uploaded' . ($d['is_published'] ? ' and published.' : '. They are unpublished until you publish them.'));
        }
        if ($rejected !== []) {
            Session::flash('_message_error', count($rejected) . ' rejected: ' . implode('; ', $rejected));
        }
        return redirect($back);
    }

    public function editImage(Request $request, string $id): Response
    {
        $image = $this->findOr404(GalleryImage::find((int) $id));
        return $this->imageForm($image, $this->values($image, ['is_featured', 'is_published', 'consent_confirmed']));
    }

    public function updateImage(Request $request, string $id): Response
    {
        $image = $this->findOr404(GalleryImage::find((int) $id));
        $iid = (int) $image['id'];
        $back = url('/admin/gallery/images/' . $iid . '/edit');
        $v = Validator::make($request->post(), [
            'title' => 'nullable|line|max:190',
            'alt_text' => 'required|line|max:255',
            'caption' => 'nullable|line|max:500',
            'taken_on' => 'nullable|date',
            'category_id' => 'required|int',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'consent_confirmed' => 'boolean',
            'sort_order' => 'nullable|int|min:0|max:99999',
        ], ['alt_text' => 'Photo description', 'category_id' => 'Album', 'taken_on' => 'Date taken']);
        $d = $v->validated();
        if ($v->passes()) {
            if (GalleryCategory::find((int) $d['category_id']) === null) {
                $v->addError('category_id', 'Album is not one of the available options.');
            }
            if ($d['is_published'] && !$d['consent_confirmed']) {
                $v->addError('consent_confirmed', 'A photo can only be published once you confirm the Foundation has permission to publish it.');
            }
        }
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        // Unpublishing or withdrawing consent moves the files to a new random name,
        // so the old public URL stops working immediately.
        $files = [];
        $withdrawn = ((int) $image['is_published'] === 1 && !$d['is_published'])
            || ((int) $image['consent_confirmed'] === 1 && !$d['consent_confirmed']);
        if ($withdrawn) {
            $renamed = (new ImageUploader())->rename((string) $image['image_path'], (string) $image['thumb_path']);
            if ($renamed !== null) {
                $files = ['image_path' => $renamed['path'], 'thumb_path' => $renamed['thumb']];
            }
        }
        GalleryImage::update($iid, $files + [
            'category_id' => (int) $d['category_id'],
            'title' => $d['title'] ?? null,
            'alt_text' => $d['alt_text'],
            'caption' => $d['caption'] ?? null,
            'taken_on' => $d['taken_on'] ?? null,
            'is_featured' => $this->bit($d['is_featured']),
            'is_published' => $this->bit($d['is_published']),
            'consent_confirmed' => $this->bit($d['consent_confirmed']),
            'sort_order' => (int) ($d['sort_order'] ?? 0),
        ]);
        $this->audit('gallery.image_update', 'gallery_image', $iid, 'Updated a photo (' . ($d['is_published'] ? 'published' : 'unpublished') . ')' . ((int) $d['category_id'] !== (int) $image['category_id'] ? ', moved to another album' : ''));
        flash_message('success', 'Photo saved.');
        return redirect(url('/admin/gallery/albums/' . (int) $d['category_id']));
    }

    public function destroyImage(Request $request, string $id): Response
    {
        $image = $this->findOr404(GalleryImage::find((int) $id));
        GalleryImage::delete((int) $image['id']);
        $this->discardImages([[(string) $image['image_path'], (string) $image['thumb_path']]]);
        $this->audit('gallery.image_delete', 'gallery_image', (int) $image['id'], 'Deleted a photo');
        flash_message('success', 'Photo deleted.');
        return redirect(url('/admin/gallery/albums/' . (int) $image['category_id']));
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /** @param array<string, mixed>|null $album @param array<string, string> $values */
    private function albumForm(?array $album, array $values): Response
    {
        return $this->page('admin/gallery/album-form', $album === null ? 'New album' : 'Edit album', 'gallery', [
            'album' => $album,
            'v' => $values,
            'action' => $album === null ? '/admin/gallery/albums' : '/admin/gallery/albums/' . (int) $album['id'],
        ]);
    }

    /** @param array<string, mixed> $image @param array<string, string> $values */
    private function imageForm(array $image, array $values): Response
    {
        $albums = [];
        foreach (GalleryCategory::allWithCounts() as $a) {
            $albums[(int) $a['id']] = (string) $a['name'];
        }
        return $this->page('admin/gallery/image-form', 'Edit photo', 'gallery', [
            'image' => $image,
            'v' => $values,
            'albums' => $albums,
            'action' => '/admin/gallery/images/' . (int) $image['id'],
        ]);
    }

    private function validateAlbum(Request $request, ?int $id, ?string &$slug): Validator
    {
        $v = Validator::make($request->post(), [
            'name' => 'required|line|max:150',
            'slug' => 'nullable|slug|max:170',
            'description' => 'nullable|string|max:2000',
            'event_date' => 'nullable|date',
            'external_url' => 'nullable|url|max:500',
            'is_published' => 'boolean',
            'sort_order' => 'nullable|int|min:0|max:9999',
        ], ['external_url' => 'Link to more photos', 'event_date' => 'Event date']);
        if ($v->passes()) {
            $d = $v->validated();
            if (($d['external_url'] ?? null) !== null && !$this->isHttps((string) $d['external_url'])) {
                $v->addError('external_url', 'Link to more photos must start with https://');
            }
            $slug = $this->resolveSlug($v, GalleryCategory::class, $d['slug'] ?? null, (string) $d['name'], $id);
        }
        return $v;
    }

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    private function albumRow(array $d, string $slug): array
    {
        return [
            'name' => $d['name'],
            'slug' => $slug,
            'description' => $d['description'] ?? null,
            'event_date' => $d['event_date'] ?? null,
            'external_url' => $d['external_url'] ?? null,
            'is_published' => $this->bit($d['is_published']),
            'sort_order' => (int) ($d['sort_order'] ?? 0),
        ];
    }
}
