<?php

namespace App\Services\AI;

class ContextBuilder
{
    private array $sections = [];

    /**
     * Add database tool results to the context.
     */
    public function addToolResult(string $toolName, string $content): self
    {
        if (!empty(trim($content))) {
            $this->sections[] = [
                'type' => 'tool',
                'name' => $toolName,
                'content' => $content,
            ];
        }
        return $this;
    }

    /**
     * Add vector search results to the context.
     */
    public function addVectorSearchResults(array $records): self
    {
        if (empty($records)) {
            return $this;
        }

        $parts = [];
        $parts[] = "--- Related Records (Vector Search) ---";

        foreach ($records as $index => $record) {
            $num = $index + 1;
            $source = ucfirst(str_replace('_', ' ', $record['source']));
            $parts[] = "Record {$num} ({$source}, ID: {$record['record_id']}, Similarity: {$record['similarity']}):";
            $parts[] = $record['content'];
            $parts[] = '';
        }

        $this->sections[] = [
            'type' => 'vector',
            'name' => 'vector_search',
            'content' => implode("\n", $parts),
        ];

        return $this;
    }

    /**
     * Add knowledge base results to the context.
     */
    public function addKnowledgeBase(string $content): self
    {
        if (!empty(trim($content))) {
            $this->sections[] = [
                'type' => 'knowledge',
                'name' => 'medical_knowledge',
                'content' => $content,
            ];
        }
        return $this;
    }

    /**
     * Build the final merged context string.
     * Prioritizes: tool results > knowledge base > vector search
     */
    public function build(): string
    {
        if (empty($this->sections)) {
            return '';
        }

        // Sort: tool results first, then knowledge, then vector
        $priority = ['tool' => 1, 'knowledge' => 2, 'vector' => 3];
        usort($this->sections, fn($a, $b) => ($priority[$a['type']] ?? 9) <=> ($priority[$b['type']] ?? 9));

        $parts = [];
        foreach ($this->sections as $section) {
            $parts[] = $section['content'];
            $parts[] = '';
        }

        return trim(implode("\n", $parts));
    }

    /**
     * Check if any context has been added.
     */
    public function isEmpty(): bool
    {
        return empty($this->sections);
    }

    /**
     * Reset the builder.
     */
    public function reset(): self
    {
        $this->sections = [];
        return $this;
    }
}
