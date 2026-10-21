<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

/**
 * Pengaturan akun milik user yang sedang login.
 */
class AccountController extends BaseController
{
    public function password(): string
    {
        $user = $this->currentUser();

        return view('account/password', [
            'title'       => 'Ganti Password',
            'breadcrumbs' => [['label' => 'Akun'], ['label' => 'Ganti Password']],
            'mustChange'  => (bool) $user['must_change_password'],
            'lastChanged' => $user['password_changed_at'],
        ]);
    }

    public function updatePassword(): RedirectResponse
    {
        $errors = $this->auth->changePassword(
            (string) $this->request->getPost('current_password'),
            (string) $this->request->getPost('new_password'),
            (string) $this->request->getPost('new_password_confirmation'),
        );

        if ($errors !== []) {
            return redirect()->route('account.password')->with('errors', $errors);
        }

        return redirect()->route('dashboard')->with('success', 'Password berhasil diganti.');
    }
}
