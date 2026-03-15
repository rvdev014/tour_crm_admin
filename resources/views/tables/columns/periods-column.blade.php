@php
    use App\Models\Hotel;use App\Enums\CurrencyEnum;use App\Enums\RoomPersonType;use App\Models\HotelRoomType;use App\Services\ExpenseService;use Illuminate\Database\Eloquent\Collection;

    /** @var Hotel $hotel */
    $state = $getState();
    $hotel = $state['hotel'] ?? null;
    $isFirst = $state['isFirst'] ?? false;
    $group = $state['group'] ?? null;
    $currency = $state['currency'] ?? CurrencyEnum::UZS->value;
    $year = $state['year'] ?? null;

//    if (!empty($year)) {
//        $hotel->load(['roomTypes' => fn ($query) => $query->where('year', $year)]);
//    } else {
//        $hotel->loadMissing('roomTypes');
//    }

//    if ($hotel->roomTypes->isEmpty()) {
//        return;
//    }

    /**
    * @var Collection<HotelRoomType> $roomTypes
    */
    $roomTypes = HotelRoomType::query()
        ->where('hotel_id', $hotel->id)
        ->when(!empty($year), fn ($query) => $query->where('year', $year))
        // PostgreSQL specific: unique per room_type_id
        // Note: The first column in orderBy MUST be the column in distinctOn
        ->selectRaw('DISTINCT ON (room_type_id) *')
        ->orderBy('room_type_id')
        ->orderBy('price_foreign') // This ensures you get the cheapest one for each type
        ->limit(2)
        ->get();

    if ($roomTypes->isNotEmpty()) {
//        dd($roomTypes);
    }

    $isUsd = $currency == CurrencyEnum::USD->value;
    $currencySymbol = $isUsd ? CurrencyEnum::USD->getSymbol() : CurrencyEnum::UZS->getSymbol();
@endphp

<div class="custom-table-wrapper">
    <table class="custom-table">
        @if($isFirst)
            <thead>
            <tr>
                <th style="min-width: 100px">Room type</th>
                <th style="min-width: 100px">Season type</th>
                <th style="min-width: 150px">Price Uz</th>
                <th style="min-width: 150px">Price Foreign</th>
            </tr>
            </thead>
        @endif
        @foreach($roomTypes as $roomType)

            <tr>
                <td style="min-width: 100px; text-align: left;">{{ $roomType?->roomType?->name }}</td>

                <td style="min-width: 100px; text-align: left;">
                    <div class="flex-td">
                        @if ($roomType?->season_type)
                            <x-filament::badge
                                    :color="$roomType->season_type->getColor()"
                                    size="sm"
                            >
                                {{ $roomType->season_type->getLabel() }}
                            </x-filament::badge>
                        @endif
                    </div>
                </td>

                @php
                    $price = $roomType->getPriceByGroup($group, RoomPersonType::Uzbek);
                    $priceForeign = $roomType->getPriceByGroup($group, RoomPersonType::Foreign);
                @endphp

                <td style="min-width: 150px; text-align: left;">{{ number_format(ExpenseService::getPrice($price, $isUsd), 0, '.', ' ') }} {{ $currencySymbol }}</td>
                <td style="min-width: 150px; text-align: left;">{{ number_format(ExpenseService::getPrice($priceForeign, $isUsd), 0, '.', ' ') }} {{ $currencySymbol }}</td>

            </tr>
        @endforeach
    </table>
</div>
