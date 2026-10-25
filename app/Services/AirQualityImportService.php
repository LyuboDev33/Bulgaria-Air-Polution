<?php

namespace App\Services;

use App\Models\City;
use App\Models\CityDailyPm;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class AirQualityImportService
{
    /**
     * Import historical PM2.5 measurements for a specific date.
     *
     * This method coordinates the complete import process:
     * 1. Discover PM sensor CSV files in the requested day's archive.
     * 2. Download the files and read their historical coordinates.
     * 3. Match readings to cities within a 10 km radius.
     * 4. Calculate one mean per sensor per city and save daily city averages.
     *
     * The archive does not expose an indoor/outdoor flag in its CSVs.
     * Therefore this import cannot guarantee outdoor-only measurements.
     *
     * The process is logged so that successful imports and unexpected
     * failures can be monitored. Incomplete downloads never overwrite data.
     *
     * @param string $date Date in Y-m-d format.
     * @return array Import statistics and failed file details.
     * @throws Throwable If an unexpected error interrupts the import.
     */
    public function import(string $date): array
    {
        $started = microtime(true);

        Log::info('Air quality import started', ['date' => $date]);

        try {
            $parsedDate = Carbon::createFromFormat('!Y-m-d', $date);

            // if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
            //     throw new \InvalidArgumentException('Invalid date. Expected Y-m-d.');
            // }

            $date = $parsedDate->toDateString();
            $cities = City::all();

            if ($cities->isEmpty()) {
                throw new \RuntimeException('No cities configured for import.');
            }

            $sensorUrls = $this->getHistoricalSensorUrls($date);

            // if ($sensorUrls === []) {
            //     throw new \RuntimeException("No PM sensor CSV files found for {$date}.");
            // }

            Log::info('Historical sensor files discovered', [
                'date' => $date,
                'total_files' => count($sensorUrls),
                'cities' => $cities->count(),
            ]);

            $downloadResult = $this->downloadAndAverageHistorical($sensorUrls, $cities, $date);
            $averages = $downloadResult[0];
            $failed = $downloadResult[1];
            $readings = $downloadResult[2];
            $matchedSensors = $downloadResult[3];
            $processedFiles = $downloadResult[4];

            // Never publish a partial daily average as if it were complete.
            if ($failed !== []) {
                Log::error('Air quality import incomplete; database unchanged', [
                    'date' => $date,
                    'failed_files_count' => count($failed),
                    'total_files' => count($sensorUrls),
                ]);

                // throw new \RuntimeException(
                //     'Historical import incomplete: ' . count($failed) . ' CSV files failed. Check logs.'
                // );
            }

            $saved = $this->saveAverages($averages, $date);

            $result = [
                'date' => $date,
                'total_files' => count($sensorUrls),
                'processed_files' => $processedFiles,
                'matched_sensors' => $matchedSensors,
                'successful_sensors' => array_sum(array_map('count', $averages)),
                'failed_sensors' => $failed,
                'readings_processed' => $readings,
                'cities_saved' => $saved,
                'execution_seconds' => round(microtime(true) - $started, 2),
            ];

            Log::info('Air quality import completed', [
                'date' => $date,
                'total_files' => $result['total_files'],
                'processed_files' => $processedFiles,
                'matched_sensors' => $matchedSensors,
                'successful_sensors' => $result['successful_sensors'],
                'failed_sensors_count' => count($failed),
                'readings_processed' => $readings,
                'cities_saved' => $saved,
                'execution_seconds' => $result['execution_seconds'],
            ]);

            return $result;
        } catch (Throwable $e) {
            Log::error('Air quality import failed', [
                'date' => $date,
                'message' => $e->getMessage(),
                'exception' => $e,
                'execution_seconds' => round(microtime(true) - $started, 2),
            ]);

            throw $e;
        }
    }

    /**
     * Retrieve historical PM sensor CSV URLs for a specific date.
     *
     * The archive's directory listing supplies filenames, but not countries
     * or coordinates. We must inspect CSV contents to locate the sensors.
     *
     * The supported sensor types below mirror the original discovery regex.
     * Expand this list if the archive contains other PM2.5-capable models.
     *
     * @param string $date Format: Y-m-d.
     * @return array List of absolute CSV URLs.
     * @throws Throwable If the archive listing cannot be retrieved.
     */


    private function getHistoricalSensorUrls(string $date): array
    {
        $archiveUrl = "https://archive.sensor.community/{$date}/";

        $cachePath = storage_path(
            "app/air-quality/listings/{$date}.html"
        );

        if (file_exists($cachePath)) {
            $html = file_get_contents($cachePath);
        } else {
            $attempt = 0;

            while (true) {
                $attempt++;

                try {
                    $response = Http::connectTimeout(30)
                        ->timeout(1000)
                        ->withOptions([
                            'version' => 1.1,
                        ])
                        ->get($archiveUrl);

                    if ($response->successful()) {
                        $html = $response->body();

                        if (!is_dir(dirname($cachePath))) {
                            mkdir(dirname($cachePath), 0755, true);
                        }

                        file_put_contents($cachePath, $html);

                        break;
                    }

                    Log::warning('Archive listing HTTP error', [
                        'date' => $date,
                        'status' => $response->status(),
                    ]);
                } catch (\Throwable $exception) {
                    Log::warning('Archive listing request failed', [
                        'date' => $date,
                        'attempt' => $attempt,
                        'error' => $exception->getMessage(),
                    ]);
                }

                sleep(30);
            }
        }

        preg_match_all(
            '/href=["\']([^"\']+_(?:sds011|sps30|pms5003|pms7003)_sensor_\d+\.csv)["\']/i',
            $html,
            $matches
        );

        $urls = [];

        foreach ($matches[1] as $filename) {
            $urls[] = $archiveUrl . basename($filename);
        }

        return array_values(array_unique($urls));
    }

    /**
     * Download historical CSV files and calculate sensor averages.
     *
     * Guzzle Pool downloads files concurrently in batches to limit memory
     * usage. Each CSV is parsed to match historical coordinates to cities.
     * Averages are grouped by city ID, one contribution per sensor file.
     *
     * Individual download failures are recorded without stopping other
     * downloads. The caller decides whether it is safe to save the results.
     *
     * @param array $sensorUrls Historical CSV URLs.
     * @param Collection $cities Cities retrieved from the database.
     * @param string $date Date in Y-m-d format.
     * @return array{0: array, 1: array, 2: int, 3: int, 4: int}
     *         Sensor averages, failed files, valid readings,
     *         matched sensor files, processed files.
     */
    private function downloadAndAverageHistorical(
        array $sensorUrls,
        Collection $cities,
        string $date
    ): array {
        $client = new Client([
            'connect_timeout' => 10,
            'timeout' => 60,
            'http_errors' => false,
        ]);

        $averages = [];
        $failed = [];
        $readings = 0;
        $matchedSensors = 0;
        $processedFiles = 0;

        // Do not hold thousands of HTTP response bodies in memory at once.
        foreach (array_chunk($sensorUrls, 100) as $batchNumber => $batch) {
            $requests = function () use ($client, $batch) {
                foreach ($batch as $url) {
                    yield fn() => $client->getAsync($url);
                }
            };

            $pool = new Pool($client, $requests(), [
                'concurrency' => 10,

                'fulfilled' => function (ResponseInterface $response, $index) use (
                    $batch,
                    $cities,
                    $date,
                    &$averages,
                    &$failed,
                    &$readings,
                    &$matchedSensors,
                    &$processedFiles
                ) {
                    $url = $batch[$index];
                    $processedFiles++;
                    $status = $response->getStatusCode();

                    if ($status !== 200) {
                        $failed[] = ['url' => $url, 'status' => $status];

                        Log::warning('Historical sensor CSV download failed', [
                            'date' => $date,
                            'url' => $url,
                            'status' => $status,
                        ]);
                        return;
                    }

                    try {
                        $sensorResult = $this->parseHistoricalCsv(
                            (string) $response->getBody(),
                            $date,
                            $cities
                        );

                        $cityAverages = $sensorResult[0];
                        $validReadings = $sensorResult[1];

                        if ($cityAverages !== []) {
                            $matchedSensors++;
                        }

                        foreach ($cityAverages as $cityId => $average) {
                            $averages[$cityId][] = $average;
                        }

                        $readings += $validReadings;
                    } catch (Throwable $e) {
                        $failed[] = ['url' => $url, 'error' => $e->getMessage()];

                        Log::error('Historical sensor CSV processing failed', [
                            'date' => $date,
                            'url' => $url,
                            'message' => $e->getMessage(),
                            'exception' => $e,
                        ]);
                    }
                },

                'rejected' => function ($reason, $index) use (
                    $batch,
                    $date,
                    &$failed,
                    &$processedFiles
                ) {
                    $url = $batch[$index];
                    $processedFiles++;
                    $failed[] = ['url' => $url, 'error' => (string) $reason];

                    Log::warning('Historical sensor CSV request rejected', [
                        'date' => $date,
                        'url' => $url,
                        'reason' => (string) $reason,
                    ]);
                },
            ]);

            $pool->promise()->wait();

            Log::info('Historical sensor CSV batch completed', [
                'date' => $date,
                'batch' => $batchNumber + 1,
                'processed_files' => $processedFiles,
                'total_files' => count($sensorUrls),
                'matched_sensor_files' => $matchedSensors,
                'failed_files' => count($failed),
            ]);
        }

        Log::info('Sensor CSV processing completed', [
            'date' => $date,
            'processed_files' => $processedFiles,
            'matched_sensors' => $matchedSensors,
            'successful_sensors' => array_sum(array_map('count', $averages)),
            'failed_sensors' => count($failed),
            'readings_processed' => $readings,
        ]);

        return [$averages, $failed, $readings, $matchedSensors, $processedFiles];
    }

    /**
     * Parse a sensor CSV file and calculate its average PM2.5 value.
     *
     * The CSV uses semicolons as delimiters. Only readings matching the
     * requested date are considered. Missing, non-numeric, negative, or
     * non-finite PM2.5 values are ignored. PM2.5 is stored in the P2 column.
     *
     * Unlike the old parser, this one also reads historical lat/lon from
     * each row. That avoids using today's potentially outdated location.
     * Each sensor file contributes at most one average per city.
     *
     * @param string $csv Raw CSV content.
     * @param string $date Date in Y-m-d format.
     * @param Collection $cities City coordinates from the database.
     * @return array{0: array, 1: int} City averages and valid matched readings.
     */
    private function parseHistoricalCsv(
        string $csv,
        string $date,
        Collection $cities
    ): array {
        $file = fopen('php://temp/maxmemory:2097152', 'w+');

        if ($file === false) {
            throw new \RuntimeException('Unable to create temporary CSV stream.');
        }

        try {
            fwrite($file, $csv);
            rewind($file);

            $headers = fgetcsv($file, 0, ';');

            if (!$headers) {
                return [[], 0];
            }

            $headers = array_map('trim', $headers);
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

            $pmIndex = array_search('P2', $headers, true);
            $dateIndex = array_search('timestamp', $headers, true);
            $latIndex = array_search('lat', $headers, true);
            $lonIndex = array_search('lon', $headers, true);

            // if (
            //     $pmIndex === false || $dateIndex === false ||
            //     $latIndex === false || $lonIndex === false
            // ) {
            //     throw new \UnexpectedValueException('Required CSV columns are missing.');
            // }

            $sums = [];
            $counts = [];
            $readings = 0;
            $locationCache = [];

            while (($row = fgetcsv($file, 0, ';')) !== false) {
                $pm = $row[$pmIndex] ?? null;
                $timestamp = $row[$dateIndex] ?? '';
                $lat = $row[$latIndex] ?? null;
                $lon = $row[$lonIndex] ?? null;

                if (
                    !is_numeric($pm) ||
                    substr((string) $timestamp, 0, 10) !== $date ||
                    !is_numeric($lat) || !is_numeric($lon)
                ) {
                    continue;
                }

                $pm = (float) $pm;
                $lat = (float) $lat;
                $lon = (float) $lon;

                if (
                    !is_finite($pm) || $pm < 0 ||
                    !is_finite($lat) || !is_finite($lon) ||
                    $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180
                ) {
                    continue;
                }

                // A sensor can move; use each row's historical coordinates.
                $locationKey = $lat . ':' . $lon;

                if (!array_key_exists($locationKey, $locationCache)) {
                    $locationCache[$locationKey] = $this->matchCoordinatesToCity(
                        $lat,
                        $lon,
                        $cities
                    );
                }

                $cityId = $locationCache[$locationKey];

                if ($cityId === null) {
                    continue;
                }

                $sums[$cityId] = ($sums[$cityId] ?? 0) + $pm;
                $counts[$cityId] = ($counts[$cityId] ?? 0) + 1;
                $readings++;
            }

            $averages = [];

            foreach ($sums as $cityId => $sum) {
                $averages[$cityId] = $sum / $counts[$cityId];
            }

            return [$averages, $readings];
        } finally {
            fclose($file);
        }
    }

    /**
     * Match historical coordinates to the nearest configured city.
     *
     * Each reading is compared against every city center. The nearest
     * city within 10 km is selected, or null if no city is close enough.
     *
     * @param float $lat Historical latitude.
     * @param float $lon Historical longitude.
     * @param Collection $cities Cities retrieved from the database.
     * @return int|null Matched city ID.
     */
    private function matchCoordinatesToCity(
        float $lat,
        float $lon,
        Collection $cities
    ): ?int {
        $closestCityId = null;
        $closestDistance = 10.0;

        foreach ($cities as $city) {
            $distance = $this->distanceInKm(
                $lat,
                $lon,
                (float) $city->latitude,
                (float) $city->longitude
            );

            if ($distance <= $closestDistance) {
                $closestDistance = $distance;
                $closestCityId = (int) $city->id;
            }
        }

        return $closestCityId;
    }

    /**
     * Save calculated daily PM2.5 averages to the database.
     * Each city receives one record per date.
     *
     * Replacing all rows for the date also removes stale city values from
     * earlier runs. This method is called only after all downloads succeed.
     *
     * @param array $averages Sensor averages grouped by city ID.
     * @param string $date Date in Y-m-d format.
     * @return int Number of city records inserted or updated.
     */
    private function saveAverages(array $averages, string $date): int
    {
        $records = [];
        $now = now();

        foreach ($averages as $cityId => $values) {
            if ($values === []) {
                continue;
            }

            $records[] = [
                'city_id' => $cityId,
                'date' => $date,
                'average_pm25' => round(array_sum($values) / count($values), 2),
                'sensor_count' => count($values),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($date, $records) {
            CityDailyPm::where('date', $date)->delete();

            if ($records !== []) {
                CityDailyPm::upsert(
                    $records,
                    ['city_id', 'date'],
                    ['average_pm25', 'sensor_count', 'updated_at']
                );
            }
        });

        Log::info('Daily city averages saved', [
            'date' => $date,
            'cities_saved' => count($records),
        ]);

        return count($records);
    }

    /**
     * Calculate the geographical distance between two coordinates.
     *
     * Coordinates are provided in decimal degrees.
     * The result is returned in kilometers.
     *
     * @param float $lat1 Latitude of the first point.
     * @param float $lon1 Longitude of the first point.
     * @param float $lat2 Latitude of the second point.
     * @param float $lon2 Longitude of the second point.
     * @return float Distance between the points in kilometers.
     */
    private function distanceInKm(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $lat = deg2rad($lat2 - $lat1);
        $lon = deg2rad($lon2 - $lon1);

        $a = sin($lat / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($lon / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($a)));
    }
}
