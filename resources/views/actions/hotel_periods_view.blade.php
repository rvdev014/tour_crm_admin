@php
    use App\Enums\RoomSeasonType;
    use App\Models\Hotel;
    use App\Models\HotelPeriod;

    /** @var Hotel $record */
    $year = now()->year;

    $roomTypes = $record->roomTypes()
        ->where('year', $year)
        ->reorder()
        ->orderByRaw(RoomSeasonType::priorityCaseSql())
        ->orderBy('room_type_id')
        ->get();
@endphp

<x-data-table :headers="['Room type', 'Season type', 'Price Uz', 'Price Foreign']">
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
                <td colspan="4" class="ep-table-empty">No pricing for {{ $year }}.</td>
            </tr>
        @endforelse
</x-data-table>
