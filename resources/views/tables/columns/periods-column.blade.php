@php
    use App\Models\Hotel;use App\Enums\CurrencyEnum;use App\Enums\RoomPersonType;use App\Services\ExpenseService;

    /** @var Hotel $hotel */
    $state = $getState();
    $hotel = $state['hotel'];
    $isFirst = $state['isFirst'];
    $group = $state['group'] ?? null;
    $currency = $state['currency'];
    $year = $state['year'];

    if (!empty($year)) {
        $hotel->load([
            'roomTypes' => function ($query) use ($group, $year) {
                $query->whereHas('period', fn ($q) => $q->whereYear('start_date', $year));
            }
        ]);
    } else {
        $hotel->loadMissing('roomTypes');
    }

//    if ($hotel->roomTypes->isEmpty()) {
//        return;
//    }

    $roomTypes = $hotel->roomTypes?->take(2) ?? [];
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
                <td style="min-width: 100px">{{ $roomType?->roomType?->name }}</td>

                <td style="min-width: 100px">
                    <div class="flex-td">
                        @if ($roomType?->season_type)
                            <x-filament::badge
                                    :color="$roomType?->period?->season_type->getColor()"
                                    size="sm"
                            >
                                {{ $roomType?->period?->season_type->getLabel() }}
                            </x-filament::badge>
                        @endif
                    </div>
                </td>

                @php
                    $price = $roomType->getPriceByGroup($group, RoomPersonType::Uzbek);
                    $priceForeign = $roomType->getPriceByGroup($group, RoomPersonType::Foreign);
                @endphp

                <td style="min-width: 150px">{{ number_format(ExpenseService::getPrice($price, $isUsd), 0, '.', ' ') }} {{ $currencySymbol }}</td>
                <td style="min-width: 150px">{{ number_format(ExpenseService::getPrice($priceForeign, $isUsd), 0, '.', ' ') }} {{ $currencySymbol }}</td>

            </tr>
        @endforeach
    </table>
</div>
