<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityDailyPm extends Model
{

    protected $table = 'cities_daily_pms';


    protected $fillable = [
        'city_id',
        'date',
        'average_pm25',
        'sensor_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'average_pm25' => 'decimal:2',
            'sensor_count' => 'integer',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
