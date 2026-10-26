<?php

namespace App\Console\Commands;

use App\Models\AccountDeletionRequest;
use App\Services\AccountDeletionService;
use Illuminate\Console\Command;

/**
 * Permanently removes accounts whose deletion grace period has ended.
 *
 * Usage:
 *   php artisan accounts:purge-deleted            # Purge due requests
 *   php artisan accounts:purge-deleted --dry-run  # List them only
 *
 * To undo a request before it is due, set cancelled_at on its
 * account_deletion_requests row and is_active back to 1 on the user.
 */
class PurgeDeletedAccounts extends Command
{
    protected $signature = 'accounts:purge-deleted
                            {--dry-run : List due requests without deleting anything}';

    protected $description = 'Permanently delete accounts whose deletion grace period has ended';

    public function handle(AccountDeletionService $deletions): int
    {
        $due = AccountDeletionRequest::due()->orderBy('id')->get();

        if ($due->isEmpty()) {
            $this->info('No account deletions are due.');
            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($due as $deletionRequest) {
            $label = "#{$deletionRequest->id} tenant={$deletionRequest->tenant_id}"
                . ($deletionRequest->deletes_clinic ? ' (whole clinic)' : '');

            if ($this->option('dry-run')) {
                $this->line("Would purge {$label}");
                continue;
            }

            try {
                $deletions->purge($deletionRequest);
                $this->info("Purged {$label}");
            } catch (\Throwable $e) {
                $failed++;
                report($e);
                $this->error("Failed {$label}: {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
