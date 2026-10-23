<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\TourType;
use App\Filament\Resources\TourTpsResource\Pages\EditTour;
use App\Filament\Resources\TourTpsResource\RelationManagers\DaysRelationManager;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Tour;
use App\Models\TourDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Editing a TPS day and adding an expense must never move the tour's day dates. */
class TpsDayDatesTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;
    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 0]));

        $this->city = City::factory()->create(['country_id' => Country::create(['name' => 'Uzbekistan'])->id]);
        // Tour::factory() still writes the dropped `pax` column; build the tour directly.
        $this->tour = Tour::create([
            'created_by' => auth()->id(),
            'type' => TourType::TPS,
            'company_id' => Company::create(['name' => 'Acme'])->id,
            'group_number' => 'TPS-DATES',
            'requested_by' => 'Tester',
            'pax_foreign' => 2,
            'start_date' => '2026-11-10 00:00:00',
            'end_date' => '2026-11-14 00:00:00',
        ]);
    }

    private function day(string $date): TourDay
    {
        return $this->tour->days()->create(['date' => $date, 'city_id' => $this->city->id]);
    }

    private function dates(): array
    {
        return $this->tour->days()->get()->map(fn ($d) => $d->date->format('Y-m-d'))->all();
    }

    private function editDay(TourDay $day, array $expense): \Livewire\Features\SupportTesting\Testable
    {
        $component = Livewire::test(DaysRelationManager::class, [
            'ownerRecord' => $this->tour,
            'pageClass' => EditTour::class,
        ]);

        // Open the modal first, so the repeater's blank item key is known (as in the browser).
        $component->mountTableAction('edit', $day);
        $state = $component->instance()->mountedTableActionsData[0];
        $key = array_key_first($state['expenses'] ?? []) ?? (string) \Illuminate\Support\Str::uuid();

        return $component
            ->setTableActionData(['expenses' => [$key => $expense]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();
    }

    public function test_adding_a_plain_expense_keeps_every_day_date(): void
    {
        $this->day('2026-11-10');
        $second = $this->day('2026-11-11');
        $this->day('2026-11-12');

        $this->editDay($second, [
            'type' => ExpenseType::Lunch->value,
            'status' => ExpenseStatus::New->value,
        ]);

        $this->assertSame(['2026-11-10', '2026-11-11', '2026-11-12'], $this->dates());
    }

    public function test_hotel_nights_add_the_following_days_from_the_edited_day(): void
    {
        $hotel = Hotel::create(['name' => 'Test Hotel', 'city_id' => $this->city->id]);
        $first = $this->day('2026-11-10');

        $this->editDay($first, [
            'type' => ExpenseType::Hotel->value,
            'status' => ExpenseStatus::New->value,
            'hotel_id' => $hotel->id,
            'hotel_checkin_time' => '14:00',
            'hotel_checkout_date_time' => '2026-11-13 12:00:00',
            'hotel_total_nights' => 3,
        ]);

        $this->assertSame(['2026-11-10', '2026-11-11', '2026-11-12'], $this->dates());
    }

    public function test_edit_form_hydrates_the_day_date_as_a_plain_date_string(): void
    {
        $day = $this->day('2026-11-11');

        $component = Livewire::test(DaysRelationManager::class, [
            'ownerRecord' => $this->tour,
            'pageClass' => EditTour::class,
        ])->mountTableAction('edit', $day);

        $date = $component->instance()->mountedTableActionsData[0]['date'];

        $this->assertIsString($date);
        $this->assertSame('2026-11-11', substr($date, 0, 10));
    }

    public function test_new_day_defaults_to_the_day_after_the_last_one(): void
    {
        $this->day('2026-11-10');
        $this->day('2026-11-12');

        $component = Livewire::test(DaysRelationManager::class, [
            'ownerRecord' => $this->tour,
            'pageClass' => EditTour::class,
        ])->mountTableAction('create');

        $this->assertSame('2026-11-13', substr($component->instance()->mountedTableActionsData[0]['date'], 0, 10));
    }
}
