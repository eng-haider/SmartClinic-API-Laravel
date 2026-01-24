<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            // For single image upload
            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:10240', // 10MB
            ],
            
            // For multiple image upload
            'images' => 'nullable|array|max:10',
            'images.*' => [
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:10240', // 10MB
            ],
            
            // Image metadata
            'type' => [
                'nullable',
                'string',
                Rule::in(['profile', 'document', 'xray', 'before', 'after', 'treatment', 'prescription', 'other']),
            ],
            'alt_text' => 'nullable|string|max:255',
            'order' => 'nullable|integer|min:0',
            
            // Polymorphic relationship
            'imageable_type' => [
                'nullable',
                'string',
                Rule::in(['Patient', 'Case', 'User', 'Reservation', 'Recipe']),
            ],
            'imageable_id' => 'nullable|integer|min:1',
        ];

        // If updating, allow partial data
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['image'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240';
            $rules['type'] = 'nullable|string';
            $rules['alt_text'] = 'nullable|string|max:255';
            $rules['order'] = 'nullable|integer|min:0';
        }

        return $rules;
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif, webp.',
            'image.max' => 'The image must not be larger than 10MB.',
            'images.array' => 'The images must be an array.',
            'images.max' => 'You can upload a maximum of 10 images at once.',
            'images.*.image' => 'Each file must be an image.',
            'images.*.mimes' => 'Each image must be a file of type: jpeg, png, jpg, gif, webp.',
            'images.*.max' => 'Each image must not be larger than 10MB.',
            'type.in' => 'The image type must be one of: profile, document, xray, before, after, treatment, prescription, other.',
            'imageable_type.in' => 'The imageable type must be one of: Patient, Case, User, Reservation, Recipe.',
        ];
    }
}
