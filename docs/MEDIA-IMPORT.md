# Getting approved photos onto the website

This guide explains how the Foundation puts photographs on the site: first confirming that it is
allowed to, then moving the files, then publishing them. **No participant or event photos are
published yet.** They are being supplied by the client and must stay unpublished until permission is
confirmed (see section 1).

---

## 1. Confirm permission first

Do this before any photo is made public. The website's tick boxes only record that **you** have
confirmed permission; they do not check it for you.

| Question | What to confirm | Evidence to keep |
|---|---|---|
| **Does the Foundation have the right to use the photo?** | The photographer's licence. The Google Drive and Pixieset folders belong to the **photographers**, not to the Foundation. Being able to download a file is not the same as having a licence to publish it. | A written licence or email from the photographer stating which photos, which uses (website), for how long, and any credit line |
| **Do the people in it agree?** | Consent from recognisable participants, speakers and guests to appear on the public website | Signed consent forms or recorded written consent |
| **Are children involved?** | A parent or guardian's written consent for anyone under 18. Never publish a child's name, school or home next to a photo | Parent or guardian consent |
| **Is it appropriate?** | Nobody is shown in a way they would find embarrassing or unsafe; no photo reveals a home or exact location of a child | Reviewer's note |

If consent or the licence is missing for a photo, **do not publish it**. Leave it unpublished or
do not upload it. If someone later withdraws consent, remove the photo (section 7).

These obligations are in the compliance register ([COMPLIANCE.md](COMPLIANCE.md), "Photographs of
identifiable people") and the open decisions are in [INCOMPLETE.md](INCOMPLETE.md).

---

## 2. Downloading from Google Drive

For photos the Foundation has the right to use:

1. Open the shared Drive folder in your browser.
2. **Whole folder:** right-click the folder > **Download**. Google prepares one or more `.zip` files;
   unzip them into a folder on your computer.
3. **A few photos:** select them (Ctrl/Cmd-click) > right-click > **Download**, or open one photo and
   use the download button.
4. Keep the originals. Check you have all photos and delete duplicates and unsuitable pictures.
5. Name the folder clearly, for example `tech-skills-season-2-approved`. Put **only approved photos**
   in the folder you will upload from.

The Foundation's Google Drive link and Pixieset link are not stored in this document; ask the
Foundation for them.

---

## 3. Uploading through the dashboard (Admin > Gallery)

Best for up to a few dozen photos.

1. Sign in (you need the Content editor or Super administrator role).
2. **Gallery > Albums & photos.** Pick an album, or create one with **New album** (name, description,
   optional date, published, order).
3. Open the album and use **Upload photos**:
   - choose up to **20 photos per batch** (repeat for more);
   - formats: **JPEG, PNG or WebP**;
   - each file must be within the size shown on the page (**8 MB** by default; set by
     `UPLOAD_IMAGE_MAX_MB` on the server, and the server's PHP limits must allow it);
   - fill in the **description (alt text)** that will be applied to the whole batch (it is required);
     optionally a caption and date taken;
   - leave **Published** and **permission** unticked unless you hold the licence and consents for
     *every* photo in this batch.
4. What the website does to every photo automatically:
   - checks that it really is an image of an allowed type;
   - resizes it so the longest side is at most **1920 pixels**, and creates a **720 pixel thumbnail**;
   - **re-encodes it** (WebP, or JPEG if the server lacks WebP) which removes hidden data such as
     **GPS location and camera details (EXIF)**, and gives it a random file name;
   - rejects pictures over about 40 million pixels.
5. After the upload, photos that were not published are listed in the album. **Edit** each photo to
   give it its own description, then tick **permission to publish** and **Published** once ready. A
   photo cannot be published without the permission tick, and its album must also be published.
6. Tick **Featured** on a few of the best photos to show them on the homepage gallery strip.

### Alt text: good and bad

Alt text is read aloud to people using screen readers and shown when an image fails to load. Describe
what is happening, briefly, without naming children.

| Good | Bad |
|---|---|
| "Participants working on laptops during the Tech Skills Challenge finale" | "IMG_0231.JPG" |
| "A trainer showing two young women how to cut fabric in a fashion workshop" | "Image" or "photo of people" |
| "Group photo of trainees holding certificates outside the training hall" | "Kofi Mensah, age 15, Class 3 at ... school" |
| "Exhibition stall displaying handmade bags and upcycled products" | "Best event ever!!! #RIT" |

Write one sentence, up to about 125 characters where possible (the limit is 255).

---

## 4. Bulk import for many photos

When there are hundreds of photos, someone with command-line access (cPanel Terminal or SSH, see
[DEPLOYMENT.md](DEPLOYMENT.md)) can import a whole folder. Each photo goes through exactly the same
checks, resizing and re-encoding as a browser upload.

1. Create the album first in **Admin > Gallery** (the importer needs an existing album). Its **slug**
   is the album's web address name. The albums created by the starting data are:
   `tech-skills-challenge-season-2`, `training-workshops`, `fashion-and-vocational-training`,
   `exhibitions-and-events`, `community-activities`.
2. Upload the approved photos to a folder on the server **outside** the web-accessible area, for
   example `/home/USER/photos-approved/`, using File Manager or SFTP.
3. Run (from the project folder):

   ```bash
   php scripts/import-gallery.php --album=tech-skills-challenge-season-2 --dir=/home/USER/photos-approved
   ```

   Options:

   | Option | Meaning |
   |---|---|
   | `--album=<slug>` | Required. Album to import into |
   | `--dir=<folder>` | Required. Folder to read; sub-folders are included. Only `.jpg .jpeg .png .webp` files are read |
   | `--alt="..."` | Description for **every** imported photo. If omitted, "Photo from <album name>" is used |
   | `--taken-on=YYYY-MM-DD` | Date taken, applied to every photo |
   | `--publish` | Publish the imported photos. **Requires `--consent-confirmed`** |
   | `--consent-confirmed` | Records that the Foundation holds the licence and consents for these photos |

   Example when everything is cleared:

   ```bash
   php scripts/import-gallery.php --album=training-workshops --dir=/home/USER/photos-approved/workshops \
       --alt="Trainees at a hands-on workshop at the training centre" --taken-on=2025-05-24 \
       --publish --consent-confirmed
   ```

4. **By default imports are unpublished** and without the consent tick. This is deliberate: after the
   run, open the album in **Admin > Gallery**, check each photo, improve its description, then
   publish photos one by one (edit the photo, tick permission and Published). Using
   `--publish --consent-confirmed` for a folder you have not fully cleared would publish photos of
   people without checked permission. Don't.
5. The script prints a dot for each photo and a summary such as
   `Imported 120, already present 3, failed 1.` A failed file is named with the reason (for example
   too large, not a real image).

Things to know:

- **Duplicates are skipped.** The script records a fingerprint of each imported file (in
  `storage/cache/import-hashes-<album number>.txt`), so running it again on the same folder will not
  create copies. Because of that record, a photo you **deleted** in the dashboard is still treated as
  "already present" if you try to re-import the same file into the same album. To re-import, ask the
  developer to remove that file's line (or the file) first.
- The same description is used for the whole run. Group photos by subject into separate folders and
  run once per folder, or edit descriptions afterwards.
- Files over the image size limit (`UPLOAD_IMAGE_MAX_MB`, default 8 MB) are skipped as failed. Reduce
  them first or ask for the limit to be raised.
- The import is recorded in the audit log as "Imported N photo(s) from the command line".
- Delete the working folder (`photos-approved`) from the server afterwards if it holds originals you
  do not need to keep there.

---

## 5. Photos for the homepage and other pages

Page photos are chosen in **Settings > Website content**; they are separate from the gallery. A page
with no photo set shows a plain coloured band, so nothing looks broken while photos are awaited.

Every photo needs a description (alt text), and the same permission rules apply as for the gallery.
Upload files up to the size limit; the site shrinks anything wider than 1920 pixels. Images are
cropped to fill their space (centre-focused), so keep the important part of the picture in the middle.
Prefer high-quality landscape photos taken in good light.

| Where | Settings tab > field | Shape and suggested size |
|---|---|---|
| Home page top banner | Homepage > Hero photo | Wide landscape, about 16:7. Around **1920 x 800** pixels |
| Home "Who we are" strip | Homepage > About strip photo | Landscape 4:3. About 1200 x 900 |
| Home gallery block | Homepage > Gallery block photo | Landscape 4:3. About 1200 x 900 |
| Top banner of each other page (About, Programs, Impact, Gallery, News, Get Involved, Volunteer, Partner, Apply, Contact, Donate) | Page headers & text > *page* > Header photo | Wide landscape, about 16:5. Around **1920 x 600** |
| Story photos on About, Impact, Get Involved | Page headers & text > *page* > photo fields | Landscape 4:3 (shown 4:3 or taller 4:5 on wide screens). About 1200 x 900 |
| Call-to-action bands | Logo & shared images > Photo for the call-to-action bands | Landscape, about 9:4. Around 1600 x 700 |
| Image shown when a page is shared on WhatsApp or Facebook | Logo & shared images > Default image when pages are shared | Landscape about **1200 x 630**; per-page ones in **Settings > SEO** |
| Logo | Logo & shared images > Logo | PNG or WebP with a transparent background; it is shown about 56 pixels high. The logo file is being supplied by the client |
| Team portraits | Impact > Team & milestones > profile photo | Portrait or square, about 3:3.2 |

These sizes are guidance taken from how the pages lay images out; the site works with other sizes.
There is no favicon from the logo yet (a placeholder icon is used until the client's logo arrives;
see [INCOMPLETE.md](INCOMPLETE.md)).

---

## 6. The Pixieset gallery

The Tech Skills Challenge Season 2 photos are held on the photographer's Pixieset gallery. The
album "Tech Skills Challenge Season 2" in the dashboard **links** to that gallery ("See the full photo
album on Pixieset", opens in a new tab) instead of copying it. This respects the photographer's
rights. If the Foundation later receives a licence to host copies, import them as in sections 3 or 4;
until then, do not download and republish Pixieset photos.

---

## 7. Removing a photo on request

If a person (or their parent or guardian) asks to have a photo taken down, or consent is withdrawn:

1. Do it the same day. **Admin > Gallery** > album > the photo > **Delete** (permanent), or untick
   **Published** if you need time to decide.
2. If the photo is also used as a page photo or in a news article, remove it there too
   (Settings > Website content; Articles > edit > featured image).
3. Reply to confirm. Note the request and the date in your private privacy log (see
   [HANDOVER.md](HANDOVER.md), section 16).
4. Deleting removes the file from the website. Copies already saved by visitors, search engines or
   social media cannot be recalled; search engine caches update over time.
