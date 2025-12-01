@php
    use App\Enums\GuideType;use App\Models\Tour;
    use App\Enums\ExpenseType;
    use App\Enums\ExpenseStatus;
    use App\Models\TourDayExpense;use App\Models\Transfer;

    /** @var Tour $record */
    $record->loadMissing('groups.expenses');
@endphp

<div class="custom-table-wrapper">
    <table class="custom-table">
        <thead>
        <tr>
            <th>Passengers</th>
            <th>Hotel</th>
            <th>Guide</th>
            <th>Flight</th>
            <th>Train</th>
            <th>Transfer</th>
            <th>Lunch</th>
            <th>Dinner</th>
            <th>Extra</th>
        </tr>
        </thead>
        @foreach($record->groups as $group)

            @php
                $hotel = $group->getExpense(ExpenseType::Hotel);

                if ($record->guide_type == GuideType::Escort) {
                    $guideName = $record->guide_name;
                    $guideStatus = ExpenseStatus::Confirmed;
                } else {
                    $expense = $group->getExpense(ExpenseType::Guide);
                    // TODO: Guide
                    $guideName = $expense?->guides->map(fn($guide) => $guide->name)->join(', ');
                    $guideStatus = $expense?->status;
                }

                $flight = $group->getExpense(ExpenseType::Flight);
                $train = $group->getExpense(ExpenseType::Train);

                $transport = $group->getExpense(ExpenseType::Transport);
                /** @var Transfer $transfer */
                $transfer = Transfer::query()->where('tour_day_expense_id', $transport?->id)->first();
                
                // Get driver names if driver_ids exist
                $driverNames = [];
                if ($transfer && $transfer->driver_ids) {
                    $driverNames = \App\Models\Driver::whereIn('id', $transfer->driver_ids)->pluck('name')->toArray();
                }

                $lunch = $group->getExpense(ExpenseType::Lunch);
                $dinner = $group->getExpense(ExpenseType::Dinner);
                $extra = $group->getExpense(ExpenseType::Extra);
            @endphp

            <tr>
                <td>{{ $group->passengers?->first()?->name }}</td>

                <td>
                    <div class="flex-td">
                        <p>{{ $hotel?->hotel?->name  }}</p>
                        @if ($hotel?->status)
                            <x-filament::badge
                                :color="$hotel->status->getColor()"
                                size="sm"
                            >
                                {{ $hotel->status->getLabel() }}
                            </x-filament::badge>
                        @endif
                    </div>
                </td>

                <td>
                    <div class="flex-td">
                        <p>{{ $guideName }}</p>
                        @if ($guideStatus)
                            <x-filament::badge
                                :color="$guideStatus->getColor()"
                                size="sm"
                            >
                                {{ $guideStatus->getLabel() }}
                            </x-filament::badge>
                        @endif
                    </div>
                </td>

                <td>
                    <div class="flex-td">
                        <p>{{ $flight?->plane_route }}</p>
                        @if ($flight?->status)
                            <x-filament::badge
                                :color="$flight->status->getColor()"
                                size="sm"
                            >
                                {{ $flight->status->getLabel() }}
                            </x-filament::badge>
                        @endif
                    </div>
                    <p>{{ $flight?->departure_time }} - {{ $flight?->arrival_time?->format('d.m.Y H:i') }}</p>
                </td>

                <td>
                    <div class="flex-td">
                        <p>{{ $train?->train?->name }}</p>
                        <p>{{ $train?->departure_time }} - {{ $train?->arrival_time?->format('d.m.Y H:i') }}</p>
                    </div>
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
                        <p>{{ $lunch?->restaurant?->name  }}</p>
                        @if ($lunch?->status)
                            <x-filament::badge
                                :color="$lunch->status->getColor()"
                                size="sm"
                            >
                                {{ $lunch->status->getLabel() }}
                            </x-filament::badge>
                        @endif
                    </div>
                </td>

                <td>
                    <div class="flex-td">
                        <p>{{ $dinner?->restaurant?->name  }}</p>
                        @if ($dinner?->status)
                            <x-filament::badge
                                :color="$dinner->status->getColor()"
                                size="sm"
                            >
                                {{ $dinner->status->getLabel() }}
                            </x-filament::badge>
                        @endif
                    </div>
                </td>

                <td>
                    <div class="flex-td">
                        <p>{{ $extra?->other_name }}</p>
                        @if ($extra?->status)
                            <x-filament::badge
                                :color="$extra->status->getColor()"
                                size="sm"
                            >
                                {{ $extra->status->getLabel() }}
                            </x-filament::badge>
                        @endif
                    </div>
                </td>

            </tr>
        @endforeach
    </table>
</div>




