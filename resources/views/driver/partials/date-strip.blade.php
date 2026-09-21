{{-- Plain links, no JavaScript: overflow scroll + scroll-snap in CSS. $strip comes from DriverTransferController::index. --}}
<nav class="strip" aria-label="{{ __('driver.nav.today') }}">
    @foreach ($strip as $d)
        <a class="day @if ($d['active']) day--active @endif @if ($d['today']) day--today @endif @if (! $d['count']) day--empty @endif"
           href="{{ route('driver.transfers', ['date' => $d['date']->toDateString()]) }}"
           @if ($d['active']) aria-current="date" @endif
           aria-label="{{ \App\Support\DriverDates::long($d['date']) }}@if ($d['count']), {{ $d['count'] }}@endif">
            <span class="day__wd">{{ \App\Support\DriverDates::weekday($d['date']) }}</span>
            <span class="day__num">{{ $d['date']->day }}</span>
            <span class="day__dot" aria-hidden="true"></span>
        </a>
    @endforeach
</nav>

@if (! $selected->isSameDay($today))
    <a class="jump" href="{{ route('driver.transfers') }}">{{ __('driver.nav.today') }}</a>
@endif
