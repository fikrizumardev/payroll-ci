<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table         = 'audit_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = [
        'user_id', 'username', 'action', 'entity', 'entity_id', 'description',
        'before', 'after', 'ip_address', 'user_agent', 'created_at',
    ];

    protected array $casts = [
        'user_id' => '?int',
        'before'  => '?json-array',
        'after'   => '?json-array',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 10): array
    {
        return $this->select('audit_logs.*, users.name AS user_name')
            ->join('users', 'users.id = audit_logs.user_id', 'left')
            ->orderBy('audit_logs.id', 'DESC')
            ->findAll($limit);
    }
}
