<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Repositories\RecipeRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecipeController extends Controller
{
    protected $recipeRepository;

    public function __construct(RecipeRepository $recipeRepository)
    {
        $this->recipeRepository = $recipeRepository;

        // Permissions - secretary role has no access to recipes
        $this->middleware('permission:view-all-recipes')->only(['index']);
        $this->middleware('permission:view-own-recipes')->only(['index']);
        $this->middleware('permission:create-recipe')->only(['store']);
        $this->middleware('permission:edit-recipe')->only(['update']);
        $this->middleware('permission:delete-recipe')->only(['destroy']);
    }

    /**
     * Display a listing of recipes with role-based filtering.
     */
    public function index(Request $request)
    {
        [$clinicId, $doctorId] = $this->getFiltersByRole();
        
        $recipes = $this->recipeRepository->getAll($request, $clinicId, $doctorId);
        
        return RecipeResource::collection($recipes);
    }

    /**
     * Store a newly created recipe.
     */
    public function store(RecipeRequest $request)
    {
        $recipe = $this->recipeRepository->create($request->validated());
        
        return new RecipeResource($recipe);
    }

    /**
     * Display the specified recipe.
     */
    public function show(int $id)
    {
        $recipe = $this->recipeRepository->find($id);
        
        if (!$recipe) {
            return response()->json(['message' => 'Recipe not found'], 404);
        }

        // Check if user has access to this recipe
        if (!$this->canAccessRecipe($recipe)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        return new RecipeResource($recipe);
    }

    /**
     * Update the specified recipe.
     */
    public function update(RecipeRequest $request, int $id)
    {
        $recipe = $this->recipeRepository->find($id);
        
        if (!$recipe) {
            return response()->json(['message' => 'Recipe not found'], 404);
        }

        // Check if user has access to this recipe
        if (!$this->canAccessRecipe($recipe)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $recipe = $this->recipeRepository->update($id, $request->validated());
        
        return new RecipeResource($recipe);
    }

    /**
     * Remove the specified recipe.
     */
    public function destroy(int $id)
    {
        $recipe = $this->recipeRepository->find($id);
        
        if (!$recipe) {
            return response()->json(['message' => 'Recipe not found'], 404);
        }

        // Check if user has access to this recipe
        if (!$this->canAccessRecipe($recipe)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $this->recipeRepository->delete($id);
        
        return response()->json(['message' => 'Recipe deleted successfully']);
    }

    /**
     * Get filters based on user role.
     * Returns [clinicId, doctorId]
     * 
     * - super_admin: [null, null] - sees all recipes
     * - clinic_super_doctor: [clinic_id, null] - sees all clinic recipes
     * - doctor: [clinic_id, user_id] - sees only their own recipes
     * - secretary: no access (blocked by middleware)
     */
    private function getFiltersByRole(): array
    {
        $user = Auth::user();

        if ($user->hasRole('super_admin')) {
            return [null, null]; // Super admin sees all
        }

        if ($user->hasRole('clinic_super_doctor')) {
            return [$user->clinic_id, null]; // Clinic super doctor sees all clinic recipes
        }

        if ($user->hasRole('doctor')) {
            return [$user->clinic_id, $user->id]; // Doctor sees only their own recipes
        }

        // Secretary has no access (should be blocked by middleware)
        return [null, null];
    }

    /**
     * Check if the current user can access the given recipe.
     */
    private function canAccessRecipe($recipe): bool
    {
        $user = Auth::user();

        // Super admin can access all
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Clinic super doctor can access clinic recipes
        if ($user->hasRole('clinic_super_doctor')) {
            return $recipe->doctor->clinic_id === $user->clinic_id;
        }

        // Doctor can only access their own recipes
        if ($user->hasRole('doctor')) {
            return $recipe->doctors_id === $user->id;
        }

        // Secretary has no access
        return false;
    }
}
