<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?><?= lang('Signup.title') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $planOptions = []; foreach ($plans as $p) { $planOptions[$p['id']] = lang('Signup.planOption', [$p['name'], (int) $p['trial_days'], money($p['price'], $p['currency'])]); } ?>
<form method="post" action="<?= site_url('registro') ?>" class="card space-y-4 rounded-2xl p-5" novalidate>
  <?= csrf_field() ?>
  <div><h2 class="text-base font-semibold"><?= lang('Signup.title') ?></h2><p class="mt-1 text-sm text-ink-soft"><?= lang('Signup.intro') ?></p></div>
  <div class="hidden" aria-hidden="true"><label><?= lang('Form.honeypot') ?> <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
  <?php if ($plans === []): ?>
    <?= view('partials/empty', ['message' => lang('Signup.noPlans')]) ?>
  <?php else: ?>
    <?= view('partials/field', ['name' => 'company_name', 'label' => lang('Signup.company'), 'required' => true, 'placeholder' => lang('Platform.companyNamePh'), 'attrs' => ['maxlength' => 150, 'autocomplete' => 'organization']]) ?>
    <?= view('partials/field', ['name' => 'owner_name', 'label' => lang('Signup.name'), 'required' => true, 'placeholder' => lang('Core.personNamePh'), 'attrs' => ['maxlength' => 120, 'autocomplete' => 'name']]) ?>
    <?= view('partials/field', ['name' => 'email', 'label' => lang('Auth.email'), 'type' => 'email', 'required' => true, 'placeholder' => lang('Auth.emailPlaceholder'), 'hint' => lang('Signup.emailHint'), 'attrs' => ['maxlength' => 190, 'autocomplete' => 'email']]) ?>
    <?= view('partials/field', ['name' => 'phone', 'label' => lang('Core.phone'), 'placeholder' => '300 123 4567', 'attrs' => ['maxlength' => 40, 'autocomplete' => 'tel']]) ?>
    <?= view('partials/field', ['name' => 'plan_id', 'label' => lang('Core.plan'), 'type' => 'select', 'options' => $planOptions, 'required' => true]) ?>
    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="accept_terms" value="1" class="mt-1"<?= old('accept_terms') ? ' checked' : '' ?>>
      <span><?= lang('Signup.terms') ?> <a class="text-accent underline" href="<?= esc($termsUrl, 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= lang('Signup.termsLink') ?></a></span></label>
    <?= field_error('accept_terms') ?>
    <button type="submit" class="btn btn-primary btn-lg w-full" data-busy="<?= esc(lang('Core.saving'), 'attr') ?>"><?= lang('Signup.submit') ?></button>
  <?php endif ?>
  <p class="text-center text-sm"><a class="text-accent" href="<?= site_url('ingresar') ?>"><?= lang('Signup.haveAccount') ?></a></p>
</form>
<?= $this->endSection() ?>
