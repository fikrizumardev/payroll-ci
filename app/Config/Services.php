<?php

namespace Config;

use App\Models\UserModel;
use App\Models\UserTokenModel;
use App\Services\AuditLogger;
use App\Services\Auth\AuthService;
use App\Services\SettingService;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /**
     * Pengaturan aplikasi dari tabel `settings`.
     */
    public static function appSettings(bool $getShared = true): SettingService
    {
        if ($getShared) {
            return static::getSharedInstance('appSettings');
        }

        return new SettingService();
    }

    /**
     * Autentikasi username + password berbasis session.
     */
    public static function auth(bool $getShared = true): AuthService
    {
        if ($getShared) {
            return static::getSharedInstance('auth');
        }

        return new AuthService(new UserModel(), new UserTokenModel(), service('auditLogger'), service('session'));
    }

    /**
     * Pencatat jejak aktivitas (tabel audit_logs).
     */
    public static function auditLogger(bool $getShared = true): AuditLogger
    {
        if ($getShared) {
            return static::getSharedInstance('auditLogger');
        }

        return new AuditLogger();
    }
}
