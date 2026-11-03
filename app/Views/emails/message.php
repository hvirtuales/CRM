<?php
/**
 * Mensaje transaccional genérico (todas las líneas se escapan).
 *
 * @var string      $greeting
 * @var list<string> $lines
 * @var string|null $cta_label
 * @var string|null $cta_url  (solo http/https)
 * @var string|null $note
 * @var string|null $sender
 * @var string      $subject
 */
$url = isset($cta_url) && preg_match('#^https?://#', (string) $cta_url) ? (string) $cta_url : null;
ob_start(); ?>
<p style="margin:0 0 12px;font-size:15px"><?= esc($greeting) ?></p>
<?php foreach ($lines ?? [] as $l): ?><p style="margin:0 0 12px;font-size:14px"><?= esc($l) ?></p><?php endforeach ?>
<?php if ($url && ! empty($cta_label)): ?>
<p style="margin:20px 0"><a href="<?= esc($url, 'attr') ?>" style="display:inline-block;background:#D80023;color:#ffffff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600"><?= esc($cta_label) ?></a></p>
<p style="margin:0 0 12px;font-size:12px;color:#6b6b76;word-break:break-all"><?= esc($url) ?></p>
<?php endif ?>
<?php if (! empty($note)): ?><p style="margin:16px 0 0;font-size:12px;color:#6b6b76"><?= esc($note) ?></p><?php endif ?>
<?php $content = (string) ob_get_clean();
echo view('emails/_layout', ['subject' => $subject, 'content' => $content, 'sender' => $sender ?? null]);
