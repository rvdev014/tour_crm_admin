@php
    use App\Models\Hotel;use App\Services\ExpenseService;

    /** @var Hotel $hotel */
    $state = $getState();
    $hotel = $state['hotel'];
    $isFirst = $state['isFirst'];
    $hotel->loadMissing('roomTypes');

//    if ($hotel->roomTypes->isEmpty()) {
//        return;
//    }

    $roomTypes = $hotel->roomTypes?->take(2) ?? [];
    $mainCurrencySymbol = ExpenseService::getMainCurrency()?->from?->getSymbol();

@endphp

<div class="custom-table-wrapper">
    <table class="custom-table">
        @if($isFirst)
            <thead>
            <tr>
                <th>Room type</th>
                <th>Season type</th>
                <th>Price Uz</th>
                <th>Price Foreign</th>
            </tr>
            </thead>
        @endif
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
