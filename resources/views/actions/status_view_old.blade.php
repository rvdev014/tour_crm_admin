@php use Filament\Infolists\Components\TextEntry; @endphp
<?php
$record->loadMissing('days.expenses');
?>

<div>
    @foreach($record->days as $day)
        <h3 style="margin-bottom: 5px; font-size: 14px;">{{ $day->date->format('d.m.Y') }}</h3>
        @foreach($day->expenses as $expense)
            <div style="display:flex; align-items: center; margin-left: 20px;margin-bottom: 5px;">
                <span style="font-size: 14px;">{{ $expense->type->getLabel() }}</span>&nbsp; - &nbsp;
                @if ($expense->status?->getLabel())
                    <x-filament::badge
                        :color="$expense->status->getColor()"
                        size="sm"
                    >
                        {{ $expense->status->getLabel() }}
                    </x-filament::badge>
                @else
                    <i style="font-size: 12px">Non set</i>
                @endif
            </div>
        @endforeach
    @endforeach
</div>




