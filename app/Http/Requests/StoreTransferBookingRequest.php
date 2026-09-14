<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quote_token' => ['required', 'string'],
            'transport_class_id' => ['required', 'integer', 'exists:transport_classes,id'],
            'extras' => ['nullable', 'array'],
            'extras.*.id' => ['required_with:extras', 'integer', 'exists:transfer_extras,id'],
            'extras.*.quantity' => ['required_with:extras', 'integer', 'min:1'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'hotel_address' => ['required', 'string', 'max:255'],
            'flight_data' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
