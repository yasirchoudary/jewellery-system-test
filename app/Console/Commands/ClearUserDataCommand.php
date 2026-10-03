<?php

namespace App\Console\Commands;

use App\Services\DataCleanupService;
use Illuminate\Console\Command;

class ClearUserDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-user-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes all user-entered data entries from the database while keeping user logins and system configuration intact';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting database cleanup for user-entered data entries...');

        $report = DataCleanupService::clearUserData();

        foreach ($report as $table => $status) {
            $this->line("<comment>{$table}:</comment> {$status}");
        }

        $this->info('Cleanup completed successfully. Logins and system configurations remain untouched.');

        return 0;
    }
}
