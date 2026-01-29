<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\TourSyncService;
use Illuminate\Support\Facades\Log;

class SyncPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $page;
    protected $dateRangeStart;
    protected $dateRangeEnd;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($page, $dateRangeStart = null, $dateRangeEnd = null)
    {
        $this->page = $page;
        $this->dateRangeStart = $dateRangeStart;
        $this->dateRangeEnd = $dateRangeEnd;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(TourSyncService $service)
    {
        Log::info("SyncPageJob: Starting sync for page {$this->page}");
        
        if ($this->dateRangeStart && $this->dateRangeEnd) {
            $service->setDateRange($this->dateRangeStart, $this->dateRangeEnd);
        }

        $service->syncPage($this->page);
        
        Log::info("SyncPageJob: Finished sync for page {$this->page}");
    }
}
