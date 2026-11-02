<?php

namespace Components\Crm\Models;

use CodeIgniter\Model;

class StageModel extends Model
{
    protected $table         = 'cmp_crm_stages';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['company_id', 'name', 'kind', 'probability', 'position'];

    public function forCompany(int $companyId): self
    {
        return $this->where($this->table . '.company_id', $companyId);
    }
}
