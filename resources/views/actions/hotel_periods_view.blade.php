@php
    use App\Enums\RoomSeasonType;
    use App\Models\Hotel;
    use App\Models\HotelPeriod;

    /** @var Hotel $record */
    $year = now()->year;

    $roomTypes = $record->roomTypes()
        ->where('year', $year)
        ->reorder('room_type_id')
        ->orderByRaw(RoomSeasonType::priorityCaseSql())
        ->get();
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
        @forelse($roomTypes as $roomType)
            <tr>
                <td>{{ $roomType->roomType?->name }}</td>

                <td>
                    <div class="flex-td">
                        @if ($roomType->season_type)
                            @php
                                $period = HotelPeriod::periodsForYear($record->id, $roomType->year)
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

                <td>{{ number_format($roomType->price, 0, '.', ' ') }}</td>
                <td>{{ number_format($roomType->price_foreign, 0, '.', ' ') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="text-align: center; padding: 1rem;">No pricing for {{ $year }}.</td>
            </tr>
        @endforelse
    </table>
</div>
