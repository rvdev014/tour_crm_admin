@php use Filament\Infolists\Components\TextEntry; @endphp
<?php
?>

<div>
    @foreach($record->days as $day)
        <h3 style="margin-bottom: 5px;">{{ $day->date->format('d.m.Y') }}</h3>
        @foreach($day->expenses as $expense)
            <div style="display:flex; align-items: center; margin-left: 20px;margin-bottom: 10px;">
                {{ $expense->type->getLabel() }} - &nbsp;
                @if ($expense->status?->getLabel())
                    <x-filament::badge
                        :color="$expense->status->getColor()"
                        size="md"
                    >
                        {{ $expense->status->getLabel() }}
                    </x-filament::badge>
                @else
                    <i>Non set</i>
                @endif
            </div>
        @endforeach
    @endforeach
</div>




