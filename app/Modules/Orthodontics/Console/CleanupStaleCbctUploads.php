<?php

namespace App\Modules\Orthodontics\Console;

use App\Models\Tenant;
use App\Modules\Orthodontics\Cbct\CbctService;
use App\Modules\Orthodontics\Models\OrthodonticCbctStudy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the partial files of CBCT uploads that were started but never
 * finished (closed browser, lost connection). Completed studies are never
 * touched. Schedule it daily: php artisan orthodontics:cleanup-cbct
 */
class CleanupStaleCbctUploads extends Command
{
    protected $signature = 'orthodontics:cleanup-cbct {--tenant= : Only this tenant id} {--hours= : Age in hours (default from config)}';

    protected $description = 'Mark abandoned CBCT uploads failed and delete their partial files';

    public function handle(CbctService $cbct): int
    {
        $hours = (int) ($this->option('hours') ?: config('orthodontics.cbct.stale_upload_hours', 24));
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $id) => $q->where('id', $id))->get();

        foreach ($tenants as $tenant) {
            try {
                $count = $tenant->run(function () use ($cbct, $hours) {
                    if (!Schema::hasTable('orthodontic_cbct_studies')) {
                        return 0;
                    }
                    $stale = OrthodonticCbctStudy::where('status', OrthodonticCbctStudy::STATUS_UPLOADING)
                        ->where('updated_at', '<', now()->subHours($hours))
                        ->get();
                    foreach ($stale as $study) {
                        $cbct->abandon($study, 'Upload not finished within ' . $hours . ' hours');
                    }

                    return $stale->count();
                });
                $this->line("  {$tenant->id}: {$count} abandoned upload(s) cleaned");
            } catch (\Throwable $e) {
                $this->error("  {$tenant->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
