<?php

namespace Tests\Feature;

use App\Enums\TransferRequestStatus;
use App\Enums\UserRole;
use App\Enums\WebTourStatus;
use App\Livewire\NewRequestsAlert;
use App\Models\ContactRequest;
use App\Models\TransferRequest;
use App\Models\User;
use App\Services\NewRequestsCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Red badge / row highlight / topbar alert for website requests that nobody has handled yet. */
class NewRequestsAlertTest extends TestCase
{
    use RefreshDatabase;

    private function as(UserRole $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        return $user;
    }

    private function contact(WebTourStatus $status = WebTourStatus::New): ContactRequest
    {
        return ContactRequest::create([
            'name' => 'Anna', 'email' => 'anna@example.com', 'comment' => 'Need a tour', 'status' => $status->value,
        ]);
    }

    private function row(string $key): ?array
    {
        return collect(NewRequestsCounter::summary())->firstWhere('key', $key);
    }

    private function toasts(): int
    {
        return count(session('filament.notifications', []));
    }

    public function test_only_requests_still_in_new_need_attention(): void
    {
        $this->as(UserRole::Admin);
        $new = $this->contact();
        $this->contact(WebTourStatus::Waiting);
        $this->contact(WebTourStatus::Done);
        $this->contact(WebTourStatus::Rejected);

        $row = $this->row('contact-requests');

        $this->assertSame(1, $row['count']);
        $this->assertSame($new->id, $row['latest']);
        $this->assertSame(1, NewRequestsCounter::total(NewRequestsCounter::summary()));
    }

    public function test_the_badge_and_the_row_highlight_use_the_same_definition(): void
    {
        $this->as(UserRole::Admin);
        $new = $this->contact();
        $busy = $this->contact(WebTourStatus::Waiting);

        $this->assertTrue($new->requiresAttention());
        $this->assertFalse($busy->requiresAttention());
        $this->assertSame(
            ContactRequest::needsAttention()->count(),
            (int) \App\Filament\Resources\ContactRequestResource::getNavigationBadge(),
        );
        $this->assertSame('danger', \App\Filament\Resources\ContactRequestResource::getNavigationBadgeColor());
    }

    public function test_transfer_request_needs_attention_only_while_open_and_not_part_of_a_booking(): void
    {
        $make = fn (TransferRequestStatus $status, ?int $booking) => (new TransferRequest)
            ->forceFill(['status' => $status->value, 'transfer_booking_id' => $booking]);

        $this->assertTrue($make(TransferRequestStatus::Created, null)->requiresAttention());
        $this->assertTrue($make(TransferRequestStatus::Booked, null)->requiresAttention());
        $this->assertFalse($make(TransferRequestStatus::Accepted, null)->requiresAttention());
        $this->assertFalse($make(TransferRequestStatus::Booked, 7)->requiresAttention());

        $sql = TransferRequest::needsAttention()->toSql();
        $this->assertStringContainsString('"transfer_booking_id" is null', $sql);
    }

    public function test_a_user_who_cannot_open_a_list_is_not_alerted_about_it(): void
    {
        $this->contact();

        $this->as(UserRole::Operator);
        $this->assertNull($this->row('contact-requests'));

        $this->as(UserRole::Admin);
        $this->assertNotNull($this->row('contact-requests'));
    }

    public function test_first_look_sets_a_baseline_without_alerting_about_old_requests(): void
    {
        $this->as(UserRole::Admin);
        $this->contact();
        $this->contact();

        Livewire::test(NewRequestsAlert::class)
            ->assertSet('total', 2)
            ->assertNotDispatched('new-requests-arrived');

        $this->assertSame(0, $this->toasts());
    }

    public function test_a_request_that_arrives_later_raises_a_toast_once(): void
    {
        $this->as(UserRole::Admin);
        $this->contact();

        $component = Livewire::test(NewRequestsAlert::class)->assertNotDispatched('new-requests-arrived');

        $this->contact();

        $component->call('check')
            ->assertSet('total', 2)
            ->assertDispatched('new-requests-arrived');
        $this->assertSame(1, $this->toasts());

        // Same state on the next poll: no second toast.
        $component->call('check');
        $this->assertSame(1, $this->toasts());
    }

    public function test_handling_the_newest_request_does_not_make_older_ones_look_new_again(): void
    {
        $this->as(UserRole::Admin);
        $older = $this->contact();
        $newest = $this->contact();

        $component = Livewire::test(NewRequestsAlert::class);

        $newest->update(['status' => WebTourStatus::Done->value]);
        $component->call('check')->assertSet('total', 1)->assertNotDispatched('new-requests-arrived');

        $this->assertSame(0, $this->toasts());
        $this->assertTrue($older->fresh()->requiresAttention());
    }

    public function test_the_pill_turns_back_to_calm_when_everything_is_handled(): void
    {
        $this->as(UserRole::Admin);
        $request = $this->contact();

        $component = Livewire::test(NewRequestsAlert::class)->assertSee('1 '.__('new requests'));

        $request->update(['status' => WebTourStatus::Waiting->value]);

        $component->call('check')->assertSet('total', 0)->assertSee(__('No new requests'));
    }

    public function test_the_pill_is_rendered_in_the_admin_topbar(): void
    {
        $this->as(UserRole::Admin);
        $this->contact();

        $this->get(\App\Filament\Resources\ContactRequestResource::getUrl('index'))
            ->assertOk()
            ->assertSee('wire:poll.10s="check"', false)
            ->assertSee('nr-pill', false);
    }

    public function test_new_rows_are_red_in_the_list_and_handled_ones_are_not(): void
    {
        $this->as(UserRole::Admin);
        $new = $this->contact();
        $busy = $this->contact(WebTourStatus::Waiting);

        $html = Livewire::test(\App\Filament\Resources\ContactRequestResource\Pages\ListContactRequests::class)
            ->assertCanSeeTableRecords([$new])
            ->html();
        $this->assertStringContainsString('ep-row-danger', $html);

        $html = Livewire::test(\App\Filament\Resources\ContactRequestResource\Pages\ListContactRequests::class)
            ->set('activeTab', 'in_process')
            ->assertCanSeeTableRecords([$busy])
            ->html();
        $this->assertStringNotContainsString('ep-row-danger', $html);
    }
}
