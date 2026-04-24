<?php

namespace App\Support;

class AccessCredentialPolicy
{
    public static function normalizeCpf(?string $cpf): string
    {
        return preg_replace('/\D+/', '', (string) $cpf) ?? '';
    }

    public static function firstAccessPassword(string $cpf): string
    {
        return self::defaultPasswordPrefix().self::normalizeCpf($cpf);
    }

    public static function defaultPasswordPrefix(): string
    {
        return (string) config('grom_access.default_password_prefix', 'DDM');
    }

    public static function superManagerCpf(): string
    {
        return self::normalizeCpf((string) config('grom_access.super_manager_cpf', '12347029835'));
    }
}