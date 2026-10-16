<?php
/** vars: $kind (volunteer|partner|contact), $reference */
$this->layout('layouts/public');
$kinds = [
    'volunteer' => [
        'crumbs' => [['Home', '/'], ['Get Involved', '/get-involved'], ['Volunteer', '/volunteer'], ['Application received', null]],
        'title' => 'Application received',
        'lead' => 'Thank you for offering to volunteer with RIT Foundation Ghana.',
        'heading' => 'We have received your volunteer application',
        'next' => ['The Foundation reviews your application.', 'If there is a suitable opportunity, we will contact you about the next steps.'],
        'note' => 'Submitting an application does not guarantee a volunteering placement.',
        'again' => ['/get-involved', 'Back to Get Involved'],
    ],
    'partner' => [
        'crumbs' => [['Home', '/'], ['Get Involved', '/get-involved'], ['Partner', '/partner'], ['Enquiry received', null]],
        'title' => 'Enquiry received',
        'lead' => 'Thank you for your interest in partnering with RIT Foundation Ghana.',
        'heading' => 'We have received your partnership enquiry',
        'next' => ['The Foundation reviews your enquiry.', 'We will contact you to talk about what a partnership could look like.'],
        'note' => 'Submitting an enquiry does not commit either side to a partnership.',
        'again' => ['/get-involved', 'Back to Get Involved'],
    ],
    'contact' => [
        'crumbs' => [['Home', '/'], ['Contact Us', '/contact'], ['Message received', null]],
        'title' => 'Message received',
        'lead' => 'Thank you for contacting RIT Foundation Ghana.',
        'heading' => 'We have received your message',
        'next' => ['A member of the team reads your message.', 'We will reply as soon as we can.'],
        'note' => '',
        'again' => ['/', 'Back to home'],
    ],
];
$k = $kinds[$kind] ?? $kinds['contact'];
?>
<?= $this->insert('partials/page-hero', ['breadcrumbs' => $k['crumbs'], 'title' => $k['title'], 'text' => $k['lead']]) ?>

<section class="section" aria-labelledby="thanks-h">
  <div class="container container--narrow">
    <div class="form-card form-card--lg thanks">
      <span class="thanks__icon"><?= icon('check-circle') ?></span>
      <h2 id="thanks-h"><?= e($k['heading']) ?></h2>
      <p class="thanks__label">Your reference number</p>
      <p class="reference" id="reference"><?= e($reference) ?></p>
      <p>Please keep this number and quote it if you contact us again.</p>
<?php if (\App\Services\Settings::bool('notify.send_confirmations')): ?>
      <p>We have queued a confirmation email to the address you gave.</p>
<?php endif; ?>

      <h3>What happens next</h3>
      <ol class="thanks__list">
<?php foreach ($k['next'] as $step): ?>
        <li><?= e($step) ?></li>
<?php endforeach; ?>
      </ol>
<?php if ($k['note'] !== ''): ?>
      <p class="notice notice--strong"><?= icon('info-circle') ?><span><?= e($k['note']) ?></span></p>
<?php endif; ?>
      <div class="form__actions">
        <a class="btn" href="<?= e(url($k['again'][0])) ?>"><?= e($k['again'][1]) ?> <?= icon('arrow-right') ?></a>
      </div>
    </div>
  </div>
</section>
