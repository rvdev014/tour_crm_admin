<?php

namespace Tests\Feature;

use App\Enums\CompanyType;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\TourType;
use App\Filament\Resources\TourCorporateResource\Pages\CreateTour;
use App\Filament\Resources\TourCorporateResource\Pages\EditTour;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\RoomType;
use App\Models\Tour;
use App\Models\TourDayExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CorporateHotelExpenseCheckInOutTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_hotel_expense_checkin_and_checkout_persist_through_the_real_filament_form(): void
    {
        $user = User::factory()->create(['role' => 0]);
        $this->actingAs($user);

        $country = Country::create(['name' => 'Uzbekistan']);
        $city = City::factory()->create(['country_id' => $country->id]);
        $company = Company::create(['name' => 'Test Corp', 'type' => CompanyType::Corporate->value]);
        // Hotel::factory() references a stale "description" column removed by localization
        // migrations; build the hotel directly to sidestep that unrelated pre-existing bug.
        $hotel = Hotel::create(['name' => 'Test Hotel', 'city_id' => $city->id]);
        $roomType = RoomType::factory()->create();
        HotelRoomType::factory()->create(['hotel_id' => $hotel->id, 'room_type_id' => $roomType->id]);

        // CreateTour mounts with one blank group/passenger/expense already seeded by the
        // Repeater's default state (this mirrors what an operator sees in the browser).
        // Discover those auto-generated item keys and fill through them, exactly like a
        // real "Add group" -> "Add expense" -> pick Hotel -> fill fields flow would.
        $instance = Livewire::test(CreateTour::class);
        $initialState = $instance->instance()->form->getRawState();
        $groupKey = array_key_first($initialState['groups']);
        $passengerKey = array_key_first($initialState['groups'][$groupKey]['passengers']);
        $expenseKey = array_key_first($initialState['groups'][$groupKey]['expenses']);
        $roomTypeKey = array_key_first($initialState['groups'][$groupKey]['expenses'][$expenseKey]['roomTypes']);

        $formState = [
            'group_number' => 'TEST-1',
            'company_id' => $company->id,
            'start_date' => '2026-08-01 00:00:00',
            'end_date' => '2026-08-05 00:00:00',
            'requested_by' => 'Tester',
            'groups' => [
                $groupKey => [
                    'passengers' => [
                        $passengerKey => ['name' => 'John Doe'],
                    ],
                    'expenses' => [
                        $expenseKey => [
                            'type' => ExpenseType::Hotel->value,
                            'city_id' => $city->id,
                            'hotel_id' => $hotel->id,
                            'date' => '2026-08-01 13:00:00',
                            'hotel_checkout_date_time' => '2026-08-03 12:00:00',
                            'status' => ExpenseStatus::New->value,
                            'hotel_total_nights' => 2,
                            'roomTypes' => [
                                $roomTypeKey => [
                                    'room_type_id' => $roomType->id,
                                    'amount_uz' => 1,
                                    'amount_foreign' => 0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $instance->fillForm($formState)
            ->call('create')
            ->assertHasNoFormErrors();

        $tour = Tour::where('type', TourType::Corporate)->latest('id')->first();
        $this->assertNotNull($tour, 'Tour was not created');

        $expense = TourDayExpense::where('type', ExpenseType::Hotel)
            ->whereHas('tourGroup', fn ($q) => $q->where('tour_id', $tour->id))
            ->first();

        $this->assertNotNull($expense, 'Hotel expense was not created');
        $this->assertNotNull($expense->date, 'Check-in date was NOT persisted (regression!)');
        $this->assertSame('2026-08-01 13:00:00', $expense->date->format('Y-m-d H:i:s'));
        $this->assertNotNull($expense->hotel_checkout_date_time, 'Check-out date was NOT persisted (regression!)');
        $this->assertSame('2026-08-03 12:00:00', Carbon::parse($expense->hotel_checkout_date_time)->format('Y-m-d H:i:s'));
        $this->assertSame('13:00', $expense->hotel_checkin_time, 'Legacy hotel_checkin_time not synced');
        $this->assertSame('12:00', $expense->hotel_checkout_time, 'Legacy hotel_checkout_time not synced');

        // Now reopen the tour via EditTour and confirm the pickers hydrate with the saved values
        // (this is exactly what previously came back blank because `date` never dehydrated).
        $editInstance = Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()]);
        $rawState = $editInstance->instance()->form->getRawState();
        $groupState = collect($rawState['groups'])->first();
        $expenseState = collect($groupState['expenses'])->first();

        $this->assertNotEmpty($expenseState['date'] ?? null, 'Check-in field rendered blank on reopen (regression!)');
        $this->assertNotEmpty($expenseState['hotel_checkout_date_time'] ?? null, 'Check-out field rendered blank on reopen (regression!)');
        $this->assertSame(
            '2026-08-01 13:00',
            Carbon::parse($expenseState['date'])->format('Y-m-d H:i')
        );
        $this->assertSame(
            '2026-08-03 12:00',
            Carbon::parse($expenseState['hotel_checkout_date_time'])->format('Y-m-d H:i')
        );
    }
}
