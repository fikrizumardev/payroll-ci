<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PtkpRateModel extends Model
{
    public const STATUSES = ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'];

    protected $table            = 'ptkp_rates';
    protected $primaryKey       = 'status';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $allowedFields    = ['status', 'description', 'amount', 'ter_category'];
    protected $useTimestamps    = true;

    protected array $casts = [
        'amount' => 'int',
    ];

    protected $validationRules = [
        'status'       => 'required|in_list[TK/0,TK/1,TK/2,TK/3,K/0,K/1,K/2,K/3]',
        'description'  => 'required|max_length[100]',
        'amount'       => 'required|is_natural',
        'ter_category' => 'required|in_list[A,B,C]',
    ];

    /**
     * @return array<string, string> status => label, untuk dropdown
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->orderBy('amount')->findAll() as $row) {
            $options[$row['status']] = $row['status'] . ' — ' . $row['description'];
        }

        return $options;
    }
}
