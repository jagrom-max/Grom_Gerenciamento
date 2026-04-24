<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'source_name',
        'source_type',
        'source_hash',
        'sheet_name',
        'header_row',
        'source_period_start',
        'source_period_end',
        'imported_by',
        'imported_at',
        'processed_at',
        'total_rows',
        'rows_staged',
        'rows_updated',
        'rows_skipped',
        'error_count',
        'notes',
    ];

    protected $casts = [
        'header_row' => 'integer',
        'source_period_start' => 'date',
        'source_period_end' => 'date',
        'imported_at' => 'datetime',
        'processed_at' => 'datetime',
        'total_rows' => 'integer',
        'rows_staged' => 'integer',
        'rows_updated' => 'integer',
        'rows_skipped' => 'integer',
        'error_count' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ImportItem::class, 'batch_id');
    }
}
