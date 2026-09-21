<?php

namespace Tests\Feature\Driver;

use App\Enums\DriverRole;
use App\Models\Driver;
use App\Services\CacheService;
use App\Services\TourService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DriverRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // CacheService keeps its values in a STATIC array, which outlives a single test.
        CacheService::$cache = [];
    }

    public function test_existing_and_new_rows_default_to_driver(): void
    {
        // A row inserted without a role — exactly what every pre-existing driver looks like after the migration.
        $id = DB::table('drivers')->insertGetId(['name' => 'Legacy Driver']);

        $this->assertSame(DriverRole::Driver, Driver::find($id)->role);
    }

    public function test_the_role_round_trips_as_an_enum(): void
    {
        $dispatcher = Driver::factory()->dispatcher()->create();

        $this->assertSame(DriverRole::Dispatcher, $dispatcher->fresh()->role);
        $this->assertTrue($dispatcher->fresh()->isDispatcher());
        $this->assertFalse(Driver::factory()->create()->fresh()->isDispatcher());
    }

    public function test_a_model_created_without_a_role_is_treated_as_a_driver_not_a_crash(): void
    {
        // The DB default is not reflected on a model that was never re-read.
        $this->assertFalse((new Driver)->isDispatcher());
    }

    public function test_the_assignable_scope_excludes_dispatchers(): void
    {
        $driver = Driver::factory()->create();
        Driver::factory()->dispatcher()->create();

        $this->assertSame([$driver->id], Driver::assignable()->pluck('id')->all());
    }

    public function test_dispatchers_are_never_offered_in_the_driver_pickers(): void
    {
        $driver = Driver::factory()->create(['name' => 'Rustam']);
        $dispatcher = Driver::factory()->dispatcher()->create(['name' => 'Dilnoza']);

        $options = TourService::getDrivers();

        $this->assertTrue($options->has($driver->id));
        $this->assertFalse($options->has($dispatcher->id), 'a dispatcher must not be assignable to a trip');
    }
}
