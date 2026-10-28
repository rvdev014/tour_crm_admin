<?php

namespace Tests\Feature;

use App\Enums\CurrencyEnum;
use App\Enums\UserRole;
use App\Filament\Resources\TrainResource\Pages\ListTrains;
use App\Filament\Resources\TrainWebPriceResource\Pages\ListTrainWebPrices;
use Livewire\Livewire;
use App\Models\City;
use App\Models\Currency;
use App\Models\Train;
use App\Models\TrainRequest;
use App\Models\TrainWebPrice;
use App\Models\User;
use App\Services\CacheService;
use App\Services\TrainWebPriceImportService;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TrainWebPriceTest extends TestCase
{
    private City $tashkent;
    private City $bukhara;
    private City $samarkand;

    protected function setUp(): void
    {
        parent::setUp();

        // The app default is Russian; the assertions below read the English messages.
        app()->setLocale('en');
        CacheService::$cache = [];
        $this->tashkent = City::create(['name' => 'Tashkent']);
        $this->bukhara = City::create(['name' => 'Bukhara']);
        $this->samarkand = City::create(['name' => 'Samarkand']);
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function xlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'tp-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function flatSheet(): string
    {
        return $this->xlsx([
            ['Train', 'From', 'To', 'Economy', 'Business', 'VIP'],
            ['Afrosiyob', 'Tashkent', 'Bukhara', 1018000, 1524000, 2276000],
            ['Afrosiyob', 'tashkent ', 'SAMARKAND', 622000, 910000, 1418000],
            ['Sharq', 'Tashkent', 'Bukhara', 842000, 844000, '-'],
        ]);
    }

    public function test_import_writes_both_directions_and_treats_dash_as_no_price(): void
    {
        $result = app(TrainWebPriceImportService::class)->import($this->flatSheet());

        $this->assertSame(['routes' => 3, 'trains' => 2], $result);
        $this->assertSame(6, TrainWebPrice::count());

        $sharq = Train::where('name', 'Sharq')->first();
        $back = TrainWebPrice::where('train_id', $sharq->id)
            ->where('from_city_id', $this->bukhara->id)->where('to_city_id', $this->tashkent->id)->first();

        $this->assertSame(842000.0, $back->price_economy);
        $this->assertNull($back->price_vip);
    }

    public function test_old_sheet_with_route_column_and_merged_return_rows_still_loads(): void
    {
        $path = $this->xlsx([
            ['Train', 'Route', 'Price (economy)', 'Price (business)', 'Price (VIP)'],
            ['Afrosiyob', 'Tashkent - Bukhara', 1018000, 1524000, 2276000],
            [null, 'Bukhara - Tashkent', null, null, null],
            [null, 'Tashkent - Samarkand', 622000, 910000, 1418000],
            [null, 'Samarkand - Tashkent', null, null, null],
        ]);

        $result = app(TrainWebPriceImportService::class)->import($path);

        $this->assertSame(['routes' => 2, 'trains' => 1], $result);
        $this->assertSame(4, TrainWebPrice::count());
    }

    public function test_unknown_city_rejects_the_whole_file_and_keeps_existing_prices(): void
    {
        $importer = app(TrainWebPriceImportService::class);
        $importer->import($this->flatSheet());

        $bad = $this->xlsx([
            ['Train', 'From', 'To', 'Economy', 'Business', 'VIP'],
            ['Afrosiyob', 'Tashkent', 'Bukhara', 1, 2, 3],
            ['Afrosiyob', 'Tashkent', 'Atlantis', 1, 2, 3],
        ]);

        try {
            $importer->import($bad);
            $this->fail('Expected the import to be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Row 3', $e->getMessage());
            $this->assertStringContainsString('Atlantis', $e->getMessage());
        }

        $this->assertSame(6, TrainWebPrice::count());
    }

    public function test_reimport_replaces_previous_prices(): void
    {
        $importer = app(TrainWebPriceImportService::class);
        $importer->import($this->flatSheet());

        $importer->import($this->xlsx([
            ['Train', 'From', 'To', 'Economy', 'Business', 'VIP'],
            ['Afrosiyob', 'Tashkent', 'Samarkand', 100, 200, 300],
        ]));

        $this->assertSame(2, TrainWebPrice::count());
    }

    public function test_export_round_trips_through_import(): void
    {
        $importer = app(TrainWebPriceImportService::class);
        $importer->import($this->flatSheet());

        $before = TrainWebPrice::orderBy('id')->get(['train_id', 'from_city_id', 'to_city_id', 'price_economy', 'price_business', 'price_vip'])->toArray();
        $importer->import($importer->export());
        $after = TrainWebPrice::orderBy('id')->get(['train_id', 'from_city_id', 'to_city_id', 'price_economy', 'price_business', 'price_vip'])->toArray();

        $this->assertEqualsCanonicalizing($before, $after);
    }

    public function test_api_lists_priced_cities_and_route_prices_in_uzs_and_usd(): void
    {
        Currency::query()->where('from', CurrencyEnum::UZS->value)->delete();
        Currency::create(['from' => CurrencyEnum::UZS, 'to' => CurrencyEnum::USD, 'rate' => 12500]);
        CacheService::$cache = [];

        app(TrainWebPriceImportService::class)->import($this->flatSheet());

        $this->getJson('/api/train-prices/cities')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->tashkent->id, 'name' => 'Tashkent']);

        $response = $this->getJson("/api/train-prices?from_city_id={$this->tashkent->id}&to_city_id={$this->bukhara->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $afrosiyob = collect($response->json('data'))->firstWhere('train.name', 'Afrosiyob');
        $this->assertSame('economy', $afrosiyob['prices'][0]['class']);
        $this->assertEquals(1018000, $afrosiyob['prices'][0]['price_uzs']);

        // Sharq has no VIP price, so that class is omitted.
        $sharq = collect($response->json('data'))->firstWhere('train.name', 'Sharq');
        $this->assertSame(['economy', 'business'], array_column($sharq['prices'], 'class'));
    }

    public function test_guaranteed_request_takes_route_and_price_from_the_price_list_not_the_client(): void
    {
        app(TrainWebPriceImportService::class)->import($this->flatSheet());
        $price = TrainWebPrice::where('from_city_id', $this->tashkent->id)->where('to_city_id', $this->bukhara->id)
            ->whereHas('train', fn ($q) => $q->where('name', 'Afrosiyob'))->first();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/train-requests', [
            'from' => 'Somewhere', 'to' => 'Else',
            'departure_date' => now()->addDays(3)->toDateString(),
            'passengers_count' => 2,
            'wagon_class' => 'kupe',
            'train_web_price_id' => $price->id,
            'train_class' => 'business',
            'price_uzs' => 1,
        ])->assertCreated();

        $request = TrainRequest::latest('id')->first();
        $this->assertTrue($request->is_guaranteed);
        $this->assertSame('Tashkent', $request->from);
        $this->assertSame('Bukhara', $request->to);
        $this->assertSame('seated', $request->wagon_class->value);
        $this->assertSame(1524000.0, $request->price_uzs);
    }

    public function test_guaranteed_request_for_a_class_that_is_not_sold_is_rejected(): void
    {
        app(TrainWebPriceImportService::class)->import($this->flatSheet());
        $sharq = TrainWebPrice::whereHas('train', fn ($q) => $q->where('name', 'Sharq'))->first();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/train-requests', [
            'from' => 'a', 'to' => 'b',
            'departure_date' => now()->addDays(3)->toDateString(),
            'passengers_count' => 1,
            'train_web_price_id' => $sharq->id,
            'train_class' => 'vip',
        ])->assertStatus(422);
    }

    public function test_plain_request_without_a_price_still_works(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/train-requests', [
            'from' => 'Tashkent', 'to' => 'Nukus',
            'departure_date' => now()->addDays(3)->toDateString(),
            'passengers_count' => 1,
        ])->assertCreated();

        $this->assertFalse(TrainRequest::latest('id')->first()->is_guaranteed);
    }

    public function test_admin_pages_render_and_upload_action_replaces_prices(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        app(TrainWebPriceImportService::class)->import($this->flatSheet());

        Livewire::test(ListTrains::class)->assertSuccessful()->assertSee('Website prices');

        Livewire::test(ListTrainWebPrices::class)
            ->assertSuccessful()
            ->assertSee('Afrosiyob')
            ->assertSee('Sharq')
            ->assertTableActionExists('edit', null, TrainWebPrice::first())
            ->assertActionExists('upload')
            ->assertActionExists('template');
    }
}
