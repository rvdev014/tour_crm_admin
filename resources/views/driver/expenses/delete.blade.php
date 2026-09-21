@extends('driver.layout', ['title' => __('driver.expenses.delete_title')])

@section('body')
    <main class="confirm">
        <h1>{{ __('driver.expenses.delete_title') }}</h1>
        <p>
            {{ $expense->type->getLabel() }} · {{ $expense->formattedAmount() }}<br>
            {{ __('driver.expenses.delete_text') }}
        </p>

        <form method="POST" action="{{ route('driver.expenses.destroy', $expense->id) }}">
            @csrf
            <button class="btn" type="submit">{{ __('driver.expenses.delete_yes') }}</button>
        </form>

        <a class="btn btn--ghost" href="{{ route('driver.transfers.show', $transferId) }}">{{ __('driver.flow.confirm_no') }}</a>
    </main>
@endsection
