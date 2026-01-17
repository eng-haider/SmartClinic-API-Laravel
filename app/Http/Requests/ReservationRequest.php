<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservationRequest extends FormRequest
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
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'required|exists:users,id',
            'clinics_id' => 'required|exists:clinics,id',
            'status_id' => 'nullable|exists:statuses,id',
            'notes' => 'nullable|string',
            'reservation_start_date' => 'required|date',
            'reservation_end_date' => 'nullable|date|after_or_equal:reservation_start_date',
            'reservation_from_time' => 'required|date_format:H:i:s',
            'reservation_to_time' => 'required|date_format:H:i:s|after:reservation_from_time',
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
            'patient_id.required' => 'Patient is required',
            'patient_id.exists' => 'The selected patient does not exist',
            'doctor_id.required' => 'Doctor is required',
            'doctor_id.exists' => 'The selected doctor does not exist',
            'clinics_id.required' => 'Clinic is required',
            'clinics_id.exists' => 'The selected clinic does not exist',
            'status_id.exists' => 'The selected status does not exist',
            'reservation_start_date.required' => 'Reservation start date is required',
            'reservation_start_date.date' => 'Reservation start date must be a valid date',
            'reservation_end_date.date' => 'Reservation end date must be a valid date',
            'reservation_end_date.after_or_equal' => 'Reservation end date must be after or equal to start date',
            'reservation_from_time.required' => 'Reservation start time is required',
            'reservation_from_time.date_format' => 'Reservation start time must be in HH:MM:SS format',
            'reservation_to_time.required' => 'Reservation end time is required',
            'reservation_to_time.date_format' => 'Reservation end time must be in HH:MM:SS format',
            'reservation_to_time.after' => 'Reservation end time must be after start time',
            'is_waiting.boolean' => 'Is waiting must be true or false',
        ];
    }
}
