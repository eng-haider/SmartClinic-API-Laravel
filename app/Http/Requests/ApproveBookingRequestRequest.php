<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a staff approval of a booking request (JWT protected).
 *
 * All fields are optional overrides applied to the reservation that is
 * created on approval. Staff pick a single date and a single time; the
 * repository maps them onto the reservation's start/end date and from time.
 */
class ApproveBookingRequestRequest extends FormRequest
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
        return [
            'doctor_id' => 'nullable|exists:users,id',
            'status_id' => 'nullable|exists:statuses,id',
            'reservation_date' => 'nullable|date',
            'reservation_time' => 'nullable|date_format:H:i,H:i:s',
            'notes' => 'nullable|string',
            'is_waiting' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reservation_time.date_format' => 'Reservation time must be in HH:MM format',
        ];
    }
}
