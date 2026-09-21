{{-- $t is an App\Support\DriverTransferPresenter — never the Eloquent model, so no price is reachable here.
     $isDispatcher comes from the including page. --}}
@php($firstDriver = ($isDispatcher ?? false) ? ($t->assignedDrivers[0] ?? null) : null)
<li class="card">
    <a class="card__main" href="{{ route('driver.transfers.show', $t->id) }}">
        <span class="card__time">{{ $t->dateTime?->format('H:i') ?? '--:--' }}</span>
        <span class="card__body">
            <span class="card__title">{{ $t->route ?? '—' }}</span>
            @if ($t->pickup)
                <span class="card__sub">{{ $t->pickup }}</span>
            @endif

            {{-- Dispatcher only: who is driving. Plain text here (the call button is a sibling below). --}}
            @if ($isDispatcher ?? false)
                @if ($firstDriver)
                    <span class="card__who">
                        <svg width="16" height="16" aria-hidden="true"><use href="#i-user"/></svg>
                        {{ $firstDriver['name'] }}@if (count($t->assignedDrivers) > 1) +{{ count($t->assignedDrivers) - 1 }}@endif
                    </span>
                @endif
            @endif

            {{-- The sign the driver holds up, and a preview of the operator's note (clamped to two lines here;
                 the full text is on the trip page). Free text, always escaped. --}}
            @if ($t->nameplate)
                <span class="card__sign"><span class="card__k">{{ __('driver.show.nameplate') }}</span>{{ $t->nameplate }}</span>
            @endif
            @if ($t->comment)
                <span class="card__note">{{ $t->comment }}</span>
            @endif

            <span class="card__meta">
                @include('driver.partials.status-badge', ['status' => $t->status])
                @if ($t->hasNoDriver)
                    <span class="badge badge--warning">{{ __('driver.dispatcher.no_driver') }}</span>
                @endif
                @if ($t->pax)
                    <span>{{ __('driver.list.passengers', ['count' => $t->pax]) }}</span>
                @endif
            </span>
        </span>
    </a>

    {{-- Siblings of the main link, not nested inside it: an <a> in an <a> is invalid HTML and
         unpredictable on touch screens. --}}
    @if ($firstDriver && $firstDriver['tel'])
        <a class="card__map" href="tel:{{ $firstDriver['tel'] }}" aria-label="{{ __('driver.dispatcher.call') }} {{ $firstDriver['name'] }}">
            <svg width="24" height="24" aria-hidden="true"><use href="#i-phone"/></svg>
        </a>
    @endif
    @if ($t->destinationMapUrl)
        <a class="card__map" href="{{ $t->destinationMapUrl }}" target="_blank" rel="noopener"
           aria-label="{{ __('driver.show.open_map') }}">
            <svg width="26" height="26" aria-hidden="true"><use href="#i-pin"/></svg>
        </a>
    @endif
</li>
