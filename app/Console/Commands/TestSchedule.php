<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test scheduler command';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('✅ Test executed at: ' . now());
        \Log::info('Test scheduler executed at: ' . now());
        return 0;
    }
}
