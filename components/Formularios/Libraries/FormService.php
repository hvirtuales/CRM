<?php

namespace Components\Formularios\Libraries;

use Config\Services;

/**
 * Formularios del negocio: borrador editable, versión publicada inmutable, cierre y archivo.
 * El esquema de campos se valida en servidor; nunca se interpreta código del usuario.
 */
class FormService
{
    public const TYPES    = ['text', 'email', 'phone', 'textarea', 'select', 'checkbox', 'number', 'date'];
    public const MAPPINGS = ['none', 'first_name', 'last_name', 'organization', 'email', 'phone', 'city', 'notes'];
    public const MAX_FIELDS = 30;

    private string $table = 'cmp_form_forms';

    /** @return list<array<string,mixed>> */
    public function list(int $companyId): array
    {
        return db_connect()->table($this->table)->where('company_id', $companyId)->where('deleted_at', null)
            ->orderBy('updated_at', 'DESC')->get()->getResultArray();
    }

    public function find(int $companyId, int $id): ?array
    {
        return db_connect()->table($this->table)->where('company_id', $companyId)->where('id', $id)->where('deleted_at', null)->get()->getRowArray() ?: null;
    }

    /** Búsqueda pública: solo formularios publicados de negocios activos. */
    public function findPublished(string $publicId): ?array
    {
        if (! preg_match('/^[0-9a-z]{20,32}$/', $publicId)) {
            return null;
        }
        $row = db_connect()->table($this->table . ' f')->select('f.*, c.name AS company_name, c.status AS company_status, c.tenant_id')
            ->join('companies c', 'c.id = f.company_id')
            ->where('f.public_id', $publicId)->where('f.status', 'published')->where('f.deleted_at', null)
            ->get()->getRowArray();

        if (! $row || ! $this->publiclyAvailable((int) $row['company_id'])) {
            return null;
        }

        return $row;
    }

    /**
     * La superficie pública respeta el interruptor de plataforma (componentes habilitados), el plan
     * y el estado comercial del negocio: nada de capturas anónimas en un negocio suspendido.
     */
    private function publiclyAvailable(int $companyId): bool
    {
        $components = service('components');

        return $components->isEnabled('form') && $components->isEnabled('crm')
            && (new PlanGuard())->serviceEnabled($companyId)
            && service('crmPlanGuard')->serviceEnabled($companyId)
            && service('subscriptions')->companyOperational($companyId);
    }

    public function save(int $companyId, array $data, ?int $id = null): Result
    {
        $guard = new PlanGuard();
        if (! $guard->serviceEnabled($companyId)) {
            return Result::fail(lang('Form.serviceDisabled'), [], 403);
        }
        if ($id === null) {
            $left = $guard->formsLeft($companyId);
            if ($left !== null && $left <= 0) {
                return Result::fail(lang('Form.quotaExhausted'), [], 409);
            }
        } elseif (! $this->find($companyId, $id)) {
            return Result::notFound();
        }

        $errors = [];
        $name   = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = lang('Form.validation.name');
        }
        $fields = $this->parseFields((string) ($data['fields_json'] ?? '[]'), $errors);

        $project = (string) ($data['project_id'] ?? '');
        $projectId = null;
        if ($project !== '' && $project !== '0') {
            // Proyecto validado a través del servicio PÚBLICO del CRM (no de sus tablas).
            if (! ctype_digit($project) || ! service('crmSetup')->projectOf($companyId, (int) $project)) {
                $errors['project_id'] = lang('Form.validation.project');
            } else {
                $projectId = (int) $project;
            }
        }
        $domains = $this->parseDomains((string) ($data['embed_domains'] ?? ''), $errors);
        $success = mb_substr(trim((string) ($data['success_message'] ?? '')), 0, 255);
        $consent = mb_substr(trim((string) ($data['consent_text'] ?? '')), 0, 2000);

        if ($errors) {
            return Result::fail(lang('Form.validation.fix'), $errors);
        }

        $row = [
            'name' => $name, 'description' => mb_substr(trim((string) ($data['description'] ?? '')), 0, 2000) ?: null,
            'project_id' => $projectId, 'fields' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'consent_text' => $consent ?: null, 'success_message' => $success ?: null,
            'embed_domains' => $domains ? json_encode($domains) : null,
            'create_opportunity' => ! empty($data['create_opportunity']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db = db_connect();
        if ($id === null) {
            $db->table($this->table)->insert($row + [
                'company_id' => $companyId, 'public_id' => $this->publicId(), 'status' => 'draft', 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $id = (int) $db->insertID();
            Services::audit()->log('form.created', 'form', $id, [], $companyId);
        } else {
            $db->table($this->table)->where('id', $id)->where('company_id', $companyId)->update($row);
            Services::audit()->log('form.updated', 'form', $id, [], $companyId);
        }

        return Result::success(['id' => $id], lang('Form.saved'));
    }

    /** Publica el borrador actual como nueva versión inmutable. */
    public function publish(int $companyId, int $id): Result
    {
        $form = $this->find($companyId, $id);
        if (! $form) {
            return Result::notFound();
        }
        $fields = json_decode((string) $form['fields'], true) ?: [];
        if ($fields === []) {
            return Result::fail(lang('Form.validation.noFields'));
        }
        $mapped = array_column($fields, 'maps_to');
        if (! in_array('email', $mapped, true) && ! in_array('phone', $mapped, true)) {
            return Result::fail(lang('Form.validation.needsReach'));
        }
        db_connect()->table($this->table)->where('id', $id)->where('company_id', $companyId)->update([
            'status' => 'published', 'published_fields' => $form['fields'], 'version' => (int) $form['version'] + 1,
            'published_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        Services::audit()->log('form.published', 'form', $id, ['version' => (int) $form['version'] + 1], $companyId);

        return Result::success(null, lang('Form.published'));
    }

    public function close(int $companyId, int $id): Result
    {
        if (! $this->find($companyId, $id)) {
            return Result::notFound();
        }
        db_connect()->table($this->table)->where('id', $id)->where('company_id', $companyId)->update(['status' => 'closed', 'updated_at' => date('Y-m-d H:i:s')]);
        Services::audit()->log('form.closed', 'form', $id, [], $companyId);

        return Result::success(null, lang('Form.closed'));
    }

    public function archive(int $companyId, int $id): Result
    {
        if (! $this->find($companyId, $id)) {
            return Result::notFound();
        }
        db_connect()->table($this->table)->where('id', $id)->where('company_id', $companyId)->update(['status' => 'closed', 'deleted_at' => date('Y-m-d H:i:s')]);
        Services::audit()->log('form.archived', 'form', $id, [], $companyId);

        return Result::success(null, lang('Form.archived'));
    }

    /** @return list<array<string,mixed>> */
    public function submissions(int $companyId, int $formId, int $limit = 200): array
    {
        return db_connect()->table('cmp_form_submissions')->where('company_id', $companyId)->where('form_id', $formId)
            ->orderBy('id', 'DESC')->get($limit)->getResultArray();
    }

    /**
     * Valida el JSON del constructor y devuelve campos normalizados.
     *
     * @param array<string,string> $errors
     *
     * @return list<array<string,mixed>>
     */
    public function parseFields(string $json, array &$errors): array
    {
        $raw = json_decode($json, true);
        if (! is_array($raw)) {
            $errors['fields'] = lang('Form.validation.fields');

            return [];
        }
        if (count($raw) > self::MAX_FIELDS) {
            $errors['fields'] = lang('Form.validation.tooManyFields', [self::MAX_FIELDS]);

            return [];
        }
        $out = [];
        $keys = [];
        $maps = [];
        foreach (array_values($raw) as $i => $f) {
            if (! is_array($f)) {
                $errors['fields'] = lang('Form.validation.fields');
                break;
            }
            $label = trim((string) ($f['label'] ?? ''));
            $type  = (string) ($f['type'] ?? 'text');
            $key   = strtolower(trim((string) ($f['key'] ?? '')));
            $map   = (string) ($f['maps_to'] ?? 'none');
            if ($key === '') {
                $key = 'campo_' . ($i + 1);
            }
            if ($label === '' || mb_strlen($label) > 120 || ! in_array($type, self::TYPES, true)
                || ! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) || isset($keys[$key]) || ! in_array($map, self::MAPPINGS, true)
                || ($map !== 'none' && isset($maps[$map]))) {
                $errors['fields'] = lang('Form.validation.fieldN', [$i + 1]);
                break;
            }
            $options = [];
            if ($type === 'select') {
                $opts = is_array($f['options'] ?? null) ? $f['options'] : explode(',', (string) ($f['options'] ?? ''));
                foreach ($opts as $o) {
                    $o = trim((string) $o);
                    if ($o !== '' && mb_strlen($o) <= 100) {
                        $options[] = $o;
                    }
                }
                $options = array_values(array_unique($options));
                if ($options === [] || count($options) > 50) {
                    $errors['fields'] = lang('Form.validation.options', [$i + 1]);
                    break;
                }
            }
            $keys[$key] = true;
            if ($map !== 'none') {
                $maps[$map] = true;
            }
            $out[] = [
                'key' => $key, 'label' => $label, 'type' => $type, 'required' => ! empty($f['required']),
                'options' => $options, 'maps_to' => $map,
                'placeholder' => mb_substr(trim((string) ($f['placeholder'] ?? '')), 0, 120),
            ];
        }

        return $out;
    }

    /**
     * @param array<string,string> $errors
     *
     * @return list<string> orígenes https://host
     */
    private function parseDomains(string $text, array &$errors): array
    {
        $out = [];
        foreach (preg_split('/[\s,]+/', trim($text)) ?: [] as $d) {
            if ($d === '') {
                continue;
            }
            $host = strtolower((string) preg_replace('#^https?://#', '', rtrim($d, '/')));
            if (! preg_match('/^(\*\.)?([a-z0-9-]+\.)+[a-z]{2,}$/', $host)) {
                $errors['embed_domains'] = lang('Form.validation.domains');

                return [];
            }
            $out[] = 'https://' . $host;
        }

        return array_slice(array_values(array_unique($out)), 0, 10);
    }

    private function publicId(): string
    {
        $alphabet = '0123456789abcdefghjkmnpqrstvwxyz';
        $bytes    = random_bytes(26);
        $id       = '';
        for ($i = 0; $i < 26; $i++) {
            $id .= $alphabet[ord($bytes[$i]) & 31];
        }

        return $id;
    }
}
