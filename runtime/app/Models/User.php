<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable([
    'name',
    'username',
    'cpf',
    'rg',
    'email',
    'phone',
    'password',
    'is_active',
    'must_change_password',
    'last_login_at',
    'last_login_ip',
    'funcionario_id',
    'tipo_usuario',
    'notes',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(RhFuncionario::class, 'funcionario_id');
    }

    public function isServidor(): bool
    {
        return $this->tipo_usuario === 'servidor';
    }

    public function getCpfFormatadoAttribute(): string
    {
        $c = $this->cpf ?? '';
        if (strlen($c) !== 11) {
            return $c;
        }
        return substr($c, 0, 3).'.'.substr($c, 3, 3).'.'.substr($c, 6, 3).'-'.substr($c, 9, 2);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps()->withPivot('assigned_by');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(UserScope::class);
    }

    public function scopeEntries(): HasMany
    {
        return $this->scopes();
    }

    public function isSuperAdmin(): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains(fn (Role $role) => $role->code === 'super_admin');
    }

    public function permissionCodes(): Collection
    {
        $this->loadMissing('roles.permissions');

        if ($this->isSuperAdmin()) {
            return collect(['*']);
        }

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->pluck('code')
            ->unique()
            ->values();
    }

    public function hasRole(string $roleCode): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains(fn (Role $role) => $role->code === $roleCode);
    }

    public function hasPermission(string $permissionCode): bool
    {
        $codes = $this->permissionCodes();

        return $codes->contains('*') || $codes->contains($permissionCode);
    }

    public function hasScope(string $scopeType, string $scopeKey): bool
    {
        return $this->scopeEntries()
            ->where('scope_type', $scopeType)
            ->where('scope_key', $scopeKey)
            ->exists();
    }

    public function scopeKeys(string $scopeType): Collection
    {
        return $this->scopeEntries()
            ->where('scope_type', $scopeType)
            ->pluck('scope_key')
            ->values();
    }
}
