<?php

namespace App\Http\Resources;

use App\Models\TransferExtra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransferExtra
 */
class TransferExtraResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'currency' => 'USD',
            'max_quantity' => $this->max_quantity,
        ];
    }
}
