<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TourSyncService;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    protected $tourSyncService;

    public function __construct(TourSyncService $tourSyncService)
    {
        $this->tourSyncService = $tourSyncService;
    }

    /**
     * Sync a single page of tours.
     * Accessible via GET /api/sync/page/{page}
     */
    public function syncPage(Request $request, $page)
    {
        Log::info("SyncController: HTTP request to sync page {$page}");
        
        $dateRangeStart = $request->query('date_range_start');
        $dateRangeEnd = $request->query('date_range_end');

        if ($dateRangeStart && $dateRangeEnd) {
            $this->tourSyncService->setDateRange($dateRangeStart, $dateRangeEnd);
        }

        try {
            $count = $this->tourSyncService->syncPage($page);
            return response()->json([
                'success' => true,
                'message' => "Synced {$count} tours on page {$page}",
                'page' => $page,
                'count' => $count
            ]);
        } catch (\Throwable $e) {
            Log::error("SyncController: Error syncing page {$page}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'page' => $page
            ], 500);
        }
    }

    /**
     * Sync a range of pages.
     * Accessible via GET /api/sync/pages?start=1&end=10
     */
    public function syncPages(Request $request)
    {
        $start = (int)$request->query('start');
        $end = (int)$request->query('end');

        if ($start <= 0 || $end <= 0 || $end < $start) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid start/end range.',
            ], 422);
        }

        Log::info("SyncController: HTTP request to sync pages {$start}-{$end}");

        $dateRangeStart = $request->query('date_range_start');
        $dateRangeEnd = $request->query('date_range_end');

        if ($dateRangeStart && $dateRangeEnd) {
            $this->tourSyncService->setDateRange($dateRangeStart, $dateRangeEnd);
        }

        try {
            $total = 0;
            for ($page = $start; $page <= $end; $page++) {
                $total += $this->tourSyncService->syncPage($page);
            }

            return response()->json([
                'success' => true,
                'message' => "Synced {$total} tours on pages {$start}-{$end}",
                'start' => $start,
                'end' => $end,
                'count' => $total,
            ]);
        } catch (\Throwable $e) {
            Log::error("SyncController: Error syncing pages {$start}-{$end}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'start' => $start,
                'end' => $end,
            ], 500);
        }
    }
}
