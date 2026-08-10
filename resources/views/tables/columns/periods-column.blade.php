@php
    use App\Models\Hotel;use App\Enums\CurrencyEnum;use App\Enums\RoomPersonType;use App\Enums\RoomSeasonType;use App\Models\HotelPeriod;use App\Models\HotelRoomType;use App\Services\ExpenseService;use Illuminate\Database\Eloquent\Collection;

    /** @var Hotel $hotel */
    $state = $getState();
    $hotel = $state['hotel'] ?? null;
    $isFirst = $state['isFirst'] ?? false;
    $group = $state['group'] ?? null;
    $currency = $state['currency'] ?? CurrencyEnum::UZS->value;
    $year = $state['year'] ?? null;
    $seasonType = $state['season_type'] ?? null;

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
        ->when(!empty($seasonType), fn ($query) => $query->where('season_type', $seasonType))
        // PostgreSQL specific: unique per room_type_id
        // Note: The first column in orderBy MUST be the column in distinctOn
        ->selectRaw('DISTINCT ON (room_type_id) *')
        ->orderBy('room_type_id')
        ->orderByRaw(RoomSeasonType::priorityCaseSql()) // Highest-priority season per type: High > Mid > Low > Yearly/Exhibition
        ->limit(2)
        ->get();

    if ($roomTypes->isNotEmpty()) {
//        dd($roomTypes);
    }

    $isUsd = $currency == CurrencyEnum::USD->value;
    $currencySymbol = $isUsd ? CurrencyEnum::USD->getSymbol() : CurrencyEnum::UZS->getSymbol();
@endphp

<x-data-table
    class="custom-table--left"
    :headers="$isFirst ? [
        ['label' => 'Room type', 'class' => 'min-w-[100px]'],
        ['label' => 'Season type', 'class' => 'min-w-[100px]'],
        ['label' => 'Price Uz', 'class' => 'min-w-[150px]'],
        ['label' => 'Price Foreign', 'class' => 'min-w-[150px]'],
    ] : null"
>
        @foreach($roomTypes as $roomType)

            <tr>
                <td class="min-w-[100px]">{{ $roomType?->roomType?->name }}</td>

                <td class="min-w-[100px]">
                    <div class="flex-td">
                        @if ($roomType?->season_type)
                            @php
                                $period = HotelPeriod::periodsForYear($hotel->id, $roomType->year)
                                    ->firstWhere('season_type', $roomType->season_type);
                                $periodTooltip = $period
                                    ? $period->start_date->format('d.m.Y') . ' — ' . $period->end_date->format('d.m.Y')
                                    : null;
                            @endphp
                            <x-filament::badge
                                    :color="$roomType->season_type->getColor()"
                                    size="sm"
                                    :tooltip="$periodTooltip"
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

                <td class="min-w-[150px]">{{ number_format(ExpenseService::getPrice($price, $isUsd), 0, '.', ' ') }} {{ $currencySymbol }}</td>
                <td class="min-w-[150px]">{{ number_format(ExpenseService::getPrice($priceForeign, $isUsd), 0, '.', ' ') }} {{ $currencySymbol }}</td>

            </tr>
        @endforeach
</x-data-table>
