<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    /**
     * The model instance.
     */
    protected Model $model;

    /**
     * Create a new repository instance.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get the query builder instance.
     */
    protected function query(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Find a record by ID.
     */
    public function findById(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    /**
     * Get all records.
     */
    public function getAll()
    {
        return $this->query()->get();
    }

    /**
     * Create a new record.
     */
    public function create(array $data): Model
    {
        return $this->query()->create($data);
    }

    /**
     * Update a record.
     */
    public function update(int $id, array $data): Model
    {
        $record = $this->findById($id);

        if (!$record) {
            throw new \Exception("Record with ID {$id} not found");
        }

        $record->update($data);

        return $record->fresh();
    }

    /**
     * Delete a record.
     */
    public function delete(int $id): bool
    {
        $record = $this->findById($id);

        if (!$record) {
            throw new \Exception("Record with ID {$id} not found");
        }

        return $record->delete();
    }

    /**
     * Check if a record exists.
     */
    public function exists(int $id): bool
    {
        return $this->query()->where('id', $id)->exists();
    }
}
