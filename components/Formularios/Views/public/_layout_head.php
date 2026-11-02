<?php
/**
 * Cabecera de la vista pública AUTOSUFICIENTE: estilos en línea, tipografía del sistema, variables
 * propias (--hf-*) y modo oscuro. No depende del CSS ni de los íconos del núcleo.
 *
 * @var string|null $brand color de marca del distribuidor (se valida y se emite crudo)
 * @var string      $title
 */
$brandHex = is_string($brand ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $brand) ? $brand : '#D80023';
?>
<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= esc($title) ?></title>
<style>
  :root{--hf-brand:<?= $brandHex ?>;--hf-bg:#f7f7f8;--hf-surface:#fff;--hf-fg:#18181b;--hf-muted:#6b6b76;--hf-line:#e4e4e7;--hf-danger:#b91c1c;--hf-ok:#15803d;--hf-radius:6px;
    --hf-font:"Segoe UI Variable","Segoe UI",system-ui,-apple-system,Roboto,"Helvetica Neue",Arial,sans-serif}
  @media (prefers-color-scheme:dark){:root{--hf-bg:#0f0f11;--hf-surface:#18181b;--hf-fg:#f4f4f5;--hf-muted:#a1a1aa;--hf-line:#2e2e33;--hf-danger:#f87171;--hf-ok:#4ade80}}
  *{box-sizing:border-box}
  body{margin:0;background:var(--hf-bg);color:var(--hf-fg);font:14px/1.5 var(--hf-font)}
  .hf-wrap{max-width:640px;margin:0 auto;padding:24px 16px}
  .hf-card{background:var(--hf-surface);border:1px solid var(--hf-line);border-radius:8px;padding:20px}
  .hf-title{margin:0 0 4px;font-size:1.25rem;font-weight:600}
  .hf-muted{color:var(--hf-muted);font-size:.875rem;margin:0 0 16px}
  .hf-field{margin:0 0 14px}
  .hf-label{display:block;margin:0 0 6px;font-weight:500;color:var(--hf-muted)}
  .hf-req{color:var(--hf-danger)}
  .hf-input,.hf-select,.hf-textarea{width:100%;height:36px;padding:0 12px;border:1px solid var(--hf-line);border-radius:var(--hf-radius);background:var(--hf-surface);color:var(--hf-fg);font:inherit}
  .hf-textarea{height:auto;min-height:96px;padding:8px 12px}
  .hf-input[aria-invalid=true],.hf-select[aria-invalid=true],.hf-textarea[aria-invalid=true]{border-color:var(--hf-danger)}
  .hf-error{color:var(--hf-danger);font-size:12px;margin:4px 0 0}
  .hf-check{display:flex;gap:8px;align-items:flex-start}
  .hf-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;height:40px;border:0;border-radius:var(--hf-radius);background:var(--hf-brand);color:#fff;font:inherit;font-weight:600;cursor:pointer}
  .hf-btn:hover{filter:brightness(1.1)}
  .hf-alert{display:flex;gap:8px;align-items:flex-start;border-radius:var(--hf-radius);padding:12px;margin:0 0 16px;border:1px solid var(--hf-line)}
  .hf-alert.err{border-color:var(--hf-danger);color:var(--hf-danger)}
  .hf-alert.ok{border-color:var(--hf-ok)}
  .hf-ico{width:20px;height:20px;flex:none}
  .hf-hp{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}
  .hf-foot{margin-top:16px;text-align:center;color:var(--hf-muted);font-size:12px}
  *:focus-visible{outline:2px solid var(--hf-brand);outline-offset:2px}
</style>
</head>
