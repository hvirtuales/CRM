<?php

namespace Components\Crm\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use Config\Services;

class Opportunities extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $cid  = (int) Services::context()->companyId();
        $rows = service('crmOpportunities')->board($cid, ['project' => $this->request->getGet('project')]);
        $keep = ['id', 'title', 'amount', 'currency', 'probability', 'stage_id', 'project_id', 'contact_id', 'owner_user_id', 'expected_close_date', 'next_action_at', 'created_at'];

        return $this->respond(['data' => array_map(static fn ($r) => array_intersect_key($r, array_flip($keep)), array_slice($rows, 0, 500))]);
    }

    public function create()
    {
        $data = (array) ($this->request->getJSON(true) ?? []);
        unset($data['company_id']);
        $r = service('crmOpportunities')->create((int) Services::context()->companyId(), $data, (int) Services::context()->userId(), 'api');

        return $r->ok
            ? $this->respondCreated(['data' => $r->data])
            : $this->fail(['error' => $r->message, 'fields' => $r->errors], $r->status);
    }
}
