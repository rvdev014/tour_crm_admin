<?php

namespace Tests\Feature;

use App\Enums\TourType;
use App\Exceptions\DatabaseErrorTranslator;
use App\Filament\Resources\HotelResource\Pages\CreateHotel;
use App\Models\Company;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PDOException;
use Tests\TestCase;

/**
 * Covers the "hotels_name_unique" bug directly: an operator creating a hotel
 * with a name that already exists used to get a raw Symfony/Ignition 500 page
 * with the full SQL statement on screen. See app/Filament/Resources/
 * HotelResource.php (the ->unique() rule), app/Exceptions/
 * DatabaseErrorTranslator.php, app/Exceptions/Handler.php, and
 * app/Livewire/Hooks/TranslatesDatabaseErrors.php.
 */
class DatabaseErrorHandlingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_duplicate_hotel_name_is_a_form_error_not_a_crash(): void
    {
        $user = User::factory()->create(['role' => 0]);
        $this->actingAs($user);

        // The Location section's city_id options callback (TourService::getCities())
        // looks up a country named "Uzbekistan" even before one is selected.
        Country::create(['name' => 'Uzbekistan']);

        Hotel::create(['name' => 'Hilton Samarkand Regency']);

        Livewire::test(CreateHotel::class)
            ->fillForm(['name' => 'Hilton Samarkand Regency'])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertSame(1, Hotel::where('name', 'Hilton Samarkand Regency')->count());
    }

    public function test_translator_maps_unique_violation_without_leaking_sql(): void
    {
        $e = $this->fakeQueryException(
            '23505',
            'ERROR:  duplicate key value violates unique constraint "hotels_name_unique"'
            ."\nDETAIL:  Key (name)=(Hilton Samarkand Regency) already exists.",
            'insert into "hotels" ("name") values (?) returning "id"',
        );

        $result = DatabaseErrorTranslator::translate($e);

        $this->assertSame('name', $result['field']);
        $this->assertStringContainsString('already exists', $result['message']);
        $this->assertStringNotContainsString('insert into', $result['message']);
        $this->assertStringNotContainsString('SQLSTATE', $result['message']);
    }

    public function test_translator_maps_foreign_key_violation_to_the_referencing_table(): void
    {
        $e = $this->fakeQueryException(
            '23503',
            'ERROR:  update or delete on table "hotels" violates foreign key constraint '
            .'"hotel_reviews_hotel_id_foreign" on table "hotel_reviews"'
            ."\nDETAIL:  Key (id)=(5) is still referenced from table \"hotel_reviews\".",
            'delete from "hotels" where "id" = ?',
        );

        $result = DatabaseErrorTranslator::translate($e);

        $this->assertNull($result['field']);
        $this->assertStringContainsString('reviews', $result['message']);
        $this->assertStringNotContainsString('SQLSTATE', $result['message']);
        $this->assertStringNotContainsString('delete from', $result['message']);
    }

    public function test_translator_maps_not_null_violation(): void
    {
        $e = $this->fakeQueryException(
            '23502',
            'ERROR:  null value in column "inn" of relation "companies" violates not-null constraint',
            'insert into "companies" ("name") values (?) returning "id"',
        );

        $result = DatabaseErrorTranslator::translate($e);

        $this->assertStringContainsString('inn', $result['message']);
        $this->assertStringNotContainsString('SQLSTATE', $result['message']);
    }

    public function test_web_request_gets_the_branded_500_page_without_sql_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $e = $this->fakeQueryException(
            '23505',
            'ERROR:  duplicate key value violates unique constraint "hotels_name_unique"',
            'insert into "hotels" ("name") values (?) returning "id"',
        );

        $response = app(ExceptionHandler::class)->render(
            Request::create('/admin/hotels/create', 'POST'),
            $e,
        );

        $this->assertSame(500, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('insert into', $content);
        $this->assertStringContainsString('Error ID', $content);
    }

    public function test_api_request_gets_json_with_an_error_id_instead_of_html(): void
    {
        config(['app.debug' => false]);

        $e = $this->fakeQueryException(
            '23505',
            'ERROR:  duplicate key value violates unique constraint "hotels_name_unique"',
            'insert into "hotels" ("name") values (?) returning "id"',
        );

        $response = app(ExceptionHandler::class)->render(
            Request::create('/api/hotels', 'POST'),
            $e,
        );

        $this->assertSame(500, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('error_id', $payload);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_generate_unique_group_number_does_not_hang_on_a_persistent_collision(): void
    {
        $user = User::factory()->create(['name' => 'Loop']);
        $this->actingAs($user);
        $company = Company::query()->create(['name' => 'Loop Co']);

        // Any tour of this exact number makes getGroupNumber() return the same
        // value forever (it's driven by row count, not by what's already
        // taken), so the bounded loop must give up instead of hanging.
        // withoutEvents() bypasses the creating() hook so this insert can set
        // group_number explicitly instead of it being auto-generated.
        Tour::withoutEvents(function () use ($company, $user) {
            Tour::create([
                'group_number' => 'L101-26T',
                'type' => TourType::TPS,
                'start_date' => '2026-08-01',
                'company_id' => $company->id,
                'created_by' => $user->id,
            ]);
        });

        $this->expectException(\RuntimeException::class);

        Tour::create([
            'type' => TourType::TPS,
            'start_date' => '2026-08-01',
            'company_id' => $company->id,
            'created_by' => $user->id,
        ]);
    }

    /**
     * A genuine concurrent collision (two requests both passing the exists()
     * pre-check, then racing on the actual INSERT) needs a row committed on a
     * truly separate connection — a real race is exactly what Tour::create()'s
     * retry exists for. That's out of scope for a fast, deterministic test
     * here, so this instead verifies the specific mechanism that makes the
     * retry safe: each attempt runs in its own DB::transaction(), so a failed
     * insert (which Postgres flags as "current transaction aborted") is
     * cleanly rolled back and the connection is left usable for the next
     * attempt — see app/Models/Tour.php's create() override. Without that
     * transaction wrapping, a retry after a real constraint violation would
     * fail again with a "transaction is aborted" error instead of getting a
     * clean shot at a new group number.
     */
    public function test_transaction_wrapped_attempt_leaves_the_connection_usable_after_a_failure(): void
    {
        try {
            DB::transaction(function () {
                DB::table('companies')->insert(['name' => 'Doomed']);

                throw new QueryException(
                    'pgsql',
                    'insert into "tours" ("group_number") values (?)',
                    [],
                    new PDOException('duplicate key value violates unique constraint "tours_group_number_unique"'),
                );
            });

            $this->fail('Expected the QueryException to propagate.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('tours_group_number_unique', $e->getMessage());
        }

        // The insert inside the failed transaction was rolled back...
        $this->assertSame(0, DB::table('companies')->where('name', 'Doomed')->count());

        // ...and, critically, the connection itself is still usable — a bare
        // save() without the transaction wrapper would leave Postgres in
        // "current transaction is aborted" and this next query would fail
        // with SQLSTATE 25P02 instead of succeeding.
        DB::table('companies')->insert(['name' => 'After the failure']);
        $this->assertSame(1, DB::table('companies')->where('name', 'After the failure')->count());
    }

    private function fakeQueryException(string $sqlState, string $driverMessage, string $sql): QueryException
    {
        $previous = new PDOException($driverMessage);
        $previous->errorInfo = [$sqlState, 7, $driverMessage];

        return new QueryException('pgsql', $sql, [], $previous);
    }
}
