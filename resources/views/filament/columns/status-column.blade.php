<div class="flex-td">
    <p>{{ $name }}</p>

    <div class="ep-inline-flex">
        @if (isset($content))
            <div class="mt-1">
                {!! $content !!}
            </div>&nbsp;&nbsp;
        @endif
        @if ($status)
            <x-filament::badge
                :color="$status->getColor()"
                :icon="$status->getIcon()"
                size="sm"
            >
                {{ $status->getLabel() }}
            </x-filament::badge>
        @endif
    </div>
</div>
