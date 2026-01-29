<?php

namespace App\Services;

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
use App\Models\Departure;
use Carbon\Carbon;
use App\Http\Controllers\TourRadarController;

class TourSyncService
{
    private $token;
    private $dateRangeStart = null;
    private $dateRangeEnd = null;

    public function setDateRange($start, $end)
    {
        $this->dateRangeStart = $start;
        $this->dateRangeEnd = $end;
    }

    public function syncPage($page)
    {
        $this->token = $this->getAccessToken();

        Log::info("TourSyncService: Processing page {$page}...");

        try {
            $response = $this->fetchDataFromApi($page);
        } catch (\Throwable $e) {
            Log::error("TourSyncService: Error fetching page {$page}: " . $e->getMessage());
            throw $e;
        }

        $tours = $response['items'] ?? [];

        Log::info("TourSyncService: Number of tours on page {$page}: " . count($tours));

        if (!empty($tours)) {
            foreach ($tours as $tourData) {
                try {
                    $this->saveTourToDatabase($tourData);
                } catch (\Throwable $e) {
                    Log::error("TourSyncService: Error saving tour (page {$page}): " . $e->getMessage(), [
                        'tour_id' => $tourData['tour_id'] ?? 'unknown',
                        'exception' => $e
                    ]);
                }
            }
            return count($tours);
        }

        return 0;
    }

    private function getAccessToken()
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

            return $response->json('access_token');
        } catch (\Exception $e) {
            Log::error('TourSyncService: Error fetching access token: ' . $e->getMessage());
            throw $e;
        }
    }

    private function fetchDataFromApi($currentPage)
    {
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

        if (isset($responseData['error'])) {
            throw new \Exception("API Error on page {$currentPage}: " . json_encode($responseData['error']));
        }

        return $responseData;
    }

    private function saveTourToDatabase($tourData)
    {
        $rawDescription = $tourData['description'] ?? ($tourData['itinerary'][0]['description'] ?? '');
        $cleanDescription = $this->sanitizeForMysql((string)$rawDescription);
        $shortDescription = mb_substr($cleanDescription, 0, 150);
        
        $images = $tourData['images'] ?? [];
        $image = collect($images)->firstWhere('type', 'image');
        $mapImage = collect($images)->firstWhere('type', 'map');
        $tourId = $tourData['tour_id'];

        // Fetch departures
        $departuresData = $this->getDeparturesByTour($tourId);
        $departuresItems = $departuresData['items'] ?? [];

        if (!empty($departuresItems)) {
            // Keep only 2 departures per month
            $grouped = [];
            foreach ($departuresItems as $item) {
                $monthKey = substr($item['date'], 0, 7);
                $grouped[$monthKey][] = $item;
            }
        
            $limitedItems = [];
            foreach ($grouped as $month => $itemsInMonth) {
                $slice = array_slice($itemsInMonth, 0, 2);
                $limitedItems = array_merge($limitedItems, $slice);
            }
        
            foreach ($limitedItems as $departureData) {
                $depId = $departureData['id'] ?? null;
                usleep(200000);
                $departureDetails = $this->getDeparture($tourId, $depId); 
                
                $accommodationsArr = data_get($departureDetails, 'prices.accommodations', []);
                if (is_string($accommodationsArr)) {
                    $decoded = json_decode($accommodationsArr, true);
                    $accommodationsArr = is_array($decoded) ? $decoded : [];
                }
                
                Departure::updateOrCreate(
                    ['id' => $depId],
                    [
                        'tour_id'               => $tourId,
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
            }
        }

        $departureStatus = 'not_guaranteed';
        $pricesResponse = TourRadarController::getPriceCategoriesByTour($tourId);
        $priceCategories = $pricesResponse['price_categories'] ?? [];

        foreach ($departuresItems as $departure) {
            if (($departure['departure_type'] ?? '') === 'guaranteed') {
                $departureStatus = 'guaranteed';
                break;
            }
        }

        $tour = Tour::updateOrCreate(
            ['tour_id' => $tourId],
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
                'prices' => $priceCategories,
            ]
        );

        if (isset($tourData['destinations']['cities'])) {
            $this->saveCitiesToDatabase($tourData['destinations']['cities'], $tourId);
        }
        if (isset($tourData['destinations']['countries'])) {
            $this->saveCountriesToDatabase($tourData['destinations']['countries'], $tourId);
        }
        if (isset($tourData['destinations']['natural_destinations'])) {
            $this->saveNaturalsToDatabase($tourData['destinations']['natural_destinations'], $tourId);
        }
        if (isset($tourData['tour_types'])) {
            $this->saveTypesToDatabase($tourData['tour_types'], $tourId);
        }
    }

    private function getDeparturesByTour($tourId)
    {
        usleep(1000000);
        $start = $this->dateRangeStart ?: Carbon::now()->addDays(1)->format('Ymd');
        $end = $this->dateRangeEnd ?: Carbon::now()->addDays(91)->format('Ymd');

        $url = "https://api.sandbox.b2b.tourradar.com/v1/tours/{$tourId}/departures?date_range={$start}-{$end}&user_country=185&currency=USD";
        
        try {
            $response = Http::withToken($this->token)->get($url);
            return $response->json();
        } catch (\Exception $e) {
            Log::error("TourSyncService: Error fetching departures for tour {$tourId}: " . $e->getMessage());
            return [];
        }
    }

    private function getDeparture($tourId, $departureId)
    {
        usleep(1000000);
        $url = "https://api.sandbox.b2b.tourradar.com/v1/tours/{$tourId}/departures/{$departureId}";
    
        try {
            $response = Http::withToken($this->token)->get($url);
            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error("TourSyncService: Exception fetching departure {$departureId} for tour {$tourId}: " . $e->getMessage());
            return [];
        }
    }

    private function sanitizeForMysql(string $s): string
    {
        $s = @mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $norm = \Normalizer::normalize($s, \Normalizer::FORM_C);
            if ($norm !== false) $s = $norm;
        }
        $map = [
            "\xE2\x80\x98" => "'", "\xE2\x80\x99" => "'", "\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"',
            "\xE2\x80\x93" => '-', "\xE2\x80\x94" => '-', "\xE2\x80\xA6" => '...', "\xC2\xA0"     => ' ',
        ];
        $s = str_replace(array_keys($map), array_values($map), $s);
        $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $s);
        return trim($s);
    }

    private function saveCitiesToDatabase($cities, $tourId)
    {
        foreach ($cities as $cityData) {
            try {
                $city = City::updateOrCreate(
                    ['t_city_id' => $cityData['location_id']],
                    ['city_name' => $cityData['city_name'], 't_country_id' => $cityData['country_code']]
                );
                TourCity::updateOrCreate(['tour_id' => $tourId, 't_city_id' => $city->t_city_id]);
            } catch (\Exception $e) {
                Log::error("TourSyncService: Error saving city: " . $e->getMessage());
            }
        }
    }

    private function saveCountriesToDatabase($countries, $tourId)
    {
        foreach ($countries as $countryData) {
            try {
                $country = Country::updateOrCreate(
                    ['t_country_id' => $countryData['location_id']],
                    ['name' => $countryData['country_name'], 'country_code' => $countryData['country_code']]
                );
                TourCountry::updateOrCreate(['tour_id' => $tourId, 't_country_id' => $country->t_country_id]);
            } catch (\Exception $e) {
                Log::error("TourSyncService: Error saving country: " . $e->getMessage());
            }
        }
    }

    private function saveNaturalsToDatabase($natural_destinations, $tourId)
    {
        foreach ($natural_destinations as $natural) {
            try {
                $externalId = data_get($natural, 'location_id') ?? data_get($natural, 'locationId') ?? null;
                if (empty($externalId)) continue;

                $naturalModel = NaturalDestination::updateOrCreate(
                    ['t_natural_id' => (int)$externalId],
                    [
                        'destination_name' => data_get($natural, 'natural_destination_name') ?? data_get($natural, 'naturalDestinationName'),
                        'destination_type' => data_get($natural, 'type')
                    ]
                );
                TourNaturalDestination::updateOrCreate(
                    ['tour_id' => $tourId, 't_natural_id' => $naturalModel->t_natural_id],
                    ['attached_at' => now()]
                );
            } catch (\Throwable $e) {
                Log::error("TourSyncService: Error saving NaturalDestination: " . $e->getMessage());
            }
        }
    }

    private function saveTypesToDatabase($types, $tourId)
    {
        foreach ($types as $typeData) {
            try {
                $type = Type::updateOrCreate(
                    ['tour_type_id' => $typeData['type_id']],
                    ['tourtype_name' => $typeData['type_name'], 'group_id' => $typeData['group_id'], 'group_name' => $typeData['group_name']]
                );
                TourType::updateOrCreate(['tour_id' => $tourId, 'tour_type_id' => $type->tour_type_id]);
            } catch (\Exception $e) {
                Log::error("TourSyncService: Error saving type: " . $e->getMessage());
            }
        }
    }
}
