<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\AirQualityImportService;
use App\Services\AirQualityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AirQualityController extends Controller
{
    public function __construct(
        private readonly AirQualityImportService $importService,
        private readonly AirQualityService $airQualityService
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $filters = [
            'start_date' => $filters['start_date'] ?? null,
            'end_date' => $filters['end_date'] ?? null,
        ];

        $data = $this->airQualityService->filteredData($filters);

        return view('Frontend.AirQuality', [
            'data' => $data,
            'winner' => $data->first(),
            'filters' => $filters,
        ]);
    }

    /**
     * Filter the data via AJAX
     *
     * @param Request $request
     * @return JsonResponse
     *
     */
    public function filter(Request $request)
    {
        $filters = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
        ]);

        $data = $this->airQualityService->filteredData($filters);

        $html = view('Frontend.components.air-quality-results', [
            'data' => $data,
            'winner' => $data->first(),
            'filters' => $filters,
        ])->render();

        return response()->json([
            'success' => true,
            'html' => $html,
        ]);
    }


    /** Store all the necessary data selected */
    public function store()
    {
        set_time_limit(0);

        $startDate = now()->setDate(2026, 8, 06)->startOfDay();
        $endDate = now()->setDate(2026, 10, 04)->startOfDay();

        $dates = [];

        while ($startDate <= $endDate) {
            $dates[] = $startDate->format('Y-m-d');
            $startDate->addDay();
        }

        foreach ($dates as $date) {
            $this->importService->import($date);
        }

        return response()->json([
            'message' => 'Historical import completed successfully.',
        ]);
    }
}
