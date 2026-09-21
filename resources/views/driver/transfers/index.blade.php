@extends('driver.layout', ['title' => \App\Support\DriverDates::short($selected)])

@section('body')
    @include('driver.partials.icons')

    <header class="top">
        {{-- Short date: the weekday is already on the strip, and a long title gets clipped next to the language switch on a phone. --}}
        <h1 class="top__title">{{ \App\Support\DriverDates::short($selected) }}</h1>
        @include('driver.partials.lang-switcher')
        <form method="POST" action="{{ route('driver.logout') }}">
            @csrf
            <button class="icon-btn" type="submit" aria-label="{{ __('driver.auth.logout') }}">
                <svg width="24" height="24" aria-hidden="true"><use href="#i-logout"/></svg>
            </button>
        </form>
    </header>

    @if ($isDispatcher)
        @include('driver.partials.tabs')
    @endif

    <main>
        @include('driver.partials.date-strip')

        @if (session('driver_error'))
            <div class="flash flash--err" role="alert">{{ session('driver_error') }}</div>
        @endif

        @if ($transfers->isEmpty())
            <p class="empty">{{ __('driver.list.empty') }}</p>
        @else
            <ul class="list">
                @foreach ($transfers as $t)
                    @include('driver.partials.transfer-card', ['t' => $t])
                @endforeach
            </ul>
        @endif
    </main>
@endsection
