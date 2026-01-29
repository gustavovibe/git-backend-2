<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SyncPageJob;
use Carbon\Carbon;
class SyncToursDataBatches extends Command
{
    protected $signature = 'sync:tours-batches 
    {pages? : Page(s) to sync. Examples: "1-10", "5", "1,3,5"} 
    {--pages= : Page(s) to sync (same formats as argument)} 
    {--date_range= : Date range in Ymd-Ymd (e.g. 20251011-20251111)}
    {--batch_size= : Number of pages per batch (e.g. 10)}
    {--driver=queue : How to dispatch: "queue" (Laravel Queue) or "http" (Cloud Tasks style)}
    {--url= : Base URL for HTTP driver (e.g. https://api.vibe.com)}';

    protected $description = 'Dispatch tour sync tasks in batches to avoid timeouts';

    public function handle()
    {
        $raw = $this->argument('pages') ?? $this->option('pages') ?? null;

        // Accept "pages:1-10" too (e.g. php artisan sync:tours-batches pages:1-10)
        if ($raw && str_starts_with($raw, 'pages:')) {
            $raw = substr($raw, strlen('pages:'));
        }

        $dateRangeRaw = $this->option('date_range') ?? null;

        // Also allow date_range inside the raw token(s)
        if (!$dateRangeRaw && is_string($raw)) {
            if (preg_match('/date_range[:=]([0-9]{8}-[0-9]{8})/', $raw, $m)) {
                $dateRangeRaw = $m[1];
                $raw = trim(str_replace($m[0], '', $raw));
            }
        }

        $pages = $this->parsePagesInput($raw ?: '1-300');
        $driver = $this->option('driver') ?? 'queue';
        $batchSize = $this->option('batch_size');
        $baseUrl = $this->option('url') ?? config('app.url');

        if (empty($pages)) {
            $this->error("No valid pages provided.");
            return 1;
        }

        if (!in_array($driver, ['queue', 'http'], true)) {
            $this->error("Invalid driver: {$driver}. Use 'queue' or 'http'.");
            return 1;
        }

        if ($batchSize !== null) {
            $batchSize = (int)$batchSize;
            if ($batchSize <= 0) {
                $this->error("Invalid batch_size: {$batchSize}. Must be a positive integer.");
                return 1;
            }
        }

        $dateRangeStart = null;
        $dateRangeEnd = null;

        if ($dateRangeRaw) {
            $dateRangeRaw = trim($dateRangeRaw);
            if (!preg_match('/^\d{8}-\d{8}$/', $dateRangeRaw)) {
                $this->error("Invalid date_range format. Expected Ymd-Ymd, e.g. 20251011-20251111");
                return 1;
            }
            [$dateRangeStart, $dateRangeEnd] = explode('-', $dateRangeRaw, 2);
            if ($dateRangeEnd < $dateRangeStart) {
                $this->error("Invalid date_range: end date is before start date.");
                return 1;
            }
            $this->info("Using date_range: {$dateRangeStart}-{$dateRangeEnd}");
        } else {
            $this->info("No date_range provided. Using default date window in worker.");
        }

        $totalPages = count($pages);
        if ($batchSize) {
            $this->info("Dispatching {$totalPages} pages in batches of {$batchSize} using {$driver} driver...");
            $chunks = array_chunk($pages, $batchSize);
            foreach ($chunks as $chunk) {
                $start = $chunk[0];
                $end = $chunk[count($chunk) - 1];
                if ($driver === 'http') {
                    $this->dispatchHttpBatch($start, $end, $baseUrl, $dateRangeStart, $dateRangeEnd);
                } else {
                    \App\Jobs\SyncPagesBatchJob::dispatch($start, $end, $dateRangeStart, $dateRangeEnd);
                    $this->line("Queued pages {$start}-{$end}");
                }
            }
        } else {
            $this->info("Dispatching {$totalPages} pages using {$driver} driver...");
            foreach ($pages as $page) {
                if ($driver === 'http') {
                    $this->dispatchHttp($page, $baseUrl, $dateRangeStart, $dateRangeEnd);
                } else {
                    SyncPageJob::dispatch($page, $dateRangeStart, $dateRangeEnd);
                    $this->line("Queued page {$page}");
                }
            }
        }

        $this->info("All batches have been dispatched.");
        return 0;
    }

    private function dispatchHttp($page, $baseUrl, $start, $end)
    {
        // This simulates what Cloud Tasks would do: call the endpoint.
        // In a real GCP setup, you would use the Google Cloud Tasks SDK here 
        // to create a task that calls this URL.
        
        $url = rtrim($baseUrl, '/') . "/api/sync/page/{$page}";
        $query = [];
        if ($start) $query['date_range_start'] = $start;
        if ($end) $query['date_range_end'] = $end;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        // We don't want to WAIT for the response, so we just log the URL
        // In reality, this command would interact with GCP Cloud Tasks API.
        $this->line("Cloud Task URL (manual trigger): {$url}");
        
        // Example of how you might actually call Cloud Tasks if you had the SDK:
        // $client->createTask($queueName, (new Task())->setHttpRequest(
        //    (new HttpRequest())->setUrl($url)->setHttpMethod(HttpMethod::GET)
        // ));
    }

    private function dispatchHttpBatch($startPage, $endPage, $baseUrl, $start, $end)
    {
        $url = rtrim($baseUrl, '/') . "/api/sync/pages";
        $query = [
            'start' => $startPage,
            'end' => $endPage,
        ];
        if ($start) $query['date_range_start'] = $start;
        if ($end) $query['date_range_end'] = $end;

        $url .= '?' . http_build_query($query);
        $this->line("Cloud Task URL (manual trigger): {$url}");
    }

    private function parsePagesInput(string $input): array
    {
        $input = trim($input);
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

        if (preg_match('/^(\d+)-(\d+)$/', $input, $matches)) {
            $start = (int)$matches[1];
            $end = (int)$matches[2];
            return ($start <= $end) ? range($start, $end) : [];
        }

        if (preg_match('/^\d+$/', $input)) {
            return [(int)$input];
        }

        return [];
    }
}
