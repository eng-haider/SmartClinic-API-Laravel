<?php

use App\Services\EmbeddingService;
use App\Services\VectorSearchService;
use App\Models\Embedding;
use Illuminate\Support\Facades\DB;

echo "--- Starting AI Test ---\n";

// 1. Manually initialize the vector search service
$embeddingService = new EmbeddingService();
$vectorSearchService = new VectorSearchService($embeddingService);

echo "\n--- Generating Test Embedding ---\n";
// 2. Generate an embedding directly to test OpenAI connection
try {
    $vector = $embeddingService->generateEmbedding('Ahmed is a 30 year old male patient with a toothache.');
    echo "✅ OpenAI Connection Successful! Generated vector of length: " . count($vector) . "\n";
} catch (\Exception $e) {
    echo "❌ OpenAI Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Testing Supabase Vector Insert ---\n";
// 3. Test saving to Supabase
try {
    // Generate a vector using the raw SQL binding to test the pgvector syntax
    DB::connection('pgsql_embeddings')->statement(
        "INSERT INTO embeddings (clinic_id, table_name, record_id, content, embedding, updated_at) 
         VALUES (?, ?, ?, ?, ?::vector, ?)
         ON CONFLICT (clinic_id, table_name, record_id) DO UPDATE 
         SET content = EXCLUDED.content, embedding = EXCLUDED.embedding, updated_at = EXCLUDED.updated_at",
         [
            'test_clinic', 
            'test_table', 
            999, 
            'Ahmed is a 30 year old male patient with a toothache.', 
            json_encode($vector), 
            now()
         ]
    );
    echo "✅ Supabase pgvector Insert Successful!\n";
} catch (\Exception $e) {
    echo "❌ Supabase Insert Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Testing Supabase Vector Search ---\n";
// 4. Test searching Supabase
try {
    $searchVector = $embeddingService->generateEmbedding('Who has a toothache?');
    
    $results = DB::connection('pgsql_embeddings')
        ->table('embeddings')
        ->where('clinic_id', 'test_clinic')
        ->select('record_id', 'content')
        ->selectRaw('1 - (embedding <=> ?::vector) as similarity', [json_encode($searchVector)])
        ->orderByRaw('embedding <=> ?::vector', [json_encode($searchVector)])
        ->limit(1)
        ->get();

    echo "✅ Supabase Search Successful! Found matches: " . count($results) . "\n";
    if (count($results) > 0) {
        echo "   Match content: " . $results[0]->content . " (Similarity: " . round($results[0]->similarity, 4) . ")\n";
    }
} catch (\Exception $e) {
    echo "❌ Supabase Search Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Testing Chat (OpenAI GPT-5-Nano) ---\n";
// 5. Test GPT chat using the service
try {
    // We mock the original records fetch to bypass the tenant DB dependency for this test
    $context = "Context from database: Ahmed is a 30 year old male patient with a toothache.";
    
    $reflection = new \ReflectionClass($vectorSearchService);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $client = $clientProperty->getValue($vectorSearchService);

    $response = $client->chat()->create([
        'model' => config('services.openai.chat_model', 'gpt-5-nano'),
        'messages' => [
            [
                'role' => 'system',
                'content' => "You are a helpful AI assistant for a clinic. Answer questions based ONLY on the provided context.\n\n$context"
            ],
            [
                'role' => 'user',
                'content' => 'Who has a toothache?'
            ]
        ],
        'temperature' => 0.2, // Low temperature for more factual answers
    ]);

    $answer = $response->choices[0]->message->content;
    echo "✅ OpenAI Chat logic successful!\n";
    echo "   Question: Who has a toothache?\n";
    echo "   Answer: " . trim($answer) . "\n";
    
} catch (\Exception $e) {
    echo "❌ OpenAI Chat Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Clean up
DB::connection('pgsql_embeddings')->table('embeddings')->where('clinic_id', 'test_clinic')->delete();

echo "\n--- All Systems Go! 🚀 ---\n";
