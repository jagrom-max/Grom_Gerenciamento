<?php

namespace App\Models;

use App\Enums\ImportItemStatus;
use App\Enums\LavradoUnidade;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ImportItem extends Model
{
    use HasUuids;

    public const STATUS_PENDING = ImportItemStatus::Pending->value;
    public const STATUS_CONFIRMED = ImportItemStatus::Confirmed->value;
    public const STATUS_REJECTED = ImportItemStatus::Rejected->value;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'batch_id',
        'cartorio_id',
        'source_process_key',
        'cartorio_hint',
        'reference_year',
        'reference_month',
        'spj',
        'naturezas',
        'num_ip',
        'num_ipe',
        'num_cnj',
        'data_fato',
        'status_origem',
        'lavrado_unidade',
        'payload',
        'import_status',
        'confirmed_by',
        'confirmed_at',
        'rejected_reason',
        'productivity_flagrante_id',
    ];

    protected $casts = [
        'reference_year' => 'integer',
        'reference_month' => 'integer',
        'data_fato' => 'date',
        'payload' => 'array',
        'confirmed_at' => 'datetime',
        'import_status' => ImportItemStatus::class,
        'lavrado_unidade' => LavradoUnidade::class,
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function cartorio(): BelongsTo
    {
        return $this->belongsTo(Cartorio::class);
    }

    public function productivityFlagrante(): BelongsTo
    {
        return $this->belongsTo(ProductivityFlagrante::class, 'productivity_flagrante_id');
    }

    protected static function booted(): void
    {
        $synchronize = function (self $item): void {
            $item->synchronizeReferencePeriod();
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
