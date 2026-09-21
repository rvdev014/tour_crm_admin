<?php

namespace Tests\Feature\Driver;

use App\Enums\DriverExpenseStatus;
use App\Enums\DriverExpenseType;
use App\Enums\ExpenseStatus;
use App\Http\Controllers\Driver\DriverExpenseController;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverExpense;
use App\Services\DriverExpenseService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DriverExpenseTest extends TestCase
{
    use RefreshDatabase;

    private Driver $driver;

    private Driver $other;

    private Driver $dispatcher;

    private Transfer $transfer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(TransferDriverExpense::DISK);
        Storage::fake('public');
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00', 'Asia/Tashkent'));

        $this->driver = Driver::factory()->create(['name' => 'Rustam Karimov']);
        $this->other = Driver::factory()->create(['name' => 'Aziz Yusupov']);
        $this->dispatcher = Driver::factory()->dispatcher()->create(['name' => 'Dilnoza Dispatcher']);
        $this->transfer = $this->trip($this->driver);
    }

    // Real (tiny) image files. The container's GD has no JPEG support, so Laravel's UploadedFile::fake()->image()
    // cannot be used — and genuine bytes are a better test anyway: the app sniffs the file's CONTENT.
    private const JPEG = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private const WEBP = 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';

    /**
     * A genuine (not Laravel's fake) upload. UploadedFile::fake() reports its MIME type from the file NAME,
     * so it cannot show whether the app really sniffs the file's bytes. This one is sniffed by finfo, exactly
     * like a browser upload in production.
     */
    private function realUpload(string $content, string $name): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, $name, null, null, true);
    }

    private function jpeg(string $name = 'receipt.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::JPEG));
    }

    private function trip(?Driver $driver = null, array $attrs = []): Transfer
    {
        return Transfer::factory()->forDrivers(...array_filter([$driver]))->create(array_merge([
            'date_time' => Carbon::parse('2026-09-21 14:30:00', 'Asia/Tashkent'),
        ], $attrs));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'parking',
            'amount' => '25 000',
            'receipt' => $this->jpeg(),
            'submission_key' => (string) Str::uuid(),
        ], $overrides);
    }

    private function submit(Transfer $transfer, array $overrides = [], ?Driver $as = null)
    {
        return $this->actingAs($as ?? $this->driver, 'driver')
            ->post(route('driver.transfers.expenses.store', $transfer->id), $this->payload($overrides));
    }

    private function expense(array $attrs = [], ?Transfer $transfer = null, ?Driver $by = null): TransferDriverExpense
    {
        $path = 'driver-expenses/x/'.Str::uuid().'.jpg';
        Storage::disk(TransferDriverExpense::DISK)->put($path, 'JPEGBYTES');

        return TransferDriverExpense::factory()->create(array_merge([
            'transfer_id' => ($transfer ?? $this->transfer)->id,
            'driver_id' => ($by ?? $this->driver)->id,
            'receipt_path' => $path,
        ], $attrs));
    }

    // ── the form ──────────────────────────────────────────────────────────

    public function test_the_form_offers_the_seven_types_a_sum_field_and_a_restricted_photo_input(): void
    {
        $html = $this->actingAs($this->driver, 'driver')
            ->get(route('driver.transfers.expenses.create', $this->transfer->id))
            ->assertOk()->getContent();

        // Seven types; the disabled placeholder is the eighth <option>, and the only one with an empty value.
        $this->assertSame(7, preg_match_all('/<option value="[a-z_]+"/', $html));
        $this->assertSame(1, substr_count($html, '<option value="" disabled'));
        foreach (DriverExpenseType::cases() as $type) {
            $this->assertStringContainsString($type->getLabel(), $html);
        }
        $this->assertStringContainsString('UZS', $html);
        $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertMatchesRegularExpression('/name="submission_key" value="[0-9a-f-]{36}"/', $html);
    }

    public function test_every_rendered_form_carries_a_fresh_token(): void
    {
        $this->actingAs($this->driver, 'driver');
        $url = route('driver.transfers.expenses.create', $this->transfer->id);

        preg_match('/name="submission_key" value="([^"]+)"/', $this->get($url)->getContent(), $a);
        preg_match('/name="submission_key" value="([^"]+)"/', $this->get($url)->getContent(), $b);

        $this->assertNotSame($a[1], $b[1]);
    }

    // ── adding ────────────────────────────────────────────────────────────

    public function test_a_driver_can_add_an_expense_with_a_receipt(): void
    {
        $this->submit($this->transfer)
            ->assertRedirect(route('driver.transfers.show', $this->transfer->id))
            ->assertSessionHas('driver_notice')
            ->assertSessionHasNoErrors();

        $expense = TransferDriverExpense::sole();
        $this->assertSame($this->transfer->id, $expense->transfer_id);
        $this->assertSame($this->driver->id, $expense->driver_id);
        $this->assertSame(DriverExpenseType::Parking, $expense->type);
        $this->assertSame('25000.00', $expense->amount);
        $this->assertSame('UZS', $expense->currency);
        $this->assertSame(DriverExpenseStatus::New, $expense->status);
        $this->assertSame('image/jpeg', $expense->receipt_mime);
    }

    public function test_the_receipt_is_stored_privately_under_a_generated_name(): void
    {
        $this->submit($this->transfer, ['receipt' => $this->jpeg('../../evil name.jpg')]);

        $expense = TransferDriverExpense::sole();

        $this->assertStringStartsWith('driver-expenses/'.$this->transfer->id.'/', $expense->receipt_path);
        $this->assertStringNotContainsString('evil', $expense->receipt_path, "the client's filename is never used");
        Storage::disk(TransferDriverExpense::DISK)->assertExists($expense->receipt_path);
        Storage::disk('public')->assertMissing($expense->receipt_path);
    }

    /** @dataProvider goodAmounts */
    public function test_amounts_typed_the_way_drivers_type_them_are_understood(string $typed, int $expected): void
    {
        $this->assertSame($expected, DriverExpenseController::parseAmount($typed));
    }

    public static function goodAmounts(): array
    {
        return [
            'plain' => ['150000', 150000],
            'space grouped' => ['150 000', 150000],
            'non-breaking space (phone keyboards)' => ["150\u{00A0}000", 150000],
            'comma grouped' => ['150,000', 150000],
            'dot grouped' => ['1.500.000', 1500000],
            'apostrophe grouped' => ["150'000", 150000],
            'small' => ['500', 500],
            'surrounding whitespace' => ['  25 000  ', 25000],
            'the cap itself' => ['1 000 000 000', 1000000000],
        ];
    }

    /** @dataProvider badAmounts */
    public function test_amounts_that_are_not_a_whole_number_of_sums_are_refused_not_guessed_at(string $typed): void
    {
        $this->assertNull(DriverExpenseController::parseAmount($typed));
    }

    public static function badAmounts(): array
    {
        return [
            'empty' => [''],
            'text' => ['abc'],
            // "12.5" must NOT become 125: reading it as thousands would silently record ten times the money.
            'decimal' => ['12.5'],
            'decimal with cents' => ['25.50'],
            'zero' => ['0'],
            'negative' => ['-5'],
            'misplaced grouping' => ['150 00'],
            'over the cap' => ['1 000 000 001'],
            'unit suffix' => ['150000 sum'],
        ];
    }

    public function test_an_invalid_amount_creates_nothing_and_says_why(): void
    {
        $this->submit($this->transfer, ['amount' => '12.5'])->assertSessionHasErrors('amount');

        $this->assertSame(0, TransferDriverExpense::count());
        $this->assertSame([], Storage::disk(TransferDriverExpense::DISK)->allFiles(), 'no photo left behind either');
    }

    public function test_the_receipt_photo_is_required(): void
    {
        $this->submit($this->transfer, ['receipt' => null])->assertSessionHasErrors('receipt');

        $this->assertSame(0, TransferDriverExpense::count());
    }

    public function test_the_type_is_required_and_must_be_one_of_the_seven(): void
    {
        $this->submit($this->transfer, ['type' => ''])->assertSessionHasErrors('type');
        $this->submit($this->transfer, ['type' => 'yacht'])->assertSessionHasErrors('type');

        $this->assertSame(0, TransferDriverExpense::count());
    }

    /** @dataProvider badFiles */
    public function test_only_real_jpeg_png_or_webp_photos_are_accepted(string $kind): void
    {
        $file = match ($kind) {
            'php script renamed .jpg' => $this->realUpload('<?php system($_GET["c"]);', 'receipt.jpg'),
            'svg with script' => $this->realUpload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'receipt.svg'),
            'svg renamed .png' => $this->realUpload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'receipt.png'),
            'html renamed .png' => $this->realUpload('<html><script>alert(1)</script></html>', 'receipt.png'),
            'empty file named .jpg' => $this->realUpload('', 'receipt.jpg'),
            'pdf' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
            'heic' => UploadedFile::fake()->create('receipt.heic', 50, 'image/heic'),
            'oversized' => UploadedFile::fake()->createWithContent('big.jpg', base64_decode(self::JPEG).str_repeat("\0", 11 * 1024 * 1024)),
        };

        $this->submit($this->transfer, ['receipt' => $file])->assertSessionHasErrors('receipt');

        $this->assertSame(0, TransferDriverExpense::count());
        $this->assertSame([], Storage::disk(TransferDriverExpense::DISK)->allFiles());
    }

    public static function badFiles(): array
    {
        return array_map(fn ($k) => [$k], [
            'php script renamed .jpg', 'svg with script', 'svg renamed .png', 'html renamed .png', 'empty file named .jpg',
            'pdf', 'heic', 'oversized',
        ]);
    }

    public function test_a_valid_photo_over_ten_megabytes_is_refused_for_its_size_specifically(): void
    {
        $big = UploadedFile::fake()->createWithContent('big.jpg', base64_decode(self::JPEG).str_repeat("\0", 11 * 1024 * 1024));

        $this->submit($this->transfer, ['receipt' => $big])
            ->assertSessionHasErrors(['receipt' => __('driver.expenses.errors.receipt_size')]);
    }

    public function test_a_photo_just_under_the_limit_is_accepted(): void
    {
        $ok = UploadedFile::fake()->createWithContent('ok.jpg', base64_decode(self::JPEG).str_repeat("\0", 9 * 1024 * 1024));

        $this->submit($this->transfer, ['receipt' => $ok])->assertSessionHasNoErrors();
    }

    public function test_the_stored_type_comes_from_the_files_bytes_not_from_the_name_the_client_claimed(): void
    {
        // PNG bytes uploaded under a .jpg name: accepted (it IS an image), but recorded as what it really is.
        $this->submit($this->transfer, ['receipt' => $this->realUpload(base64_decode(self::PNG), 'claims-to-be.jpg')])
            ->assertSessionHasNoErrors();

        $this->assertSame('image/png', TransferDriverExpense::sole()->receipt_mime);
    }

    public function test_real_uploaded_jpeg_png_and_webp_files_are_accepted(): void
    {
        foreach (['a.jpg' => self::JPEG, 'b.png' => self::PNG, 'c.webp' => self::WEBP] as $name => $b64) {
            $this->submit($this->transfer, ['receipt' => $this->realUpload(base64_decode($b64), $name)])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(
            ['image/jpeg', 'image/png', 'image/webp'],
            TransferDriverExpense::orderBy('id')->pluck('receipt_mime')->all(),
        );
    }

    public function test_png_and_webp_photos_are_accepted_too(): void
    {
        $this->submit($this->transfer, ['receipt' => UploadedFile::fake()->createWithContent('a.png', base64_decode(self::PNG))])->assertSessionHasNoErrors();
        $this->submit($this->transfer, ['receipt' => UploadedFile::fake()->createWithContent('b.webp', base64_decode(self::WEBP))])->assertSessionHasNoErrors();

        $this->assertSame(2, TransferDriverExpense::count());
    }

    // ── retries on a bad connection ───────────────────────────────────────

    public function test_a_retry_with_the_same_token_does_not_create_a_second_expense_or_photo(): void
    {
        $key = (string) Str::uuid();

        $this->submit($this->transfer, ['submission_key' => $key])->assertSessionHasNoErrors();
        $this->submit($this->transfer, ['submission_key' => $key])->assertSessionHasNoErrors();

        $this->assertSame(1, TransferDriverExpense::count());
        $this->assertCount(1, Storage::disk(TransferDriverExpense::DISK)->allFiles());
    }

    public function test_someone_elses_token_cannot_be_used_to_reach_or_overwrite_their_expense(): void
    {
        $victimKey = (string) Str::uuid();
        $victim = $this->expense(['submission_key' => $victimKey]);
        $otherTrip = $this->trip($this->other);

        // A forged request reusing the victim's token must simply create the attacker's OWN expense.
        $this->submit($otherTrip, ['submission_key' => $victimKey, 'amount' => '999'], $this->other)
            ->assertSessionHasNoErrors();

        $this->assertSame(2, TransferDriverExpense::count());
        $this->assertSame('25000.00', $victim->fresh()->amount, 'the victim\'s expense is untouched');
        $mine = TransferDriverExpense::where('driver_id', $this->other->id)->sole();
        $this->assertSame('999.00', $mine->amount);
        $this->assertNotSame($victimKey, $mine->submission_key);
        $this->assertCount(2, Storage::disk(TransferDriverExpense::DISK)->allFiles(), 'no orphaned photo');
    }

    public function test_a_failed_insert_leaves_no_photo_behind(): void
    {
        $ghost = (new Transfer)->forceFill(['id' => 987654321]);   // no such transfer: the foreign key rejects it

        try {
            DriverExpenseService::add($ghost, $this->driver, DriverExpenseType::Fuel, 1000, $this->jpeg('r.jpg'), (string) Str::uuid());
            $this->fail('the foreign key should have refused this');
        } catch (QueryException) {
            // expected
        }

        $this->assertSame([], Storage::disk(TransferDriverExpense::DISK)->allFiles());
    }

    public function test_submissions_are_rate_limited_per_account(): void
    {
        RateLimiter::clear('driver-expenses');

        for ($i = 0; $i < 20; $i++) {
            $this->submit($this->transfer, ['type' => ''])->assertSessionHasErrors('type');
        }

        $this->submit($this->transfer)->assertStatus(429);
        $this->assertSame(0, TransferDriverExpense::count());
    }

    // ── who can add, and where ────────────────────────────────────────────

    public function test_a_driver_cannot_add_to_a_trip_that_is_not_theirs(): void
    {
        $theirs = $this->trip($this->other);

        $this->actingAs($this->driver, 'driver')
            ->get(route('driver.transfers.expenses.create', $theirs->id))->assertNotFound();
        $this->submit($theirs)->assertNotFound();

        $this->assertSame(0, TransferDriverExpense::count());
    }

    public function test_a_dispatcher_can_add_to_any_trip_including_an_unassigned_one(): void
    {
        $unassigned = $this->trip(null);

        $this->submit($unassigned, ['amount' => '10 000'], $this->dispatcher)->assertSessionHasNoErrors();
        $this->submit($this->transfer, ['amount' => '20 000'], $this->dispatcher)->assertSessionHasNoErrors();

        $this->assertSame(2, TransferDriverExpense::where('driver_id', $this->dispatcher->id)->count());
    }

    public function test_expenses_can_be_added_after_the_operator_closed_the_trip_but_not_to_draft_trips(): void
    {
        $done = $this->trip($this->driver, ['status' => ExpenseStatus::Done]);
        $draft = $this->trip($this->driver, ['status' => ExpenseStatus::New]);

        $this->submit($done)->assertSessionHasNoErrors();   // fuel receipts arrive after the trip ends
        $this->submit($draft)->assertNotFound();            // not visible in the cabinet at all

        $this->assertSame(1, TransferDriverExpense::count());
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('driver.transfers.expenses.create', $this->transfer->id))->assertRedirect(route('driver.login'));
        $this->post(route('driver.transfers.expenses.store', $this->transfer->id), $this->payload())->assertRedirect(route('driver.login'));
    }

    // ── what is shown on the trip page ────────────────────────────────────

    public function test_the_trip_page_lists_my_expenses_with_a_total_and_a_photo_link(): void
    {
        $a = $this->expense(['type' => DriverExpenseType::Fuel, 'amount' => 150000]);
        $this->expense(['type' => DriverExpenseType::Parking, 'amount' => 25000]);

        $this->actingAs($this->driver, 'driver')->get(route('driver.transfers.show', $this->transfer->id))
            ->assertOk()
            ->assertSee(DriverExpenseType::Fuel->getLabel())
            ->assertSee('150 000 UZS')
            ->assertSee('25 000 UZS')
            ->assertSee(__('driver.expenses.total', ['amount' => '175 000 UZS']))
            ->assertSee(route('driver.expenses.receipt', $a->id), false)
            ->assertSee(route('driver.transfers.expenses.create', $this->transfer->id), false);
    }

    public function test_a_rejected_expense_is_left_out_of_the_total_and_shows_the_reason(): void
    {
        $this->expense(['amount' => 100000, 'status' => DriverExpenseStatus::Approved]);
        $this->expense([
            'amount' => 40000, 'status' => DriverExpenseStatus::Rejected, 'review_note' => 'Receipt is unreadable',
        ]);

        $this->actingAs($this->driver, 'driver')->get(route('driver.transfers.show', $this->transfer->id))
            ->assertSee(__('driver.expenses.total', ['amount' => '100 000 UZS']))
            ->assertDontSee(__('driver.expenses.total', ['amount' => '140 000 UZS']))
            ->assertSee('Receipt is unreadable');
    }

    public function test_a_driver_sees_only_their_own_entries_not_a_codrivers(): void
    {
        $shared = $this->trip($this->driver);
        $shared->update(['driver_ids' => [(string) $this->driver->id, (string) $this->other->id]]);
        $this->expense(['type' => DriverExpenseType::Water, 'amount' => 3000], $shared, $this->driver);
        $this->expense(['type' => DriverExpenseType::Wash, 'amount' => 777000], $shared, $this->other);

        $this->actingAs($this->driver, 'driver')->get(route('driver.transfers.show', $shared->id))
            ->assertSee('3 000 UZS')
            ->assertDontSee('777 000')
            ->assertDontSee('Aziz Yusupov');
    }

    public function test_a_dispatcher_sees_every_entry_and_who_made_it(): void
    {
        $this->expense(['amount' => 3000], $this->transfer, $this->driver);
        $this->expense(['amount' => 777000], $this->transfer, $this->other);

        $this->actingAs($this->dispatcher, 'driver')->get(route('driver.transfers.show', $this->transfer->id))
            ->assertSee('3 000 UZS')
            ->assertSee('777 000 UZS')
            ->assertSee(__('driver.expenses.added_by', ['name' => 'Rustam Karimov']))
            ->assertSee(__('driver.expenses.added_by', ['name' => 'Aziz Yusupov']));
    }

    public function test_the_expense_pages_never_show_prices_or_the_client_company(): void
    {
        $company = Company::query()->forceCreate(['name' => 'SECRET-COMPANY-LLC']);
        $t = $this->trip($this->driver, ['company_id' => $company->id, 'sell_price' => 3333333.33, 'buy_price' => 4444444.44]);
        $expense = $this->expense([], $t);

        $this->actingAs($this->driver, 'driver');
        foreach ([
            route('driver.transfers.show', $t->id),
            route('driver.transfers.expenses.create', $t->id),
            route('driver.expenses.delete', $expense->id),
        ] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('3333333', $html);
            $this->assertStringNotContainsString('4444444', $html);
            $this->assertStringNotContainsString('SECRET-COMPANY-LLC', $html);
        }
    }

    public function test_the_expense_screens_are_available_in_uzbek(): void
    {
        $this->driver->update(['locale' => 'uz']);

        $this->actingAs($this->driver, 'driver')->get(route('driver.transfers.expenses.create', $this->transfer->id))
            ->assertSee('Xarajat turi')
            ->assertSee("Yoqilg'i quyish")
            ->assertDontSee('Тип затрат');
    }

    // ── deleting ──────────────────────────────────────────────────────────

    public function test_i_can_delete_my_own_unreviewed_expense_and_its_photo_goes_too(): void
    {
        $expense = $this->expense();
        $path = $expense->receipt_path;

        $this->actingAs($this->driver, 'driver')
            ->get(route('driver.expenses.delete', $expense->id))->assertOk()->assertSee(__('driver.expenses.delete_title'));
        $this->post(route('driver.expenses.destroy', $expense->id))
            ->assertRedirect(route('driver.transfers.show', $this->transfer->id))
            ->assertSessionHas('driver_notice');

        $this->assertSame(0, TransferDriverExpense::count());
        Storage::disk(TransferDriverExpense::DISK)->assertMissing($path);
    }

    /** @dataProvider reviewedStatuses */
    public function test_a_reviewed_expense_can_no_longer_be_deleted(DriverExpenseStatus $status): void
    {
        $expense = $this->expense(['status' => $status]);

        $this->actingAs($this->driver, 'driver')
            ->get(route('driver.expenses.delete', $expense->id))->assertRedirect(route('driver.transfers.show', $this->transfer->id));
        $this->post(route('driver.expenses.destroy', $expense->id))->assertSessionHas('driver_error');

        $this->assertSame(1, TransferDriverExpense::count());
        Storage::disk(TransferDriverExpense::DISK)->assertExists($expense->receipt_path);
    }

    public static function reviewedStatuses(): array
    {
        return ['approved' => [DriverExpenseStatus::Approved], 'rejected' => [DriverExpenseStatus::Rejected]];
    }

    public function test_the_page_only_offers_delete_where_it_is_allowed(): void
    {
        $mine = $this->expense();
        $reviewed = $this->expense(['status' => DriverExpenseStatus::Approved]);

        $html = $this->actingAs($this->driver, 'driver')
            ->get(route('driver.transfers.show', $this->transfer->id))->getContent();

        $this->assertStringContainsString(route('driver.expenses.delete', $mine->id), $html);
        $this->assertStringNotContainsString(route('driver.expenses.delete', $reviewed->id), $html);
    }

    public function test_a_driver_cannot_reach_a_codrivers_expense_at_all(): void
    {
        $theirs = $this->expense([], $this->transfer, $this->other);

        $this->actingAs($this->driver, 'driver');
        $this->get(route('driver.expenses.delete', $theirs->id))->assertNotFound();
        $this->post(route('driver.expenses.destroy', $theirs->id))->assertNotFound();
        $this->get(route('driver.expenses.receipt', $theirs->id))->assertNotFound();

        $this->assertSame(1, TransferDriverExpense::count());
    }

    public function test_a_dispatcher_can_delete_their_own_entry_but_not_a_drivers(): void
    {
        $drivers = $this->expense([], $this->transfer, $this->driver);
        $own = $this->expense([], $this->transfer, $this->dispatcher);

        $this->actingAs($this->dispatcher, 'driver');
        $this->post(route('driver.expenses.destroy', $drivers->id))->assertSessionHas('driver_error');
        $this->post(route('driver.expenses.destroy', $own->id))->assertSessionHas('driver_notice');

        $this->assertSame([$drivers->id], TransferDriverExpense::pluck('id')->all());
    }

    // ── the receipt photo ─────────────────────────────────────────────────

    public function test_the_owner_can_open_the_receipt_and_it_is_not_cacheable_or_sniffable(): void
    {
        $expense = $this->expense();

        $response = $this->actingAs($this->driver, 'driver')
            ->get(route('driver.expenses.receipt', $expense->id))->assertOk();

        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_a_dispatcher_can_open_any_receipt(): void
    {
        $expense = $this->expense([], $this->transfer, $this->other);

        $this->actingAs($this->dispatcher, 'driver')->get(route('driver.expenses.receipt', $expense->id))->assertOk();
    }

    public function test_a_guest_cannot_open_a_receipt(): void
    {
        $expense = $this->expense();

        $this->get(route('driver.expenses.receipt', $expense->id))->assertRedirect(route('driver.login'));
    }

    public function test_a_missing_file_is_a_404_not_an_error_page(): void
    {
        $expense = $this->expense();
        Storage::disk(TransferDriverExpense::DISK)->delete($expense->receipt_path);

        $this->actingAs($this->driver, 'driver')->get(route('driver.expenses.receipt', $expense->id))->assertNotFound();
    }

    public function test_a_receipt_is_not_reachable_through_any_public_url(): void
    {
        $this->submit($this->transfer);
        $path = TransferDriverExpense::sole()->receipt_path;

        Storage::disk(TransferDriverExpense::DISK)->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->assertFileDoesNotExist(public_path('storage/'.$path));
    }

    // ── clean-up ──────────────────────────────────────────────────────────

    public function test_deleting_a_transfer_removes_its_receipt_photos_from_disk(): void
    {
        $a = $this->expense();
        $b = $this->expense(['type' => DriverExpenseType::Fuel]);

        $this->transfer->delete();

        $this->assertSame(0, TransferDriverExpense::count());
        Storage::disk(TransferDriverExpense::DISK)->assertMissing($a->receipt_path);
        Storage::disk(TransferDriverExpense::DISK)->assertMissing($b->receipt_path);
    }

    public function test_the_expense_records_who_entered_it_and_survives_that_account_being_deleted(): void
    {
        $expense = $this->expense();

        $this->driver->delete();

        $this->assertNull($expense->fresh()->driver_id, 'the entry stays; only the link to the account is cleared');
    }
}
