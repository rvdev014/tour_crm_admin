<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255', 'different:from'],
            'from_coords' => ['required', 'string'],
            'to_coords' => ['required', 'string'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'passengers' => ['required', 'integer', 'min:1', 'max:50'],
            'trip_type' => ['required', 'string', 'in:one_way,return'],
            'return_date' => ['required_if:trip_type,return', 'nullable', 'date', 'after_or_equal:date'],
            'return_time' => ['required_if:trip_type,return', 'nullable', 'date_format:H:i'],
        ];
    }
}
