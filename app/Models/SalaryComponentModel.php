<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class SalaryComponentModel extends Model
{
    public const TYPE_EARNING              = 'earning';
    public const TYPE_DEDUCTION            = 'deduction';
    public const TYPE_COMPANY_CONTRIBUTION = 'company_contribution';

    public const TYPES = [
        self::TYPE_EARNING              => 'Pendapatan',
        self::TYPE_DEDUCTION            => 'Potongan',
        self::TYPE_COMPANY_CONTRIBUTION => 'Kontribusi Perusahaan',
    ];

    public const CALC_TYPES = [
        'fixed'   => 'Tetap per bulan',
        'daily'   => 'Per hari hadir',
        'formula' => 'Dihitung sistem',
        'manual'  => 'Input manual',
    ];

    protected $table         = 'salary_components';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'code', 'name', 'type', 'calc_type', 'default_amount', 'taxable',
        'is_bpjs_base', 'is_system', 'is_active', 'sort_order', 'description',
    ];

    protected array $casts = [
        'id'             => 'int',
        'default_amount' => 'int',
        'taxable'        => 'bool',
        'is_bpjs_base'   => 'bool',
        'is_system'      => 'bool',
        'is_active'      => 'bool',
        'sort_order'     => 'int',
    ];

    protected $validationRules = [
        'id'             => 'permit_empty|is_natural_no_zero',
        'code'           => 'required|max_length[30]|regex_match[/^[A-Z0-9_]+$/]|is_unique[salary_components.code,id,{id}]',
        'name'           => 'required|max_length[100]',
        'type'           => 'required|in_list[earning,deduction,company_contribution]',
        'calc_type'      => 'required|in_list[fixed,daily,formula,manual]',
        'default_amount' => 'permit_empty|is_natural',
        'sort_order'     => 'permit_empty|is_natural',
    ];

    public function findByCode(string $code): ?array
    {
        return $this->where('code', $code)->first();
    }

    /**
     * @return array<string, int> code => id
     */
    public function idsByCode(): array
    {
        return array_map('intval', array_column($this->findAll(), 'id', 'code'));
    }
}
