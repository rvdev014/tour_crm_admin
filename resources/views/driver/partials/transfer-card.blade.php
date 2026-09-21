{{-- $t is an App\Support\DriverTransferPresenter — never the Eloquent model, so no price is reachable here. --}}
<li class="card">
    <a class="card__main" href="{{ route('driver.transfers.show', $t->id) }}">
        <span class="card__time">{{ $t->dateTime?->format('H:i') ?? '--:--' }}</span>
        <span class="card__body">
            <span class="card__title">{{ $t->route ?? '—' }}</span>
            @if ($t->pickup)
                <span class="card__sub">{{ $t->pickup }}</span>
            @endif
            <span class="card__meta">
                @include('driver.partials.status-badge', ['status' => $t->status])
                @if ($t->pax)
                    <span>{{ __('driver.list.passengers', ['count' => $t->pax]) }}</span>
                @endif
            </span>
        </span>
    </a>

    {{-- A sibling of the main link, not nested inside it: an <a> in an <a> is invalid HTML and
         unpredictable on touch screens. --}}
    @if ($t->destinationMapUrl)
        <a class="card__map" href="{{ $t->destinationMapUrl }}" target="_blank" rel="noopener"
           aria-label="{{ __('driver.show.open_map') }}">
            <svg width="26" height="26" aria-hidden="true"><use href="#i-pin"/></svg>
        </a>
    @endif
</li>
