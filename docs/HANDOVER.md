# Staff guide: running the RIT Foundation Ghana website

This guide is for Foundation staff and volunteers. You do not need technical knowledge. If you see
something technical (a command or a file name), it is for whoever looks after the server; see
[DEPLOYMENT.md](DEPLOYMENT.md). A shorter version of this guide is built into the dashboard under
**Help** (bottom of every admin page).

---

## 1. Signing in

1. Go to `https://www.ritfoundationglobal.com/admin/login` (the address may differ until the site
   is live) and enter your email address and password.
2. If you forget your password, use **Forgot password**. The reset link is sent by email, is valid for
   60 minutes and works once. It only works if email has been set up on the website; otherwise ask a
   super administrator to set a new password for you.
3. After five wrong attempts the account is locked for 15 minutes. Wait, then try again.
4. You are signed out automatically after 30 minutes of doing nothing, or after 8 hours in total.
   Always click **Log out** on a shared computer.
5. Change your own password under your name (top right) > **My account**. Use a long passphrase of at
   least 12 characters that you do not use anywhere else. It must not contain your name or email name.

### Roles: who can do what

| What you can do | Super administrator | Content editor | Applications officer |
|---|:-:|:-:|:-:|
| Programs, news and success stories, gallery, impact figures, team and milestones | Yes | Yes | No |
| Website content (page texts, photos, contact details), social links, SEO | Yes | Yes | No |
| Read and manage training applications, volunteers, partnership enquiries, contact messages | Yes | No | Yes |
| See the donations list | Yes | No | No |
| Donations notice, email notification settings, privacy notice and terms | Yes | No | No |
| Create and manage staff accounts, see the email log | Yes | No | No |
| See the audit log (who changed what) | Yes | No | No |
| Dashboard, Help, My account | Yes | Yes | Yes |

Give each person the lowest role they need. The **Applications officer** role is for people who
handle applicants' personal details; the **Content editor** role cannot see any of that.

---

## 2. The dashboard and the setup checklist

The dashboard shows the number of young people reached, published programs, applications received
this year, published news, a chart of applications by month and by status, the latest applications
(if your role allows), and recent activity by staff.

**Setup checklist (super administrators only).** It lists what is still missing before the site is
fully ready, for example: the setup token still in the server file, facts to confirm and fill in,
the privacy notice or terms not yet marked as reviewed, email not configured, no logo, online
donations not configured, and any emails that failed. Work through it; items disappear as they are
done. Note that facts the developers pre-filled (such as the contact email and phone number) are not
flagged, so check them yourselves under **Settings > Website content > Contact details**.

---

## 3. Programs and opening applications

**Programs > All programs > Add New Program.** Fill in the title, a short summary, the full
description, and optionally a featured image (with a description, see section 12), who it is for,
duration, location and eligibility. Programs have a **status**:

| Status | Meaning |
|---|---|
| Draft | Not shown on the website |
| Published | Shown on the website |
| Archived | Removed from the website and kept for the record |

The programs currently on the site say "More details about this program, including how and when to
apply, will be published here." Replace that with real details once they are confirmed (dates,
duration, location, eligibility). Do not invent any.

**To open applications for a program**, all three of these must be true at the same time:

1. The program is **Published**.
2. **"This program accepts applications online"** is ticked, and today falls between **Applications open on**
   and **Applications close on** (leave a date empty for "no limit"). You can add application instructions.
3. The site-wide switch **Settings > Website content > Training applications > "Accept training applications on the website"** is on.

If the Apply page says applications are closed, check these three. The message people see when
nothing is open is also editable in that same settings group.

Other useful items on the program page: **Feature this program on the homepage**, display order,
search-engine title and description, extra images (each batch needs a description), and the red
**Delete program** button at the bottom. **Categories** (Programs > Categories) group the programs.

---

## 4. News and success stories

**News & Stories > Articles > new article.** Enter a title, a short summary, the article text, a
category, an optional featured image and its description, and the publication date. Status is
**Draft** (not visible) or **Published**. Publishing with a future date schedules the article; it
appears automatically on that date.

**Consent rule for success stories.** An article in the category **Success Stories** about a named
person, a young person, or with their photo, can only be published after you tick the consent box,
and you must tick it only if you hold that person's permission **in writing**, or a parent or
guardian's permission if they are under 18. The system will refuse to publish until it is ticked. If
someone withdraws consent, **unpublish** the story immediately (the article's Unpublish button) or delete it.

Use plain, honest wording. Do not promise outcomes (jobs, places) that the Foundation cannot guarantee.

---

## 5. Gallery

**Gallery > Albums & photos.** Albums group photos. The seeded album "Tech Skills Challenge Season 2"
links to the photographer's online gallery rather than holding copies of the photos.

- **Upload:** open an album and use **Upload photos**. Choose up to **20 photos at a time**, JPEG, PNG
  or WebP, each up to the stated size (8 MB unless the server was set differently). The site resizes the photos and
  removes hidden data such as GPS location. For hundreds of photos, ask the person who runs the
  server to use the bulk importer ([MEDIA-IMPORT.md](MEDIA-IMPORT.md)).
- **Alt text is required** for every photo: a plain description for people who cannot see it. See
  [MEDIA-IMPORT.md](MEDIA-IMPORT.md) for good and bad examples.
- **Consent to publish.** A photo can be shown on the website only if you tick **permission to
  publish** (the Foundation has the photographer's licence and the consent of recognisable people;
  parents' consent for children). Photos uploaded without ticking it stay unpublished.
- **Featured photos** appear on the homepage gallery strip. Tick *Featured* on a photo's edit page.
- **Edit** a photo to change its description, caption, date, album, order, featured, published, or
  consent. **Delete** removes the photo file from the website for good.
- An album must itself be **published** for its published photos to show.

---

## 6. Impact figures

**Impact > Impact metrics.** Each figure has a label, a value, an optional "approximate" flag,
description, icon, reporting period, and whether it shows on the homepage.

**Only publish figures that the Foundation has verified.** The form will not let you publish a
figure unless you tick the verification box, which means a named person at the Foundation has checked
the number against records. Today the only published figure is "approximately 576 young women, girls
and youth reached" (marked approximate). Figures that appeared in the design mockups but were not
verified are **not** on the site, see [INCOMPLETE.md](INCOMPLETE.md).

---

## 7. Team and milestones

**Impact > Team & milestones.**

- **Team profiles:** name, role, short biography and photo. A profile can be published only if you tick
  that the person has approved the details and photo shown. Add more profiles only with each person's approval.
- **Milestones** (the "Our Journey" timeline): a date or period, a title and description. Tick
  **verified** only once the fact is confirmed, then publish.

---

## 8. Website content settings

**Settings > Website content** has tabs. Edit a tab and click Save; nothing changes until you save.

| Tab | What it controls | Who |
|---|---|---|
| Organization | Name, motto, goal, vision, mission, values, objectives, overview, year established, registration details | Content editor, Super admin |
| Homepage | Headline, texts and photos on the home page | same |
| Page headers & text | The title, introduction and photo at the top of each page, and other page texts | same |
| Contact details | Email, telephones, address, postal address, office hours, optional map link | same |
| Logo & shared images | Logo, photo for the "call to action" bands, default share image, footer texts | same |
| Training applications | The site-wide on/off switch, the "closed" message, whether applicants may attach a document | same |
| Donations | Notice shown while online donations are unavailable; other ways to give | Super admin only |
| Email notifications | Where staff notifications go; whether people get a confirmation email | Super admin only |
| Privacy & terms | The privacy notice and terms and conditions, and their "reviewed" ticks | Super admin only |

Tips:

- Any photo needs a **description (alt text)**; the form will not save a photo without one.
- Fields marked **Needs confirmation** are facts that must be checked before being published.
- Formatted text boxes accept simple formatting (headings with `##`, lists, **bold**), shown as a hint
  beside the box.
- Map link: only add a Google Maps embed link if the Foundation has a full public address. It must start
  with `https://`. Adding it means visitors' browsers contact Google; the privacy notice must say so.
- **Office hours** are blank until confirmed. Leave them blank rather than guessing.
- **Privacy & terms "reviewed" ticks.** Tick them only after a responsible person (ideally with legal
  advice) has read and approved the wording. The ticks are a reminder for the team: they clear items from the dashboard
  checklist. They do not change what visitors see, and the pages are shown either way.

---

## 9. Social links and SEO

- **Settings > Social links:** the Foundation's profiles shown in the header and footer. Every link must be a full
  address starting with `https://`. Untick a platform to hide it. Check each link opens the right,
  official page (see [INCOMPLETE.md](INCOMPLETE.md)).
- **Settings > SEO:** for each page, an optional search title, a search description, and a share image used when the
  page is posted on WhatsApp or Facebook. Leave blank to use the defaults. Keep titles short and descriptions honest.

---

## 10. Reviewing training applications

**Applications > Training applications.** Use the search, status, program and date filters. Click an
application to open it. The new-submission bell at the top shows how many are waiting.

**Statuses**

| Status | Meaning |
|---|---|
| New | Nobody has looked at it yet |
| Under review | You are reading it or waiting for more information |
| Shortlisted | A strong candidate, not yet decided |
| Accepted | You have decided to offer a place. **The applicant is not emailed automatically**; contact them yourself |
| Rejected | Not offered a place. Be kind when you tell them |
| Withdrawn | The applicant no longer wants to take part |

Changing a status is recorded (who and when). It never emails the applicant.

**On an application you will see:** the applicant's details, their reason for applying, and any
attached document. Also:

- **Notes:** a private box for staff. Never put sensitive details (health, religion, etc.) in notes.
- **Possible duplicate:** the same email address applied to the same program more than once.
- **Attachment:** download it from the application page. Only staff with access can open it; every
  download is logged. Don't save it to shared drives or forward it.
- **Under 18:** the form requires a parent or guardian name, telephone number and consent. The
  application page shows these. Make contact through the guardian for under-18s.
- **Delete:** the red button permanently deletes the application, its attachment and related
  queued emails. Use it when someone asks for their data to be deleted (section 14).

Nobody should be told they have a place unless a staff member has decided so. Confirmation messages
on the website say that applying does not guarantee a place.

---

## 11. Volunteers, partners and contact messages

Each has its own list under **Applications**, with search and status filters:

| List | Statuses |
|---|---|
| Volunteers | New, contacted, active, declined, closed |
| Partnerships | New, in discussion, agreed, declined, closed |
| Contact messages | New, in progress, replied, closed |

Open a record, answer the person by your normal email or phone, then update the status and add notes.
Each has a delete button (permanent). Closed records are still personal data: they are removed
automatically after the retention period, or earlier on request.

---

## 12. Writing good photo descriptions (alt text)

Describe what a person would see, briefly and factually, without names of children.

| Good | Not good |
|---|---|
| "Two young women fitting a sewn garment on a mannequin in a training workshop" | "IMG_4421.jpg" |
| "Participants at laptops during the coding session" | "photo" or "picture of people" |
| "Group of trainees and facilitators holding certificates at the end of a workshop" | "Ama, 14, from X school getting certificate" (names a child) |

---

## 13. Donations page

Online donations are **not configured**. No payment provider has been chosen, so the page shows the
notice from **Settings > Website content > Donations** and no payment form; nothing can be charged. The
**Donations** list in the menu is empty and says so. If you have confirmed bank or mobile money
details, you may add them under "Other ways to give" in the same settings tab; publish only
details the Foundation has verified. Adding online giving later is a developer task, described in
[PAYMENTS.md](PAYMENTS.md), and needs the Foundation to choose a provider.

---

## 14. Email and the email log

**Users > Email log** (super administrators) lists every email the website tried to send, with
its status:

| Status | Meaning |
|---|---|
| Queued | Waiting to be sent |
| Handed off | The mail server accepted it. This does **not** prove it reached the inbox; it may be in spam |
| Failed | Could not be sent (the reason is shown); it is retried automatically a few times |
| Skipped | Email is not configured, so it was not sent |

Staff notifications contain only a reference number, not the person's details: open the dashboard to
see the record. If the dashboard says email is not configured, no notifications or password resets
are being sent.

---

## 15. Managing staff accounts (super administrators)

**Users > Administrators.**

- **Add** a person: name, email, role, and an initial password. Share it with them securely, not by email, and ask
  them to change it under My account.
- **Change role** or **name/email** on their edit page. Changing a role signs them out everywhere.
- **Set a new password** for someone else if they are locked out (you change your own under My account). Doing so signs them out everywhere.
- **Deactivate** someone who leaves, straight away. They are signed out and cannot sign in. You cannot
  deactivate yourself, change your own role, or remove the last active super administrator.
- The **Audit log** (Users > Audit log) shows who did what and when. It does not contain applicants'
  names or emails.

---

## 16. Privacy duties

The website holds personal data of applicants (some under 18), volunteers, partners and enquirers.
Under the Data Protection Act, 2012 (Act 843) the Foundation is responsible for it. In practice:

1. **Do not forward, download, print or copy applicant data** (including attachments) unless you
   really need to. Never paste it into group chats, shared drives or personal email.
2. **Requests from a person** to see, correct or delete their information (data subject requests):
   confirm who they are, then deal with it promptly. To delete, open the record and use the red delete
   button; this also removes the attachment. Record the request and what you did in your own
   private log (without copying the data). If you are unsure how to answer, ask the data protection
   lead before replying. Corrections can be made by deleting and asking the person to resubmit, or by the
   developers if needed.
3. **Retention:** submissions are removed automatically after the retention period (24 months unless
   the Foundation chooses otherwise), by the monthly purge job. Don't keep your own copies longer.
4. **Photos:** publish photos of identifiable people only with consent (and the photographer's licence).
   If someone asks for their photo to be removed, do it the same day (delete the photo, or untick *published*),
   and reply to confirm.
5. **Report incidents at once** (a lost laptop with the dashboard open, a shared password, an email sent to the
   wrong person, a suspicious sign-in) to the data protection lead and to the person who runs the server. The Foundation
   may have a legal duty to tell the Data Protection Commission and affected people, so don't wait.
6. Sign out on shared computers, never share your login, and use a different password for the dashboard.

The Foundation still needs to register with the Data Protection Commission and have its privacy notice
and terms reviewed; see [INCOMPLETE.md](INCOMPLETE.md) and [COMPLIANCE.md](COMPLIANCE.md).

---

## 17. Who to call

Fill in this table and keep it up to date. The developers did not know these details.

| Need | Name | Phone | Email |
|---|---|---|---|
| Website content questions (Foundation lead) | _to be filled in_ | | |
| Data protection lead (privacy requests, incidents) | _to be filled in_ | | |
| Person who looks after the server / hosting | _to be filled in_ | | |
| Web hosting provider support | _to be filled in_ | | |
| Developer (for faults and changes) | _to be filled in_ | | |
| Email / domain registrar support | _to be filled in_ | | |
