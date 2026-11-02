<?php

namespace Components\Crm\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use Config\Services;

/**
 * API v1 de contactos. El negocio sale del token (contexto), nunca de un parámetro.
 * Campos mínimos en las respuestas (minimización de datos personales).
 */
class Contacts extends ResourceController
{
    protected $format = 'json';

    private const FIELDS = ['id', 'kind', 'first_name', 'last_name', 'organization', 'email', 'phone', 'city', 'source', 'consent_marketing', 'status', 'created_at'];

    public function index()
    {
        $cid     = (int) Services::context()->companyId();
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 20)));
        $model   = service('crmContacts')->search($cid, ['q' => (string) $this->request->getGet('q'), 'status' => 'active']);
        $rows    = $model->paginate($perPage);

        return $this->respond([
            'data' => array_map(fn ($r) => array_intersect_key($r, array_flip(self::FIELDS)), $rows),
            'meta' => ['page' => $model->pager->getCurrentPage(), 'perPage' => $perPage, 'total' => $model->pager->getTotal()],
        ]);
    }

    public function show($id = null)
    {
        $row = service('crmContacts')->find((int) Services::context()->companyId(), (int) $id);

        // 404 también si existe en otro negocio: no se confirma su existencia.
        return $row ? $this->respond(['data' => array_intersect_key($row, array_flip(self::FIELDS))]) : $this->failNotFound(lang('Crm.notFound'));
    }

    public function create()
    {
        $data = (array) ($this->request->getJSON(true) ?? []);
        unset($data['company_id'], $data['owner_user_id']);
        $r = service('crmContacts')->create((int) Services::context()->companyId(), $data, Services::context()->userId(), 'api');

        return $r->ok
            ? $this->respondCreated(['data' => $r->data])
            : $this->fail(['error' => $r->message, 'fields' => $r->errors], $r->status);
    }
}
