<?php

namespace App\Support;

use App\Models\User;

class UserBladeExecution
{
    public const string DISABLED_MESSAGE = 'Blade markup is disabled on this server. Use Liquid, or set INSECURE_BLADE_ALLOW_PHP_EXECUTION=true.';

    public const string ADMIN_ONLY_MESSAGE = 'Blade rendering is restricted to administrators. Switch the plugin to Liquid or contact an admin.';

    /**
     * Explicit INSECURE_BLADE_ALLOW_PHP_EXECUTION wins. Otherwise, in multi-user mode Blade is
     * allowed for admins (or everyone with MULTI_USER_DANGEROUSLY_ALLOW_BLADE_TEMPLATES_FOR_NON_ADMINS);
     * outside multi-user mode it is only allowed for a single user.
     *
     * $user defaults to the authenticated user. Without any user, multi-user mode denies Blade.
     */
    public static function allowed(?User $user = null): bool
    {
        $configured = config('app.blade.allow_php_execution');

        if ($configured !== null) {
            return (bool) $configured;
        }

        if (config('app.multi_user_mode')) {
            if (config('app.dangerously_allow_blade_for_non_admins')) {
                return true;
            }

            $user ??= auth()->user();

            return $user?->isAdmin() ?? false;
        }

        return User::query()->count() < 2;
    }

    /**
     * Whether a plugin owned by $owner may compile Blade. In multi-user mode unowned plugins
     * are allowed, since only admins can leave a plugin without an owner.
     */
    public static function allowedForOwner(?User $owner): bool
    {
        if ($owner === null && config('app.multi_user_mode') && config('app.blade.allow_php_execution') === null) {
            return true;
        }

        return self::allowed($owner);
    }

    /**
     * Message explaining why allowed() returned false.
     */
    public static function deniedMessage(): string
    {
        return config('app.blade.allow_php_execution') === null && config('app.multi_user_mode')
            ? self::ADMIN_ONLY_MESSAGE
            : self::DISABLED_MESSAGE;
    }
}
