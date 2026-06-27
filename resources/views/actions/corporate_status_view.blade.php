@php
    use App\Enums\GuideType;
    use App\Models\Tour;
    use App\Enums\ExpenseType;
    use App\Enums\ExpenseStatus;
    use App\Models\Transfer;
    use Carbon\Carbon;

    /** @var Tour $record */
    $record->loadMissing([
        'groups.passengers' => fn($q) => $q->orderBy('id'),
        'groups.expenses.city',
        'groups.expenses.hotel',
        'groups.expenses.train',
        'groups.expenses.guides',
    ]);
@endphp

<div class="custom-table-wrapper">
    <table class="custom-table">
        <thead>
        <tr>
            <th>Passengers</th>
            <th>Date</th>
            <th>City</th>
            <th>Hotel</th>
            <th>Guide</th>
            <th>Flight</th>
            <th>Train</th>
            <th>Transfer</th>
            <th>Extra</th>
        </tr>
        </thead>
        @foreach($record->groups as $group)
            @php
                $groupedByDate = $group->expenses
                    ->sortBy('date')
                    ->groupBy(fn($e) => $e->date?->format('Y-m-d') ?? '');

                $dates = $groupedByDate->keys();

                if ($dates->isEmpty()) {
                    $dates = collect(['']);
                    $groupedByDate[''] = collect();
                }
            @endphp

            @foreach($dates as $dateKey)
                @php
                    $dateExpenses = $groupedByDate[$dateKey];

                    $hotel = $dateExpenses->first(fn($e) => $e->type == ExpenseType::Hotel);

                    if ($record->guide_type == GuideType::Escort) {
                        $guideName = $record->guide_name;
                        $guideStatus = ExpenseStatus::Confirmed;
                    } else {
                        $guideExpense = $dateExpenses->first(fn($e) => $e->type == ExpenseType::Guide);
                        $guideName = $guideExpense?->guides->map(fn($guide) => $guide->name)->join(', ');
                        $guideStatus = $guideExpense?->status;
                    }

                    $flight = $dateExpenses->first(fn($e) => $e->type == ExpenseType::Flight);
                    $train = $dateExpenses->first(fn($e) => $e->type == ExpenseType::Train);

                    $transport = $dateExpenses->first(fn($e) => $e->type == ExpenseType::Transport);
                    $transfer = null;
                    $driverNames = [];
                    if ($transport) {
                        $transfer = Transfer::query()->where('tour_day_expense_id', $transport->id)->first();
                        if ($transfer && $transfer->driver_ids) {
                            $driverNames = \App\Models\Driver::whereIn('id', $transfer->driver_ids)->pluck('name')->toArray();
                        }
                    }

                    $extra = $dateExpenses->first(fn($e) => $e->type == ExpenseType::Extra);

                    $city = $hotel?->city ?? $dateExpenses->first()?->city;
                @endphp

                <tr>
                    @if ($loop->first)
                        <td rowspan="{{ $dates->count() }}">
                            @foreach($group->passengers as $passenger)
                                <p>{{ $passenger->name }}</p>
                            @endforeach
                        </td>
                    @endif

                    <td>{{ $dateKey ? Carbon::parse($dateKey)->format('d.m.Y') : '' }}</td>

                    <td>{{ $city?->name }}</td>

                    <td>
                        <div class="flex-td">
                            <p>{{ $hotel?->hotel?->name }}</p>
                            @if ($hotel?->status)
                                <x-filament::badge :color="$hotel->status->getColor()" size="sm">
                                    {{ $hotel->status->getLabel() }}
                                </x-filament::badge>
                            @endif
                        </div>
                    </td>

                    <td>
                        <div class="flex-td">
                            <p>{{ $guideName }}</p>
                            @if ($guideStatus)
                                <x-filament::badge :color="$guideStatus->getColor()" size="sm">
                                    {{ $guideStatus->getLabel() }}
                                </x-filament::badge>
                            @endif
                        </div>
                    </td>

                    <td>
                        <div class="flex-td">
                            <p>{{ $flight?->plane_route }}</p>
                            @if ($flight?->status)
                                <x-filament::badge :color="$flight->status->getColor()" size="sm">
                                    {{ $flight->status->getLabel() }}
                                </x-filament::badge>
                            @endif
                        </div>
                        @if ($flight)
                            <p>{{ $flight->departure_time }} - {{ $flight->arrival_time?->format('d.m.Y H:i') }}</p>
                        @endif
                    </td>

                    <td>
                        <div class="flex-td">
                            <p>{{ $train?->train?->name }}</p>
                        </div>
                        @if ($train)
                            <p>{{ $train->departure_time }} - {{ $train->arrival_time?->format('d.m.Y H:i') }}</p>
                        @endif
                    </td>

                    <td>
                        <div class="flex-td">
                            @if ($transfer)
                                <a target="_blank" href="/admin/transfers/{{ $transfer->id }}/edit">{{ $transfer->number }}</a>
                                @if (!empty($driverNames))
                                    <p>{{ implode(', ', $driverNames) }}</p>
                                @endif
                                @if ($transfer->nameplate)
                                    <p>{{ $transfer->nameplate }}</p>
                                @endif
                            @endif
                        </div>
                    </td>

                    <td>
                        <div class="flex-td">
                            <p>{{ $extra?->other_name }}</p>
                            @if ($extra?->status)
                                <x-filament::badge :color="$extra->status->getColor()" size="sm">
                                    {{ $extra->status->getLabel() }}
                                </x-filament::badge>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        @endforeach
    </table>
</div>
