<x-filament-panels::page>
    @php
        $contacts = $this->contacts();
        $contact = $this->selectedContact();
        $messages = $this->messages();
        $withinWindow = $contact?->isWithin24HourWindow() ?? false;
    @endphp

    <div
        wire:poll.5s="$refresh"
        class="flex h-[calc(100vh-12rem)] min-h-[32rem] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
    >
        {{-- Conversation list --}}
        <div class="flex w-72 shrink-0 flex-col border-r border-gray-200 dark:border-white/10">
            <div class="p-3">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Search conversations..."
                    />
                </x-filament::input.wrapper>
            </div>

            <div class="flex-1 overflow-y-auto">
                @forelse ($contacts as $item)
                    <button
                        type="button"
                        wire:click="selectContact({{ $item->id }})"
                        wire:key="wa-contact-{{ $item->id }}"
                        @class([
                            'flex w-full items-start gap-3 border-b border-gray-100 px-3 py-2.5 text-left transition dark:border-white/5',
                            'bg-primary-50 dark:bg-primary-500/10' => $item->id === $this->selectedContactId,
                            'hover:bg-gray-50 dark:hover:bg-white/5' => $item->id !== $this->selectedContactId,
                        ])
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-400">
                            {{ mb_strtoupper(mb_substr($item->displayName(), 0, 1)) }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $item->displayName() }}
                                </span>

                                @if ($item->last_message_at)
                                    <span class="shrink-0 text-xs text-gray-400">
                                        {{ $item->last_message_at->diffForHumans(null, true) }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $item->phone }}
                                </span>

                                @if ($item->unread_count > 0)
                                    <x-filament::badge color="danger" size="sm">
                                        {{ $item->unread_count }}
                                    </x-filament::badge>
                                @endif
                            </div>
                        </div>
                    </button>
                @empty
                    <p class="p-4 text-sm text-gray-500 dark:text-gray-400">
                        No conversations yet. Incoming WhatsApp messages will appear here.
                    </p>
                @endforelse
            </div>
        </div>

        {{-- Thread --}}
        <div class="flex flex-1 flex-col">
            @if (! $contact)
                <div class="flex flex-1 items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                    Select a conversation to view messages.
                </div>
            @else
                <div class="flex items-center gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-400">
                        {{ mb_strtoupper(mb_substr($contact->displayName(), 0, 1)) }}
                    </span>

                    <div>
                        <p class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ $contact->displayName() }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $contact->phone }}</p>
                    </div>
                </div>

                <div class="flex-1 space-y-3 overflow-y-auto px-4 py-4" id="wa-thread">
                    @forelse ($messages as $message)
                        <div
                            wire:key="wa-message-{{ $message->id }}"
                            @class([
                                'flex',
                                'justify-end' => $message->direction->value === 'out',
                                'justify-start' => $message->direction->value === 'in',
                            ])
                        >
                            <div
                                @class([
                                    'max-w-md rounded-lg px-3 py-2 text-sm shadow-sm',
                                    'bg-primary-600 text-white' => $message->direction->value === 'out',
                                    'bg-gray-100 text-gray-950 dark:bg-white/5 dark:text-white' => $message->direction->value === 'in',
                                ])
                            >
                                @foreach ($message->attachments as $attachment)
                                    @if ($attachment->category->value === 'photo')
                                        <img
                                            src="{{ $attachment->getUrl() }}"
                                            alt="{{ $attachment->file_name }}"
                                            class="mb-2 max-h-64 rounded-md"
                                        />
                                    @else
                                        <a
                                            href="{{ $attachment->getUrl() }}"
                                            target="_blank"
                                            class="mb-2 flex items-center gap-1 underline"
                                        >
                                            <x-filament::icon icon="heroicon-o-paper-clip" class="h-4 w-4" />
                                            {{ $attachment->file_name }}
                                        </a>
                                    @endif
                                @endforeach

                                @if ($message->body)
                                    <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                                @endif

                                <div
                                    @class([
                                        'mt-1 flex items-center gap-1 text-[11px]',
                                        'justify-end text-white/70' => $message->direction->value === 'out',
                                        'text-gray-400' => $message->direction->value === 'in',
                                    ])
                                >
                                    <span>{{ optional($message->wa_timestamp)->format('H:i') }}</span>

                                    @if ($message->direction->value === 'out')
                                        <span>&middot; {{ $message->status->getLabel() }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No messages yet.</p>
                    @endforelse
                </div>

                <div class="border-t border-gray-200 p-3 dark:border-white/10">
                    @if ($withinWindow)
                        <form wire:submit="send" class="flex items-end gap-2">
                            <div class="flex-1">
                                <x-filament::input.wrapper>
                                    <textarea
                                        wire:model="newMessage"
                                        rows="1"
                                        placeholder="Type a message..."
                                        class="fi-input block w-full resize-none border-none py-1.5 text-base text-gray-950 placeholder:text-gray-400 focus:ring-0 dark:text-white dark:placeholder:text-gray-500 sm:text-sm sm:leading-6"
                                    ></textarea>
                                </x-filament::input.wrapper>
                            </div>

                            <x-filament::button type="submit">
                                Send
                            </x-filament::button>
                        </form>
                    @else
                        <div class="flex items-center gap-2 rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                            <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4 shrink-0" />
                            <span>
                                This contact is outside the 24-hour reply window. Free-form replies aren't allowed
                                by WhatsApp — send a pre-approved template message instead.
                            </span>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
