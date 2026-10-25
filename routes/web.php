<?php

use App\Http\Controllers\AirQualityController;
use Illuminate\Support\Facades\Route;



Route::get('/', [AirQualityController::class, 'index'])->name('air-quality.index');
Route::get('/air-quality/filter', [AirQualityController::class, 'filter'])->name('air-quality.filter');


require __DIR__ . '/api.php';
