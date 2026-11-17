<?php

namespace App\Console\Commands;

use App\Support\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifySecurityAudit extends Command
{
    protected $signature = 'relay:verify-audit {--limit=1000 : Inspect at most the most recent N events (1 to 5000)}';

    protected $description = 'Verify integrity tags on recent security events without exposing their metadata';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 5000) {
            $this->error('The --limit value must be between 1 and 5000.');

            return self::FAILURE;
        }

        $inspected = 0;
        $failures = 0;
        foreach (DB::table('security_events')->orderByDesc('id')->limit($limit)->cursor() as $row) {
            $inspected++;
            if (! SecurityAudit::verifyRecord($row)) {
                $failures++;
            }
        }

        if ($failures > 0) {
            $this->error("Found {$failures} modified security events in {$inspected} inspected rows.");

            return self::FAILURE;
        }

        $this->info("Verified {$inspected} recent security event rows. Row deletion cannot be detected by this check.");

        return self::SUCCESS;
    }
}
