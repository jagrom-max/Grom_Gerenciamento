<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RhHoliday extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'rh_holidays';

    protected $keyType = 'string';

    protected $fillable = [
        'holiday_date',
        'name',
        'scope',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_active' => 'boolean',
    ];
}
