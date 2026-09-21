@extends('driver.layout', ['title' => __('driver.show.title', ['number' => $t->number])])

@php
    /** @var \App\Support\DriverTransferPresenter $t */
    $next = $t->next();

    // Plain fields, in display order. Prices are absent by construction (see DriverTransferPresenter).
    // The pickup sign and the comment are NOT here: they get their own prominent block below the time, and
    // the client's name lives in the "Client" block next to the contact buttons.
    $details = array_filter([
        __('driver.show.terminal') => $t->terminal,
        __('driver.show.city') => $t->city,
        __('driver.show.pax') => $t->pax,
        __('driver.show.mark') => $t->mark,
        __('driver.show.transport') => $t->transportType !== '-' ? $t->transportType : null,
    ], fn ($value) => filled($value));
@endphp

@section('body')
    @include('driver.partials.icons')

    <header class="top">
        <a class="top__back"
           href="{{ $t->dateTime ? route('driver.transfers', ['date' => $t->dateTime->toDateString()]) : route('driver.transfers') }}"
           aria-label="{{ __('driver.nav.back') }}">
            <svg width="24" height="24" aria-hidden="true"><use href="#i-back"/></svg>
        </a>
        <h1 class="top__title">{{ __('driver.show.title', ['number' => $t->number]) }}</h1>
        @include('driver.partials.lang-switcher')
    </header>

    <main>
        @if (session('driver_notice'))
            <div class="flash flash--ok" role="status">{{ session('driver_notice') }}</div>
        @endif
        @if (session('driver_error'))
            <div class="flash flash--err" role="alert">{{ session('driver_error') }}</div>
        @endif

        <section class="panel">
            <div class="hero">
                <span class="hero__time">{{ $t->dateTime?->format('H:i') ?? '--:--' }}</span>
                @if ($t->dateTime)
                    <span class="hero__date">{{ \App\Support\DriverDates::long($t->dateTime) }}</span>
                @endif
                @include('driver.partials.status-badge', ['status' => $t->status])
            </div>

            {{-- What the driver needs at the pickup: the sign to hold up, and the operator's note. Free text, so
                 always escaped; pre-line keeps the operator's line breaks. --}}
            @if ($t->nameplate || $t->comment)
                <div class="callout">
                    @if ($t->nameplate)
                        <div class="callout__label">{{ __('driver.show.nameplate') }}</div>
                        <div class="callout__sign">{{ $t->nameplate }}</div>
                    @endif
                    @if ($t->comment)
                        <div class="callout__label">{{ __('driver.show.comment') }}</div>
                        <div class="callout__text">{{ $t->comment }}</div>
                    @endif
                </div>
            @endif

            {{-- The client. The phone is present only while the trip is open (or for a dispatcher); the name
                 stays regardless. Each button is a plain link — tel:, or a wa.me / t.me universal link that opens
                 the app when it is installed and the website when it is not. --}}
            @if ($t->passenger || $t->clientContact)
                <div class="client">
                    <div class="callout__label">{{ __('driver.show.client') }}</div>
                    @if ($t->passenger)
                        <div class="person__name">{{ $t->passenger }}</div>
                    @endif
                    @if ($t->clientContact)
                        <div class="person__meta">{{ $t->clientContact['display'] }}</div>
                        <div class="contact">
                            <a class="contact__btn contact__btn--call" href="tel:{{ $t->clientContact['tel'] }}">
                                <svg width="24" height="24" aria-hidden="true"><use href="#i-phone"/></svg>
                                <span>{{ __('driver.show.call_client') }}</span>
                            </a>
                            @if ($t->clientContact['whatsapp'])
                                <a class="contact__btn contact__btn--whatsapp" href="{{ $t->clientContact['whatsapp'] }}" target="_blank" rel="noopener">
                                    <svg width="24" height="24" aria-hidden="true"><use href="#i-whatsapp"/></svg>
                                    <span>WhatsApp</span>
                                </a>
                            @endif
                            @if ($t->clientContact['telegram'])
                                <a class="contact__btn contact__btn--telegram" href="{{ $t->clientContact['telegram'] }}" target="_blank" rel="noopener">
                                    <svg width="24" height="24" aria-hidden="true"><use href="#i-telegram"/></svg>
                                    <span>Telegram</span>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            <dl class="rows">
                {{-- Dispatcher only: who is driving, how to reach them. Drivers never get assignedDrivers. --}}
                @if ($isDispatcher)
                    @forelse ($t->assignedDrivers as $person)
                        <div class="row">
                            <div class="row__text">
                                <dt>{{ __('driver.dispatcher.driver') }}</dt>
                                <dd>
                                    <span class="person__name">{{ $person['name'] }}</span>
                                    @if ($person['car'])
                                        <div class="person__meta">{{ $person['car'] }}</div>
                                    @endif
                                    @if ($person['phone'])
                                        <div class="person__meta">{{ $person['phone'] }}</div>
                                    @endif
                                </dd>
                            </div>
                            @if ($person['tel'])
                                <a class="row__map row__map--solid" href="tel:{{ $person['tel'] }}">
                                    <svg width="20" height="20" aria-hidden="true"><use href="#i-phone"/></svg>
                                    {{ __('driver.dispatcher.call') }}
                                </a>
                            @endif
                        </div>
                    @empty
                        <div class="row">
                            <div class="row__text">
                                <dt>{{ __('driver.dispatcher.driver') }}</dt>
                                <dd><span class="badge badge--warning">{{ __('driver.dispatcher.no_driver') }}</span></dd>
                            </div>
                        </div>
                    @endforelse
                @endif

                @if ($t->route)
                    <div class="row">
                        <div class="row__text">
                            <dt>{{ __('driver.show.destination') }}</dt>
                            <dd>{{ $t->route }}</dd>
                        </div>
                        @if ($t->destinationMapUrl)
                            <a class="row__map" href="{{ $t->destinationMapUrl }}" target="_blank" rel="noopener">
                                <svg width="20" height="20" aria-hidden="true"><use href="#i-pin"/></svg>
                                {{ __('driver.show.open_map') }}
                            </a>
                        @endif
                    </div>
                @endif

                @if ($t->pickup)
                    <div class="row">
                        <div class="row__text">
                            <dt>{{ __('driver.show.pickup') }}</dt>
                            <dd>{{ $t->pickup }}</dd>
                        </div>
                        @if ($t->pickupMapUrl)
                            <a class="row__map" href="{{ $t->pickupMapUrl }}" target="_blank" rel="noopener">
                                <svg width="20" height="20" aria-hidden="true"><use href="#i-pin"/></svg>
                                {{ __('driver.show.open_map') }}
                            </a>
                        @endif
                    </div>
                @endif

                @foreach ($details as $label => $value)
                    <div class="row">
                        <div class="row__text">
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- Money spent on this trip. A driver sees only the entries they made; a dispatcher sees all of them.
             Photos are served by an authenticated route (the files are on a private disk). --}}
        <section class="panel" aria-labelledby="expenses-title">
            <div class="panel__head">
                <h2 id="expenses-title">{{ __('driver.expenses.title') }}</h2>
                @if ($expensesTotal)
                    <span class="badge badge--gray">{{ __('driver.expenses.total', ['amount' => $expensesTotal]) }}</span>
                @endif
            </div>

            @forelse ($expenses as $expense)
                <div class="expense">
                    <div class="expense__main">
                        <div class="person__name">{{ $expense->type }} · {{ $expense->amount }}</div>
                        <div class="person__meta">
                            {{ $expense->createdAt->format('d.m H:i') }}
                            @if ($expense->addedBy)
                                · {{ __('driver.expenses.added_by', ['name' => $expense->addedBy]) }}
                            @endif
                        </div>
                        @if ($expense->reviewNote)
                            <div class="person__meta">{{ __('driver.expenses.rejected_note', ['note' => $expense->reviewNote]) }}</div>
                        @endif
                    </div>
                    <span class="badge badge--{{ $expense->status->getColor() }}">{{ $expense->status->getLabel() }}</span>
                    <div class="expense__actions">
                        <a href="{{ $expense->receiptUrl }}" target="_blank" rel="noopener">
                            <svg width="20" height="20" aria-hidden="true"><use href="#i-camera"/></svg>
                            {{ __('driver.expenses.photo') }}
                        </a>
                        @if ($expense->canDelete)
                            <a class="expense__delete" href="{{ route('driver.expenses.delete', $expense->id) }}">{{ __('driver.expenses.delete') }}</a>
                        @endif
                    </div>
                </div>
            @empty
                <p class="person__meta" style="padding: 0 16px 12px; margin: 0;">{{ __('driver.expenses.empty') }}</p>
            @endforelse

            <div class="panel__foot">
                <a class="btn btn--add" href="{{ route('driver.transfers.expenses.create', $t->id) }}">
                    <svg width="20" height="20" aria-hidden="true"><use href="#i-plus"/></svg>
                    {{ __('driver.expenses.add') }}
                </a>
            </div>
        </section>
    </main>

    @if ($isDispatcher)
        <div class="action action--form">
            @if ($t->isClosed)
                <p class="person__meta">{{ __('driver.flow.closed') }}</p>
            @else
                {{-- Any status, either direction: a dispatcher corrects mis-taps and finishes forgotten trips.
                     Plain form, no JavaScript. Each change is logged under the dispatcher's name. --}}
                <form method="POST" action="{{ route('driver.transfers.status', $t->id) }}" class="setstatus">
                    @csrf
                    <label for="to">{{ __('driver.show.status') }}</label>
                    <select id="to" name="to">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($status === $t->status)>{{ $status->getLabel() }}</option>
                        @endforeach
                    </select>
                    <button class="btn" type="submit">{{ __('driver.dispatcher.save') }}</button>
                </form>
            @endif
        </div>
    @elseif ($next)
        <div class="action">
            @if ($next === \App\Enums\DriverTransferStatus::Completed)
                {{-- Last step goes through a confirmation page: no JavaScript confirm() dialog, and a mis-tap is recoverable. --}}
                <a class="btn" href="{{ route('driver.transfers.complete', $t->id) }}">{{ $next->getActionLabel() }}</a>
            @else
                <form method="POST" action="{{ route('driver.transfers.status', $t->id) }}">
                    @csrf
                    {{-- `from` lets the server detect a stale page instead of silently skipping a step. --}}
                    <input type="hidden" name="from" value="{{ $t->status->value }}">
                    <input type="hidden" name="to" value="{{ $next->value }}">
                    <button class="btn" type="submit">{{ $next->getActionLabel() }}</button>
                </form>
            @endif
        </div>
    @elseif ($t->status === \App\Enums\DriverTransferStatus::Completed)
        <div class="action">
            <div class="done">
                <svg width="22" height="22" aria-hidden="true"><use href="#i-check"/></svg>
                {{ __('driver.flow.finished') }}
            </div>
        </div>
    @endif
@endsection
