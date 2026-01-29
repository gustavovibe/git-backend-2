<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\TourSyncService;
use Illuminate\Support\Facades\Log;

class SyncPagesBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $startPage;
    protected $endPage;
    protected $dateRangeStart;
    protected $dateRangeEnd;

    public function __construct($startPage, $endPage, $dateRangeStart = null, $dateRangeEnd = null)
    {
        $this->startPage = (int)$startPage;
        $this->endPage = (int)$endPage;
        $this->dateRangeStart = $dateRangeStart;
        $this->dateRangeEnd = $dateRangeEnd;
    }

    public function handle(TourSyncService $service)
    {
        Log::info("SyncPagesBatchJob: Starting pages {$this->startPage}-{$this->endPage}");

        if ($this->dateRangeStart && $this->dateRangeEnd) {
            $service->setDateRange($this->dateRangeStart, $this->dateRangeEnd);
        }

        for ($page = $this->startPage; $page <= $this->endPage; $page++) {
            try {
                $service->syncPage($page);
            } catch (\Throwable $e) {
                Log::error("SyncPagesBatchJob: Error syncing page {$page}: " . $e->getMessage(), [
                    'page' => $page,
                    'exception' => $e,
                ]);
            }
        }

        Log::info("SyncPagesBatchJob: Finished pages {$this->startPage}-{$this->endPage}");
    }
}
