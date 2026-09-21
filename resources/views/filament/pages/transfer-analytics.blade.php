@php
    $r = $this->report;
    $s = $r['summary'];
    $warnCoverage = $s['priced_share'] !== null && $s['priced_share'] < 0.8;
    $hasData = $r['rows'] !== [];
@endphp

<x-filament-panels::page>
{{-- Scoped, self-contained styles: the panel's CSS is prebuilt and committed, so new Tailwind classes would not
     exist. Colours come from Filament's own CSS variables so light and dark themes both work. --}}
<style>
    .ta { --ta-fg: rgb(var(--gray-950)); --ta-muted: rgb(var(--gray-500)); --ta-card: #fff; --ta-line: rgba(3, 7, 18, .08);
        --ta-head: rgb(var(--gray-50)); --ta-rev: #0d9488; --ta-exp: #f59e0b; --ta-profit: #16a34a; --ta-neg: #dc2626;
        display: grid; gap: 1.25rem; color: var(--ta-fg); }
    .dark .ta { --ta-fg: rgb(var(--gray-50)); --ta-muted: rgb(var(--gray-400)); --ta-card: rgb(var(--gray-900));
        --ta-line: rgba(255, 255, 255, .1); --ta-head: rgb(var(--gray-800)); }
    .ta-card { background: var(--ta-card); border-radius: .75rem; box-shadow: 0 0 0 1px var(--ta-line); padding: 1rem 1.15rem; min-width: 0; }
    .ta-h { margin: 0 0 .75rem; font-size: 1rem; font-weight: 600; }
    .ta-kpis { display: grid; gap: .9rem; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
    .ta-k { font-size: .8rem; color: var(--ta-muted); }
    .ta-v { font-size: 1.5rem; font-weight: 700; line-height: 1.25; margin-top: .15rem; font-variant-numeric: tabular-nums; }
    .ta-s { font-size: .8rem; color: var(--ta-muted); margin-top: .1rem; font-variant-numeric: tabular-nums; }
    .ta-pos { color: var(--ta-profit); } .ta-negv { color: var(--ta-neg); }
    .ta-warn { background: #fef3c7; color: #92400e; border-radius: .75rem; padding: .9rem 1.1rem; font-weight: 500; line-height: 1.45; }
    .dark .ta-warn { background: #451a03; color: #fcd34d; }
    .ta-warn a { color: inherit; text-decoration: underline; font-weight: 700; }
    .ta-2 { display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 420px), 1fr)); }
    .ta-scroll { overflow-x: auto; }
    .ta table { width: 100%; border-collapse: collapse; font-size: .85rem; font-variant-numeric: tabular-nums; }
    .ta th { text-align: right; font-weight: 600; padding: .5rem .6rem; background: var(--ta-head); white-space: nowrap; }
    .ta td { text-align: right; padding: .45rem .6rem; border-top: 1px solid var(--ta-line); white-space: nowrap; }
    .ta th:first-child, .ta td:first-child { text-align: left; white-space: normal; }
    .ta tfoot td { font-weight: 700; background: var(--ta-head); }
    .ta .ta-note { color: var(--ta-muted); font-size: .8rem; white-space: normal; text-align: left; }
    .ta-chart svg { width: 100%; height: auto; display: block; }
    .ta-chart .ta-axis { stroke: var(--ta-line); stroke-width: 1; }
    .ta-chart .ta-label { fill: var(--ta-muted); font-size: 11px; }
    .ta-chart .ta-c-rev { fill: var(--ta-rev); } .ta-chart .ta-c-exp { fill: var(--ta-exp); } .ta-chart .ta-c-profit { fill: var(--ta-profit); }
    .ta-chart .ta-neg { fill: var(--ta-neg); }
    .ta-chart .ta-line { fill: none; stroke-width: 2.2; stroke-linejoin: round; }
    .ta-chart .ta-line.ta-c-rev { stroke: var(--ta-rev); } .ta-chart .ta-line.ta-c-profit { stroke: var(--ta-profit); }
    .ta-keys { display: flex; flex-wrap: wrap; gap: .4rem 1.1rem; margin-top: .4rem; font-size: .8rem; color: var(--ta-muted); }
    .ta-key i { display: inline-block; width: .7rem; height: .7rem; border-radius: 2px; margin-right: .35rem; vertical-align: -1px; }
    .ta-key i.ta-c-rev { background: var(--ta-rev); } .ta-key i.ta-c-exp { background: var(--ta-exp); } .ta-key i.ta-c-profit { background: var(--ta-profit); }
    .ta-empty { text-align: center; padding: 2.5rem 1rem; color: var(--ta-muted); }
    .ta-badge { display: inline-block; padding: .05rem .5rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: var(--ta-head); }
    .ta-badge.ta-important { background: #fef3c7; color: #92400e; } .dark .ta-badge.ta-important { background: #451a03; color: #fcd34d; }
</style>

    <div class="ta">
        <div class="ta-card">
            <h3 class="ta-h">{{ __('analytics.page.filters') }}</h3>
            {{ $this->form }}
            @if ($this->clamped)
                <p class="ta-note" style="margin-top:.6rem">{{ __('analytics.page.too_long', ['days' => \App\Services\Analytics\TransferAnalytics::MAX_DAYS]) }}</p>
            @endif
        </div>

        @unless ($hasData)
            <div class="ta-card ta-empty">{{ __('analytics.summary.no_data') }}</div>
        @else
            {{-- The one thing the owner must not miss: how much of the revenue picture is missing. --}}
            @if ($warnCoverage)
                <div class="ta-warn" role="alert">
                    {{ __('analytics.summary.coverage_warn', ['sheet' => __('analytics.sheets.issues')]) }}
                    ({{ $s['revenue_trips'] - $s['unpriced'] }} / {{ $s['revenue_trips'] }} — {{ $this->pct($s['priced_share'], 0) }}).
                    <a href="{{ $this->exportUrl() }}">{{ __('analytics.page.download') }}</a>
                </div>
            @endif

            <div class="ta-kpis">
                <div class="ta-card">
                    <div class="ta-k">{{ __('analytics.summary.revenue') }}</div>
                    <div class="ta-v">{{ $this->uzs($s['revenue_uzs']) }}</div>
                    <div class="ta-s">UZS · $ {{ $this->usd($s['revenue_usd']) }}</div>
                </div>
                <div class="ta-card">
                    <div class="ta-k">{{ __('analytics.summary.expenses') }}</div>
                    <div class="ta-v">{{ $this->uzs($s['expenses_uzs']) }}</div>
                    <div class="ta-s">UZS · $ {{ $this->usd($s['expenses_usd']) }}</div>
                </div>
                <div class="ta-card">
                    <div class="ta-k">{{ __('analytics.summary.profit') }}</div>
                    <div class="ta-v {{ $s['profit_uzs'] < 0 ? 'ta-negv' : 'ta-pos' }}">{{ $this->uzs($s['profit_uzs']) }}</div>
                    <div class="ta-s">UZS · $ {{ $this->usd($s['profit_usd']) }}</div>
                </div>
                <div class="ta-card">
                    <div class="ta-k">{{ __('analytics.summary.margin') }}</div>
                    <div class="ta-v">{{ $this->pct($s['margin']) }}</div>
                    <div class="ta-s">{{ __('analytics.summary.avg') }}: {{ $s['avg_revenue_per_trip_uzs'] !== null ? $this->uzs($s['avg_revenue_per_trip_uzs']) : '—' }}</div>
                </div>
                <div class="ta-card">
                    <div class="ta-k">{{ __('analytics.summary.trips') }}</div>
                    <div class="ta-v">{{ $this->uzs($s['trips']) }}</div>
                    <div class="ta-s">{{ __('analytics.summary.done_confirmed', ['done' => $s['done'], 'confirmed' => $s['confirmed']]) }}</div>
                </div>
                <div class="ta-card">
                    <div class="ta-k">{{ __('analytics.summary.with_price') }}</div>
                    <div class="ta-v {{ $warnCoverage ? 'ta-negv' : '' }}">{{ $this->pct($s['priced_share'], 0) }}</div>
                    <div class="ta-s">{{ __('analytics.summary.with_buy') }}: {{ $this->pct($s['bought_share'], 0) }}</div>
                </div>
            </div>

            @if ($s['package_trips'] > 0)
                <p class="ta-note" style="margin:0">
                    {{ __('analytics.sources.package') }}: {{ $s['package_trips'] }} · {{ __('analytics.summary.package') }}: {{ $this->uzs($s['package_cost_uzs']) }} UZS
                </p>
            @endif

            @php($monthsSvg = $this->monthsChart())
            @php($daysSvg = $this->daysChart())
            @if ($monthsSvg !== '' || $daysSvg !== '')
                <div class="ta-2">
                    @if ($monthsSvg !== '')
                        <div class="ta-card"><h3 class="ta-h">{{ __('analytics.summary.chart_months') }}</h3>{!! $monthsSvg !!}</div>
                    @endif
                    @if ($daysSvg !== '')
                        <div class="ta-card"><h3 class="ta-h">{{ __('analytics.summary.chart_days') }}</h3>{!! $daysSvg !!}</div>
                    @endif
                </div>
            @endif

            <div class="ta-card">
                <h3 class="ta-h">{{ __('analytics.sheets.months') }}</h3>
                <div class="ta-scroll">
                    <table>
                        <thead><tr>
                            <th>{{ __('analytics.cols.month') }}</th><th>{{ __('analytics.cols.trips') }}</th>
                            <th>{{ __('analytics.cols.revenue_uzs') }}</th><th>{{ __('analytics.cols.supplier_uzs') }}</th>
                            <th>{{ __('analytics.cols.driver_uzs') }}</th><th>{{ __('analytics.cols.profit_uzs') }}</th>
                            <th>{{ __('analytics.cols.margin') }}</th><th>{{ __('analytics.cols.revenue_usd') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($r['by_month'] as $key => $b)
                                <tr>
                                    <td>{{ $this->monthLabel($key) }}</td><td>{{ $b['trips'] }}</td>
                                    <td>{{ $this->uzs($b['revenue_uzs']) }}</td><td>{{ $this->uzs($b['supplier_uzs']) }}</td>
                                    <td>{{ $this->uzs($b['driver_exp_uzs']) }}</td>
                                    <td class="{{ $b['profit_uzs'] < 0 ? 'ta-negv' : '' }}">{{ $this->uzs($b['profit_uzs']) }}</td>
                                    <td>{{ $this->pct($b['margin']) }}</td><td>{{ $this->usd($b['revenue_usd']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr>
                            <td>{{ __('analytics.cols.total') }}</td><td>{{ $s['trips'] }}</td>
                            <td>{{ $this->uzs($s['revenue_uzs']) }}</td><td>{{ $this->uzs($s['supplier_uzs']) }}</td>
                            <td>{{ $this->uzs($s['driver_exp_uzs']) }}</td>
                            <td class="{{ $s['profit_uzs'] < 0 ? 'ta-negv' : '' }}">{{ $this->uzs($s['profit_uzs']) }}</td>
                            <td>{{ $this->pct($s['margin']) }}</td><td>{{ $this->usd($s['revenue_usd']) }}</td>
                        </tr></tfoot>
                    </table>
                </div>
            </div>

            @if (count($r['by_day']) <= 62)
                <div class="ta-card">
                    <h3 class="ta-h">{{ __('analytics.sheets.days') }}</h3>
                    <div class="ta-scroll">
                        <table>
                            <thead><tr>
                                <th>{{ __('analytics.cols.date') }}</th><th>{{ __('analytics.cols.trips') }}</th>
                                <th>{{ __('analytics.cols.revenue_uzs') }}</th><th>{{ __('analytics.cols.supplier_uzs') }}</th>
                                <th>{{ __('analytics.cols.driver_uzs') }}</th><th>{{ __('analytics.cols.profit_uzs') }}</th>
                                <th>{{ __('analytics.cols.margin') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($r['by_day'] as $key => $b)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($key)->format('d.m.Y') }}</td><td>{{ $b['trips'] }}</td>
                                        <td>{{ $this->uzs($b['revenue_uzs']) }}</td><td>{{ $this->uzs($b['supplier_uzs']) }}</td>
                                        <td>{{ $this->uzs($b['driver_exp_uzs']) }}</td>
                                        <td class="{{ $b['profit_uzs'] < 0 ? 'ta-negv' : '' }}">{{ $this->uzs($b['profit_uzs']) }}</td>
                                        <td>{{ $this->pct($b['margin']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <p class="ta-note" style="margin:0">{{ __('analytics.sheets.days') }}: {{ __('analytics.page.more') }}</p>
            @endif

            <div class="ta-2">
                <div class="ta-card">
                    <h3 class="ta-h">{{ __('analytics.summary.by_source') }}</h3>
                    <div class="ta-scroll">
                        <table>
                            <thead><tr>
                                <th>{{ __('analytics.cols.source') }}</th><th>{{ __('analytics.cols.trips') }}</th>
                                <th>{{ __('analytics.cols.revenue_uzs') }}</th><th>{{ __('analytics.cols.profit_uzs') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($r['by_source'] as $source => $b)
                                    <tr>
                                        <td>{{ __("analytics.sources.{$source}") }}<div class="ta-note">{{ __("analytics.source_hint.{$source}") }}</div></td>
                                        <td>{{ $b['trips'] }}</td>
                                        <td>{{ $source === 'package' ? '—' : $this->uzs($b['revenue_uzs']) }}</td>
                                        <td>{{ $source === 'package' ? '—' : $this->uzs($b['profit_uzs']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ta-card">
                    <h3 class="ta-h">{{ __('analytics.page.top_clients') }}</h3>
                    <div class="ta-scroll">
                        <table>
                            <thead><tr>
                                <th>{{ __('analytics.cols.client') }}</th><th>{{ __('analytics.cols.trips') }}</th>
                                <th>{{ __('analytics.cols.revenue_uzs') }}</th><th>{{ __('analytics.cols.profit_uzs') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach (array_slice($r['by_company'], 0, 8, true) as $name => $b)
                                    <tr><td>{{ $name }}</td><td>{{ $b['trips'] }}</td><td>{{ $this->uzs($b['revenue_uzs']) }}</td><td>{{ $this->uzs($b['profit_uzs']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (count($r['by_company']) > 8)<p class="ta-note">{{ __('analytics.page.more') }}</p>@endif
                </div>
            </div>

            @if ($r['by_driver'] !== [])
                <div class="ta-card">
                    <h3 class="ta-h">{{ __('analytics.page.top_drivers') }}</h3>
                    <div class="ta-scroll">
                        <table>
                            <thead><tr>
                                <th>{{ __('analytics.cols.drivers') }}</th><th>{{ __('analytics.cols.trips') }}</th>
                                <th>{{ __('analytics.cols.amount_uzs') }}</th><th>{{ __('analytics.cols.pending') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach (array_slice($r['by_driver'], 0, 10, true) as $name => $d)
                                    <tr><td>{{ $name }}</td><td>{{ $d['trips'] }}</td><td>{{ $this->uzs($d['exp_uzs']) }}</td><td>{{ $this->uzs($d['pending_uzs']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (count($r['by_driver']) > 10)<p class="ta-note">{{ __('analytics.page.more') }}</p>@endif
                </div>
            @endif

            @if ($this->issueCounts() !== [])
                <div class="ta-card">
                    <h3 class="ta-h">{{ __('analytics.sheets.issues') }}</h3>
                    <div class="ta-scroll">
                        <table>
                            <tbody>
                                @foreach ($this->issueCounts() as $code => $n)
                                    <tr>
                                        <td>
                                            <span class="ta-badge {{ $this->isImportant($code) ? 'ta-important' : '' }}">{{ $this->isImportant($code) ? __('analytics.severity.warning') : __('analytics.severity.info') }}</span>
                                            {{ __("analytics.issues.{$code}") }}
                                            <div class="ta-note">{{ __("analytics.issue_action.{$code}") }}</div>
                                        </td>
                                        <td>{{ $n }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="ta-note">{{ __('analytics.page.more') }}</p>
                </div>
            @endif
        @endunless
    </div>
</x-filament-panels::page>
