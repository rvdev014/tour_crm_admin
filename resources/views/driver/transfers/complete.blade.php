@extends('driver.layout', ['title' => __('driver.flow.confirm_title')])

@section('body')
    <main class="confirm">
        <h1>{{ __('driver.flow.confirm_title') }}</h1>
        <p>{{ __('driver.show.title', ['number' => $t->number]) }}<br>{{ __('driver.flow.confirm_text') }}</p>

        <form method="POST" action="{{ route('driver.transfers.status', $t->id) }}">
            @csrf
            <input type="hidden" name="from" value="{{ $t->status->value }}">
            <input type="hidden" name="to" value="{{ \App\Enums\DriverTransferStatus::Completed->value }}">
            <button class="btn" type="submit">{{ __('driver.flow.confirm_yes') }}</button>
        </form>

        <a class="btn btn--ghost" href="{{ route('driver.transfers.show', $t->id) }}">{{ __('driver.flow.confirm_no') }}</a>
    </main>
@endsection
