<?php

namespace App\Support\Produtividade;

use Illuminate\Database\Eloquent\Builder;

class BoletimQueryFilters
{
    public static function validatedRules(): array
    {
        return [
            'cartorio_id' => ['nullable', 'exists:cartorios,id'],
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:0', 'max:12'],
            'is_flagrante' => ['nullable', 'in:0,1'],
            'has_mpu' => ['nullable', 'in:0,1'],
            'without_ip' => ['nullable', 'in:0,1'],
            'lavrado_unidade' => ['nullable', 'in:DDM,OUTRAS_UNIDADES'],
        ];
    }

    public static function apply(Builder $query, array $filters, array $scopeCartorioIds): Builder
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $month = array_key_exists('month', $filters) ? (int) $filters['month'] : 0;

        return $query
            ->when($scopeCartorioIds !== [], fn (Builder $builder) => $builder->whereIn('cartorio_id', $scopeCartorioIds))
            ->where('is_active', true)
            ->where('reference_year', $year)
            ->when($month > 0, fn (Builder $builder) => $builder->where('reference_month', $month))
            ->when(array_key_exists('is_flagrante', $filters) && $filters['is_flagrante'] !== null && $filters['is_flagrante'] !== '', fn (Builder $builder) => $builder->where('is_flagrante', (bool) ((int) $filters['is_flagrante'])))
            ->when(array_key_exists('has_mpu', $filters) && $filters['has_mpu'] === '1', fn (Builder $builder) => $builder->whereNotNull('mpu_numero')->where('mpu_numero', '!=', ''))
            ->when(array_key_exists('has_mpu', $filters) && $filters['has_mpu'] === '0', fn (Builder $builder) => $builder->where(function (Builder $inner): void {
                $inner->whereNull('mpu_numero')->orWhere('mpu_numero', '');
            }))
            ->when(($filters['without_ip'] ?? null) === '1', fn (Builder $builder) => $builder->where(function (Builder $inner): void {
                $inner->whereNull('num_ip')->orWhere('num_ip', '');
            }))
            ->when(($filters['without_ip'] ?? null) === '0', fn (Builder $builder) => $builder->whereNotNull('num_ip')->where('num_ip', '!=', ''))
            ->when(! empty($filters['lavrado_unidade']), fn (Builder $builder) => $builder->where('lavrado_unidade', $filters['lavrado_unidade']));
    }
}