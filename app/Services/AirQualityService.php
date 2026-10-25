<?php

namespace App\Services;

use App\Models\CityDailyPm;
use Illuminate\Support\Collection;

class AirQualityService
{
    public function filteredData(array $filters): Collection
    {
        $data = CityDailyPm::query()
            ->with('city')
            ->whereBetween('date', [
                $filters['start_date'],
                $filters['end_date'],
            ])
            ->get()
            ->groupBy('city_id')
            ->map(function ($records) {
                return [
                    'city' => $records->first()->city->name,
                    'average_pm25' => round($records->avg('average_pm25'), 2),
                ];
            })
            ->sortBy('average_pm25')
            ->values();

        $maxPm25 = $data->max('average_pm25') ?: 1;

        return $data->map(function ($city, $index) use ($maxPm25) {
            $city['rank'] = $index + 1;

            $city['bar_width'] = round(($city['average_pm25'] / $maxPm25) * 100, 2);

            return $city;
        });
    }




}
