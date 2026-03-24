<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\EmbeddingService;
use App\Services\VectorSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestAiChatbot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:test {tenant_id} {question}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the AI chatbot for a specific tenant';

    /**
     * Execute the console command.
     */
    public function handle(VectorSearchService $vectorSearchService, EmbeddingService $embeddingService)
    {
        $tenantId = $this->argument('tenant_id');
        $question = $this->argument('question');

        $this->info("Initializing tenant: {$tenantId}");
        
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant not found!");
            return 1;
        }

        tenancy()->initialize($tenant);

        // Manually configure the tenant connection for local testing
        $dbName = $tenant->db_name ?? (config('tenancy.database.prefix') . $tenant->id);
        $dbUsername = $tenant->db_username ?? $dbName;
        $dbPassword = $tenant->db_password ?? env('TENANT_DB_PASSWORD', '');
        $centralConfig = config('database.connections.central');
        
        config([
            'database.connections.tenant.database' => $dbName,
            'database.connections.tenant.username' => $dbUsername,
            'database.connections.tenant.password' => $dbPassword,
            'database.connections.tenant.host' => $centralConfig['host'] ?? '127.0.0.1',
            'database.connections.tenant.port' => $centralConfig['port'] ?? '3306',
        ]);
        DB::purge('tenant');

        $this->info("Tenant initialized successfully (DB: {$dbName}).");
        $this->newLine();
        
        $patient = \App\Models\Patient::where('name', 'like', '%علي طالب%')->first();
        if ($patient) {
            $this->info("Found Patient: " . $patient->name . " | Phone: " . $patient->phone);
            $eb = DB::connection('pgsql_embeddings')->table('embeddings')->where('record_id', $patient->id)->where('table_name', 'patients')->first();
            if ($eb) {
                $this->info("Has embedding: YES");
            } else {
                $this->info("Has embedding: NO");
            }
        } else {
            $this->error("Patient Ali Talib not found in DB.");
        }

        // 1. First sync embeddings if they don't exist
        $this->info("Step 1: Skipping bulk sync to save OpenAI credits...");
        // try {
        //     $patients = $embeddingService->bulkSync(\App\Models\Patient::class, $tenantId, 10);
        //     $this->line("Patients synced: " . $patients['synced']);
        // } catch (\Exception $e) {
        //     $this->error("Failed to sync patients: " . $e->getMessage());
        // }

        $this->newLine();
        $this->info("Step 2: Asking AI...");
        $this->line("Question: " . $question);
        $this->newLine();

        $this->info("Thinking... (this takes a few seconds to call OpenAI)");
        
        try {
            $result = $vectorSearchService->chat($tenantId, $question);

            if (!$result['success']) {
                $this->error("AI returned an error: " . ($result['error'] ?? 'Unknown error'));
                return 1;
            }

            $this->info("=== AI RESPONSE ===");
            $this->line($result['answer']);
            $this->newLine();
            
            $this->info("=== SOURCES FOUND ===");
            if (empty($result['sources'])) {
                $this->line("No relevant sources found in the database.");
            } else {
                foreach ($result['sources'] as $source) {
                    $this->line("- " . $source['content']);
                }
            }

        } catch (\Exception $e) {
            $this->error("Exception occurred: " . $e->getMessage());
            $this->error("Trace: " . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
