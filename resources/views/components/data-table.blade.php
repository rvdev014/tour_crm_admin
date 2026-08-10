{{--
    Shared wrapper for the hand-written report tables used across day/room-
    period/status views (previously the same wrapper+table+thead markup was
    copy-pasted into each view, with per-column widths as inline style=""
    attributes). Styling lives in .custom-table-wrapper/.custom-table in
    resources/css/filament/admin/theme.css; column widths are Tailwind's own
    min-w-* utilities passed in via $headers, not inline styles.

    Usage:
        <x-data-table :headers="['Date', 'Cities', 'Hotel']">
            @foreach ($rows as $row)
                <tr>...</tr>
            @endforeach
        </x-data-table>

    $headers accepts either a list of plain labels, or ['label' => ..., 'class' => 'min-w-[150px]']
    for columns that need a minimum width. Omit $headers entirely to render a
    header-less table (used when a component only paints its own <thead> on
    the first of several instances, e.g. periods-column.blade.php).

    Extra classes passed on the tag (e.g. <x-data-table class="custom-table--left">)
    land on the <table> itself — used by periods-column.blade.php, whose cells
    are left-aligned instead of the default centered text.
--}}
@props(['headers' => null])

<div class="custom-table-wrapper">
    <table {{ $attributes->class(['custom-table']) }}>
        @if ($headers)
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th @class([is_array($header) ? ($header['class'] ?? '') : ''])>
                            {{ is_array($header) ? $header['label'] : $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        {{ $slot }}
    </table>
</div>
