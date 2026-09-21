<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\Transfer;
use App\Support\DriverTransferPresenter;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The presenter is the first of two barriers keeping driver contact details away from plain drivers
 * (the second is the view's @if ($isDispatcher)). Tested directly so a regression in one layer is not
 * hidden by the other.
 */
class DriverTransferPresenterTest extends TestCase
{
    private function transfer(array $attrs = []): Transfer
    {
        return (new Transfer)->forceFill(array_merge(['id' => 5, 'driver_ids' => ['7', '9']], $attrs));
    }

    private function drivers(): Collection
    {
        return collect([
            7 => (new Driver)->forceFill(['id' => 7, 'name' => 'Rustam', 'phone' => '90 111 22 33', 'car_model' => 'Malibu', 'car_number' => '01 A 123 AA']),
            9 => (new Driver)->forceFill(['id' => 9, 'name' => 'Aziz', 'phone' => '+998912223344', 'car_model' => null, 'car_number' => '  ']),
        ]);
    }

    public function test_without_drivers_passed_in_nothing_about_them_is_exposed(): void
    {
        // The transfer names two drivers, but the caller (a plain driver's request) did not pass them in.
        $p = new DriverTransferPresenter($this->transfer(['driver_name' => 'Taxi Bekzod', 'driver_phone' => '+998905554433']));

        $this->assertSame([], $p->assignedDrivers);
        $this->assertFalse($p->hasNoDriver, 'no "No driver" flag either — a driver has no business with that');
    }

    public function test_with_drivers_passed_in_their_contact_details_are_exposed(): void
    {
        $p = new DriverTransferPresenter($this->transfer(), $this->drivers());

        $this->assertCount(2, $p->assignedDrivers);
        $this->assertSame(
            ['name' => 'Rustam', 'phone' => '90 111 22 33', 'tel' => '+998901112233', 'car' => 'Malibu · 01 A 123 AA'],
            $p->assignedDrivers[0],
        );
        // Blank car fields collapse to null instead of rendering " · ".
        $this->assertSame(['name' => 'Aziz', 'phone' => '+998912223344', 'tel' => '+998912223344', 'car' => null], $p->assignedDrivers[1]);
        $this->assertFalse($p->hasNoDriver);
    }

    public function test_an_empty_assignment_is_flagged_as_no_driver(): void
    {
        $p = new DriverTransferPresenter($this->transfer(['driver_ids' => []]), collect());

        $this->assertSame([], $p->assignedDrivers);
        $this->assertTrue($p->hasNoDriver);
    }

    public function test_a_driver_record_that_no_longer_exists_counts_as_no_driver(): void
    {
        $p = new DriverTransferPresenter($this->transfer(['driver_ids' => ['404']]), $this->drivers());

        $this->assertSame([], $p->assignedDrivers);
        $this->assertTrue($p->hasNoDriver);
    }

    public function test_the_free_text_driver_is_used_only_when_no_driver_record_matched(): void
    {
        $free = ['driver_name' => 'Taxi Bekzod', 'driver_phone' => '90 555 44 33'];

        $withoutRecord = new DriverTransferPresenter($this->transfer(['driver_ids' => []] + $free), collect());
        $this->assertSame('Taxi Bekzod', $withoutRecord->assignedDrivers[0]['name']);
        $this->assertSame('+998905554433', $withoutRecord->assignedDrivers[0]['tel']);
        $this->assertFalse($withoutRecord->hasNoDriver);

        $withRecord = new DriverTransferPresenter($this->transfer($free), $this->drivers());
        $this->assertSame(['Rustam', 'Aziz'], array_column($withRecord->assignedDrivers, 'name'));
    }

    public function test_a_phone_the_normalizer_refuses_is_still_dialable(): void
    {
        $drivers = collect([7 => (new Driver)->forceFill(['id' => 7, 'name' => 'Ravshan', 'phone' => '998299221'])]);

        $p = new DriverTransferPresenter($this->transfer(['driver_ids' => ['7']]), $drivers);

        // Not a trustworthy LOGIN number (see PhoneNormalizer), but a dispatcher can still ring it.
        $this->assertSame('998299221', $p->assignedDrivers[0]['tel']);
    }
}
