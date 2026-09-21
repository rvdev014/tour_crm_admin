@extends('driver.layout', ['title' => __('driver.dispatcher.tab_drivers')])

@section('body')
    @include('driver.partials.icons')

    <header class="top">
        <h1 class="top__title">{{ __('driver.dispatcher.tab_drivers') }}</h1>
        @include('driver.partials.lang-switcher')
        <form method="POST" action="{{ route('driver.logout') }}">
            @csrf
            <button class="icon-btn" type="submit" aria-label="{{ __('driver.auth.logout') }}">
                <svg width="24" height="24" aria-hidden="true"><use href="#i-logout"/></svg>
            </button>
        </form>
    </header>

    @include('driver.partials.tabs')

    <main>
        @if ($drivers->isEmpty())
            <p class="empty">{{ __('driver.dispatcher.no_drivers') }}</p>
        @else
            <ul class="list">
                {{-- $drivers holds contact fields only: no chat_id, no password hash, no normalized phone. --}}
                @foreach ($drivers as $person)
                    <li class="card @unless ($person['active']) person--off @endunless">
                        <div class="card__main">
                            <span class="card__body">
                                <span class="card__title person__name">{{ $person['name'] }}</span>
                                @if ($person['car'])
                                    <span class="card__sub">{{ $person['car'] }}</span>
                                @endif
                                @if ($person['phone'])
                                    <span class="card__sub">{{ $person['phone'] }}</span>
                                @endif
                                <span class="card__meta">
                                    @unless ($person['active'])
                                        <span class="badge badge--gray">{{ __('driver.dispatcher.disabled') }}</span>
                                    @endunless
                                    @if ($person['trips'] > 0)
                                        <span class="badge badge--primary">{{ __('driver.dispatcher.trips_today', ['count' => $person['trips']]) }}</span>
                                    @else
                                        <span>{{ __('driver.dispatcher.no_trips') }}</span>
                                    @endif
                                </span>
                            </span>
                        </div>
                        @if ($person['tel'])
                            <a class="card__map" href="tel:{{ $person['tel'] }}"
                               aria-label="{{ __('driver.dispatcher.call') }} {{ $person['name'] }}">
                                <svg width="24" height="24" aria-hidden="true"><use href="#i-phone"/></svg>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </main>
@endsection
