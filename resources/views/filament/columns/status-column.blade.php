<div class="flex-td">
    <p>{{ $name }}</p>

    <div style="display: flex; align-items: center;">
        @if (isset($content))
            <div class="mt-1">
                {!! $content !!}
            </div>&nbsp;&nbsp;
        @endif
        @if ($status)
            <x-filament::badge
                :color="$status->getColor()"
                size="sm"
            >
                {{ $status->getLabel() }}
            </x-filament::badge>
        @endif
    </div>
</div>


{{--<p>{{ $name }}</p>--}}
{{--<div style="margin-top: 5px; flex-direction: row; justify-content: center">--}}
{{--    @if (isset($content)) {!! $content !!} &nbsp;&nbsp;@endif--}}
{{--    @if ($status)--}}
{{--        <x-filament::badge--}}
{{--            :color="$status->getColor()"--}}
{{--            size="md"--}}
{{--        >--}}
{{--            {{ $status->getLabel() }}--}}
{{--        </x-filament::badge>--}}
{{--    @endif--}}
{{--</div>--}}
