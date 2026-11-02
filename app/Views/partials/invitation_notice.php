<?php /** Enlace de invitación solo en desarrollo (InvitationNotice). */ ?>
<?php if (ENVIRONMENT !== 'production' && ($link = session()->getFlashdata('invitation_link'))): ?>
  <div class="notice-accent mb-4" role="status">
    <?= icon('mail', 16, 'mt-0.5 text-accent') ?>
    <div class="min-w-0"><p><?= lang('Core.invitationDevLink') ?></p><p class="mt-1 break-all font-mono text-xs select-all"><?= esc($link) ?></p></div>
  </div>
<?php endif ?>
