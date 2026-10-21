<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Tarif Efektif Rata-rata (TER) PPh 21 bulanan — PP 58/2023.
 */
class TerRateModel extends Model
{
    public const CATEGORIES = ['A', 'B', 'C'];

    protected $table         = 'ter_rates';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['category', 'min_income', 'max_income', 'rate'];
    protected $useTimestamps = true;

    protected array $casts = [
        'id'         => 'int',
        'min_income' => 'int',
        'max_income' => '?int',
        'rate'       => 'float',
    ];

    protected $validationRules = [
        'category'   => 'required|in_list[A,B,C]',
        'min_income' => 'required|is_natural',
        'max_income' => 'permit_empty|is_natural',
        'rate'       => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
    ];

    /**
     * Tarif TER (dalam persen) untuk penghasilan bruto sebulan.
     */
    public function rateFor(string $category, int $grossIncome): float
    {
        $row = $this->where('category', $category)
            ->where('min_income <=', $grossIncome)
            ->groupStart()
            ->where('max_income >=', $grossIncome)
            ->orWhere('max_income', null)
            ->groupEnd()
            ->orderBy('min_income', 'DESC')
            ->first();

        return $row === null ? 0.0 : (float) $row['rate'];
    }
}
