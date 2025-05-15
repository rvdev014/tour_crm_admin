@php
    use App\Enums\GuideType;use App\Models\Tour;
    use App\Enums\ExpenseType;
    use App\Enums\ExpenseStatus;
    use App\Models\TourDayExpense;

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
            <th>Lunch</th>
            <th>Dinner</th>
            <th>Extra</th>
        </tr>
        </thead>
        @foreach($record->days as $day)

            @php
                /** @var TourDayExpense $hotel */
                $hotel = $day->expenses()->where('type', ExpenseType::Hotel)->first();

                if ($record->guide_type == GuideType::Escort) {
                    $guideName = $record->guide_name;
                    $guideStatus = ExpenseStatus::Confirmed;
                } else {
                    /** @var TourDayExpense $expense */
                    $expense = $day->expenses()->where('type', ExpenseType::Guide)->first();
                    // TODO: Guide
                    $guideName = $expense?->guides->map(fn($guide) => $guide->name)->join(', ');
                    $guideStatus = $expense?->status;
                }

                /** @var TourDayExpense $plane */
                $plane = $day->expenses()->where('type', ExpenseType::Flight)->first();

                /** @var TourDayExpense $lunch */
                $lunch = $day->expenses()->where('type', ExpenseType::Lunch)->first();

                /** @var TourDayExpense $dinner */
                $dinner = $day->expenses()->where('type', ExpenseType::Dinner)->first();

                /** @var TourDayExpense $extra */
                $extra = $day->expenses()->where('type', ExpenseType::Extra)->first();
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
                    <div class="flex-td">
                        @if ($plane?->status)
                            <x-filament::badge
                                :color="$plane->status->getColor()"
                                size="sm"
                            >
                                {{ $plane->status->getLabel() }}
                            </x-filament::badge>
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




