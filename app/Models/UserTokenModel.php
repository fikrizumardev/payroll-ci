<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class UserTokenModel extends Model
{
    protected $table         = 'user_tokens';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['user_id', 'selector', 'token_hash', 'expires_at', 'last_used_at', 'user_agent', 'ip_address'];

    protected array $casts = [
        'user_id' => 'int',
    ];

    public function purgeExpired(): void
    {
        $this->where('expires_at <', date('Y-m-d H:i:s'))->delete();
    }
}
