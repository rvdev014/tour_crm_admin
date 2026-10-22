<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WebTourStatus;
use App\Filament\Resources\ContactRequestResource;
use App\Filament\Resources\ContactRequestResource\Pages\ListContactRequests;
use App\Filament\Resources\ContactRequestResource\Pages\ViewContactRequest;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Contact requests page: tabs, opening a request, status changes, mailto link. */
class ContactRequestAdminTest extends TestCase
{
    use RefreshDatabase;

    private function as(UserRole $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        return $user;
    }

    private function request(WebTourStatus $status, array $attrs = []): ContactRequest
    {
        return ContactRequest::create(array_merge([
            'name' => 'Anna', 'email' => 'anna@example.com', 'comment' => "Hi\nNeed a tour", 'status' => $status->value,
        ], $attrs));
    }

    public function test_tabs_split_requests_by_status_and_rejected_counts_as_done(): void
    {
        $this->as(UserRole::Admin);
        $new = $this->request(WebTourStatus::New);
        $busy = $this->request(WebTourStatus::Waiting);
        $done = $this->request(WebTourStatus::Done);
        $rejected = $this->request(WebTourStatus::Rejected);

        Livewire::test(ListContactRequests::class)
            ->assertCanSeeTableRecords([$new])
            ->assertCanNotSeeTableRecords([$busy, $done, $rejected])
            ->set('activeTab', 'in_process')
            ->assertCanSeeTableRecords([$busy])
            ->assertCanNotSeeTableRecords([$new, $done])
            ->set('activeTab', 'done')
            ->assertCanSeeTableRecords([$done, $rejected])
            ->assertCanNotSeeTableRecords([$new, $busy]);
    }

    public function test_row_links_to_view_page_and_shows_created_time(): void
    {
        $this->as(UserRole::Admin);
        $request = $this->request(WebTourStatus::New);

        $this->get(ContactRequestResource::getUrl('view', ['record' => $request]))
            ->assertOk()
            ->assertSee('Need a tour')
            ->assertSee($request->created_at->format('d.m.Y H:i'));
    }

    public function test_operator_and_accountant_cannot_open_the_page(): void
    {
        $request = $this->request(WebTourStatus::New);

        foreach ([UserRole::Operator, UserRole::Accountant] as $role) {
            $this->as($role);
            $this->get(ContactRequestResource::getUrl('view', ['record' => $request]))->assertForbidden();
        }
    }

    public function test_table_status_actions_update_status_and_actor(): void
    {
        $admin = $this->as(UserRole::Admin);
        $request = $this->request(WebTourStatus::New);

        Livewire::test(ListContactRequests::class)
            ->callTableAction('set_in_process', $request);
        $this->assertSame(WebTourStatus::Waiting, $request->fresh()->status);
        $this->assertSame($admin->id, $request->fresh()->status_updated_by);

        Livewire::test(ListContactRequests::class)
            ->set('activeTab', 'in_process')
            ->callTableAction('set_done', $request);
        $this->assertSame(WebTourStatus::Done, $request->fresh()->status);
    }

    public function test_view_page_status_actions(): void
    {
        $this->as(UserRole::Admin);
        $request = $this->request(WebTourStatus::New);

        Livewire::test(ViewContactRequest::class, ['record' => $request->id])
            ->callAction('set_done')
            ->assertHasNoActionErrors();

        $this->assertSame(WebTourStatus::Done, $request->fresh()->status);
    }

    public function test_mailto_has_recipient_subject_and_quoted_comment(): void
    {
        $request = $this->request(WebTourStatus::New);

        $url = ContactRequestResource::mailto($request);

        $this->assertStringStartsWith('mailto:anna@example.com?subject=', $url);
        $this->assertStringContainsString(rawurlencode('Re: your request'), $url);
        $this->assertStringContainsString(rawurlencode("> Hi\n> Need a tour"), $url);
    }
}
