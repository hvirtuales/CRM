<?php

namespace Components\Crm\Models;

use CodeIgniter\Model;

class OpportunityModel extends Model
{
    protected $table          = 'cmp_crm_opportunities';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'company_id', 'project_id', 'contact_id', 'stage_id', 'title', 'amount', 'currency', 'probability',
        'owner_user_id', 'source', 'expected_close_date', 'next_action', 'next_action_at', 'lost_reason', 'closed_at',
    ];

    public function forCompany(int $companyId): self
    {
        return $this->where($this->table . '.company_id', $companyId);
    }
}
