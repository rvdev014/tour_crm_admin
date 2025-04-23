<div class="flex-td">
    <p>{{ $name  }}</p>
    @if ($status)
        <x-filament::badge
            :color="$status->getColor()"
            size="sm"
        >
            {{ $status->getLabel() }}
        </x-filament::badge>
    @endif
</div>
