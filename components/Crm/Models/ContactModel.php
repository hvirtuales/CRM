<?php

namespace Components\Crm\Models;

use CodeIgniter\Model;

class ContactModel extends Model
{
    protected $table          = 'cmp_crm_contacts';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'company_id', 'kind', 'first_name', 'last_name', 'organization', 'email', 'phone', 'phone_normalized',
        'city', 'source', 'owner_user_id', 'consent_marketing', 'consent_at', 'consent_source', 'notes', 'status',
    ];

    /** Aislamiento centralizado: ninguna consulta del componente se hace sin esto. */
    public function forCompany(int $companyId): self
    {
        return $this->where($this->table . '.company_id', $companyId);
    }
}
