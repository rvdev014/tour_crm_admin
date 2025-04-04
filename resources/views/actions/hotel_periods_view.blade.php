@php
    use App\Models\Hotel;

    /** @var Hotel $record */
    $record->loadMissing('roomTypes');
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
        @foreach($record->roomTypes as $roomType)

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

                <td>{{ number_format($roomType->price, 0, '.', ' ') }}</td>
                <td>{{ number_format($roomType->price_foreign, 0, '.', ' ') }}</td>

            </tr>
        @endforeach
    </table>
</div>




