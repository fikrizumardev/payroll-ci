<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PtkpRateModel;
use App\Models\SettingModel;
use App\Models\TerRateModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Pengaturan aplikasi. Lihat: HR & Finance · Ubah: Super Admin.
 */
class SettingController extends BaseController
{
    /** Tab → grup setting di tabel `settings`. */
    private const TABS = [
        'company'    => ['label' => 'Perusahaan', 'icon' => 'building', 'groups' => ['company']],
        'bpjs'       => ['label' => 'BPJS', 'icon' => 'shield-check', 'groups' => ['bpjs']],
        'tax'        => ['label' => 'Pajak', 'icon' => 'landmark', 'groups' => ['tax']],
        'attendance' => ['label' => 'Payroll & Kehadiran', 'icon' => 'clock-4', 'groups' => ['payroll', 'attendance']],
    ];

    public function index(): string
    {
        $settings = [];

        foreach ((new SettingModel())->orderBy('sort_order')->findAll() as $row) {
            $settings[$row['group']][] = $row;
        }

        $ter = [];

        foreach ((new TerRateModel())->orderBy('category')->orderBy('min_income')->findAll() as $row) {
            $ter[$row['category']][] = $row;
        }

        $tab = (string) $this->request->getGet('tab');

        return view('settings/index', [
            'title'       => 'Pengaturan',
            'breadcrumbs' => [['label' => 'Sistem'], ['label' => 'Pengaturan']],
            'tabs'        => self::TABS,
            'activeTab'   => array_key_exists($tab, self::TABS) ? $tab : 'company',
            'settings'    => $settings,
            'ptkp'        => (new PtkpRateModel())->orderBy('amount')->orderBy('status')->findAll(),
            'ter'         => $ter,
            'canEdit'     => $this->auth->hasRole('superadmin'),
        ]);
    }

    public function update(string $tab): RedirectResponse
    {
        if (! array_key_exists($tab, self::TABS)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $back   = url_to('settings.index') . '?tab=' . $tab;
        $errors = [];
        $before = [];
        $after  = [];

        $rows = (new SettingModel())->whereIn('group', self::TABS[$tab]['groups'])->findAll();
        $post = (array) $this->request->getPost('settings');

        foreach ($rows as $row) {
            if (! array_key_exists($row['key'], $post)) {
                continue;
            }

            $field = 'settings.' . $row['key'];
            [$value, $error] = $this->normalize($row, $post[$row['key']]);

            if ($error !== null) {
                $errors[$field] = $error;

                continue;
            }

            if ((string) $row['value'] !== (string) $value) {
                $before[$row['key']] = $row['value'];
                $after[$row['key']]  = $value;
            }
        }

        if ($tab === 'tax') {
            $this->collectPtkpChanges($errors, $before, $after);
            $this->collectTerChanges($errors, $before, $after);
        }

        if ($errors !== []) {
            return redirect()->to($back)->withInput()->with('errors', $errors)
                ->with('error', 'Pengaturan belum disimpan. Periksa kembali isian yang ditandai.');
        }

        if ($after === []) {
            return redirect()->to($back)->with('info', 'Tidak ada perubahan yang perlu disimpan.');
        }

        $db = db_connect();
        $db->transException(true)->transStart();

        $settings = service('appSettings');

        foreach ($after as $key => $value) {
            if (str_starts_with($key, 'ptkp.')) {
                $db->table('ptkp_rates')->where('status', substr($key, 5))->update(['amount' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
            } elseif (str_starts_with($key, 'ter.')) {
                $db->table('ter_rates')->where('id', (int) substr($key, 4))->update(['rate' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
            } else {
                $settings->set($key, $value);
            }
        }

        $this->audit('setting.update', 'setting', $tab, 'Memperbarui pengaturan ' . self::TABS[$tab]['label'] . ' (' . count($after) . ' nilai)', $before, $after);

        $db->transComplete();

        return redirect()->to($back)->with('success', 'Pengaturan ' . self::TABS[$tab]['label'] . ' berhasil disimpan. Perubahan tarif berlaku untuk payroll yang di-generate berikutnya.');
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{0: string|null, 1: string|null} [nilai ternormalisasi, pesan error]
     */
    private function normalize(array $row, mixed $raw): array
    {
        $raw   = is_string($raw) ? trim($raw) : '';
        $label = $row['label'];

        return match ($row['type']) {
            'int' => preg_match('/^\d{1,13}$/', $digits = preg_replace('/[.\s]/', '', $raw)) === 1
                ? [$digits, null]
                : [null, "{$label} harus berupa angka bulat."],
            'decimal' => is_numeric($decimal = str_replace(',', '.', $raw)) && (float) $decimal >= 0 && (float) $decimal <= 100
                ? [number_format((float) $decimal, 3, '.', ''), null]
                : [null, "{$label} harus berupa persentase 0–100."],
            'time' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $raw) === 1
                ? [$raw, null]
                : [null, "{$label} harus berformat JJ:MM."],
            'text' => mb_strlen($raw) <= 500 ? [$raw === '' ? null : $raw, null] : [null, "{$label} maksimal 500 karakter."],
            default => mb_strlen($raw) <= 255 ? [$raw === '' ? null : $raw, null] : [null, "{$label} maksimal 255 karakter."],
        };
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $before
     * @param array<string, mixed>  $after
     */
    private function collectPtkpChanges(array &$errors, array &$before, array &$after): void
    {
        $post = (array) $this->request->getPost('ptkp');

        foreach ((new PtkpRateModel())->findAll() as $row) {
            if (! isset($post[$row['status']])) {
                continue;
            }

            $amount = preg_replace('/\D/', '', (string) $post[$row['status']]);

            if ($amount === '' || (int) $amount <= 0) {
                $errors['ptkp.' . $row['status']] = "PTKP {$row['status']} harus lebih dari nol.";

                continue;
            }

            if ((int) $amount !== (int) $row['amount']) {
                $before['ptkp.' . $row['status']] = (int) $row['amount'];
                $after['ptkp.' . $row['status']]  = (int) $amount;
            }
        }
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $before
     * @param array<string, mixed>  $after
     */
    private function collectTerChanges(array &$errors, array &$before, array &$after): void
    {
        $post = (array) $this->request->getPost('ter');

        if ($post === []) {
            return;
        }

        foreach ((new TerRateModel())->whereIn('id', array_map('intval', array_keys($post)))->findAll() as $row) {
            $rate = str_replace(',', '.', trim((string) $post[$row['id']]));

            if (! is_numeric($rate) || (float) $rate < 0 || (float) $rate > 100) {
                $errors['ter.' . $row['id']] = 'Tarif TER kategori ' . $row['category'] . ' harus 0–100%.';

                continue;
            }

            if (abs((float) $rate - (float) $row['rate']) > 0.0001) {
                $before['ter.' . $row['id']] = (float) $row['rate'];
                $after['ter.' . $row['id']]  = number_format((float) $rate, 3, '.', '');
            }
        }
    }
}
