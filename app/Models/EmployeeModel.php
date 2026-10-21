<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    public const STATUS_ACTIVE     = 'active';
    public const STATUS_RESIGNED   = 'resigned';
    public const STATUS_TERMINATED = 'terminated';

    public const STATUSES = [
        self::STATUS_ACTIVE     => 'Aktif',
        self::STATUS_RESIGNED   => 'Resign',
        self::STATUS_TERMINATED => 'Diberhentikan',
    ];

    public const EMPLOYMENT_TYPES = [
        'permanent' => 'Tetap',
        'contract'  => 'Kontrak',
        'probation' => 'Masa Percobaan',
    ];

    public const GENDERS = ['L' => 'Laki-laki', 'P' => 'Perempuan'];

    public const BANKS = ['BCA', 'Mandiri', 'BNI', 'BRI', 'BSI', 'CIMB Niaga', 'Permata'];

    protected $table          = 'employees';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'nip', 'full_name', 'nik', 'npwp', 'gender', 'birth_place', 'birth_date', 'address', 'phone',
        'department_id', 'position_id', 'employment_type', 'status', 'join_date', 'resign_date',
        'ptkp_status', 'basic_salary', 'bank_name', 'bank_account_no', 'bank_account_name',
        'bpjs_kes_enrolled', 'bpjs_tk_enrolled', 'photo',
    ];

    protected array $casts = [
        'department_id'     => 'int',
        'position_id'       => 'int',
        'basic_salary'      => 'int',
        'bpjs_kes_enrolled' => 'bool',
        'bpjs_tk_enrolled'  => 'bool',
    ];

    protected $validationRules = [
        'id'                => 'permit_empty|is_natural_no_zero',
        'nip'               => 'required|max_length[20]|is_unique[employees.nip,id,{id}]',
        'full_name'         => 'required|min_length[3]|max_length[150]',
        'nik'               => 'required|exact_length[16]|numeric|is_unique[employees.nik,id,{id}]',
        'npwp'              => 'permit_empty|min_length[15]|max_length[16]|numeric',
        'gender'            => 'required|in_list[L,P]',
        'birth_place'       => 'permit_empty|max_length[100]',
        'birth_date'        => 'permit_empty|valid_date[Y-m-d]',
        'phone'             => 'permit_empty|max_length[20]|regex_match[/^[0-9+\- ]+$/]',
        'department_id'     => 'required|is_natural_no_zero|is_not_unique[departments.id]',
        'position_id'       => 'required|is_natural_no_zero|is_not_unique[positions.id]',
        'employment_type'   => 'required|in_list[permanent,contract,probation]',
        'status'            => 'required|in_list[active,resigned,terminated]',
        'join_date'         => 'required|valid_date[Y-m-d]',
        'resign_date'       => 'permit_empty|valid_date[Y-m-d]',
        'ptkp_status'       => 'required|in_list[TK/0,TK/1,TK/2,TK/3,K/0,K/1,K/2,K/3]',
        'basic_salary'      => 'required|is_natural',
        'bank_name'         => 'permit_empty|max_length[50]',
        'bank_account_no'   => 'permit_empty|numeric|max_length[30]',
        'bank_account_name' => 'permit_empty|max_length[150]',
    ];

    protected $validationMessages = [
        'nip' => ['is_unique' => 'NIP sudah terdaftar.'],
        'nik' => [
            'is_unique'    => 'NIK sudah terdaftar pada karyawan lain.',
            'exact_length' => 'NIK harus 16 digit.',
        ],
    ];

    /**
     * Query builder karyawan lengkap dengan nama departemen & jabatan.
     */
    public function withRelations(): static
    {
        return $this->select('employees.*, departments.name AS department_name, departments.code AS department_code')
            ->select('positions.name AS position_name, positions.grade, positions.overtime_eligible')
            ->join('departments', 'departments.id = employees.department_id')
            ->join('positions', 'positions.id = employees.position_id');
    }

    public function active(): static
    {
        return $this->where('employees.status', self::STATUS_ACTIVE);
    }

    /**
     * Karyawan yang bekerja minimal satu hari di rentang tanggal tersebut
     * (termasuk yang masuk/keluar di tengah periode).
     */
    public function employedBetween(string $start, string $end): static
    {
        return $this->where('employees.join_date <=', $end)
            ->groupStart()
            ->where('employees.resign_date', null)
            ->orWhere('employees.resign_date >=', $start)
            ->groupEnd();
    }

    public function nextNip(): string
    {
        $last = $this->withDeleted()->selectMax('nip')->first();
        $next = $last['nip'] === null ? 1 : ((int) substr($last['nip'], 4)) + 1;

        return sprintf('EMP-%04d', $next);
    }
}
