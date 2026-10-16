<?php $this->layout('layouts/admin'); /** $mailDriver */ ?>
<?= $this->insert('admin/partials/page-header', ['title' => 'Help', 'subtitle' => 'A short guide to running the website day to day.']) ?>

<nav class="card toc" aria-label="On this page">
  <ul>
    <li><a href="#programs">Programs and opening applications</a></li>
    <li><a href="#news">News and success stories</a></li>
    <li><a href="#photos">Photos and the gallery</a></li>
    <li><a href="#applications">Reviewing applications</a></li>
    <li><a href="#enquiries">Volunteers, partners and messages</a></li>
    <li><a href="#privacy">Privacy reminders</a></li>
    <li><a href="#email">Email notifications</a></li>
    <li><a href="#accounts">Accounts and passwords</a></li>
  </ul>
</nav>

<div class="prose">
<section class="card" id="programs">
  <h2>Programs and opening applications</h2>
  <ol>
    <li>Go to <strong>Programs</strong> and choose <strong>Add New Program</strong>. Fill in the title, a short summary, the description and a photo with alt text.</li>
    <li>Set the status to <strong>Published</strong> when it is ready. Drafts are never shown on the website.</li>
    <li>To let people apply, tick <strong>Accepts applications</strong> and set the opening and closing dates.</li>
    <li>Applications are only open when all three are true: the program is published, the dates include today, and the site-wide applications switch (in Settings) is on. If the Apply page says applications are closed, check these three things.</li>
  </ol>
</section>

<section class="card" id="news">
  <h2>News and success stories</h2>
  <p>Write the article under <strong>News &amp; Stories</strong>, choose a category and publish it. A <strong>success story</strong> about a named person or a young person must only be published with that person's consent (or a parent or guardian's consent if they are under 18). Tick the consent box only once you hold that consent in writing. If consent is withdrawn, unpublish the story straight away.</p>
</section>

<section class="card" id="photos">
  <h2>Photos and the gallery</h2>
  <ul>
    <li>Add photos under <strong>Gallery</strong>. Every photo needs <strong>alt text</strong>: a plain description of what is in the picture, for people who cannot see it.</li>
    <li>Only upload photos you have permission to use. For recognisable people, especially children, you need consent. Do not upload photos that show a child's name, school or home.</li>
    <li>For many photos at once, ask whoever looks after the server to run <code>scripts/import-gallery.php</code>, which imports a whole folder.</li>
  </ul>
</section>

<section class="card" id="applications">
  <h2>Reviewing applications</h2>
  <p>Open <strong>Applications</strong> to see everyone who applied. Use the search and filters to find someone, then open the application. Under-18 applicants show whether a guardian gave consent. Change the status as you work:</p>
  <dl class="detail-list">
    <dt>New</dt><dd>Nobody has looked at it yet.</dd>
    <dt>Under review</dt><dd>You are reading it or waiting for more information.</dd>
    <dt>Shortlisted</dt><dd>A strong candidate, not yet decided.</dd>
    <dt>Accepted</dt><dd>You have decided to offer a place. The applicant is <em>not</em> emailed automatically; contact them yourself.</dd>
    <dt>Rejected</dt><dd>Not offered a place. Be kind when you tell them.</dd>
    <dt>Withdrawn</dt><dd>The applicant no longer wants to take part.</dd>
  </dl>
  <p>A <strong>Possible duplicate</strong> label means the same email address applied to the same program more than once. Keep notes in the notes box; they are only visible to staff.</p>
</section>

<section class="card" id="enquiries">
  <h2>Volunteers, partners and messages</h2>
  <p>Volunteer sign-ups, partnership enquiries and contact messages each have their own list. Mark them <strong>contacted</strong> or <strong>replied</strong> after you answer, and <strong>closed</strong> when nothing more is needed. On a contact message, <strong>Reply by email</strong> opens your email program with the subject filled in.</p>
</section>

<section class="card" id="privacy">
  <h2>Privacy reminders</h2>
  <ul>
    <li>Applicants' details are personal data. Do not download, print, forward or copy them anywhere unless you really need to, and never share them in group chats.</li>
    <li>If someone asks for their information to be deleted, delete their record (the red button on the page). This also removes any attachment.</li>
    <li>Records are kept only as long as they are needed. Old submissions are removed under the retention period set by the Foundation.</li>
    <li>Do not put sensitive details (health, religion, and so on) in internal notes.</li>
    <li>Always sign out on shared computers.</li>
  </ul>
</section>

<section class="card" id="email">
  <h2>Email notifications</h2>
  <p>The website emails staff when something new arrives, and (if switched on) sends people a confirmation with their reference number. These emails contain references, not personal details.</p>
  <p><strong>Handed off</strong> in the <a href="<?= e(url('/admin/email-log')) ?>">Email log</a> means the mail server accepted the message. It does not prove it reached the inbox: it may still land in spam. Failed messages are retried automatically a few times.</p>
<?php if ($mailDriver === 'none'): ?>
  <p class="notice">Email is not configured on this website yet, so no emails are being sent.</p>
<?php endif; ?>
</section>

<section class="card" id="accounts">
  <h2>Accounts and passwords</h2>
  <p>Ask a super administrator if you need an account, a different role or a password reset. Change your own password under <a href="<?= e(url('/admin/account')) ?>">My account</a>. Use a long passphrase (at least 12 characters) that you do not use anywhere else. You are signed out automatically after a period of inactivity.</p>
</section>
</div>
