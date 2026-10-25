<?php

use App\Http\Controllers\AirQualityController;
use Illuminate\Support\Facades\Route;


Route::get('/test', [AirQualityController::class, 'store'])->name('csv.import.store');
