@extends('driver.layout', ['title' => __('driver.show.title', ['number' => $t->number])])

@php
    /** @var \App\Support\DriverTransferPresenter $t */
    $next = $t->next();

    // Plain fields, in display order. Prices are absent by construction (see DriverTransferPresenter).
    $details = array_filter([
        __('driver.show.terminal') => $t->terminal,
        __('driver.show.city') => $t->city,
        __('driver.show.pax') => $t->pax,
        __('driver.show.passenger') => $t->passenger,
        __('driver.show.nameplate') => $t->nameplate,
        __('driver.show.mark') => $t->mark,
        __('driver.show.transport') => $t->transportType !== '-' ? $t->transportType : null,
        __('driver.show.comment') => $t->comment,
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

            <dl class="rows">
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
    </main>

    @if ($next)
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
