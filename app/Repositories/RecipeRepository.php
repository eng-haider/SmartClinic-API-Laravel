<?php

namespace App\Repositories;

use App\Models\Recipe;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class RecipeRepository
{
    /**
     * Get all recipes with optional filters.
     *
     * @param Request $request
     * @param int|null $doctorId
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAll(Request $request, ?int $doctorId = null)
    {
        $query = QueryBuilder::for(Recipe::class)
            ->allowedIncludes(['patient', 'doctor', 'recipeItems'])
            ->allowedFilters([
                AllowedFilter::exact('patient_id'),
                AllowedFilter::exact('doctors_id'),
                AllowedFilter::partial('notes'),
                AllowedFilter::scope('created_after'),
                AllowedFilter::scope('created_before'),
            ])
            ->allowedSorts(['id', 'created_at', 'updated_at'])
            ->defaultSort('-created_at');

        // Filter by specific doctor
        if ($doctorId !== null) {
            $query->where('doctors_id', $doctorId);
        }

        return $query->paginate($request->get('per_page', 15));
    }

    /**
     * Find a recipe by ID.
     *
     * @param int $id
     * @return \App\Models\Recipe|null
     */
    public function find(int $id)
    {
        return Recipe::with(['patient', 'doctor', 'recipeItems'])->find($id);
    }

    /**
     * Create a new recipe.
     *
     * @param array $data
     * @return \App\Models\Recipe
     */
    public function create(array $data)
    {
        return Recipe::create($data);
    }

    /**
     * Update a recipe.
     *
     * @param int $id
     * @param array $data
     * @return \App\Models\Recipe
     */
    public function update(int $id, array $data)
    {
        $recipe = Recipe::findOrFail($id);
        $recipe->update($data);
        return $recipe->fresh(['patient', 'doctor', 'recipeItems']);
    }

    /**
     * Delete a recipe.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id)
    {
        $recipe = Recipe::findOrFail($id);
        return $recipe->delete();
    }
}
