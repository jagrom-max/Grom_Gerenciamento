<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RhFuncionario extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'rh_funcionarios';

    protected $keyType = 'string';

    protected $fillable = [
        'legacy_id',
        'matricula',
        'name',
        'short_name',
        'email',
        'cargo_id',
        'sector',
        'phone',
        'rg',
        'cpf',
        'birth_date',
        'admission_date',
        'designation_date',
        'departure_date',
        'removal_date',
        'concorre_escala',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'legacy_id' => 'integer',
        'birth_date' => 'date',
        'admission_date' => 'date',
        'designation_date' => 'date',
        'departure_date' => 'date',
        'removal_date' => 'date',
        'concorre_escala' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(RhCargo::class, 'cargo_id');
    }

    public function afastamentos(): HasMany
    {
        return $this->hasMany(RhAfastamento::class, 'funcionario_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class, 'funcionario_id');
    }

    public function currentAfastamento(): ?RhAfastamento
    {
        $today = Carbon::today();

        return $this->afastamentos
            ->first(fn (RhAfastamento $afastamento): bool => $afastamento->is_active
                && $afastamento->start_date?->lessThanOrEqualTo($today)
                && ($afastamento->end_date === null || $afastamento->end_date?->greaterThanOrEqualTo($today)));
    }
}
