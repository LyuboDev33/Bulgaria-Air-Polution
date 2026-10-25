<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            [
                'name' => 'Sofia',
                'latitude' => 42.6977,
                'longitude' => 23.3219,
            ],
            [
                'name' => 'Plovdiv',
                'latitude' => 42.1354,
                'longitude' => 24.7453,
            ],
            [
                'name' => 'Varna',
                'latitude' => 43.2141,
                'longitude' => 27.9147,
            ],
            [
                'name' => 'Burgas',
                'latitude' => 42.5048,
                'longitude' => 27.4626,
            ],
            [
                'name' => 'Ruse',
                'latitude' => 43.8356,
                'longitude' => 25.9657,
            ],
        ];

        foreach ($cities as $city) {
            City::updateOrCreate(
                ['name' => $city['name']],
                [
                    'latitude' => $city['latitude'],
                    'longitude' => $city['longitude'],
                    'radius_km' => 10,
                ]
            );
        }
    }

    
}
