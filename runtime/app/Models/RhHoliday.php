<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RhHoliday extends Model
{
    use HasFactory, HasUuids;

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

    public function setDateAttribute($value): void
    {
        $this->attributes['holiday_date'] = $value;
    }

    public function setDescricaoAttribute($value): void
    {
        $this->attributes['name'] = $value;
    }
}
