<?php /** Correo de invitación (HTML simple, sin recursos externos). @var string $name, $organization, $link @var int $hours */ ?>
<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale()) ?>"><body style="font-family:Segoe UI,system-ui,Arial,sans-serif;color:#18181b;line-height:1.5">
<p><?= esc(lang('Core.invitationHello', [$name])) ?></p>
<p><?= esc(lang('Core.invitationBody', [$organization])) ?></p>
<p><a href="<?= esc($link, 'attr') ?>" style="display:inline-block;background:#D80023;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none"><?= esc(lang('Core.invitationAccept')) ?></a></p>
<p style="color:#6b6b76;font-size:13px"><?= esc(lang('Core.invitationExpires', [$hours])) ?></p>
</body></html>
