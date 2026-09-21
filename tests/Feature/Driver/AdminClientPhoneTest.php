<?php

namespace Tests\Feature\Driver;

use App\Filament\Resources\TransferResource\Pages\EditTransfer;
use App\Models\Country;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminClientPhoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 0]));   // Admin
        Country::create(['name' => 'Uzbekistan']);                 // the transfer form's city options look this up
    }

    private function edit(Transfer $transfer)
    {
        return Livewire::test(EditTransfer::class, ['record' => $transfer->id]);
    }

    public function test_the_form_has_a_client_phone_field(): void
    {
        $this->edit(Transfer::factory()->create())->assertFormFieldExists('client_phone');
    }

    public function test_an_international_number_is_saved_compactly(): void
    {
        $transfer = Transfer::factory()->create(['client_phone' => null]);

        $this->edit($transfer)
            ->fillForm(['client_phone' => '+44 7911 123456'])
            ->call('save')
            ->assertHasNoFormErrors();

        // Stored as +<country><number> with no spaces, which is what wa.me / t.me links are built from.
        $this->assertSame('+447911123456', $transfer->fresh()->client_phone);
    }

    public function test_the_phone_is_optional_and_can_be_cleared(): void
    {
        $transfer = Transfer::factory()->create(['client_phone' => '+447911123456']);

        $this->edit($transfer)->fillForm(['client_phone' => ''])->call('save')->assertHasNoFormErrors();

        $this->assertNull($transfer->fresh()->client_phone);
    }

    /** @dataProvider notInternational */
    public function test_a_number_that_is_not_in_international_format_is_refused(string $typed): void
    {
        $transfer = Transfer::factory()->create(['client_phone' => null]);

        $this->edit($transfer)
            ->fillForm(['client_phone' => $typed])
            ->call('save')
            ->assertHasFormErrors(['client_phone']);

        $this->assertNull($transfer->fresh()->client_phone);
    }

    public static function notInternational(): array
    {
        return [
            'too short' => ['12345'],
            'words' => ['abc'],
            // Without a country code a wa.me / t.me link would be built for the wrong number.
            'no country code' => ['(555) 123-4567'],
            'local Uzbek number without +998' => ['90 123 45 67'],
            'plus then zero (not a country code)' => ['+0 123 456 789'],
            'too long' => ['+1234567890123456'],
        ];
    }

    public function test_saving_other_fields_keeps_the_number(): void
    {
        $transfer = Transfer::factory()->create(['client_phone' => '+447911123456', 'pax' => 2]);

        $this->edit($transfer)->fillForm(['pax' => 3])->call('save')->assertHasNoFormErrors();

        $fresh = $transfer->fresh();
        $this->assertSame(3, $fresh->pax);
        $this->assertSame('+447911123456', $fresh->client_phone);
    }

    public function test_an_old_transfer_with_a_website_typed_number_can_still_be_saved(): void
    {
        // Numbers copied from a website booking are stored as the customer typed them, not as +E.164. An
        // operator changing something else on that transfer must not be forced to rewrite the number.
        $transfer = Transfer::factory()->create(['client_phone' => '90 123 45 67', 'pax' => 2]);

        $this->edit($transfer)->fillForm(['pax' => 4])->call('save')->assertHasNoFormErrors();

        $this->assertSame(4, $transfer->fresh()->pax);
        $this->assertStringContainsString('901234567', preg_replace('/\D/', '', $transfer->fresh()->client_phone));
    }
}
