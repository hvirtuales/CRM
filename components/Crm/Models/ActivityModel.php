<?php

namespace Components\Crm\Models;

use CodeIgniter\Model;

class ActivityModel extends Model
{
    protected $table         = 'cmp_crm_activities';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['company_id', 'opportunity_id', 'contact_id', 'user_id', 'type', 'body'];

    public function forCompany(int $companyId): self
    {
        return $this->where($this->table . '.company_id', $companyId);
    }
}
