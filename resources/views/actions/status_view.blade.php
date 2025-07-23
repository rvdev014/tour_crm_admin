@php
    use App\Enums\GuideType;use App\Models\Tour;
    use App\Enums\ExpenseType;
    use App\Enums\ExpenseStatus;
    use App\Models\TourDayExpense;use App\Models\Transfer;

    /** @var Tour $record */
    $record->loadMissing('days.expenses');
@endphp

<div class="custom-table-wrapper">
    <table class="custom-table">
        <thead>
        <tr>
            <th>Date</th>
            <th>Cities</th>
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
        @foreach($record->days as $day)

            @php
                $hotel = $day->getExpense(ExpenseType::Hotel);

                if ($record->guide_type == GuideType::Escort) {
                    $guideName = $record->guide_name;
                    $guideStatus = ExpenseStatus::Confirmed;
                } else {
                    $expense = $day->getExpense(ExpenseType::Guide);
                    // TODO: Guide
                    $guideName = $expense?->guides->map(fn($guide) => $guide->name)->join(', ');
                    $guideStatus = $expense?->status;
                }

                $flight = $day->getExpense(ExpenseType::Flight);
                $train = $day->getExpense(ExpenseType::Train);

                $transport = $day->getExpense(ExpenseType::Transport);
                /** @var Transfer $transfer */
                $transfer = Transfer::query()->where('tour_day_expense_id', $transport?->id)->first();

                $lunch = $day->getExpense(ExpenseType::Lunch);
                $dinner = $day->getExpense(ExpenseType::Dinner);
                $extra = $day->getExpense(ExpenseType::Extra);
            @endphp

            <tr>
                <td>{{ $day->date->format('d.m.Y') }}</td>

                <td>{{ $day->city->name }}</td>

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
                        <p>{{ $guideName  }}</p>
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
                    <div class="flex-td" style="flex-direction: row; justify-content: center">
                        <p>{{ $flight?->plane_route }}</p>&nbsp;
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
                        <a target="_blank" href="/admin/transfers/{{ $transfer->id }}/edit">{{ $transfer?->number }}</a>
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




