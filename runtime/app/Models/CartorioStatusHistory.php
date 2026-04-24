<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartorioStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'cartorio_status_history';

    protected $fillable = [
        'cartorio_id',
        'status',
        'reason',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function cartorio(): BelongsTo
    {
        return $this->belongsTo(Cartorio::class);
    }
}
