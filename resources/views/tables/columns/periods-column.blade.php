@php
    use App\Models\Hotel;use App\Enums\RoomSeasonType;use App\Services\ExpenseService;

    /** @var Hotel $hotel */
    $hotel = $getState();
    $hotel->loadMissing('roomTypes');

    if ($hotel->roomTypes->isEmpty()) {
        return;
    }

    $roomTypes = $hotel->roomTypes
        ->sortBy(function ($price) {
            return array_search($price->season_type, [
                RoomSeasonType::High->value,
                RoomSeasonType::Yearly->value,
                RoomSeasonType::Exhibition->value,
                RoomSeasonType::Mid->value,
                RoomSeasonType::Low->value,
            ]);
        })
        ->take(2);

    $mainCurrencySymbol = ExpenseService::getMainCurrency()?->from?->getSymbol();

@endphp

<div class="custom-table-wrapper">
    <table class="custom-table">
        <thead>
        <tr>
            <th>Room type</th>
            <th>Season type</th>
            <th>Price Uz</th>
            <th>Price Foreign</th>
        </tr>
        </thead>
        @foreach($roomTypes as $roomType)

            <tr>
                <td>{{ $roomType?->roomType?->name }}</td>

                <td>
                    <div class="flex-td">
                        @if ($roomType?->season_type)
                            <x-filament::badge
                                :color="$roomType?->season_type->getColor()"
                                size="sm"
                            >
                                {{ $roomType?->season_type->getLabel() }}
                            </x-filament::badge>
                        @endif
                    </div>
                </td>

                <td>{{ number_format($roomType->price, 0, '.', ' ') }} {{ $mainCurrencySymbol }}</td>
                <td>{{ number_format($roomType->price_foreign, 0, '.', ' ') }} {{ $mainCurrencySymbol }}</td>

            </tr>
        @endforeach
    </table>
</div>
