<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

return new class extends Migration
{
    /**
     * Run the migrations on all tenant databases.
     */
    public function up(): void
    {
        // Get all tenants
        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            echo "No tenants found.\n";
            return;
        }

        echo "Found {$tenants->count()} tenant(s) to process.\n\n";

        $processed = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            echo "┌─ Tenant: {$tenant->id}\n";

            try {
                $tenant->run(function () {
                    // Check if column already exists
                    if (!Schema::hasColumn('cases', 'case_date')) {
                        Schema::table('cases', function (Blueprint $table) {
                            $table->dateTime('case_date')->nullable()->after('is_paid');
                        });
                        echo "│  ✓ Added case_date column\n";
                    } else {
                        echo "│  ✓ case_date column already exists\n";
                    }
                    
                    // Update existing records to have created_at as case_date
                    $updated = DB::table('cases')
                        ->whereNull('case_date')
                        ->update(['case_date' => DB::raw('created_at')]);
                    
                    echo "│  ✓ Updated {$updated} case(s) with case_date\n";
                });
                
                echo "└─ ✅ Done: {$tenant->id}\n";
                $processed++;
            } catch (\Exception $e) {
                echo "└─ ❌ Failed: {$tenant->id}\n";
                echo "   Error: " . $e->getMessage() . "\n";
                $failed++;
            }

            echo "\n";
        }

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "✅ Summary: {$processed} succeeded, {$failed} failed\n";
        echo "\n";
    }

    /**
     * Reverse the migrations on all tenant databases.
     */
    public function down(): void
    {
        // Get all tenants
        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            echo "No tenants found.\n";
            return;
        }

        echo "Found {$tenants->count()} tenant(s) to rollback.\n\n";

        $processed = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            echo "┌─ Tenant: {$tenant->id}\n";

            try {
                $tenant->run(function () {
                    // Check if column exists before dropping
                    if (Schema::hasColumn('cases', 'case_date')) {
                        Schema::table('cases', function (Blueprint $table) {
                            $table->dropColumn('case_date');
                        });
                        echo "│  ✓ Dropped case_date column\n";
                    } else {
                        echo "│  ✓ case_date column not found\n";
                    }
                });
                
                echo "└─ ✅ Done: {$tenant->id}\n";
                $processed++;
            } catch (\Exception $e) {
                echo "└─ ❌ Failed: {$tenant->id}\n";
                echo "   Error: " . $e->getMessage() . "\n";
                $failed++;
            }

            echo "\n";
        }

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "✅ Summary: {$processed} succeeded, {$failed} failed\n";
        echo "\n";
    }
};
