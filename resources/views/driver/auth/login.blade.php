@extends('driver.layout', ['title' => __('driver.auth.title')])

@section('body')
    <main class="auth">
        <h1>{{ __('driver.app_name') }}</h1>
        <p>{{ __('driver.auth.hint') }}</p>

        <form method="POST" action="{{ route('driver.login.attempt') }}">
            @csrf

            <div class="field">
                <label for="phone">{{ __('driver.auth.phone') }}</label>
                {{-- autocomplete="username" lets the phone's password manager save the pair; inputmode
                     brings up the number pad. --}}
                <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="username"
                       placeholder="+998 90 123 45 67" value="{{ old('phone') }}" required autofocus>
                @error('phone')
                    <div class="err" role="alert">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label for="password">{{ __('driver.auth.password') }}</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                @error('password')
                    <div class="err" role="alert">{{ $message }}</div>
                @enderror
            </div>

            <button class="btn" type="submit">{{ __('driver.auth.submit') }}</button>
        </form>

        @include('driver.partials.lang-switcher')
    </main>
@endsection
