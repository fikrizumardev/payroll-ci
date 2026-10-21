<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class HolidayModel extends Model
{
    public const TYPES = [
        'national'         => 'Libur Nasional',
        'collective_leave' => 'Cuti Bersama',
        'company'          => 'Libur Perusahaan',
    ];

    protected $table         = 'holidays';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['date', 'name', 'type'];
    protected $useTimestamps = true;

    protected array $casts = [
        'id' => 'int',
    ];

    protected $validationRules = [
        'id'   => 'permit_empty|is_natural_no_zero',
        'date' => ['label' => 'Tanggal', 'rules' => 'required|valid_date[Y-m-d]|is_unique[holidays.date,id,{id}]'],
        'name' => ['label' => 'Keterangan', 'rules' => 'required|max_length[150]'],
        'type' => ['label' => 'Jenis libur', 'rules' => 'required|in_list[national,collective_leave,company]'],
    ];

    /**
     * @return list<string> daftar tanggal (Y-m-d) libur di rentang tersebut
     */
    public function datesBetween(string $start, string $end): array
    {
        $rows = $this->select('date')
            ->where('date >=', $start)
            ->where('date <=', $end)
            ->orderBy('date')
            ->findAll();

        return array_column($rows, 'date');
    }
}
