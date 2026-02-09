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
        // $this->middleware('permission:view-own-recipes')->only(['index']);
        $this->middleware('permission:create-recipe')->only(['store']);
        $this->middleware('permission:edit-recipe')->only(['update']);
        $this->middleware('permission:delete-recipe')->only(['destroy']);
    }

    /**
     * Display a listing of recipes with role-based filtering.
     */
    public function index(Request $request)
    {
        // Multi-tenancy: Database is already isolated by tenant
        // Only filter by doctor_id for regular doctors
        $doctorId = $this->getDoctorIdFilter();
        
        $recipes = $this->recipeRepository->getAll($request, $doctorId);
        
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
     * Multi-tenancy: Database is already isolated by tenant via middleware.
     * We only need to filter by doctor for regular doctors who should only see their own recipes.
     * 
     * - Super Doctor: sees all recipes in their tenant database [null]
     * - Doctor: sees ONLY their own recipes [user_id]
     * - Secretary: no access (blocked by middleware)
     */
    private function getDoctorIdFilter(): ?int
    {
        $user = Auth::user();

        if ($user->hasRole('super_admin') || $user->hasRole('clinic_super_doctor')) {
            return null; // Super admin and clinic super doctor see all
        }

        if ($user->hasRole('doctor')) {
            return $user->id; // Doctor sees only their own recipes
        }

        // Secretary has no access (should be blocked by middleware)
        return null;
    }

    /**
     * Check if the current user can access the given recipe.
     */
    private function canAccessRecipe($recipe): bool
    {
        $user = Auth::user();

        // Super admin can access all
        if ($user->hasRole('super_admin') || $user->hasRole('clinic_super_doctor')) {
            return true;
        }

        // Doctor can only access their own recipes
        if ($user->hasRole('doctor')) {
            return $recipe->doctors_id === $user->id;
        }

        // Secretary has no access
        return false;
    }
}
