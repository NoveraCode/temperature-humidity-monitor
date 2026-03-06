<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeOldLogs extends Command
{
    protected $signature = 'purge:old-logs';

    protected $description = 'Delete sensor_logs and sensor_readings older than 90 days';

    public function handle(): int
    {
        $cutoff = now()->subDays(90);

        $logsDeleted = DB::table('sensor_logs')->where('created_at', '<', $cutoff)->delete();
        $readingsDeleted = DB::table('sensor_readings')->where('created_at', '<', $cutoff)->delete();

        $this->info("Purged {$logsDeleted} sensor_logs and {$readingsDeleted} sensor_readings older than 90 days.");

        return self::SUCCESS;
    }
}
