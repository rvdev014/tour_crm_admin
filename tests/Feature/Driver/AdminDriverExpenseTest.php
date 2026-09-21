<?php

namespace Tests\Feature\Driver;

use App\Enums\DriverExpenseStatus;
use App\Enums\DriverExpenseType;
use App\Filament\Resources\TransferResource;
use App\Filament\Resources\TransferResource\Pages\EditTransfer;
use App\Filament\Resources\TransferResource\RelationManagers\DriverExpensesRelationManager;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\TransferDriverExpense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDriverExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Driver $driver;

    private Transfer $transfer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(TransferDriverExpense::DISK);
        $this->admin = User::factory()->create(['role' => 0]);
        $this->actingAs($this->admin);

        $this->driver = Driver::factory()->create(['name' => 'Rustam Karimov']);
        $this->transfer = Transfer::factory()->forDrivers($this->driver)->create();
    }

    private function expense(array $attrs = [], ?Transfer $transfer = null): TransferDriverExpense
    {
        $path = 'driver-expenses/x/'.Str::uuid().'.jpg';
        Storage::disk(TransferDriverExpense::DISK)->put($path, 'JPEGBYTES');

        return TransferDriverExpense::factory()->create(array_merge([
            'transfer_id' => ($transfer ?? $this->transfer)->id,
            'driver_id' => $this->driver->id,
            'receipt_path' => $path,
        ], $attrs));
    }

    private function manager(?Transfer $transfer = null)
    {
        return Livewire::test(DriverExpensesRelationManager::class, [
            'ownerRecord' => $transfer ?? $this->transfer,
            'pageClass' => EditTransfer::class,
        ]);
    }

    // ── the tab ───────────────────────────────────────────────────────────

    public function test_the_expenses_tab_is_registered_on_the_transfer_page(): void
    {
        $this->assertContains(DriverExpensesRelationManager::class, TransferResource::getRelations());
    }

    public function test_the_tab_lists_each_expense_with_type_amount_and_who_added_it(): void
    {
        $fuel = $this->expense(['type' => DriverExpenseType::Fuel, 'amount' => 150000]);
        $parking = $this->expense(['type' => DriverExpenseType::Parking, 'amount' => 25000]);

        $this->manager()
            ->assertCanSeeTableRecords([$fuel, $parking])
            ->assertTableColumnFormattedStateSet('amount', '150 000 UZS', $fuel)
            ->assertTableColumnFormattedStateSet('amount', '25 000 UZS', $parking)
            ->assertSee(DriverExpenseType::Fuel->getLabel())
            ->assertSee('Rustam Karimov');
    }

    public function test_an_entry_made_by_a_dispatcher_is_labelled_as_such(): void
    {
        $dispatcher = Driver::factory()->dispatcher()->create(['name' => 'Dilnoza']);
        $this->expense(['driver_id' => $dispatcher->id]);

        $this->manager()->assertSee('Dilnoza')->assertSee(__('Dispatcher'));
    }

    public function test_the_photo_column_points_at_the_authenticated_route_never_at_a_storage_path(): void
    {
        $expense = $this->expense();

        $html = $this->manager()->html();

        $this->assertStringContainsString(route('admin.driver-expenses.receipt', $expense), $html);
        $this->assertStringNotContainsString($expense->receipt_path, $html, 'the private storage path must not appear');
        $this->assertStringNotContainsString('/storage/', $html);
    }

    // ── review ────────────────────────────────────────────────────────────

    public function test_approving_records_who_and_when(): void
    {
        $expense = $this->expense();

        $this->manager()->callTableAction('approve', $expense)->assertHasNoTableActionErrors();

        $fresh = $expense->fresh();
        $this->assertSame(DriverExpenseStatus::Approved, $fresh->status);
        $this->assertSame($this->admin->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);
    }

    public function test_rejecting_keeps_the_reason(): void
    {
        $expense = $this->expense();

        $this->manager()
            ->callTableAction('reject', $expense, data: ['review_note' => 'Receipt is blurry'])
            ->assertHasNoTableActionErrors();

        $fresh = $expense->fresh();
        $this->assertSame(DriverExpenseStatus::Rejected, $fresh->status);
        $this->assertSame('Receipt is blurry', $fresh->review_note);
        $this->assertSame($this->admin->id, $fresh->reviewed_by);
    }

    public function test_a_reason_is_optional_when_rejecting(): void
    {
        $expense = $this->expense();

        $this->manager()->callTableAction('reject', $expense, data: ['review_note' => ''])->assertHasNoTableActionErrors();

        $this->assertNull($expense->fresh()->review_note);
        $this->assertSame(DriverExpenseStatus::Rejected, $expense->fresh()->status);
    }

    public function test_review_buttons_disappear_once_an_expense_has_been_reviewed(): void
    {
        $reviewed = $this->expense(['status' => DriverExpenseStatus::Approved]);
        $pending = $this->expense();

        $this->manager()
            ->assertTableActionHidden('approve', $reviewed)
            ->assertTableActionHidden('reject', $reviewed)
            ->assertTableActionVisible('approve', $pending)
            ->assertTableActionVisible('reject', $pending);
    }

    public function test_an_admin_can_reopen_a_review_and_it_clears_the_reviewer(): void
    {
        $expense = $this->expense(['status' => DriverExpenseStatus::Rejected, 'review_note' => 'x', 'reviewed_by' => $this->admin->id, 'reviewed_at' => now()]);

        $this->manager()->callTableAction('reopen', $expense)->assertHasNoTableActionErrors();

        $fresh = $expense->fresh();
        $this->assertSame(DriverExpenseStatus::New, $fresh->status);
        $this->assertNull($fresh->reviewed_by);
        $this->assertNull($fresh->reviewed_at);
        $this->assertNull($fresh->review_note);
    }

    public function test_reopen_and_delete_are_admin_only(): void
    {
        $reviewed = $this->expense(['status' => DriverExpenseStatus::Approved]);
        $this->actingAs(User::factory()->create(['role' => 1]));   // operator

        $this->manager()
            ->assertTableActionHidden('reopen', $reviewed)
            ->assertTableActionHidden('delete_expense', $reviewed);
    }

    public function test_an_admin_can_delete_an_expense_and_its_photo_goes_too(): void
    {
        $expense = $this->expense();
        $path = $expense->receipt_path;

        $this->manager()->callTableAction('delete_expense', $expense)->assertHasNoTableActionErrors();

        $this->assertSame(0, TransferDriverExpense::count());
        Storage::disk(TransferDriverExpense::DISK)->assertMissing($path);
    }

    // ── the cabinet sees the outcome ──────────────────────────────────────

    public function test_a_rejection_reaches_the_driver_with_its_reason_and_locks_the_entry(): void
    {
        $expense = $this->expense();
        $this->manager()->callTableAction('reject', $expense, data: ['review_note' => 'Wrong receipt']);

        $this->actingAs($this->driver, 'driver')
            ->get(route('driver.transfers.show', $this->transfer->id))
            ->assertSee(DriverExpenseStatus::Rejected->getLabel())
            ->assertSee('Wrong receipt');

        $this->post(route('driver.expenses.destroy', $expense->id))->assertSessionHas('driver_error');
        $this->assertSame(1, TransferDriverExpense::count());
    }

    public function test_reopening_lets_the_driver_delete_their_entry_again(): void
    {
        $expense = $this->expense(['status' => DriverExpenseStatus::Approved, 'reviewed_by' => $this->admin->id, 'reviewed_at' => now()]);
        $this->manager()->callTableAction('reopen', $expense);

        $this->actingAs($this->driver, 'driver')
            ->post(route('driver.expenses.destroy', $expense->id))
            ->assertSessionHas('driver_notice');

        $this->assertSame(0, TransferDriverExpense::count());
    }

    // ── money: reviewing must not move any number ─────────────────────────

    public function test_entering_and_reviewing_expenses_never_changes_any_price_or_sends_a_message(): void
    {
        $this->transfer->forceFill([
            'price' => 100, 'total_price' => 200, 'sell_price' => 300, 'buy_price' => 400,
            'sell_price_result' => 300, 'buy_price_result' => 400, 'old_values' => ['pax' => 2],
        ])->saveQuietly();
        $before = Transfer::find($this->transfer->id)->only([
            'price', 'total_price', 'sell_price', 'buy_price', 'sell_price_result', 'buy_price_result', 'old_values', 'status', 'driver_status',
        ]);

        Http::fake();
        $approved = $this->expense(['amount' => 500000]);
        $rejected = $this->expense(['amount' => 700000]);
        $this->manager()
            ->callTableAction('approve', $approved)
            ->callTableAction('reject', $rejected, data: ['review_note' => 'no']);
        $this->manager()->callTableAction('reopen', $approved->fresh());

        $this->assertEquals($before, Transfer::find($this->transfer->id)->only(array_keys($before)));
        Http::assertNothingSent();
    }

    // ── the receipt route for staff ───────────────────────────────────────

    public function test_an_admin_can_open_any_receipt(): void
    {
        $expense = $this->expense();

        $response = $this->get(route('admin.driver-expenses.receipt', $expense))->assertOk();

        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('JPEGBYTES', $response->streamedContent());
    }

    public function test_an_operator_can_open_receipts_only_for_transfers_they_created(): void
    {
        $operator = User::factory()->create(['role' => 1]);
        $mine = Transfer::factory()->create();
        $mine->forceFill(['created_by' => $operator->id])->saveQuietly();   // the creating hook stamps the auth user
        $theirs = Transfer::factory()->create();
        $theirs->forceFill(['created_by' => $this->admin->id])->saveQuietly();

        $this->actingAs($operator);
        $this->get(route('admin.driver-expenses.receipt', $this->expense([], $mine)))->assertOk();
        $this->get(route('admin.driver-expenses.receipt', $this->expense([], $theirs)))->assertForbidden();
    }

    public function test_a_guest_gets_a_403_not_a_redirect_to_the_post_only_login(): void
    {
        $expense = $this->expense();
        auth()->logout();

        $this->get(route('admin.driver-expenses.receipt', $expense))->assertForbidden();
    }

    public function test_a_logged_in_driver_cannot_use_the_admin_receipt_route(): void
    {
        $expense = $this->expense();
        auth()->logout();

        $this->actingAs($this->driver, 'driver')
            ->get(route('admin.driver-expenses.receipt', $expense))
            ->assertForbidden();
    }

    public function test_a_website_customer_account_cannot_open_receipts(): void
    {
        $expense = $this->expense();
        $this->actingAs(User::factory()->create(['role' => 10]));   // UserRole::User: the public-site customer

        $this->get(route('admin.driver-expenses.receipt', $expense))->assertForbidden();
    }

    public function test_the_route_is_bound_to_a_numeric_id(): void
    {
        $this->get('/admin/driver-expenses/not-a-number/receipt')->assertNotFound();
    }

    public function test_a_missing_photo_file_is_a_404(): void
    {
        $expense = $this->expense();
        Storage::disk(TransferDriverExpense::DISK)->delete($expense->receipt_path);

        $this->get(route('admin.driver-expenses.receipt', $expense))->assertNotFound();
    }

    public function test_the_expense_dates_are_shown_in_tashkent_time(): void
    {
        // 2026-09-21 09:00 UTC is 14:00 in Tashkent (UTC+5).
        $this->expense(['created_at' => Carbon::parse('2026-09-21 09:00:00', 'UTC')]);

        $this->manager()->assertSee('21.09.2026 14:00');
    }
}
