<?php

namespace App\Services\Analytics;

use App\Enums\CurrencyEnum;
use App\Enums\DriverExpenseStatus;
use App\Enums\ExpenseStatus;
use App\Models\Currency;
use App\Models\Driver;
use App\Models\TransferDriverExpense;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Money report for transfers, for the owner.
 *
 * WHAT COUNTS
 *  - Confirmed and Done transfers, by the transfer's own date (date_time).
 *  - Revenue is `sell_price`. Expenses are the supplier price `buy_price` plus the driver's out-of-pocket
 *    expenses (parking, fuel, ... entered in the cabinet; pending and approved count, rejected does not).
 *
 * SOURCES — where the transfer came from, because it decides whether its price is revenue:
 *  - website    : a customer booked on the site           -> revenue
 *  - manual     : an operator created it by hand          -> revenue
 *  - corporate  : created from a Corporate tour's transport -> revenue (the CRM's own company-income report
 *                 treats it the same way)
 *  - package    : created from a package (TPS) tour        -> NOT revenue. The transfer's `sell_price` is a
 *                 copy of the tour expense (a COST of the package); the client pays one price for the whole
 *                 tour, which is the tour's income. Counting it here would double-count. Its costs are still
 *                 reported (as `package_*`) so "all expenses" stays complete.
 *
 * MONEY — see TransferMoney: currency-less prices are resolved by magnitude and flagged, converted UZS
 * results are trusted only when they really look converted.
 *
 * What this cannot know: whether the client has actually PAID. Transfers have no payment status, so
 * "revenue" here means billed, not cash received.
 */
final class TransferAnalytics
{
    public const SOURCE_WEBSITE = 'website';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_CORPORATE = 'corporate';

    public const SOURCE_PACKAGE = 'package';

    public const SOURCES = [self::SOURCE_WEBSITE, self::SOURCE_MANUAL, self::SOURCE_CORPORATE, self::SOURCE_PACKAGE];

    /** The longest range that can be requested; a report over more than this is not a report. */
    public const MAX_DAYS = 800;

    private const TOUR_TYPE_TPS = 1;

    private const TOUR_TYPE_CORPORATE = 2;

    private const ID_CHUNK = 4000;

    public function __construct(private readonly float $usdRate) {}

    /** The rate configured in the CRM (UZS per 1 USD), or 0 when there is none. */
    public static function make(): self
    {
        $rate = Currency::query()
            ->where('from', CurrencyEnum::UZS->value)
            ->where('to', CurrencyEnum::USD->value)
            ->value('rate');

        return new self(is_numeric($rate) ? (float) $rate : 0.0);
    }

    public function usdRate(): float
    {
        return $this->usdRate;
    }

    /**
     * @param  list<string>|null  $sources  restrict to these sources; null = all
     */
    public function build(CarbonInterface $from, CarbonInterface $to, ?array $sources = null): array
    {
        $from = Carbon::instance($from)->startOfDay();
        $to = Carbon::instance($to)->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        if ($from->diffInDays($to) > self::MAX_DAYS) {
            throw new \InvalidArgumentException('The report period is too long (more than '.self::MAX_DAYS.' days).');
        }

        $sources = $sources === null ? self::SOURCES : array_values(array_intersect(self::SOURCES, $sources));

        $rows = $this->rows($from, $to, $sources);
        $expenses = $this->driverExpenses(array_column($rows, 'id'));

        $rows = $this->attachExpenses($rows, $expenses['byTransfer']);

        return [
            'from' => $from,
            'to' => $to,
            'usd_rate' => $this->usdRate,
            'sources' => $sources,
            'rows' => $rows,
            'summary' => $this->summary($rows),
            'by_day' => $this->byPeriod($rows, $from, $to, 'day'),
            'by_month' => $this->byPeriod($rows, $from, $to, 'month'),
            'by_source' => $this->bySource($rows, $sources),
            'by_company' => $this->groupBy($rows, 'company'),
            'by_operator' => $this->groupBy($rows, 'operator'),
            'by_city' => $this->groupBy($rows, 'city'),
            'by_driver' => $this->byDriver($rows, $expenses['list']),
            'driver_expenses' => $expenses['list'],
            'expense_types' => $expenses['types'],
            'issues' => $this->issues($rows),
        ];
    }

    // ── loading ───────────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(Carbon $from, Carbon $to, array $sources): array
    {
        $drivers = Driver::query()->pluck('name', 'id');

        $records = DB::table('transfers as t')
            ->leftJoin('tour_day_expenses as e', 'e.id', '=', 't.tour_day_expense_id')
            ->leftJoin('tour_days as d', 'd.id', '=', 'e.tour_day_id')
            ->leftJoin('tour_groups as g', 'g.id', '=', 'e.tour_group_id')
            ->leftJoin('tours as tr', 'tr.id', '=', DB::raw('COALESCE(e.tour_id, d.tour_id, g.tour_id)'))
            ->leftJoin('companies as c', 'c.id', '=', 't.company_id')
            ->leftJoin('users as u', 'u.id', '=', 't.created_by')
            ->leftJoin('cities as tc', 'tc.id', '=', 't.to_city_id')
            ->whereIn('t.status', [ExpenseStatus::Confirmed->value, ExpenseStatus::Done->value])
            ->whereBetween('t.date_time', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->orderBy('t.date_time')
            ->orderBy('t.id')
            ->get([
                't.id', 't.number', 't.date_time', 't.status', 't.pax', 't.route', 't.driver_ids',
                't.sell_price', 't.sell_price_currency', 't.sell_price_result',
                't.buy_price', 't.buy_price_currency', 't.buy_price_result',
                't.transfer_request_id', 't.tour_day_expense_id',
                'tr.type as tour_type', 'c.name as company', 'u.name as operator', 'tc.name as city',
            ]);

        $out = [];

        foreach ($records as $r) {
            $source = $this->source($r);

            if (! in_array($source, $sources, true)) {
                continue;
            }

            $sell = TransferMoney::normalize($r->sell_price, $r->sell_price_currency, $r->sell_price_result, $this->usdRate);
            $buy = TransferMoney::normalize($r->buy_price, $r->buy_price_currency, $r->buy_price_result, $this->usdRate);

            $driverIds = array_values(array_filter((array) json_decode((string) $r->driver_ids, true), 'is_numeric'));
            $driverNames = array_values(array_filter(array_map(fn ($id) => $drivers[(int) $id] ?? null, $driverIds)));

            $out[] = [
                'id' => (int) $r->id,
                'number' => $r->number !== null ? (int) $r->number : 1000 + (int) $r->id,
                'date_time' => $r->date_time,
                'day' => substr((string) $r->date_time, 0, 10),
                'month' => substr((string) $r->date_time, 0, 7),
                'status' => (int) $r->status,
                'source' => $source,
                'company' => $r->company ?: null,
                'operator' => $r->operator ?: null,
                'city' => $r->city ?: null,
                'route' => $r->route ?: null,
                'pax' => $r->pax !== null ? (int) $r->pax : null,
                'drivers' => $driverNames,
                'sell' => $sell,
                'buy' => $buy,
                // filled by attachExpenses()
                'exp_uzs' => 0.0,
                'exp_approved_uzs' => 0.0,
                'exp_pending_uzs' => 0.0,
            ];
        }

        return $out;
    }

    private function source(object $r): string
    {
        if ($r->transfer_request_id !== null) {
            return self::SOURCE_WEBSITE;
        }

        if ($r->tour_day_expense_id === null) {
            return self::SOURCE_MANUAL;
        }

        // Tour-linked. A tour we cannot resolve is treated as a package tour: the safe side, since counting
        // a mirrored cost as revenue would overstate income.
        return (int) $r->tour_type === self::TOUR_TYPE_CORPORATE ? self::SOURCE_CORPORATE : self::SOURCE_PACKAGE;
    }

    /**
     * @param  list<int>  $transferIds
     * @return array{list: list<array<string, mixed>>, byTransfer: array<int, array{approved: float, pending: float}>, types: array<string, array{count: int, uzs: float, usd: float}>}
     */
    private function driverExpenses(array $transferIds): array
    {
        $list = [];
        $byTransfer = [];
        $types = [];

        foreach (array_chunk($transferIds, self::ID_CHUNK) as $chunk) {
            TransferDriverExpense::query()
                ->whereIn('transfer_id', $chunk)
                ->whereIn('status', [DriverExpenseStatus::New->value, DriverExpenseStatus::Approved->value])
                ->with(['addedBy:id,name', 'transfer:id,number,date_time'])
                ->orderBy('id')
                ->get()
                ->each(function (TransferDriverExpense $e) use (&$list, &$byTransfer, &$types) {
                    $uzs = (float) $e->amount;   // driver expenses are entered in sums only
                    $approved = $e->status === DriverExpenseStatus::Approved;

                    $byTransfer[$e->transfer_id] ??= ['approved' => 0.0, 'pending' => 0.0];
                    $byTransfer[$e->transfer_id][$approved ? 'approved' : 'pending'] += $uzs;

                    $type = $e->type->getLabel();
                    $types[$type] ??= ['count' => 0, 'uzs' => 0.0, 'usd' => 0.0];
                    $types[$type]['count']++;
                    $types[$type]['uzs'] += $uzs;

                    $list[] = [
                        'transfer_id' => $e->transfer_id,
                        'transfer_number' => $e->transfer?->number ?? 1000 + $e->transfer_id,
                        'transfer_date' => $e->transfer?->date_time?->format('Y-m-d H:i'),
                        'entered_at' => $e->created_at->copy()->timezone('Asia/Tashkent')->format('Y-m-d H:i'),
                        'type' => $type,
                        'uzs' => $uzs,
                        'usd' => TransferMoney::uzsToUsd($uzs, $this->usdRate),
                        'status' => $e->status->getLabel(),
                        'approved' => $approved,
                        'added_by' => $e->addedBy?->name,
                        'driver_id' => $e->driver_id,
                    ];
                });
        }

        foreach ($types as $type => $t) {
            $types[$type]['uzs'] = round($t['uzs'], 2);
            $types[$type]['usd'] = TransferMoney::uzsToUsd($t['uzs'], $this->usdRate);
        }
        ksort($types);

        return ['list' => $list, 'byTransfer' => $byTransfer, 'types' => $types];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, array{approved: float, pending: float}>  $byTransfer
     * @return list<array<string, mixed>>
     */
    private function attachExpenses(array $rows, array $byTransfer): array
    {
        foreach ($rows as &$row) {
            $e = $byTransfer[$row['id']] ?? ['approved' => 0.0, 'pending' => 0.0];

            $row['exp_approved_uzs'] = round($e['approved'], 2);
            $row['exp_pending_uzs'] = round($e['pending'], 2);
            $row['exp_uzs'] = round($e['approved'] + $e['pending'], 2);

            $isPackage = $row['source'] === self::SOURCE_PACKAGE;

            // A package transfer's price is a copy of the tour's cost, not income: no profit is computed for
            // it, and its sell value is kept only as a memo.
            $row['counts_revenue'] = ! $isPackage;
            $row['revenue_uzs'] = $isPackage ? 0.0 : $row['sell']['uzs'];
            $row['revenue_usd'] = $isPackage ? 0.0 : $row['sell']['usd'];
            $row['memo_uzs'] = $isPackage ? $row['sell']['uzs'] : 0.0;

            $expUsd = TransferMoney::uzsToUsd($row['exp_uzs'], $this->usdRate);
            $row['exp_usd'] = $expUsd;

            $row['profit_uzs'] = $isPackage ? null : round($row['revenue_uzs'] - $row['buy']['uzs'] - $row['exp_uzs'], 2);
            $row['profit_usd'] = $isPackage ? null : round($row['revenue_usd'] - $row['buy']['usd'] - $expUsd, 2);
        }
        unset($row);

        return $rows;
    }

    // ── aggregation ───────────────────────────────────────────────────────

    /** @return array<string, int|float> */
    private function emptyBucket(): array
    {
        return [
            'trips' => 0, 'done' => 0, 'confirmed' => 0, 'pax' => 0,
            'revenue_uzs' => 0.0, 'revenue_usd' => 0.0,
            'supplier_uzs' => 0.0, 'supplier_usd' => 0.0,
            'driver_exp_uzs' => 0.0, 'driver_exp_usd' => 0.0,
            'driver_exp_pending_uzs' => 0.0,
            'profit_uzs' => 0.0, 'profit_usd' => 0.0,
            'package_trips' => 0, 'package_cost_uzs' => 0.0, 'package_cost_usd' => 0.0, 'package_memo_uzs' => 0.0,
            'unpriced' => 0, 'no_buy' => 0,
        ];
    }

    /**
     * Add one transfer to a bucket. Revenue-bearing transfers feed revenue/supplier/driver-expense/profit;
     * package transfers feed only the package_* figures, so their costs are visible but never netted against
     * revenue they do not have.
     *
     * @param  array<string, int|float>  $b
     * @param  array<string, mixed>  $row
     */
    private function add(array &$b, array $row): void
    {
        $b['trips']++;
        $b['pax'] += (int) ($row['pax'] ?? 0);
        $row['status'] === ExpenseStatus::Done->value ? $b['done']++ : $b['confirmed']++;

        if (! $row['counts_revenue']) {
            $b['package_trips']++;
            $b['package_cost_uzs'] += $row['buy']['uzs'] + $row['exp_uzs'];
            $b['package_cost_usd'] += $row['buy']['usd'] + $row['exp_usd'];
            $b['package_memo_uzs'] += $row['memo_uzs'];

            return;
        }

        $b['revenue_uzs'] += $row['revenue_uzs'];
        $b['revenue_usd'] += $row['revenue_usd'];
        $b['supplier_uzs'] += $row['buy']['uzs'];
        $b['supplier_usd'] += $row['buy']['usd'];
        $b['driver_exp_uzs'] += $row['exp_uzs'];
        $b['driver_exp_usd'] += $row['exp_usd'];
        $b['driver_exp_pending_uzs'] += $row['exp_pending_uzs'];
        $b['profit_uzs'] += $row['profit_uzs'];
        $b['profit_usd'] += $row['profit_usd'];

        if (! $row['sell']['present']) {
            $b['unpriced']++;
        }
        if (! $row['buy']['present']) {
            $b['no_buy']++;
        }
    }

    /**
     * @param  array<string, int|float>  $b
     * @return array<string, int|float|null>
     */
    private function finish(array $b): array
    {
        foreach ($b as $k => $v) {
            if (is_float($v)) {
                $b[$k] = round($v, 2);
            }
        }

        $b['margin'] = $b['revenue_uzs'] > 0 ? round($b['profit_uzs'] / $b['revenue_uzs'], 4) : null;
        // Coverage: of the transfers that should earn money, how many actually carry a price. A revenue total is
        // only as complete as this — on real data it is well under half, which the owner must see.
        $b['revenue_trips'] = $b['trips'] - $b['package_trips'];
        $b['priced_share'] = $b['revenue_trips'] > 0 ? round(($b['revenue_trips'] - $b['unpriced']) / $b['revenue_trips'], 4) : null;
        $b['bought_share'] = $b['revenue_trips'] > 0 ? round(($b['revenue_trips'] - $b['no_buy']) / $b['revenue_trips'], 4) : null;
        $b['expenses_uzs'] = round($b['supplier_uzs'] + $b['driver_exp_uzs'] + $b['package_cost_uzs'], 2);
        $b['expenses_usd'] = round($b['supplier_usd'] + $b['driver_exp_usd'] + $b['package_cost_usd'], 2);

        return $b;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int|float|null>
     */
    private function summary(array $rows): array
    {
        $b = $this->emptyBucket();

        foreach ($rows as $row) {
            $this->add($b, $row);
        }

        $s = $this->finish($b);
        $s['driver_exp_approved_uzs'] = round(array_sum(array_column($rows, 'exp_approved_uzs')), 2);
        $s['driver_exp_pending_all_uzs'] = round(array_sum(array_column($rows, 'exp_pending_uzs')), 2);
        // Average over the transfers that HAVE a price: dividing by all of them would drag it down with every
        // unpriced trip and mean nothing.
        $priced = $s['revenue_trips'] - $s['unpriced'];
        $s['avg_revenue_per_trip_uzs'] = $priced > 0 ? round($s['revenue_uzs'] / $priced, 2) : null;

        return $s;
    }

    /**
     * Every day / month of the range, including empty ones, so a chart has no gaps.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, int|float|null>>
     */
    private function byPeriod(array $rows, Carbon $from, Carbon $to, string $unit): array
    {
        $buckets = [];
        $cursor = $unit === 'month' ? $from->copy()->startOfMonth() : $from->copy()->startOfDay();
        $format = $unit === 'month' ? 'Y-m' : 'Y-m-d';

        while ($cursor->lte($to)) {
            $buckets[$cursor->format($format)] = $this->emptyBucket();
            $unit === 'month' ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        foreach ($rows as $row) {
            $key = $unit === 'month' ? $row['month'] : $row['day'];
            $this->add($buckets[$key], $row);
        }

        return array_map(fn ($b) => $this->finish($b), $buckets);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $sources
     * @return array<string, array<string, int|float|null>>
     */
    private function bySource(array $rows, array $sources): array
    {
        $buckets = [];

        foreach ($sources as $source) {
            $buckets[$source] = $this->emptyBucket();
        }

        foreach ($rows as $row) {
            $this->add($buckets[$row['source']], $row);
        }

        return array_map(fn ($b) => $this->finish($b), $buckets);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, int|float|null>> biggest revenue first
     */
    private function groupBy(array $rows, string $field): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $key = $row[$field] ?? '—';
            $buckets[$key] ??= $this->emptyBucket();
            $this->add($buckets[$key], $row);
        }

        $buckets = array_map(fn ($b) => $this->finish($b), $buckets);
        uasort($buckets, fn ($a, $b) => [$b['revenue_uzs'], $b['trips']] <=> [$a['revenue_uzs'], $a['trips']]);

        return $buckets;
    }

    /**
     * Per driver: how many trips they were assigned to, and what THEY entered as expenses. Cost of a trip is
     * not split between drivers of a shared trip — a supplier price cannot be attributed to one of them.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $expenses
     * @return array<string, array{trips: int, exp_uzs: float, exp_usd: float, approved_uzs: float, pending_uzs: float}>
     */
    private function byDriver(array $rows, array $expenses): array
    {
        $drivers = [];
        $blank = ['trips' => 0, 'exp_uzs' => 0.0, 'exp_usd' => 0.0, 'approved_uzs' => 0.0, 'pending_uzs' => 0.0];

        foreach ($rows as $row) {
            foreach ($row['drivers'] as $name) {
                $drivers[$name] ??= $blank;
                $drivers[$name]['trips']++;
            }
        }

        foreach ($expenses as $e) {
            $name = $e['added_by'] ?? '—';
            $drivers[$name] ??= $blank;
            $drivers[$name]['exp_uzs'] += $e['uzs'];
            $drivers[$name]['exp_usd'] += $e['usd'];
            $drivers[$name][$e['approved'] ? 'approved_uzs' : 'pending_uzs'] += $e['uzs'];
        }

        foreach ($drivers as $name => $d) {
            foreach (['exp_uzs', 'exp_usd', 'approved_uzs', 'pending_uzs'] as $k) {
                $drivers[$name][$k] = round($d[$k], 2);
            }
        }

        uasort($drivers, fn ($a, $b) => [$b['trips'], $b['exp_uzs']] <=> [$a['trips'], $a['exp_uzs']]);

        return $drivers;
    }

    // ── data quality ──────────────────────────────────────────────────────

    /**
     * Everything an owner should double-check: transfers whose numbers rest on an assumption, or are missing.
     *
     * severity: warning = the total is probably wrong / incomplete; info = worth knowing.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{transfer_id: int, number: int, date_time: string, source: string, severity: string, code: string, detail: ?string}>
     */
    private function issues(array $rows): array
    {
        $issues = [];

        $push = function (array $row, string $severity, string $code, ?string $detail = null) use (&$issues) {
            $issues[] = [
                'transfer_id' => $row['id'],
                'number' => $row['number'],
                'date_time' => $row['date_time'],
                'source' => $row['source'],
                'severity' => $severity,
                'code' => $code,
                'detail' => $detail,
            ];
        };

        foreach ($rows as $row) {
            $sell = $row['sell'];
            $buy = $row['buy'];

            if ($row['counts_revenue']) {
                if (! $sell['present']) {
                    $push($row, 'warning', 'no_sell');
                }
                if (! $buy['present']) {
                    $push($row, 'info', 'no_buy');
                }
                if ($sell['present'] && $row['profit_uzs'] < 0) {
                    $push($row, 'warning', 'loss', (string) round($row['profit_uzs']));
                }
            }

            foreach (['sell' => $sell, 'buy' => $buy] as $side => $m) {
                if (in_array(TransferMoney::FLAG_CURRENCY_ASSUMED, $m['flags'], true)) {
                    $push($row, 'warning', 'currency_assumed', $side.':'.$m['currency']);
                }
                if (in_array(TransferMoney::FLAG_NO_RATE, $m['flags'], true)) {
                    $push($row, 'warning', 'no_rate', $side);
                }
                // A USD price whose stored UZS value was not a real conversion is re-priced at today's rate.
                // (For a UZS price the USD side always uses today's rate; that is by design, not an issue.)
                if ($m['currency'] === 'USD' && in_array(TransferMoney::FLAG_RATE_CURRENT, $m['flags'], true)
                    && ! in_array(TransferMoney::FLAG_CURRENCY_ASSUMED, $m['flags'], true)) {
                    $push($row, 'info', 'usd_repriced', $side);
                }
            }

            if ($row['exp_pending_uzs'] > 0) {
                $push($row, 'info', 'expenses_pending', (string) round($row['exp_pending_uzs']));
            }
        }

        return $issues;
    }
}
