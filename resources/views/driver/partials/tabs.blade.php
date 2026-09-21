{{-- Dispatcher navigation. Drivers never get this partial (see the @if in the pages that include it). --}}
<nav class="tabs" aria-label="{{ __('driver.app_name') }}">
    <a href="{{ route('driver.transfers') }}" @if (request()->routeIs('driver.transfers*')) aria-current="page" @endif>
        {{ __('driver.dispatcher.tab_transfers') }}
    </a>
    <a href="{{ route('driver.drivers') }}" @if (request()->routeIs('driver.drivers')) aria-current="page" @endif>
        {{ __('driver.dispatcher.tab_drivers') }}
    </a>
</nav>
