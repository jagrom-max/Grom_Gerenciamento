<?php

namespace App\Models;

use App\Enums\LavradoUnidade;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ProductivityFlagrante extends Model
{
    use HasUuids;

    public const LAVRADO_DDM = LavradoUnidade::Ddm->value;
    public const LAVRADO_OUTRAS = LavradoUnidade::OutrasUnidades->value;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'produtividade_flagrantes';

    protected $fillable = [
        'cartorio_id',
        'source_item_id',
        'reference_year',
        'reference_month',
        'spj',
        'naturezas',
        'num_ip',
        'num_ipe',
        'num_cnj',
        'data_fato',
        'lavrado_unidade',
        'manually_confirmed',
        'is_active',
        'confirmed_by',
        'confirmed_at',
        'notes',
    ];

    protected $casts = [
        'reference_year' => 'integer',
        'reference_month' => 'integer',
        'data_fato' => 'date',
        'lavrado_unidade' => LavradoUnidade::class,
        'manually_confirmed' => 'boolean',
        'is_active' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    public function cartorio(): BelongsTo
    {
        return $this->belongsTo(Cartorio::class);
    }

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(ImportItem::class, 'source_item_id');
    }

    protected static function booted(): void
    {
        $synchronize = function (self $flagrante): void {
            $flagrante->synchronizeReferencePeriod();
        };

        static::creating($synchronize);
        static::saving($synchronize);
    }

    private function synchronizeReferencePeriod(): void
    {
        if (! $this->data_fato) {
            return;
        }

        $date = $this->data_fato instanceof \DateTimeInterface
            ? Carbon::instance($this->data_fato)
            : Carbon::parse((string) $this->data_fato);

        $this->reference_year ??= (int) $date->format('Y');
        $this->reference_month ??= (int) $date->format('n');
    }
}
