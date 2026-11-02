<?php
/**
 * Formulario público autosuficiente.
 *
 * @var array       $form
 * @var list<array> $fields
 * @var string      $token
 * @var array       $errors
 * @var string|null $message
 * @var bool        $done
 * @var array       $old
 * @var string|null $brand
 */
include __DIR__ . '/_icons.php';
echo view('Components\Formularios\Views\public\_layout_head', ['brand' => $brand, 'title' => $form['name']]);
$query = service('request')->getUri()->getQuery();
?>
<body>
<main class="hf-wrap">
  <div class="hf-card">
    <h1 class="hf-title"><?= esc($form['name']) ?></h1>
    <?php if ($form['description']): ?><p class="hf-muted"><?= nl2br(esc($form['description'])) ?></p><?php endif ?>

    <?php if ($done): ?>
      <div class="hf-alert ok" role="status"><?= hf_icon('check') ?><span><?= esc($message) ?></span></div>
    <?php else: ?>
      <?php if ($message): ?><div class="hf-alert err" role="alert"><?= hf_icon('alert') ?><span><?= esc($message) ?></span></div><?php endif ?>
      <form method="post" action="<?= esc(site_url('form-publico/' . $form['public_id']) . ($query ? '?' . $query : ''), 'attr') ?>" novalidate>
        <input type="hidden" name="_hv_token" value="<?= esc($token, 'attr') ?>">
        <div class="hf-hp" aria-hidden="true"><label><?= lang('Form.honeypot') ?> <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
        <?php foreach ($fields as $f): $id = 'hf-' . $f['key']; $err = $errors[$f['key']] ?? null; $val = (string) ($old[$f['key']] ?? ''); $aria = $err ? ' aria-invalid="true" aria-describedby="' . $id . '-e"' : ''; ?>
          <div class="hf-field">
            <?php if ($f['type'] === 'checkbox'): ?>
              <label class="hf-check" for="<?= $id ?>"><input id="<?= $id ?>" type="checkbox" name="f[<?= esc($f['key'], 'attr') ?>]" value="1"<?= $val === '1' ? ' checked' : '' ?><?= $aria ?>> <span><?= esc($f['label']) ?><?= $f['required'] ? ' <span class="hf-req">*</span>' : '' ?></span></label>
            <?php else: ?>
              <label class="hf-label" for="<?= $id ?>"><?= esc($f['label']) ?><?= $f['required'] ? ' <span class="hf-req">*</span>' : '' ?></label>
              <?php if ($f['type'] === 'textarea'): ?>
                <textarea class="hf-textarea" id="<?= $id ?>" name="f[<?= esc($f['key'], 'attr') ?>]" maxlength="5000" placeholder="<?= esc($f['placeholder'] ?? '', 'attr') ?>"<?= $f['required'] ? ' required' : '' ?><?= $aria ?>><?= esc($val) ?></textarea>
              <?php elseif ($f['type'] === 'select'): ?>
                <select class="hf-select" id="<?= $id ?>" name="f[<?= esc($f['key'], 'attr') ?>]"<?= $f['required'] ? ' required' : '' ?><?= $aria ?>>
                  <option value=""><?= lang('Form.choose') ?></option>
                  <?php foreach ($f['options'] as $o): ?><option value="<?= esc($o, 'attr') ?>"<?= $o === $val ? ' selected' : '' ?>><?= esc($o) ?></option><?php endforeach ?>
                </select>
              <?php else:
                  $type = ['email' => 'email', 'phone' => 'tel', 'number' => 'text', 'date' => 'date'][$f['type']] ?? 'text';
                  $auto = ['first_name' => 'given-name', 'last_name' => 'family-name', 'email' => 'email', 'phone' => 'tel', 'organization' => 'organization', 'city' => 'address-level2'][$f['maps_to']] ?? 'off'; ?>
                <input class="hf-input" id="<?= $id ?>" type="<?= $type ?>" name="f[<?= esc($f['key'], 'attr') ?>]" value="<?= esc($val, 'attr') ?>" maxlength="255" autocomplete="<?= $auto ?>" placeholder="<?= esc($f['placeholder'] ?? '', 'attr') ?>"<?= $f['type'] === 'number' ? ' inputmode="decimal"' : '' ?><?= $f['required'] ? ' required' : '' ?><?= $aria ?>>
              <?php endif ?>
            <?php endif ?>
            <?php if ($err): ?><p class="hf-error" id="<?= $id ?>-e"><?= esc($err) ?></p><?php endif ?>
          </div>
        <?php endforeach ?>
        <?php if ($form['consent_text']): ?>
          <div class="hf-field">
            <label class="hf-check" for="hf-consent"><input id="hf-consent" type="checkbox" name="consent" value="1" required<?= isset($errors['consent']) ? ' aria-invalid="true" aria-describedby="hf-consent-e"' : '' ?>> <span><?= nl2br(esc($form['consent_text'])) ?> <span class="hf-req">*</span></span></label>
            <?php if (isset($errors['consent'])): ?><p class="hf-error" id="hf-consent-e"><?= esc($errors['consent']) ?></p><?php endif ?>
          </div>
        <?php endif ?>
        <button type="submit" class="hf-btn"><?= hf_icon('send') ?><?= lang('Form.send') ?></button>
      </form>
    <?php endif ?>
  </div>
  <p class="hf-foot"><?= esc($form['company_name']) ?> · <?= lang('Form.poweredBy') ?></p>
</main>
</body>
</html>
