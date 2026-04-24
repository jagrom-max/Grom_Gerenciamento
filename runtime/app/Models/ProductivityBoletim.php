<?php

namespace App\Models;

use App\Enums\LavradoUnidade;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductivityBoletim extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'produtividade_boletins';

    protected $fillable = [
        'cartorio_id',
        'import_batch_id',
        'reference_year',
        'reference_month',
        'data_fato',
        'spj',
        'naturezas',
        'lavrado_unidade',
        'is_flagrante',
        'mpu_numero',
        'mpu_decisao',
        'despacho_fundamentado',
        'encaminhado_outra_unidade',
        'encaminhado_para_unidade',
        'num_ip',
        'num_ipe',
        'num_cnj',
        'productivity_flagrante_id',
        'is_active',
        'notes',
        'imported_by',
    ];

    protected $casts = [
        'reference_year' => 'integer',
        'reference_month' => 'integer',
        'data_fato' => 'date',
        'lavrado_unidade' => LavradoUnidade::class,
        'is_flagrante' => 'boolean',
        'despacho_fundamentado' => 'boolean',
        'encaminhado_outra_unidade' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function cartorio(): BelongsTo
    {
        return $this->belongsTo(Cartorio::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function productivityFlagrante(): BelongsTo
    {
        return $this->belongsTo(ProductivityFlagrante::class, 'productivity_flagrante_id');
    }
}
