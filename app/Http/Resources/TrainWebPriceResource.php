<?php

namespace App\Http\Resources;

use App\Enums\TrainWebClass;
use App\Models\TrainWebPrice;
use App\Services\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TrainWebPrice */
class TrainWebPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Same USD conversion the hotel prices use; null when no UZS→USD rate is configured.
        $rate = ExpenseService::getUsdToUzsCurrency()?->rate;

        $prices = [];
        foreach (TrainWebClass::cases() as $class) {
            $uzs = $this->priceFor($class);

            // A class that isn't sold on this train is left out instead of shown as 0.
            if ($uzs === null) {
                continue;
            }

            $prices[] = [
                'class' => $class->value,
                'price_uzs' => $uzs,
                'price_usd' => $rate ? round($uzs / $rate, 2) : null,
            ];
        }

        return [
            'id' => $this->id,
            'train' => ['id' => $this->train_id, 'name' => $this->train->name],
            'from' => $this->fromCity->name,
            'to' => $this->toCity->name,
            'prices' => $prices,
        ];
    }
}
