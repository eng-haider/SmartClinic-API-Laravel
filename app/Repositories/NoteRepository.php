<?php

namespace App\Repositories;

use App\Models\Note;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class NoteRepository
{
    /**
     * Get the query builder instance
     */
    protected function query(): Builder
    {
        return Note::query();
    }

    /**
     * Get the QueryBuilder instance with all allowed filters and sorts
     */
    protected function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(Note::class)
            ->allowedFilters([
                'noteable_id',
                'noteable_type',
                'created_by',
                AllowedFilter::exact('noteable_id'),
                AllowedFilter::exact('noteable_type'),
                AllowedFilter::exact('created_by'),
            ])
            ->allowedSorts([
                'id',
                'created_at',
                'updated_at',
            ])
            ->allowedIncludes([
                'noteable',
                'creator',
            ])
            ->defaultSort('-created_at');
    }

    /**
     * Get all notes with filters and pagination
     */
    public function getAllWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->queryBuilder();
        
        return $query->paginate($perPage);
    }

    /**
     * Get note by ID
     */
    public function getById(int $id): ?Note
    {
        return $this->query()
            ->with(['noteable', 'creator'])
            ->find($id);
    }

    /**
     * Get notes for a specific noteable (e.g., patient, case)
     */
    public function getByNoteable(string $noteableType, int $noteableId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->where('noteable_type', $noteableType)
            ->where('noteable_id', $noteableId)
            ->with(['creator'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create a new note
     */
    public function create(array $data): Note
    {
        return $this->query()->create($data);
    }

    /**
     * Update note
     */
    public function update(int $id, array $data): Note
    {
        $note = $this->getById($id);

        if (!$note) {
            throw new \Exception("Note with ID {$id} not found");
        }

        $note->update($data);

        return $note->fresh(['noteable', 'creator']);
    }

    /**
     * Delete note
     */
    public function delete(int $id): bool
    {
        $note = $this->getById($id);

        if (!$note) {
            throw new \Exception("Note with ID {$id} not found");
        }

        return $note->delete();
    }
}
