<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Services\ExpenseService;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'birthday' => $this->birthday,
            'gender' => $this->gender,
            'role' => $this->role,
            'avatar_url' => $this->avatar_url,
            'google_id' => $this->google_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'timezone' => $this->timezone,
            'currency' => ExpenseService::getMainCurrency()
        ];
    }
}
