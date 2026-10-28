<?php

namespace App\Http\Requests;

use App\Enums\TrainWebClass;
use App\Enums\WagonClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'departure_date' => ['required', 'date', 'after_or_equal:today'],
            'return_date' => ['nullable', 'date', 'after_or_equal:departure_date'],
            'passengers_count' => ['required', 'integer', 'min:1', 'max:50'],
            'wagon_class' => ['nullable', Rule::enum(WagonClass::class)],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],
            // Set together when the visitor books a guaranteed ticket from the price list.
            'train_web_price_id' => ['nullable', 'integer', 'exists:train_web_prices,id', 'required_with:train_class'],
            'train_class' => ['nullable', Rule::enum(TrainWebClass::class), 'required_with:train_web_price_id'],
        ];
    }
}
