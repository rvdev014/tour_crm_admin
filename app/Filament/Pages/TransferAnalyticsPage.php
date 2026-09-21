<?php

namespace App\Filament\Pages;

use App\Services\Analytics\TransferAnalytics as Analytics;
use App\Support\AnalyticsAccess;
use App\Support\AnalyticsCharts;
use App\Support\AnalyticsPeriod;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;

/**
 * The owner's transfer money report: key figures, trends and tables on screen, everything in Excel.
 * The calculation lives in App\Services\Analytics — this page only asks for a period and shows the result.
 */
class TransferAnalyticsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'transfer-analytics';

    protected static string $view = 'filament.pages.transfer-analytics';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Set when the requested period was longer than the report allows and had to be cut. */
    public bool $clamped = false;

    public static function canAccess(): bool
    {
        return AnalyticsAccess::allows(auth()->user());
    }

    public static function getNavigationLabel(): string
    {
        return __('analytics.nav');
    }

    public static function getNavigationGroup(): ?string
    {
        return ($group = parent::getNavigationGroup()) ? __($group) : null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('analytics.title');
    }

    public function mount(): void
    {
        $this->form->fill($this->presetData(AnalyticsPeriod::DEFAULT));
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                    Select::make('preset')
                        ->label(__('analytics.page.preset'))
                        ->options(collect(AnalyticsPeriod::PRESETS)->push('custom')->mapWithKeys(
                            fn ($p) => [$p => __("analytics.page.presets.{$p}")]
                        )->all())
                        ->native(false)
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            if ($dates = AnalyticsPeriod::dates((string) $state)) {
                                $set('from', $dates[0]->toDateString());
                                $set('until', $dates[1]->toDateString());
                            }
                        }),
                    DatePicker::make('from')
                        ->label(__('analytics.page.from'))
                        ->native(false)
                        ->displayFormat('d.m.Y')
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('preset', 'custom')),
                    DatePicker::make('until')
                        ->label(__('analytics.page.until'))
                        ->native(false)
                        ->displayFormat('d.m.Y')
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('preset', 'custom')),
                    CheckboxList::make('sources')
                        ->label(__('analytics.page.sources'))
                        ->options(collect(Analytics::SOURCES)->mapWithKeys(fn ($s) => [$s => __("analytics.sources.{$s}")])->all())
                        ->live()
                        ->columns(1),
                ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label(__('analytics.page.download'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                // Re-evaluated on every render, so it always carries the filters currently on screen.
                ->url(fn () => $this->exportUrl()),
        ];
    }

    // ── data ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    #[Computed]
    public function report(): array
    {
        [$from, $to] = $this->range();

        return Analytics::make()->build($from, $to, $this->sources());
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(): array
    {
        $default = AnalyticsPeriod::dates(AnalyticsPeriod::DEFAULT);

        $parse = function (mixed $value, Carbon $fallback): Carbon {
            try {
                return Carbon::createFromFormat('!Y-m-d', substr((string) $value, 0, 10)) ?: $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        };

        $from = $parse($this->data['from'] ?? null, $default[0]);
        $to = $parse($this->data['until'] ?? null, $default[1]);

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        // A longer period is cut rather than refused, so the page never shows an error where a report belongs.
        $this->clamped = $from->diffInDays($to) > Analytics::MAX_DAYS;
        if ($this->clamped) {
            $to = $from->copy()->addDays(Analytics::MAX_DAYS);
        }

        return [$from, $to];
    }

    /** @return list<string> */
    private function sources(): array
    {
        $chosen = array_values(array_intersect(Analytics::SOURCES, (array) ($this->data['sources'] ?? [])));

        // Nothing ticked means "all": an empty report because no box is ticked helps nobody.
        return $chosen === [] ? Analytics::SOURCES : $chosen;
    }

    /** @return array<string, mixed> */
    private function presetData(string $preset): array
    {
        [$from, $to] = AnalyticsPeriod::dates($preset);

        return [
            'preset' => $preset,
            'from' => $from->toDateString(),
            'until' => $to->toDateString(),
            'sources' => Analytics::SOURCES,
        ];
    }

    public function exportUrl(): string
    {
        [$from, $to] = $this->range();

        return route('admin.transfer-analytics.export', [
            'from' => $from->toDateString(),
            'until' => $to->toDateString(),
            'sources' => $this->sources(),
        ]);
    }

    // ── presentation helpers used by the view ─────────────────────────────

    public function uzs(float|int|null $v): string
    {
        return number_format((float) $v, 0, '.', ' ');
    }

    public function usd(float|int|null $v): string
    {
        return number_format((float) $v, 2, '.', ' ');
    }

    public function pct(float|int|null $v, int $decimals = 1): string
    {
        return $v === null ? '—' : number_format($v * 100, $decimals, '.', ' ').'%';
    }

    /** SVG for the months chart, or '' when there is nothing to draw. */
    public function monthsChart(): string
    {
        $periods = [];
        foreach ($this->report['by_month'] as $key => $b) {
            $label = Carbon::parse($key.'-01')->locale($this->locale())->translatedFormat('M y');
            $periods[$label] = ['revenue' => $b['revenue_uzs'], 'expenses' => $b['expenses_uzs'], 'profit' => $b['profit_uzs']];
        }

        return AnalyticsCharts::bars($periods, [
            'revenue' => __('analytics.cols.revenue_uzs'), 'expenses' => __('analytics.summary.expenses'), 'profit' => __('analytics.cols.profit_uzs'),
        ]);
    }

    /** SVG for the daily chart; only worth drawing for a period short enough to read. */
    public function daysChart(): string
    {
        $days = [];
        foreach ($this->report['by_day'] as $key => $b) {
            $days[Carbon::parse($key)->format('d.m')] = ['revenue' => $b['revenue_uzs'], 'profit' => $b['profit_uzs']];
        }

        return count($days) <= 92 ? AnalyticsCharts::lines($days, ['revenue' => __('analytics.cols.revenue_uzs'), 'profit' => __('analytics.cols.profit_uzs')]) : '';
    }

    public function monthLabel(string $key): string
    {
        $text = Carbon::parse($key.'-01')->locale($this->locale())->translatedFormat('F Y');

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    private function locale(): string
    {
        return app()->getLocale() === 'en' ? 'en' : 'ru';
    }

    /** @return array<string, int> issue code => how many, most frequent first */
    public function issueCounts(): array
    {
        $counts = [];
        foreach ($this->report['issues'] as $i) {
            $counts[$i['code']] = ($counts[$i['code']] ?? 0) + 1;
        }
        arsort($counts);

        return $counts;
    }

    /** Codes that make a total wrong or incomplete (vs. plain notes). */
    public function isImportant(string $code): bool
    {
        foreach ($this->report['issues'] as $i) {
            if ($i['code'] === $code) {
                return $i['severity'] === 'warning';
            }
        }

        return false;
    }
}
