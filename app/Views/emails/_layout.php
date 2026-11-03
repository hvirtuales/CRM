<?php
/**
 * Plantilla base de correo: HTML simple con estilos en línea (clientes de correo), sin recursos
 * externos ni rastreadores. Todo dato se escapa. @var string $subject @var string $content HTML ya escapado
 * @var string|null $sender nombre del remitente comercial (negocio)
 */
?>
<!DOCTYPE html>
<html lang="<?= esc(service('language')->getLocale()) ?>"><head><meta charset="utf-8"><title><?= esc($subject) ?></title></head>
<body style="margin:0;background:#f7f7f8;font-family:'Segoe UI',system-ui,Arial,sans-serif;color:#18181b;line-height:1.5">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7f8;padding:24px 12px"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e4e4e7;border-radius:8px">
<tr><td style="height:4px;background:#D80023;border-radius:8px 8px 0 0"></td></tr>
<tr><td style="padding:24px">
<?= $content ?>
</td></tr>
<tr><td style="padding:12px 24px 20px;color:#6b6b76;font-size:12px;border-top:1px solid #e4e4e7">
<?= esc($sender ?? lang('Core.appName')) ?> · <?= esc(lang('Notify.footer')) ?>
</td></tr></table>
</td></tr></table>
</body></html>
