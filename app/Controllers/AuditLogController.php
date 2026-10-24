<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\UserModel;

/**
 * Jejak audit (read-only): filter pengguna, aksi/modul, rentang tanggal, dan kata kunci,
 * dengan rincian data sebelum/sesudah. Akses: Super Admin & Finance.
 */
class AuditLogController extends BaseController
{
    private const PER_PAGE = 30;

    /** Entitas yang punya halaman detail: entity → [named route, label]. */
    private const ENTITY_ROUTES = [
        'employee'       => ['employees.show', 'Karyawan'],
        'payroll_period' => ['payroll.show', 'Periode payroll'],
        'loan'           => ['loans.show', 'Pinjaman'],
        'payroll'        => ['payslips.show', 'Slip gaji'],
    ];

    public function index(): string
    {
        $filters = $this->filters();
        $model   = $this->filteredQuery($filters);
        $logs    = $model->orderBy('audit_logs.created_at', 'DESC')->orderBy('audit_logs.id', 'DESC')->paginate(self::PER_PAGE, 'default');

        return view('audit_logs/index', [
            'title'       => 'Audit Log',
            'breadcrumbs' => [['label' => 'Sistem'], ['label' => 'Audit Log']],
            'logs'        => $logs,
            'pager'       => $model->pager,
            'filters'     => $filters,
            'users'       => $this->userOptions(),
            'actions'     => $this->actionOptions(),
            'entities'    => self::ENTITY_ROUTES,
        ]);
    }

    /**
     * @return array{user_id: int, action: string, from: string, to: string, q: string}
     */
    private function filters(): array
    {
        $date   = static fn (mixed $value): string => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false ? $value : '';
        $action = (string) $this->request->getGet('action');
        $from   = $date($this->request->getGet('from'));
        $to     = $date($this->request->getGet('to'));

        if ($from !== '' && $to !== '' && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'user_id' => max(0, (int) $this->request->getGet('user_id')),
            'action'  => preg_match('/^[a-z_]+(\.[a-z_]+)*(\.\*)?$/', $action) ? $action : '',
            'from'    => $from,
            'to'      => $to,
            'q'       => mb_substr(trim((string) $this->request->getGet('q')), 0, 100),
        ];
    }

    /**
     * @param array{user_id: int, action: string, from: string, to: string, q: string} $filters
     */
    private function filteredQuery(array $filters): AuditLogModel
    {
        $model = (new AuditLogModel())
            ->select('audit_logs.*, users.name AS user_name, users.role AS user_role')
            ->join('users', 'users.id = audit_logs.user_id', 'left');

        if ($filters['user_id'] > 0) {
            $model->where('audit_logs.user_id', $filters['user_id']);
        }

        if ($filters['action'] !== '') {
            str_ends_with($filters['action'], '.*')
                ? $model->like('audit_logs.action', substr($filters['action'], 0, -1), 'after')
                : $model->where('audit_logs.action', $filters['action']);
        }

        if ($filters['from'] !== '') {
            $model->where('audit_logs.created_at >=', $filters['from'] . ' 00:00:00');
        }

        if ($filters['to'] !== '') {
            $model->where('audit_logs.created_at <', date('Y-m-d', strtotime($filters['to'] . ' +1 day')) . ' 00:00:00');
        }

        if ($filters['q'] !== '') {
            $model->groupStart()
                ->like('audit_logs.description', $filters['q'])
                ->orLike('audit_logs.username', $filters['q'])
                ->orLike('audit_logs.ip_address', $filters['q'])
                ->orLike('audit_logs.entity_id', $filters['q'], 'none')
                ->groupEnd();
        }

        return $model;
    }

    /**
     * @return array<int, string>
     */
    private function userOptions(): array
    {
        $options = [];

        foreach ((new UserModel())->select('id, username, name')->orderBy('name')->findAll() as $user) {
            $options[$user['id']] = $user['name'] . ' (@' . $user['username'] . ')';
        }

        return $options;
    }

    /**
     * Aksi yang pernah tercatat, dikelompokkan per modul: label modul → [nilai filter → label].
     *
     * @return array<string, array<string, string>>
     */
    private function actionOptions(): array
    {
        $recorded = array_column(db_connect()->table('audit_logs')->distinct()->select('action')->get()->getResultArray(), 'action');
        $groups   = [];

        foreach ($recorded as $action) {
            $module = AuditLogModel::module($action);
            $label  = AuditLogModel::MODULES[$module][0] ?? ucfirst($module);

            $groups[$label] ??= [$module . '.*' => 'Semua aktivitas ' . mb_strtolower($label)];
            $groups[$label][$action] = AuditLogModel::label($action);
        }

        $order = array_flip(array_column(AuditLogModel::MODULES, 0));
        uksort($groups, static fn (string $a, string $b): int => ($order[$a] ?? 99) <=> ($order[$b] ?? 99) ?: strcmp($a, $b));

        foreach ($groups as &$options) {
            $all     = array_slice($options, 0, 1, true);
            $rest    = array_slice($options, 1, null, true);
            asort($rest);
            $options = $all + $rest;
        }
        unset($options);

        return $groups;
    }
}
