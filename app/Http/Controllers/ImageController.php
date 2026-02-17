<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageRequest;
use App\Http\Resources\ImageResource;
use App\Repositories\ImageRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImageController extends Controller
{
    protected ImageRepository $imageRepository;

    /**
     * Create a new controller instance.
     */
    public function __construct(ImageRepository $imageRepository)
    {
        $this->imageRepository = $imageRepository;
    }

    /**
     * Display a listing of images.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'type',
            'imageable_type',
            'imageable_id',
            'date_from',
            'date_to',
        ]);

        $paginate = $request->boolean('paginate', true);

        $images = $this->imageRepository->getAllWithFilters($filters, $paginate);

        if ($paginate) {
            return response()->json([
                'success' => true,
                'message' => 'Images retrieved successfully',
                'data' => ImageResource::collection($images->items()),
                'meta' => [
                    'current_page' => $images->currentPage(),
                    'last_page' => $images->lastPage(),
                    'per_page' => $images->perPage(),
                    'total' => $images->total(),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Images retrieved successfully',
            'data' => ImageResource::collection($images),
        ]);
    }

    /**
     * Upload a single or multiple images.
     *
     * @param ImageRequest $request
     * @return JsonResponse
     */
    public function store(ImageRequest $request): JsonResponse
    {
        try {
            $imageableType = 'App\Models\Patient';
            $imageableId = $request->input('imageable_id');
            $type = $request->input('type');
            $altText = $request->input('alt_text');

            // Handle multiple images
            if ($request->hasFile('images')) {
                $images = $this->imageRepository->uploadMultiple(
                    $request->file('images'),
                    $imageableType,
                    $imageableId,
                    $type
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Images uploaded successfully',
                    'data' => ImageResource::collection($images),
                ], 201);
            }

            // Handle single image
            if ($request->hasFile('image')) {
                $additionalData = [];
                if ($altText) {
                    $additionalData['alt_text'] = $altText;
                }

                $image = $this->imageRepository->uploadImage(
                    $request->file('image'),
                    $imageableType,
                    $imageableId,
                    $type,
                    $additionalData
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Image uploaded successfully',
                    'data' => new ImageResource($image),
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => 'No image file provided',
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified image.
     *
     * @param string|int $id
     * @return JsonResponse
     */
    public function show(string|int $id): JsonResponse
    {
        try {
            $image = $this->imageRepository->findById((int)$id);

            if (!$image) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Image retrieved successfully',
                'data' => new ImageResource($image),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified image metadata.
     *
     * @param ImageRequest $request
     * @param string|int $id
     * @return JsonResponse
     */
    public function update(ImageRequest $request, string|int $id): JsonResponse
    {
        try {
            $data = $request->only(['type', 'alt_text', 'order']);

            $image = $this->imageRepository->update((int)$id, $data);

            return response()->json([
                'success' => true,
                'message' => 'Image updated successfully',
                'data' => new ImageResource($image),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified image.
     *
     * @param string|int $id
     * @return JsonResponse
     */
    public function destroy(string|int $id): JsonResponse
    {
        try {
            $this->imageRepository->deleteImage((int)$id);

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get images by imageable type and ID.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getByImageable(Request $request): JsonResponse
    {
        $request->validate([
            'imageable_type' => 'required|string',
            'imageable_id' => 'required|integer',
            'type' => 'nullable|string',
        ]);

        try {
            $images = $this->imageRepository->getByImageable(
                $request->input('imageable_type'),
                $request->input('imageable_id'),
                $request->input('type')
            );

            return response()->json([
                'success' => true,
                'message' => 'Images retrieved successfully',
                'data' => ImageResource::collection($images),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve images',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics about images.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            $clinicId = $user->role === 'super_admin' ? null : $user->clinic_id;

            $stats = $this->imageRepository->getStatistics($clinicId);

            return response()->json([
                'success' => true,
                'message' => 'Statistics retrieved successfully',
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the order of an image.
     *
     * @param Request $request
     * @param string|int $id
     * @return JsonResponse
     */
    public function updateOrder(Request $request, string|int $id): JsonResponse
    {
        $request->validate([
            'order' => 'required|integer|min:0',
        ]);

        try {
            $image = $this->imageRepository->updateOrder((int)$id, $request->input('order'));

            return response()->json([
                'success' => true,
                'message' => 'Image order updated successfully',
                'data' => new ImageResource($image),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update image order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
