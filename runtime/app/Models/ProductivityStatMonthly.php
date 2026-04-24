<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductivityStatMonthly extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'productivity_stats_monthly';

    protected $fillable = [
        'cartorio_id',
        'reference_year',
        'reference_month',
        'ip_instaurados',
        'ip_relatados',
        'cotas',
        'despachos',
        'concluidos',
        'registros',
        'ips_andamento',
        'flagrantes_total',
        'flagrantes_ddm',
        'flagrantes_outras',
        'source_mode',
        'manual_notes',
    ];

    protected $casts = [
        'reference_year' => 'integer',
        'reference_month' => 'integer',
        'ip_instaurados' => 'integer',
        'ip_relatados' => 'integer',
        'cotas' => 'integer',
        'despachos' => 'integer',
        'concluidos' => 'integer',
        'registros' => 'integer',
        'ips_andamento' => 'integer',
        'flagrantes_total' => 'integer',
        'flagrantes_ddm' => 'integer',
        'flagrantes_outras' => 'integer',
    ];

    public function cartorio(): BelongsTo
    {
        return $this->belongsTo(Cartorio::class);
    }
}
