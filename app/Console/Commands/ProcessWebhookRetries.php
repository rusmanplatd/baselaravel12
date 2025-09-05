<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessWebhookRetries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhooks:process-retries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process failed webhook deliveries and retry them';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\WebhookService $webhookService)
    {
        $this->info('Processing webhook retries...');
        
        $processed = $webhookService->processRetries();
        
        $this->info("Processed {$processed} webhook retries");
        
        return 0;
    }
}
