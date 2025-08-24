<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHotelRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'room_type' => ['required', 'exists:room_types,id'],
            'hotel_id' => ['required', 'exists:hotels,id'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ];
    }
}