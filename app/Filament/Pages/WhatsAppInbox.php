<?php

namespace App\Filament\Pages;

use App\Models\WhatsAppContact;
use App\Services\WhatsAppService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class WhatsAppInbox extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'WhatsApp';

    protected static ?string $title = 'WhatsApp Inbox';

    protected static string $view = 'filament.pages.whats-app-inbox';

    public ?int $selectedContactId = null;

    public string $search = '';

    public string $newMessage = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->isAdmin() || $user->isSeniorOperator() || $user->isOperator());
    }

    public static function getNavigationBadge(): ?string
    {
        $count = WhatsAppContact::where('unread_count', '>', 0)->count();

        return $count > 0 ? (string) $count : null;
    }

    public function contacts(): Collection
    {
        return WhatsAppContact::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q
                        ->where('profile_name', 'ilike', "%{$this->search}%")
                        ->orWhere('phone', 'ilike', "%{$this->search}%")
                        ->orWhere('wa_id', 'ilike', "%{$this->search}%");
                });
            })
            ->recent()
            ->get();
    }

    public function selectedContact(): ?WhatsAppContact
    {
        return $this->selectedContactId
            ? WhatsAppContact::find($this->selectedContactId)
            : null;
    }

    public function messages(): Collection
    {
        $contact = $this->selectedContact();

        if (! $contact) {
            return new Collection;
        }

        return $contact->messages()->with('attachments')->orderBy('wa_timestamp')->get();
    }

    public function selectContact(int $contactId, WhatsAppService $whatsapp): void
    {
        $this->selectedContactId = $contactId;
        $this->newMessage = '';

        $contact = WhatsAppContact::find($contactId);

        if (! $contact || $contact->unread_count === 0) {
            return;
        }

        $contact->update(['unread_count' => 0]);

        $lastInbound = $contact->messages()
            ->where('direction', 'in')
            ->orderByDesc('wa_timestamp')
            ->first();

        if ($lastInbound) {
            $whatsapp->markAsRead($lastInbound->wa_message_id);
        }
    }

    public function send(WhatsAppService $whatsapp): void
    {
        $this->validate(['newMessage' => ['required', 'string', 'max:4096']]);

        $contact = $this->selectedContact();

        if (! $contact) {
            return;
        }

        try {
            $whatsapp->sendText($contact, $this->newMessage, auth()->user());
            $this->newMessage = '';
        } catch (\DomainException $e) {
            Notification::make()
                ->title('Could not send message')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
