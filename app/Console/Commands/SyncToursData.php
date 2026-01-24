<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Tour;
use App\Models\City;
use App\Models\TourCity;
use App\Models\Country;
use App\Models\TourCountry;
use App\Models\NaturalDestination;
use App\Models\TourNaturalDestination;
use App\Models\TourType;
use App\Models\Type;
use Carbon\Carbon;
use App\Http\Controllers\TourRadarController;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Http\Controllers\ProxyTourRadarController;
use App\Models\Departure;

class SyncToursData extends Command
{
    protected $signature = 'sync:tours 
    {pages? : Page(s) to sync. Examples: "1-10", "5", "1,3,5", or "pages:1-10"} 
    {--pages= : Page(s) to sync (same formats as argument)} 
    {--date_range= : Date range in Ymd-Ymd (e.g. 20251011-20251111)}';

    protected $description = 'Sync tours data from the API to the database';
    private $token;
    private $dateRangeStart = null; // string Ymd or null
    private $dateRangeEnd = null;

    public function handle()
    {
        $this->token = $this->asyncGetAccessToken();

        $raw = $this->argument('pages') ?? $this->option('pages') ?? null;

        // Accept "pages:1-10" too (in case user runs: php artisan sync:tours pages:1-10)
        if ($raw && str_starts_with($raw, 'pages:')) {
            $raw = substr($raw, strlen('pages:'));
        }

        $dateRangeRaw = $this->option('date_range') ?? null;

        // also allow passing date_range:YYYYMMDD-YYYYMMDD or date_range=... inside the raw token(s)
        if (!$dateRangeRaw && is_string($raw)) {
            // raw could contain "pages:1-10 date_range:20251011-20251111" in weird usages;
            // check for "date_range:" or "date_range="
            if (preg_match('/date_range[:=]([0-9]{8}-[0-9]{8})/', $raw, $m)) {
                $dateRangeRaw = $m[1];
                // If we matched date_range inside the same token, remove it from $raw so pages parsing remains correct
                $raw = trim(str_replace($m[0], '', $raw));
            }
        }

        // default behavior if no pages provided
        if (empty($raw)) {
            $this->info("No pages provided. Using default pages 1-2.");
            $pages = ["pages:1-300"];
        } else {
            $pages = $this->parsePagesInput($raw);
            if (empty($pages)) {
                $this->error("Invalid pages input: {$raw}. Use formats like 1-10, 5, or 1,3,5");
                return 1; // non-zero exit code for failure
            }
        }

        if ($dateRangeRaw) {
            $dateRangeRaw = trim($dateRangeRaw);
            if (!preg_match('/^\d{8}-\d{8}$/', $dateRangeRaw)) {
                $this->error("Invalid date_range format. Expected Ymd-Ymd, e.g. 20251011-20251111");
                return 1;
            }
            [$start, $end] = explode('-', $dateRangeRaw, 2);
            try {
                $startCarbon = Carbon::createFromFormat('Ymd', $start);
                $endCarbon   = Carbon::createFromFormat('Ymd', $end);
                if ($endCarbon->lt($startCarbon)) {
                    $this->error("Invalid date_range: end date is before start date.");
                    return 1;
                }
                // store as Ymd strings used by the API
                $this->dateRangeStart = $startCarbon->format('Ymd');
                $this->dateRangeEnd   = $endCarbon->format('Ymd');
                $this->info("Using date_range: {$this->dateRangeStart}-{$this->dateRangeEnd}");
            } catch (\Exception $e) {
                $this->error("Invalid dates in date_range: " . $e->getMessage());
                return 1;
            }
        } else {
            // no date_range provided — keep the previous behavior (1..91 days)
            $this->info("No date_range provided. Using default date window (now +1 day to now +91 days).");
        }

        $this->info('Pages to process: ' . implode(', ', $pages));

        foreach ($pages as $currentPage) {
            $this->info("Processing page {$currentPage}...");

            try {
                $response = $this->fetchDataFromApi($currentPage);
            } catch (\Throwable $e) {
                $this->error("Error fetching page {$currentPage}: " . $e->getMessage());
                Log::error('sync:tours - fetch error', ['page' => $currentPage, 'exception' => $e]);
                continue;
            }

            $tours = $response['items'] ?? [];

            $this->info("Number of tours on page {$currentPage}: " . count($tours));

            if (!empty($tours)) {
                foreach ($tours as $tourData) {
                    try {
                        $this->saveTourToDatabase($tourData);
                    } catch (\Throwable $e) {
                        $this->error("Error saving tour (page {$currentPage}): " . $e->getMessage());
                        Log::error('sync:tours - save error', ['page' => $currentPage, 'exception' => $e, 'tour' => $tourData]);
                        // continue to next tour
                    }
                }
                $this->info("Synced data for page {$currentPage}");
            } else {
                $this->info("No more data on page {$currentPage}");
            }

            // be polite to the API
            sleep(3);
        }
        $this->info('Done.');
        return 0;
    }

    private function parsePagesInput(string $input): array
    {
        $input = trim($input);

        // if comma separated list
        if (strpos($input, ',') !== false) {
            $parts = array_filter(array_map('trim', explode(',', $input)));
            $pages = [];
            foreach ($parts as $p) {
                if (preg_match('/^\d+$/', $p)) {
                    $pages[] = (int)$p;
                } elseif (preg_match('/^(\d+)-(\d+)$/', $p, $m)) {
                    $start = (int)$m[1];
                    $end = (int)$m[2];
                    if ($end >= $start) {
                        for ($i = $start; $i <= $end; $i++) $pages[] = $i;
                    }
                }
            }
            $pages = array_unique($pages);
            sort($pages);
            return $pages;
        }

        // if range format "start-end"
        if (preg_match('/^(\d+)-(\d+)$/', $input, $matches)) {
            $start = (int)$matches[1];
            $end = (int)$matches[2];
            if ($start <= 0 || $end <= 0 || $end < $start) {
                return [];
            }
            $pages = range($start, $end);
            return $pages;
        }

        // single page "5"
        if (preg_match('/^\d+$/', $input)) {
            $n = (int)$input;
            if ($n <= 0) return [];
            return [$n];
        }

        return [];
    }

    private function asyncGetAccessToken()
    {
        try {
            $clientId = "2gmdq5q758vtiwxxwxgwse5whv";
            $clientSecret = "cz3p1gnwatvepzdrpw7b68uyxizte2noabkslo1ue5gkm3lmu97";

            $response = Http::withHeaders([
				'Accept-Language' => 'en',
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$clientId:$clientSecret"),
            ])
                ->asForm()
                ->post('https://oauth.api.sandbox.b2b.tourradar.com/oauth2/token', [
                    'grant_type' => 'client_credentials',
                    'scope' => 'com.tourradar.tours/read',
                ]);

            $accessToken = $response->json('access_token');
            return $accessToken;

        } catch (\Exception $e) {
            $this->error('Error fetching access token: ' . $e->getMessage());
            throw $e;
        }
    }

    private function fetchDataFromApi($currentPage)
    {
        try {
            $response = Http::withToken($this->token)
                ->get('https://api.sandbox.b2b.tourradar.com/v1/tours/search', [
                    'sort_order' => 'asc',
                    'limit' => 10,
                    'sort_by' => 'price',
                    'currency' => 'USD',
                    'user_country' => 185,
                    'is_instant_confirmable' => true,
                    'page' => $currentPage,
                ]);

            $responseData = $response->json();
            $tours = $responseData['items'] ?? [];
            $this->line("Response for page {$currentPage}: Number of tours: " . count($tours));

            if (isset($responseData['error'])) {
                $this->error("API Error on page {$currentPage}: " . json_encode($responseData['error']));
                return [];
            }

            return $responseData;
        } catch (\Exception $e) {
            $this->error('Error fetching data from API: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getDeparturesByTour($tourId, $dateRangeStart = null, $dateRangeEnd = null)
    {
        // Delay of 1 second
        usleep(1000000);

        $accessToken = $this->token;
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        // If not explicit, use defaults
        if (!$dateRangeStart || !$dateRangeEnd) {
            $dateRangeStart = Carbon::now()->addDays(1)->format('Ymd');
            $dateRangeEnd   = Carbon::now()->addDays(91)->format('Ymd');
        }

        $url = "https://api.sandbox.b2b.tourradar.com/v1/tours/{$tourId}/departures?date_range={$dateRangeStart}-{$dateRangeEnd}&user_country=185&currency=USD";
        Log::info("Departures url {$url}");
        $this->info("Departures url {$url}");

        try {
            $response = Http::withHeaders($headers)->get($url);
            Log::info("Departures response {$response}");
            $this->info("Departures response {$response}");
            return $response->json();
        } catch (\Exception $e) {
            if ($e->getCode() == 504) {
                $this->error("Error fetching departures for tour {$tourId}: Request failed with status code 504. Continuing to the next tour.");
            } else {
                $this->error('Error fetching departures for tour ' . $tourId . ': ' . $e->getMessage());
            }
            return [];
        }
    }


    private function getDeparture($tourId, $departureId)
    {
        // Delay of 1 second
        usleep(1000000); // 1 second in microseconds
    
        $accessToken = $this->token;
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ];
    
        $url = "https://api.sandbox.b2b.tourradar.com/v1/tours/{$tourId}/departures/{$departureId}";
        Log::info("Departure detail url {$url}");
        $this->info("Departure detail url {$url}");
    
        try {
            $response = Http::withHeaders($headers)->get($url);
    
            // Log response status and a truncated body (avoid huge logs)
            $status = $response->status();
            $body = $response->body(); // raw body (string)
            $bodySnippet = mb_substr($body, 0, 2000); // first 2000 chars
    
            // DON'T log the Authorization header. If you want to log headers, remove auth
            $safeHeaders = $headers;
            $safeHeaders['Authorization'] = 'Bearer [REDACTED]';
    
            Log::info('Departure API response', [
                'url' => $url,
                'status' => $status,
                'headers' => $safeHeaders,
                'body_snippet' => $bodySnippet,
            ]);
    
            // If non-successful, log and return empty array
            if (! $response->successful()) {
                Log::warning("Departure API returned non-success status {$status}", [
                    'tour_id' => $tourId,
                    'departure_id' => $departureId,
                    'status' => $status,
                ]);
                return [];
            }
    
            // decode safely; Http::json() / ->json() returns array or null
            $json = $response->json() ?? [];
    
            return is_array($json) ? $json : [];
    
        } catch (\Exception $e) {
            // Http::get normally doesn't throw unless ->throw() used, but keep catch for completeness
            $code = $e->getCode();
            if ($code == 504) {
                $this->error("Error fetching departures for tour {$tourId}: Request failed with status code 504. Continuing to the next tour.");
            } else {
                $this->error('Error fetching departures for tour ' . $tourId . ': ' . $e->getMessage());
            }
    
            Log::error('Exception fetching departure', [
                'tour_id' => $tourId,
                'departure_id' => $departureId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
    
            return [];
        }
    }
    

    public static function getPriceCategoriesByTour($tourId)
    {
        $accessToken = self::getAccessToken();
        $url = "https://api.sandbox.b2b.tourradar.com/v1/tours/{$tourId}/prices";
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        try {
            $response = Http::withHeaders($headers)->get($url);
            return $response->json();
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
/**
 * Sanitize a string so it will safely insert into utf8mb4 MySQL columns.
 */
private function sanitizeForMysql(string $s): string
{
    // 1) Ensure it's valid UTF-8 (drops invalid sequences)
    $s = @mb_convert_encoding($s, 'UTF-8', 'UTF-8');

    // 2) Normalize (if ext-intl available)
    if (class_exists(\Normalizer::class)) {
        $norm = \Normalizer::normalize($s, \Normalizer::FORM_C);
        if ($norm !== false) $s = $norm;
    }

    // 3) Replace common smart punctuation with ASCII equivalents
    $map = [
        "\xE2\x80\x98" => "'", // left single quote
        "\xE2\x80\x99" => "'", // right single quote
        "\xE2\x80\x9C" => '"', // left double quote
        "\xE2\x80\x9D" => '"', // right double quote
        "\xE2\x80\x93" => '-', // en dash
        "\xE2\x80\x94" => '-', // em dash
        "\xE2\x80\xA6" => '...', // ellipsis
        "\xC2\xA0"     => ' ', // non-break space
    ];
    $s = str_replace(array_keys($map), array_values($map), $s);

    // 4) Remove/ignore any remaining invalid UTF-8 bytes
    $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s);

    // 5) Remove control characters except newline/tab
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $s);

    // 6) Trim and return
    return trim($s);
}
        
private function saveTourToDatabase($tourData)
{
    try {
        $rawDescription = $tourData['description'] ?? ($tourData['itinerary'][0]['description'] ?? '');
        $cleanDescription = $this->sanitizeForMysql((string)$rawDescription);
        $shortDescription = mb_substr($cleanDescription, 0, 150); // adjust length as needed
        $images = $tourData['images'] ?? [];
        $image = collect($images)->firstWhere('type', 'image');
        $mapImage = collect($images)->firstWhere('type', 'map');
        $tourId = $tourData['tour_id'];
        // Fetch departures data
        $departuresData = $this->getDeparturesByTour($tourId);

        $departuresItems = $departuresData['items'] ?? [];

        //Log::info("Departures data {$departuresData}");
        //$this->info("Departures data {$departuresData}");
        $maxPerMonth = 2;

        if (isset($departuresData['items']) && is_array($departuresData['items'])) {
            // Group departures by year-month
            $grouped = [];
            foreach ($departuresData['items'] as $item) {
                $monthKey = substr($item['date'], 0, 7); // e.g. "2025-08"
                $grouped[$monthKey][] = $item;
            }
        
            // Build a new flat array, keeping only $maxPerMonth from each month
            $limitedItems = [];
            foreach ($grouped as $month => $itemsInMonth) {
                $slice = array_slice($itemsInMonth, 0, $maxPerMonth);
                $limitedItems = array_merge($limitedItems, $slice);
            }
        
            // Replace the original items array
            $departuresData['items'] = $limitedItems;
            $itemCount = count($departuresData['items']);
            Log::info("Found {$itemCount} departure items for tour ID {$tourId}");

            foreach ($departuresData['items'] as $departureData) {
                $depId = $departureData['id'] ?? null;
                Log::info('Processing departure', ['tour_id' => $tourId, 'departure_id' => $depId]);
                $this->info("Processing departure ID: {$depId}");
                usleep(200000); // 0.2 seconds in microseconds
                $departureDetails = $this->getDeparture($tourId,$departureData['id']); 
                
                // Proper logging: pass arrays as context or json_encode them
                Log::info('Processing accommodations for departure', [
                    'tour_id' => $tourId,
                    'departure_id' => $depId,
                    // logger will serialize the array context OK
                    'departure_details' => $departureDetails,
                ]);

                // Safely extract accommodations using data_get (handles missing keys)
                $accommodationsArr = data_get($departureDetails, 'prices.accommodations', []);
                if (is_string($accommodationsArr)) {
                    $decoded = json_decode($accommodationsArr, true);
                    $accommodationsArr = is_array($decoded) ? $decoded : [];
                }
                
                Departure::updateOrCreate(
                    ['id' => $depId], // Unique identifier.
                    [
                        'tour_id'               => $tourId, // save the tour_id
                        'date'                   => $departureData['date'],
                        'availability'           => $departureData['availability'],
                        'departure_type'         => $departureData['departure_type'],
                        'is_instant_confirmable' => $departureData['is_instant_confirmable'],
                        'currency'               => $departureData['currency'] ?? 'USD',
                        'based_on'               => $departureData['prices']['based_on'] ?? null,
                        'price_base'             => $departureData['prices']['price_base'] ?? 0,
                        'price_addons'           => $departureData['prices']['price_addons'] ?? 0,
                        'price_promotion'        => $departureData['prices']['price_total'] ?? 0,
                        'price_total_upfront'    => $departureData['prices']['price_total_upfront'] ?? 0,
                        'price_total'            => $departureData['prices']['price_total'] ?? 0,
                        'promotion'              => json_encode($departureData['prices']['promotion'] ?? []),
                        'mandatory_addons'       => json_encode($departureData['prices']['mandatory_addons'] ?? []),
                        'optional_extras'        => json_encode($departureData['optional_extras'] ?? []),
                        'accommodations'        => $accommodationsArr,
                    ]
                );
                Log::info("Saved departure ID: " . $departureData['id'] . " for tour ID: {$tourId}");
                $this->info("Saved departure ID: " . $departureData['id']);
            }
            Log::info("Finished processing departures of tour ID {$tourId}");
            $this->info("Finished processing departures of tour ID {$tourId}");
        } else {
            Log::warning("No departure items found for tour ID: {$tourId}");
            $this->warn("No departure items found for tour ID: {$tourId}");
        }

        $departureStatus = 'not_guaranteed';
		
        $pricesResponse = TourradarController::getPriceCategoriesByTour($tourData['tour_id']);
        $this->info("price response: " . json_encode($pricesResponse));
        // if your controller returns a JSON response object, you might need:
            $priceCategories = [];
            if (isset($pricesResponse['price_categories'])) {
                $priceCategories = $pricesResponse['price_categories'];
            }
        $this->info("price categories: " . json_encode($priceCategories)); 
        foreach ($departuresItems as $departure) {
            if ($departure['departure_type'] === 'guaranteed') {
                $departureStatus = 'guaranteed';
            }
			break;
        }

        //if ($departureStatus !== 'guaranteed') {
        //    $this->info("Tour {$tourData['tour_id']} does not have any guaranteed departures. Skipping...");
        //    return;
        //}
        
        $tour = Tour::updateOrCreate(
            ['tour_id' => $tourData['tour_id']],
            [
                'tour_name' => $tourData['tour_name'] ?? null,
                'locale' => $tourData['locale'] ?? null,
                'language' => $tourData['language'] ?? null,
                'is_active' => $tourData['is_active'] ?? false,
                'tour_length_days' => $tourData['tour_length_days'] ?? null,
                'start_city' => $tourData['start_city']['location_id'] ?? null,
                'end_city' => $tourData['end_city']['location_id'] ?? null,
                'is_instant_confirmable' => $tourData['is_instant_confirmable'] ?? false,
                'price_total' => $tourData['prices']['price_total'] ?? null,
                'price_currency' => $tourData['prices']['currency'] ?? null,
                'price_promotion' => $tourData['prices']['promotion']['discount'] ?? null,
                'reviews_count' => $tourData['reviews_count'] ?? null,
                'ratings_overall' => $tourData['ratings']['overall'] ?? null,
                'ratings_operator' => $tourData['ratings']['operator'] ?? null,
                'description' => $shortDescription,
                'min_age' => $tourData['age_range']['strict']['min_age'] ?? null,
                'max_age' => $tourData['age_range']['strict']['max_age'] ?? null,
                'max_group_size' => $tourData['max_group_size'] ?? null,
                'main_image' => $image['url'] ?? null,
                'main_thumbnail' => $image['thumbnail_url'] ?? null,
                'map_image' => $mapImage['url'] ?? null,
                'map_thumbnail' => $mapImage['thumbnail_url'] ?? null,
                'departures' => $departureStatus,
				'operator_id' => $tourData['operator']['id'] ?? null,
				'operator_name' => $tourData['operator']['name'] ?? null,
                'commission' => $tourData['prices']['partner_info']['commission_rate'] ?? null,
                'prices' => $priceCategories ?? null,
            ]
        );

        $this->info("Saved tour: {$tourData['tour_id']} - {$tourData['tour_name']}");

        // Save related cities
        if (isset($tourData['destinations']['cities'])) {
            $this->saveCitiesToDatabase($tourData['destinations']['cities'], $tourData['tour_id']);
        }
		
		// Save related countries
        if (isset($tourData['destinations']['countries'])) {
            $this->saveCountriesToDatabase($tourData['destinations']['countries'], $tourData['tour_id']);
        }
		// Save related natural destinations
        if (isset($tourData['destinations']['natural_destinations'])) {
            $this->saveNaturalsToDatabase($tourData['destinations']['natural_destinations'], $tourData['tour_id']);
        }
		
		// Save related tour types
        if (isset($tourData['tour_types'])) {
            $this->saveTypesToDatabase($tourData['tour_types'], $tourData['tour_id']);
        }

        //$this->weeklyHealth();
        

    } catch (\Exception $e) {
        $this->error("Error saving tour: {$tourData['tour_id']} - {$e->getMessage()}");
    }

}
private function weeklyHealth()
{
    // Initialize counters
    $totalChecked = 0;
    $passed       = 0;
    $failed       = 0;

    // Find all country IDs that we have tours for
    $countryIds = TourCountry::distinct('t_country_id')
                  ->pluck('t_country_id');

    foreach ($countryIds as $countryId) {
        usleep(500000);
        $this->info("Country {$countryId}: picking up to 20 tours…");

        // Grab 20 random tours in that country
        $tours = Tour::whereHas('countries', fn($q) => 
                    $q->where('t_country_id', $countryId))
                  ->inRandomOrder()
                  ->limit(10)
                  ->get();

        foreach ($tours as $tour) {
                    $totalChecked++;
                    $this->line(" → Tour {$tour->tour_id}: fetching summary departures…");
    
                    // A) Build date range string
                    $dateRange = Carbon::now()->format('Ymd')
                               . '-' 
                               . Carbon::now()->addMonths(6)->format('Ymd');
    
                    // B) Call the "departures" endpoint to get summaries
                    $summaryReq = new Request([
                        'tourId'     => $tour->tour_id,
                        'date_range' => $dateRange,
                    ]);
                    $summaryResp = app(ProxyTourRadarController::class)->departures($summaryReq);

                    $payload     = $summaryResp->getData(true);
    
                    // C) Validate the summary response
                    if (empty($payload['success'] ?? false)) {
                        $this->warn("    ERROR fetching departures summary.");
                        $tour->is_active = 3;
                        $tour->save();
                        continue;
                    }
    
                    $items = $payload['data']['items'] ?? [];
                    if (empty($items)) {
                        $this->warn("    No departures found.");
                        $tour->is_active = 3;
                        $tour->save();
                        continue;
                    }
    
                    // D) Pick one summary departure at random
                    $depSummary = Arr::random($items);
    
                    $this->line("    → picked departure {$depSummary['id']} (summary)");
    
                    // E) Fetch the detailed departure (to get accommodations)
                    $detailReq  = new Request([
                        'tourId'      => $tour->tour_id,
                        'departureId' => $depSummary['id'],
                    ]);

                    // 1) Call and decode (returns an array with success, data.items, etc.)
                    $detail = app(ProxyTourRadarController::class)->departure($detailReq);

                    $this->line("→ detail response: " . json_encode($detail));

                    // 2) Check for success
                    if (empty($detail['id'] ?? false)) {
                    $this->warn("ERROR fetching detailed departure.");
                    $tour->is_active = 3;
                    $tour->save();
                    continue;
                    }

                    
                    // 3) Pull out the first item
                    $firstItem = $detail ?? null;
                    if (! $firstItem) {
                    $this->warn("No detailed prices returned.");
                    $tour->is_active = 3;
                    $tour->save();
                    continue;
                    }

                    // 4) Get the accommodations array from that first item
                    $accoms = $firstItem['prices']['accommodations'] ?? [];

                    // 5) Check that every beds_number > 0
                    $allBedsPositive = collect($accoms)
                    ->pluck('beds_number')
                    ->every(fn($n) => $n > 0);

                    // … then combine with your other summary‐level rules …
                    $ok = 
                        ($firstItem['availability'] > 0)
                        && ($firstItem['departure_type'] === 'guaranteed')
                        && ($firstItem['is_instant_confirmable'] === true)
                        && $allBedsPositive;

                    // J) Update is_active using ->update([...]) instead of ->save()
                    Tour::where('tour_id', $tour->tour_id)
                        ->update(['is_active' => $ok ? 2 : 3]);

                    if ($ok) {
                        $passed++;
                        $this->info("    → Departure {$firstItem['id']} → PASS");
                    } else {
                        $failed++;
                        $this->warn("    → Departure {$firstItem['id']} → FAIL");
                    }

                    /* J) (Optional) Persist the detailed departure
                    Departure::updateOrCreate(
                        ['tour_id' => $tour->tour_id, 'departure_id' => $dep['id']],
                        [
                            'date'        => $dep['date'],
                            'availability'=> $dep['availability'],
                            'type'        => $dep['departure_type'],
                            'instant'     => $dep['is_instant_confirmable'],
                            'raw'         => json_encode($dep),
                        ]
                    );
                    */
                }
    }      

    $this->info("Summary: {$totalChecked} tours checked, {$passed} passed, {$failed} failed.");
}

private function saveCitiesToDatabase($cities, $tourId)
{
    $this->info("Saving cities for tour ID: {$tourId}");

    foreach ($cities as $cityData) {
        try {
            // Check if city exists, if not, create it
            $city = City::updateOrCreate(
                ['t_city_id' => $cityData['location_id']],
                [
                    'city_name' => $cityData['city_name'],
                    't_country_id' => $cityData['country_code']
                ]
            );

            // Log city object to debug
            $this->info("City object: " . json_encode($city));

            if ($tourId && $city->t_city_id) {
                // Attach the city to the tour
                TourCity::updateOrCreate(
                    ['tour_id' => $tourId, 't_city_id' => $city->t_city_id],
                );

                $this->info("Saved city: {$cityData['location_id']} - {$cityData['city_name']} for tour: {$tourId}");
            } else {
                $this->error("Tour or City not found for IDs: Tour ID - {$tourId}, City ID - {$city->t_city_id}");
            }
        } catch (\Exception $e) {
            $this->error("Error saving city: {$cityData['location_id']} - {$e->getMessage()}");
        }
    }
}
	
private function saveCountriesToDatabase($countries, $tourId)
{
    $this->info("Saving countries for tour ID: {$tourId}");

    foreach ($countries as $countryData) {
        try {
            // Check if country exists, if not, create it
            $country = country::updateOrCreate(
                ['t_country_id' => $countryData['location_id']],
                [
                    'name' => $countryData['country_name'],
                    'country_code' => $countryData['country_code']
                ]
            );

            // Log country object to debug
            $this->info("country object: " . json_encode($country));

            if ($tourId && $country->t_country_id) {
                // Attach the country to the tour
                Tourcountry::updateOrCreate(
                    ['tour_id' => $tourId, 't_country_id' => $country->t_country_id],
                );

                $this->info("Saved country: {$countryData['location_id']} - {$countryData['country_name']} for tour: {$tourId}");
            } else {
                $this->error("Tour or country not found for IDs: Tour ID - {$tourId}, country ID - {$country->t_country_id}");
            }
        } catch (\Exception $e) {
            $this->error("Error saving country: {$countryData['location_id']} - {$e->getMessage()}");
        }
    }
}

	
private function saveNaturalsToDatabase($natural_destinations, $tourId)
{
    $this->info("Saving natural_destinations for tour ID: {$tourId}");

    foreach ($natural_destinations as $natural) {
        try {
            // Prefer location_id, fall back to other possible keys
            $externalId = data_get($natural, 'location_id') ?? data_get($natural, 'locationId') ?? null;
            $name = data_get($natural, 'natural_destination_name') ?? data_get($natural, 'naturalDestinationName') ?? null;
            $type = data_get($natural, 'type') ?? null;

            // If we don't have an external id, skip and log (you can change behaviour if desired)
            if (empty($externalId) || !is_numeric($externalId) || (int)$externalId <= 0) {
                Log::warning("Natural destination missing external location_id; skipping", [
                    'tour_id' => $tourId,
                    'natural' => $natural,
                ]);
                $this->warn("Skipping natural destination without location_id: " . ($name ?? json_encode($natural)));
                continue;
            }

            // Normalize values
            $externalId = (int)$externalId;
            $name = $name ? trim($name) : null;
            $type = $type ? trim($type) : null;

            // updateOrCreate by external id (t_natural_id)
            $naturalModel = NaturalDestination::updateOrCreate(
                ['t_natural_id' => $externalId],
                [
                    'destination_name' => $name,
                    'destination_type' => $type,
                ]
            );

            $this->info("NaturalDestination object: " . json_encode($naturalModel));
            $this->info("Saved NaturalDestination: {$naturalModel->t_natural_id} - {$naturalModel->destination_name} for tour: {$tourId}");

            // Link natural destination with tour: ensure we use the external id (not 0)
            TourNaturalDestination::updateOrCreate(
                ['tour_id' => $tourId, 't_natural_id' => $naturalModel->t_natural_id],
                ['attached_at' => now()] // optional extra fields; pass [] if none
            );

        } catch (\Throwable $e) {
            $this->error("Error saving NaturalDestination for tour {$tourId}: " . $e->getMessage());
            Log::error('sync:tours - save natural destination error', [
                'tour_id' => $tourId,
                'natural' => $natural,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}


private function saveTypesToDatabase($types, $tourId)
{
    $this->info("Saving types for tour ID: {$tourId}");

    foreach ($types as $typeData) {
        try {
            // Check if type exists, if not, create it
            $type = Type::updateOrCreate(
                ['tour_type_id' => $typeData['type_id']],
                [
                    'tourtype_name' => $typeData['type_name'],
                    'group_id' => $typeData['group_id'],
                    'group_name' => $typeData['group_name']
                ]
            );

            // Log type object to debug
            $this->info("type object: " . json_encode($type));

            if ($tourId && $type->tour_type_id) {
                // Attach the type to the tour
                TourType::updateOrCreate(
                    ['tour_id' => $tourId, 'tour_type_id' => $type->tour_type_id],
                );

                $this->info("Saved type: {$typeData['type_id']} - {$typeData['type_name']} for tour: {$tourId}");
            } else {
                $this->error("Tour or type not found for IDs: Tour ID - {$tourId}, type ID - {$type->tour_type_id}");
            }
        } catch (\Exception $e) {
            $this->error("Error saving type: {$typeData['type_id']} - {$e->getMessage()}");
        }
    }
}   



}
