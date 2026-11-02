<?php

namespace Components\Crm\Models;

use CodeIgniter\Model;

class ProjectModel extends Model
{
    protected $table          = 'cmp_crm_projects';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = ['company_id', 'name', 'code', 'description', 'status'];

    public function forCompany(int $companyId): self
    {
        return $this->where($this->table . '.company_id', $companyId);
    }
}
