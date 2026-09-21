@extends('driver.layout', ['title' => __('driver.expenses.add')])

@section('body')
    @include('driver.partials.icons')

    <header class="top">
        <a class="top__back" href="{{ route('driver.transfers.show', $t->id) }}" aria-label="{{ __('driver.nav.back') }}">
            <svg width="24" height="24" aria-hidden="true"><use href="#i-back"/></svg>
        </a>
        <h1 class="top__title">{{ __('driver.expenses.add') }}</h1>
        @include('driver.partials.lang-switcher')
    </header>

    <main>
        <p class="person__meta" style="padding: 12px 16px 0; margin: 0;">
            {{ __('driver.show.title', ['number' => $t->number]) }}@if ($t->route) · {{ $t->route }}@endif
        </p>

        {{-- Plain form, no JavaScript. `required` gives the browser's own validation, and the server re-checks
             everything. The hidden token makes a retry or double tap on a bad connection harmless. --}}
        <form method="POST" action="{{ route('driver.transfers.expenses.store', $t->id) }}" enctype="multipart/form-data"
              class="panel form-panel">
            @csrf
            <input type="hidden" name="submission_key" value="{{ $submissionKey }}">

            <div class="field">
                <label for="type">{{ __('driver.expenses.type') }}</label>
                <select id="type" name="type" required>
                    <option value="" disabled @selected(! old('type'))>{{ __('driver.expenses.type_placeholder') }}</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->getLabel() }}</option>
                    @endforeach
                </select>
                @error('type')
                    <div class="err" role="alert">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label for="amount">{{ __('driver.expenses.amount') }}</label>
                <div class="amount">
                    {{-- Text + numeric keypad, not type=number: drivers type "150 000" with spaces. --}}
                    <input id="amount" name="amount" type="text" inputmode="numeric" autocomplete="off"
                           placeholder="150 000" value="{{ old('amount') }}" required>
                    <span class="amount__cur">UZS</span>
                </div>
                @error('amount')
                    <div class="err" role="alert">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label for="receipt">{{ __('driver.expenses.receipt') }}</label>
                {{-- Only jpeg/png/webp: iOS Safari converts HEIC to JPEG for these types. No `capture`, so the
                     phone offers both the camera and the photo library. A file input cannot be refilled after a
                     validation error, so the photo has to be chosen again. --}}
                <input id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp" required>
                <p class="hint">{{ __('driver.expenses.receipt_hint') }}</p>
                @error('receipt')
                    <div class="err" role="alert">{{ $message }}</div>
                @enderror
            </div>

            <button class="btn" type="submit">{{ __('driver.expenses.add') }}</button>
        </form>
    </main>
@endsection
