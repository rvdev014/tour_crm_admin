{{-- ru / uz switch. GET links so it works without JavaScript, including on the login screen. --}}
<nav class="lang" aria-label="Language">
    @foreach (['ru' => 'RU', 'uz' => 'UZ'] as $code => $label)
        <a href="{{ route('driver.locale', $code) }}"
           @if (app()->getLocale() === $code) aria-current="true" @endif
           lang="{{ $code }}">{{ $label }}</a>
    @endforeach
</nav>
