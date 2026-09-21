<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The owner's Excel report, built from a TransferAnalytics::build() result.
 *
 * Design rules:
 *  - Every total is a live formula over the "Detail" sheet, so the owner can audit any figure and filter the
 *    detail (the row above its header carries SUBTOTALs that follow the filter).
 *  - Every sheet has a frozen header, sensible widths, number formats that show negatives in red, and prints
 *    on one page width.
 *  - Nothing is hidden: the assumptions behind the numbers are on their own sheets ("Data check", "How it is
 *    calculated") and the price-coverage warning is on the first screen.
 */
final class TransferAnalyticsWorkbook
{
    private const TEAL = '0F766E';

    private const TEAL_LIGHT = 'CCFBF1';

    private const GRAY = 'F1F5F9';

    private const LINE = 'CBD5E1';

    private const RED_FILL = 'FEE2E2';

    private const AMBER_FILL = 'FEF3C7';

    private const FMT_UZS = '#,##0;[Red]-#,##0';

    private const FMT_USD = '#,##0.00;[Red]-#,##0.00';

    private const FMT_PCT = '0.0%;[Red]-0.0%';

    private const FMT_INT = '#,##0';

    private const FMT_DATE = 'dd.mm.yyyy';

    private const FMT_DATETIME = 'dd.mm.yyyy hh:mm';

    /** Below this share of priced transfers the summary shouts about it. */
    private const COVERAGE_WARN_BELOW = 0.8;

    private const DETAIL_HEADER_ROW = 5;

    private const DETAIL_FIRST_ROW = 6;

    /** Detail sheet columns: key => letter. Everything that sums the detail refers to these. */
    private const D = [
        'number' => 'A', 'datetime' => 'B', 'status' => 'C', 'source' => 'D', 'client' => 'E', 'operator' => 'F',
        'city' => 'G', 'route' => 'H', 'drivers' => 'I', 'pax' => 'J',
        'sell' => 'K', 'sell_cur' => 'L', 'rev_uzs' => 'M', 'rev_usd' => 'N',
        'buy' => 'O', 'buy_cur' => 'P', 'sup_uzs' => 'Q', 'sup_usd' => 'R',
        'drv_uzs' => 'S', 'drv_usd' => 'T', 'profit_uzs' => 'U', 'profit_usd' => 'V',
        'pkg_uzs' => 'W', 'pkg_usd' => 'X', 'flags' => 'Y',
        'h_rev' => 'Z', 'h_sell' => 'AA', 'h_buy' => 'AB',
    ];

    private Spreadsheet $wb;

    /** @var array<string, mixed> */
    private array $r;

    private string $generatedBy;

    private int $detailLast;

    /** @var array<string, string> sheet key => title */
    private array $names;

    private function __construct() {}

    /**
     * @param  array<string, mixed>  $report  the result of TransferAnalytics::build()
     */
    public static function build(array $report, string $generatedBy): Spreadsheet
    {
        $self = new self;
        $self->r = $report;
        $self->generatedBy = $generatedBy;
        $self->names = collect(__('analytics.sheets'))->all();
        $self->wb = new Spreadsheet;
        $self->wb->getProperties()
            ->setCreator($generatedBy)
            ->setTitle(__('analytics.report'))
            ->setSubject($self->periodText())
            ->setCompany('East Asia Point');

        $self->detailLast = max(self::DETAIL_FIRST_ROW, self::DETAIL_FIRST_ROW + count($report['rows']) - 1);

        // Created in tab order; the Summary (first tab) is filled last because it points at the others.
        $summary = $self->wb->getActiveSheet();
        $summary->setTitle($self->names['summary']);
        $days = $self->sheet('days', '0EA5E9');
        $months = $self->sheet('months', '0EA5E9');
        $clients = $self->sheet('clients', '64748B');
        $operators = $self->sheet('operators', '64748B');
        $cities = $self->sheet('cities', '64748B');
        $drivers = $self->sheet('drivers', '64748B');
        $expenses = $self->sheet('expenses', 'F59E0B');
        $detail = $self->sheet('detail', '16A34A');
        $issues = $self->sheet('issues', 'DC2626');
        $method = $self->sheet('method', '94A3B8');

        $self->detailSheet($detail);
        $self->issuesSheet($issues);
        $self->expensesSheet($expenses);
        $self->periodSheet($days, 'day');
        $self->periodSheet($months, 'month');
        $self->groupSheet($clients, 'clients', 'by_company');
        $self->groupSheet($operators, 'operators', 'by_operator');
        $self->groupSheet($cities, 'cities', 'by_city');
        $self->driversSheet($drivers);
        $self->methodSheet($method);
        $self->summarySheet($summary);

        $self->wb->setActiveSheetIndex(0);

        return $self->wb;
    }

    /** Write to a stream/path with formulas pre-calculated (so previews that do not recalculate still show numbers). */
    public static function write(Spreadsheet $wb, string $path): void
    {
        $writer = new Xlsx($wb);
        $writer->setIncludeCharts(true);
        $writer->setPreCalculateFormulas(true);
        $writer->save($path);
    }

    // ── sheets ────────────────────────────────────────────────────────────

    private function summarySheet(Worksheet $ws): void
    {
        $s = $this->r['summary'];
        $d = self::D;
        $first = self::DETAIL_FIRST_ROW;
        $last = $this->detailLast;
        $detail = $this->q($this->names['detail']);
        $col = fn (string $key) => "{$detail}!\${$d[$key]}\${$first}:\${$d[$key]}\${$last}";

        foreach (['A' => 50, 'B' => 20, 'C' => 20, 'D' => 20, 'E' => 20, 'F' => 20, 'G' => 14, 'H' => 22, 'I' => 46] as $c => $w) {
            $ws->getColumnDimension($c)->setWidth($w);
        }

        $ws->setCellValue('A1', __('analytics.report'));
        $ws->getStyle('A1')->getFont()->setSize(20)->setBold(true)->getColor()->setRGB(self::TEAL);
        $ws->setCellValue('A2', $this->periodText());
        $ws->getStyle('A2')->getFont()->setSize(12)->setBold(true);
        $ws->setCellValue('A3', __('analytics.generated', ['at' => Carbon::now('Asia/Tashkent')->format('d.m.Y H:i'), 'by' => $this->generatedBy]));
        $ws->setCellValue('A4', __('analytics.rate_note', ['rate' => number_format($this->r['usd_rate'], 0, '.', ' ')]));
        $ws->getStyle('A3:A4')->getFont()->getColor()->setRGB('64748B');

        if ($this->r['rows'] === []) {
            $ws->setCellValue('A6', __('analytics.summary.no_data'));
            $ws->getStyle('A6')->getFont()->setBold(true)->getColor()->setRGB('B45309');

            return;
        }

        // ── key figures ────────────────────────────────────────────────
        $row = 6;
        $this->sectionTitle($ws, $row, __('analytics.summary.kpi'));
        $row++;
        $this->header($ws, $row, [__('analytics.cols.metric'), 'UZS', 'USD']);
        $row++;

        $sum = fn (string $key) => "=SUM({$col($key)})";
        $lines = [
            'revenue' => [$sum('rev_uzs'), $sum('rev_usd')],
            'expenses' => [null, null],   // filled below: needs the three rows under it
            'supplier' => [$sum('sup_uzs'), $sum('sup_usd')],
            'driver' => [$sum('drv_uzs'), $sum('drv_usd')],
            'package' => [$sum('pkg_uzs'), $sum('pkg_usd')],
            'profit' => [$sum('profit_uzs'), $sum('profit_usd')],
        ];
        $at = [];
        foreach ($lines as $key => [$uzs, $usd]) {
            $at[$key] = $row;
            $ws->setCellValue("A{$row}", __("analytics.summary.{$key}"));
            if ($uzs !== null) {
                $ws->setCellValue("B{$row}", $uzs);
                $ws->setCellValue("C{$row}", $usd);
            }
            $ws->getStyle("B{$row}")->getNumberFormat()->setFormatCode(self::FMT_UZS);
            $ws->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FMT_USD);
            $row++;
        }
        $e = $at['expenses'];
        foreach (['B', 'C'] as $c) {
            $ws->setCellValue("{$c}{$e}", "={$c}{$at['supplier']}+{$c}{$at['driver']}+{$c}{$at['package']}");
        }
        foreach (['revenue', 'expenses', 'profit'] as $bold) {
            $ws->getStyle("A{$at[$bold]}:C{$at[$bold]}")->getFont()->setBold(true);
        }
        $ws->getStyle("A{$at['profit']}:C{$at['profit']}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TEAL_LIGHT);

        $ws->setCellValue("A{$row}", __('analytics.summary.margin'));
        $ws->setCellValue("B{$row}", "=IF(B{$at['revenue']}>0,B{$at['profit']}/B{$at['revenue']},\"\")");
        $ws->setCellValue("C{$row}", "=IF(C{$at['revenue']}>0,C{$at['profit']}/C{$at['revenue']},\"\")");
        $ws->getStyle("B{$row}:C{$row}")->getNumberFormat()->setFormatCode(self::FMT_PCT);
        $row++;

        $ws->setCellValue("A{$row}", __('analytics.summary.avg'));
        $ws->setCellValue("B{$row}", "=IFERROR(B{$at['revenue']}/COUNTIF({$col('rev_uzs')},\">0\"),\"\")");
        $ws->setCellValue("C{$row}", "=IFERROR(C{$at['revenue']}/COUNTIF({$col('rev_usd')},\">0\"),\"\")");
        $ws->getStyle("B{$row}")->getNumberFormat()->setFormatCode(self::FMT_UZS);
        $ws->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::FMT_USD);
        $row++;

        $ws->setCellValue("A{$row}", __('analytics.summary.trips'));
        $ws->setCellValue("B{$row}", "=COUNTA({$col('number')})");
        $ws->getStyle("B{$row}")->getNumberFormat()->setFormatCode(self::FMT_INT);
        $row++;
        $ws->setCellValue("A{$row}", __('analytics.summary.done_confirmed', ['done' => $s['done'], 'confirmed' => $s['confirmed']]));
        $ws->getStyle("A{$row}")->getFont()->getColor()->setRGB('64748B');
        $this->box($ws, "A8:C{$row}");
        $row += 2;

        // ── how complete the data is ───────────────────────────────────
        $this->sectionTitle($ws, $row, __('analytics.summary.coverage'));
        $row++;
        $revTrips = $row;
        $ws->setCellValue("A{$row}", __('analytics.summary.revenue_trips'));
        $ws->setCellValue("B{$row}", "=SUM({$col('h_rev')})");
        $row++;
        $withPrice = $row;
        $ws->setCellValue("A{$row}", __('analytics.summary.with_price'));
        $ws->setCellValue("B{$row}", "=SUMPRODUCT({$col('h_rev')}*{$col('h_sell')})");
        $ws->setCellValue("C{$row}", "=IF(B{$revTrips}>0,B{$row}/B{$revTrips},\"\")");
        $row++;
        $ws->setCellValue("A{$row}", __('analytics.summary.with_buy'));
        $ws->setCellValue("B{$row}", "=SUMPRODUCT({$col('h_rev')}*{$col('h_buy')})");
        $ws->setCellValue("C{$row}", "=IF(B{$revTrips}>0,B{$row}/B{$revTrips},\"\")");
        $row++;
        $issuesSheet = $this->q($this->names['issues']);
        $issuesRange = "{$issuesSheet}!\$A\$6:\$A\$".max(6, 5 + count($this->r['issues']));
        // "Important" are the ones that make a total wrong or incomplete; the rest are notes. Showing only the
        // grand count (hundreds, on real data) would bury the few that matter.
        $ws->setCellValue("A{$row}", __('analytics.summary.warning_issues'));
        $ws->setCellValue("B{$row}", "=COUNTIF({$issuesRange},\"".__('analytics.severity.warning').'")');
        $row++;
        $ws->setCellValue("A{$row}", __('analytics.summary.open_issues'));
        $ws->setCellValue("B{$row}", "=COUNTA({$issuesRange})");
        $ws->getStyle("B{$revTrips}:B{$row}")->getNumberFormat()->setFormatCode(self::FMT_INT);
        $ws->getStyle("C{$withPrice}:C{$row}")->getNumberFormat()->setFormatCode('0%');
        $this->box($ws, "A{$revTrips}:C{$row}");

        // Loud when the revenue figure cannot be relied on: the single most important thing on this sheet.
        $priced = $s['priced_share'];
        if ($priced !== null && $priced < self::COVERAGE_WARN_BELOW) {
            $row++;
            $ws->setCellValue("A{$row}", __('analytics.summary.coverage_warn', ['sheet' => $this->names['issues']]));
            $ws->mergeCells("A{$row}:F{$row}");
            $ws->getStyle("A{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $ws->getRowDimension($row)->setRowHeight(34);
            $ws->getStyle("A{$row}:F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::AMBER_FILL);
            $ws->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setRGB('92400E');
            $ws->getStyle("B{$withPrice}:C{$withPrice}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::RED_FILL);
        }
        $row += 2;

        // ── by source ──────────────────────────────────────────────────
        $this->sectionTitle($ws, $row, __('analytics.summary.by_source'));
        $row++;
        $this->header($ws, $row, [
            __('analytics.cols.source'), __('analytics.cols.trips'), __('analytics.cols.revenue_uzs'), __('analytics.cols.supplier_uzs'),
            __('analytics.cols.driver_uzs'), __('analytics.cols.profit_uzs'), __('analytics.cols.margin'), __('analytics.cols.package_uzs'), '',
        ]);
        $row++;
        $srcFirst = $row;
        foreach (array_keys($this->r['by_source']) as $source) {
            $label = __("analytics.sources.{$source}");
            $ws->setCellValue("A{$row}", $label);
            $ws->setCellValue("B{$row}", "=COUNTIF({$col('source')},A{$row})");
            foreach (['C' => 'rev_uzs', 'D' => 'sup_uzs', 'E' => 'drv_uzs', 'F' => 'profit_uzs', 'H' => 'pkg_uzs'] as $c => $key) {
                $ws->setCellValue("{$c}{$row}", "=SUMIFS({$col($key)},{$col('source')},\$A{$row})");
                $ws->getStyle("{$c}{$row}")->getNumberFormat()->setFormatCode(self::FMT_UZS);
            }
            $ws->setCellValue("G{$row}", "=IF(C{$row}>0,F{$row}/C{$row},\"\")");
            $ws->getStyle("G{$row}")->getNumberFormat()->setFormatCode(self::FMT_PCT);
            $ws->setCellValue("I{$row}", __("analytics.source_hint.{$source}"));
            $ws->getStyle("I{$row}")->getFont()->getColor()->setRGB('64748B');
            $ws->getStyle("I{$row}")->getAlignment()->setWrapText(true);
            $row++;
        }
        $srcLast = $row - 1;
        $ws->setCellValue("A{$row}", __('analytics.cols.total'));
        foreach (['B', 'C', 'D', 'E', 'F', 'H'] as $c) {
            $ws->setCellValue("{$c}{$row}", "=SUM({$c}{$srcFirst}:{$c}{$srcLast})");
            $ws->getStyle("{$c}{$row}")->getNumberFormat()->setFormatCode($c === 'B' ? self::FMT_INT : self::FMT_UZS);
        }
        $ws->setCellValue("G{$row}", "=IF(C{$row}>0,F{$row}/C{$row},\"\")");
        $ws->getStyle("G{$row}")->getNumberFormat()->setFormatCode(self::FMT_PCT);
        $this->totalRow($ws, "A{$row}:H{$row}");
        $this->box($ws, "A{$srcFirst}:I{$row}");
        $row += 2;

        // ── chart: months ──────────────────────────────────────────────
        if (count($this->r['by_month']) >= 1) {
            $this->monthChart($ws, "A{$row}", 'H'.($row + 19));
        }

        $ws->freezePane('A6');
        $this->printSetup($ws);
    }

    private function periodSheet(Worksheet $ws, string $unit): void
    {
        $data = $this->r[$unit === 'day' ? 'by_day' : 'by_month'];
        $hasDelta = $unit === 'month';

        $titleKey = $unit === 'day' ? 'days' : 'months';
        $this->sheetTitle($ws, $this->names[$titleKey]);

        $cols = [
            [$unit === 'day' ? __('analytics.cols.date') : __('analytics.cols.month'), 14, 'text'],
        ];
        if ($unit === 'day') {
            $cols[] = [__('analytics.cols.weekday'), 8, 'text'];
        }
        $cols = array_merge($cols, [
            [__('analytics.cols.trips'), 10, 'int'],
            [__('analytics.cols.revenue_uzs'), 18, 'uzs'],
            [__('analytics.cols.supplier_uzs'), 18, 'uzs'],
            [__('analytics.cols.driver_uzs'), 18, 'uzs'],
            [__('analytics.cols.profit_uzs'), 18, 'uzs'],
            [__('analytics.cols.margin'), 10, 'pct'],
        ]);
        if ($hasDelta) {
            $cols[] = [__('analytics.cols.delta'), 14, 'pct'];
        }
        $cols = array_merge($cols, [
            [__('analytics.cols.revenue_usd'), 14, 'usd'],
            [__('analytics.cols.profit_usd'), 14, 'usd'],
            [__('analytics.cols.package_uzs'), 18, 'uzs'],
            [__('analytics.cols.unpriced'), 12, 'int'],
        ]);

        $headerRow = 5;
        $this->header($ws, $headerRow, array_column($cols, 0));
        foreach ($cols as $i => [, $w]) {
            $ws->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }
        $ws->getRowDimension($headerRow)->setRowHeight(32);

        $idx = fn (string $title) => Coordinate::stringFromColumnIndex(array_search($title, array_column($cols, 0), true) + 1);
        $cTrips = $idx(__('analytics.cols.trips'));
        $cRev = $idx(__('analytics.cols.revenue_uzs'));
        $cSup = $idx(__('analytics.cols.supplier_uzs'));
        $cDrv = $idx(__('analytics.cols.driver_uzs'));
        $cProfit = $idx(__('analytics.cols.profit_uzs'));
        $cMargin = $idx(__('analytics.cols.margin'));
        $cDelta = $hasDelta ? $idx(__('analytics.cols.delta')) : null;
        $cRevUsd = $idx(__('analytics.cols.revenue_usd'));
        $cProfitUsd = $idx(__('analytics.cols.profit_usd'));
        $cPkg = $idx(__('analytics.cols.package_uzs'));
        $cUnpriced = $idx(__('analytics.cols.unpriced'));

        $row = $headerRow + 1;
        $first = $row;
        foreach ($data as $key => $b) {
            $c = 1;
            if ($unit === 'day') {
                $carbon = Carbon::parse($key);
                $ws->setCellValueByColumnAndRow($c++, $row, ExcelDate::PHPToExcel($carbon));
                $ws->getStyleByColumnAndRow(1, $row)->getNumberFormat()->setFormatCode(self::FMT_DATE);
                $ws->setCellValueByColumnAndRow($c++, $row, $carbon->locale($this->locale())->isoFormat('dd'));
                if ($carbon->isWeekend()) {
                    $ws->getStyle("A{$row}:".Coordinate::stringFromColumnIndex(count($cols)).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRAY);
                }
            } else {
                $ws->setCellValueByColumnAndRow($c++, $row, $this->capitalize(Carbon::parse($key.'-01')->locale($this->locale())->translatedFormat('F Y')));
            }
            $values = [
                $cTrips => $b['trips'], $cRev => $b['revenue_uzs'], $cSup => $b['supplier_uzs'], $cDrv => $b['driver_exp_uzs'],
                $cProfit => $b['profit_uzs'], $cRevUsd => $b['revenue_usd'], $cProfitUsd => $b['profit_usd'],
                $cPkg => $b['package_cost_uzs'], $cUnpriced => $b['unpriced'],
            ];
            foreach ($values as $letter => $v) {
                $ws->setCellValue("{$letter}{$row}", $v);
            }
            $ws->setCellValue("{$cMargin}{$row}", "=IF({$cRev}{$row}>0,{$cProfit}{$row}/{$cRev}{$row},\"\")");
            if ($hasDelta) {
                $ws->setCellValue("{$cDelta}{$row}", $row > $first
                    ? "=IF({$cRev}".($row - 1).">0,({$cRev}{$row}-{$cRev}".($row - 1).")/{$cRev}".($row - 1).',"")'
                    : '');
            }
            $row++;
        }
        $last = $row - 1;

        // totals
        $ws->setCellValue('A'.$row, __('analytics.cols.total'));
        foreach ([$cTrips, $cRev, $cSup, $cDrv, $cProfit, $cRevUsd, $cProfitUsd, $cPkg, $cUnpriced] as $letter) {
            $ws->setCellValue("{$letter}{$row}", "=SUM({$letter}{$first}:{$letter}{$last})");
        }
        $ws->setCellValue("{$cMargin}{$row}", "=IF({$cRev}{$row}>0,{$cProfit}{$row}/{$cRev}{$row},\"\")");
        $this->totalRow($ws, "A{$row}:".Coordinate::stringFromColumnIndex(count($cols)).$row);

        $this->formatColumns($ws, $cols, $first, $row);
        $this->box($ws, "A{$first}:".Coordinate::stringFromColumnIndex(count($cols)).$row);
        $ws->freezePane('A6');
        $ws->setAutoFilter("A{$headerRow}:".Coordinate::stringFromColumnIndex(count($cols)).$last);

        // chart
        $chartFrom = Coordinate::stringFromColumnIndex(count($cols) + 2).'5';
        $chartTo = Coordinate::stringFromColumnIndex(count($cols) + 12).'24';
        if ($unit === 'day') {
            $this->dayChart($ws, $first, $last, $cRev, $cProfit, $chartFrom, $chartTo);
        } else {
            $this->monthChart($ws, $chartFrom, $chartTo, true);
        }

        $this->printSetup($ws);
    }

    private function groupSheet(Worksheet $ws, string $nameKey, string $dataKey): void
    {
        $this->sheetTitle($ws, $this->names[$nameKey]);
        $label = match ($nameKey) {
            'clients' => __('analytics.cols.client'),
            'operators' => __('analytics.cols.operator'),
            default => __('analytics.cols.city'),
        };
        $cols = [
            [$label, 36, 'text'], [__('analytics.cols.trips'), 10, 'int'],
            [__('analytics.cols.revenue_uzs'), 18, 'uzs'], [__('analytics.cols.supplier_uzs'), 18, 'uzs'],
            [__('analytics.cols.driver_uzs'), 18, 'uzs'], [__('analytics.cols.profit_uzs'), 18, 'uzs'],
            [__('analytics.cols.margin'), 10, 'pct'], [__('analytics.cols.revenue_usd'), 14, 'usd'],
            [__('analytics.cols.profit_usd'), 14, 'usd'], [__('analytics.cols.package_uzs'), 18, 'uzs'],
            [__('analytics.cols.unpriced'), 12, 'int'],
        ];
        $headerRow = 5;
        $this->header($ws, $headerRow, array_column($cols, 0));
        foreach ($cols as $i => [, $w]) {
            $ws->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }
        $ws->getRowDimension($headerRow)->setRowHeight(32);

        $row = $headerRow + 1;
        $first = $row;
        foreach ($this->r[$dataKey] as $name => $b) {
            $ws->setCellValue("A{$row}", $name);
            foreach (['B' => 'trips', 'C' => 'revenue_uzs', 'D' => 'supplier_uzs', 'E' => 'driver_exp_uzs', 'F' => 'profit_uzs', 'H' => 'revenue_usd', 'I' => 'profit_usd', 'J' => 'package_cost_uzs', 'K' => 'unpriced'] as $c => $k) {
                $ws->setCellValue("{$c}{$row}", $b[$k]);
            }
            $ws->setCellValue("G{$row}", "=IF(C{$row}>0,F{$row}/C{$row},\"\")");
            $row++;
        }
        $last = max($first, $row - 1);

        $ws->setCellValue("A{$row}", __('analytics.cols.total'));
        foreach (['B', 'C', 'D', 'E', 'F', 'H', 'I', 'J', 'K'] as $c) {
            $ws->setCellValue("{$c}{$row}", "=SUM({$c}{$first}:{$c}{$last})");
        }
        $ws->setCellValue("G{$row}", "=IF(C{$row}>0,F{$row}/C{$row},\"\")");
        $this->totalRow($ws, "A{$row}:K{$row}");

        $this->formatColumns($ws, $cols, $first, $row);
        $this->box($ws, "A{$first}:K{$row}");
        $ws->freezePane('B6');
        $ws->setAutoFilter("A{$headerRow}:K{$last}");
        $this->printSetup($ws);
    }

    private function driversSheet(Worksheet $ws): void
    {
        $this->sheetTitle($ws, $this->names['drivers']);
        $cols = [
            [__('analytics.cols.drivers'), 32, 'text'], [__('analytics.cols.trips'), 10, 'int'],
            [__('analytics.cols.amount_uzs'), 18, 'uzs'], [__('analytics.cols.approved'), 18, 'uzs'],
            [__('analytics.cols.pending'), 18, 'uzs'], [__('analytics.cols.amount_usd'), 14, 'usd'],
        ];
        $headerRow = 5;
        $this->header($ws, $headerRow, array_column($cols, 0));
        foreach ($cols as $i => [, $w]) {
            $ws->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }
        $ws->getRowDimension($headerRow)->setRowHeight(32);

        $row = $headerRow + 1;
        $first = $row;
        foreach ($this->r['by_driver'] as $name => $d) {
            $ws->setCellValue("A{$row}", $name);
            $ws->setCellValue("B{$row}", $d['trips']);
            $ws->setCellValue("C{$row}", $d['exp_uzs']);
            $ws->setCellValue("D{$row}", $d['approved_uzs']);
            $ws->setCellValue("E{$row}", $d['pending_uzs']);
            $ws->setCellValue("F{$row}", $d['exp_usd']);
            $row++;
        }
        $last = max($first, $row - 1);
        $ws->setCellValue("A{$row}", __('analytics.cols.total'));
        foreach (['B', 'C', 'D', 'E', 'F'] as $c) {
            $ws->setCellValue("{$c}{$row}", "=SUM({$c}{$first}:{$c}{$last})");
        }
        $this->totalRow($ws, "A{$row}:F{$row}");
        $this->formatColumns($ws, $cols, $first, $row);
        $this->box($ws, "A{$first}:F{$row}");
        $ws->freezePane('B6');
        $ws->setAutoFilter("A{$headerRow}:F{$last}");
        $this->printSetup($ws);
    }

    private function expensesSheet(Worksheet $ws): void
    {
        $this->sheetTitle($ws, $this->names['expenses']);

        // by type, top-left
        $this->header($ws, 5, [__('analytics.cols.type'), __('analytics.cols.count'), __('analytics.cols.amount_uzs'), __('analytics.cols.amount_usd')]);
        $row = 6;
        $tFirst = $row;
        foreach ($this->r['expense_types'] as $type => $t) {
            $ws->setCellValue("A{$row}", $type);
            $ws->setCellValue("B{$row}", $t['count']);
            $ws->setCellValue("C{$row}", $t['uzs']);
            $ws->setCellValue("D{$row}", $t['usd']);
            $row++;
        }
        $tLast = max($tFirst, $row - 1);
        $ws->setCellValue("A{$row}", __('analytics.cols.total'));
        foreach (['B', 'C', 'D'] as $c) {
            $ws->setCellValue("{$c}{$row}", "=SUM({$c}{$tFirst}:{$c}{$tLast})");
        }
        $this->totalRow($ws, "A{$row}:D{$row}");
        $ws->getStyle("B{$tFirst}:B{$row}")->getNumberFormat()->setFormatCode(self::FMT_INT);
        $ws->getStyle("C{$tFirst}:C{$row}")->getNumberFormat()->setFormatCode(self::FMT_UZS);
        $ws->getStyle("D{$tFirst}:D{$row}")->getNumberFormat()->setFormatCode(self::FMT_USD);
        $this->box($ws, "A{$tFirst}:D{$row}");

        // every entry
        $row += 3;
        $headerRow = $row;
        $cols = [
            [__('analytics.cols.datetime'), 18, 'datetime'], [__('analytics.cols.number'), 12, 'text'],
            [__('analytics.cols.entered_at'), 18, 'text'], [__('analytics.cols.entered_by'), 24, 'text'],
            [__('analytics.cols.type'), 22, 'text'], [__('analytics.cols.amount_uzs'), 16, 'uzs'],
            [__('analytics.cols.amount_usd'), 14, 'usd'], [__('analytics.cols.status'), 16, 'text'],
        ];
        $this->header($ws, $headerRow, array_column($cols, 0));
        foreach ($cols as $i => [, $w]) {
            $ws->getColumnDimensionByColumn($i + 1)->setWidth(max($w, $i === 0 ? 30 : $w));
        }
        $row++;
        $first = $row;
        foreach ($this->r['driver_expenses'] as $e) {
            $ws->setCellValue("A{$row}", $e['transfer_date'] ? ExcelDate::PHPToExcel(Carbon::parse($e['transfer_date'])) : '');
            $ws->setCellValue("B{$row}", $e['transfer_number']);
            $ws->setCellValue("C{$row}", $e['entered_at']);
            $ws->setCellValue("D{$row}", $e['added_by'] ?? '—');
            $ws->setCellValue("E{$row}", $e['type']);
            $ws->setCellValue("F{$row}", $e['uzs']);
            $ws->setCellValue("G{$row}", $e['usd']);
            $ws->setCellValue("H{$row}", $e['status']);
            if (! $e['approved']) {
                $ws->getStyle("A{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::AMBER_FILL);
            }
            $row++;
        }
        $last = max($first, $row - 1);
        $ws->getStyle("A{$first}:A{$last}")->getNumberFormat()->setFormatCode(self::FMT_DATETIME);
        $ws->getStyle("F{$first}:F{$last}")->getNumberFormat()->setFormatCode(self::FMT_UZS);
        $ws->getStyle("G{$first}:G{$last}")->getNumberFormat()->setFormatCode(self::FMT_USD);
        $ws->setCellValue("E{$row}", __('analytics.cols.total'));
        $ws->setCellValue("F{$row}", "=SUM(F{$first}:F{$last})");
        $ws->setCellValue("G{$row}", "=SUM(G{$first}:G{$last})");
        $ws->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::FMT_UZS);
        $ws->getStyle("G{$row}")->getNumberFormat()->setFormatCode(self::FMT_USD);
        $this->totalRow($ws, "A{$row}:H{$row}");
        $this->box($ws, "A{$first}:H{$row}");
        $ws->setAutoFilter("A{$headerRow}:H{$last}");
        $ws->freezePane('A6');
        $this->printSetup($ws);
    }

    private function detailSheet(Worksheet $ws): void
    {
        $this->sheetTitle($ws, $this->names['detail']);
        $d = self::D;
        $first = self::DETAIL_FIRST_ROW;
        $headerRow = self::DETAIL_HEADER_ROW;

        $cols = [
            'number' => [__('analytics.cols.number'), 12, 'int'],
            'datetime' => [__('analytics.cols.datetime'), 17, 'datetime'],
            'status' => [__('analytics.cols.status'), 14, 'text'],
            'source' => [__('analytics.cols.source'), 22, 'text'],
            'client' => [__('analytics.cols.client'), 26, 'text'],
            'operator' => [__('analytics.cols.operator'), 18, 'text'],
            'city' => [__('analytics.cols.city'), 16, 'text'],
            'route' => [__('analytics.cols.route'), 34, 'text'],
            'drivers' => [__('analytics.cols.drivers'), 22, 'text'],
            'pax' => [__('analytics.cols.pax'), 7, 'int'],
            'sell' => [__('analytics.cols.sell'), 13, 'money'],
            'sell_cur' => [__('analytics.cols.currency'), 9, 'text'],
            'rev_uzs' => [__('analytics.cols.revenue_uzs'), 16, 'uzs'],
            'rev_usd' => [__('analytics.cols.revenue_usd'), 13, 'usd'],
            'buy' => [__('analytics.cols.buy'), 13, 'money'],
            'buy_cur' => [__('analytics.cols.currency'), 9, 'text'],
            'sup_uzs' => [__('analytics.cols.supplier_uzs'), 16, 'uzs'],
            'sup_usd' => [__('analytics.cols.supplier_usd'), 13, 'usd'],
            'drv_uzs' => [__('analytics.cols.driver_uzs'), 16, 'uzs'],
            'drv_usd' => [__('analytics.cols.driver_usd'), 13, 'usd'],
            'profit_uzs' => [__('analytics.cols.profit_uzs'), 16, 'uzs'],
            'profit_usd' => [__('analytics.cols.profit_usd'), 13, 'usd'],
            'pkg_uzs' => [__('analytics.cols.package_uzs'), 16, 'uzs'],
            'pkg_usd' => [__('analytics.cols.package_usd'), 13, 'usd'],
            'flags' => [__('analytics.cols.flags'), 46, 'text'],
            // helper columns the Summary's coverage formulas read; deliberately at the far right
            'h_rev' => ['1 = '.__('analytics.summary.revenue_trips'), 12, 'int'],
            'h_sell' => ['1 = '.__('analytics.summary.with_price'), 12, 'int'],
            'h_buy' => ['1 = '.__('analytics.summary.with_buy'), 12, 'int'],
        ];

        $ws->setCellValue('A1', __('analytics.sheets.detail'));
        $ws->getStyle('A1')->getFont()->setSize(16)->setBold(true)->getColor()->setRGB(self::TEAL);
        $ws->setCellValue('A2', $this->periodText());

        $this->header($ws, $headerRow, array_column($cols, 0));
        foreach ($cols as $key => [, $w]) {
            $ws->getColumnDimension($d[$key])->setWidth($w);
        }
        $ws->getRowDimension($headerRow)->setRowHeight(48);
        $ws->getStyle("Z{$headerRow}:AB{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('94A3B8');

        $issueText = $this->issueTextByTransfer();

        $row = $first;
        foreach ($this->r['rows'] as $t) {
            $isPackage = ! $t['counts_revenue'];
            $sell = $t['sell'];
            $buy = $t['buy'];
            $flags = $issueText[$t['id']] ?? '';

            $ws->setCellValue("{$d['number']}{$row}", $t['number']);
            $ws->setCellValue("{$d['datetime']}{$row}", ExcelDate::PHPToExcel(Carbon::parse($t['date_time'])));
            $ws->setCellValue("{$d['status']}{$row}", $t['status'] === 5 ? __('analytics.status.done') : __('analytics.status.confirmed'));
            $ws->setCellValue("{$d['source']}{$row}", __("analytics.sources.{$t['source']}"));
            $ws->setCellValue("{$d['client']}{$row}", $t['company'] ?? '—');
            $ws->setCellValue("{$d['operator']}{$row}", $t['operator'] ?? '—');
            $ws->setCellValue("{$d['city']}{$row}", $t['city'] ?? '—');
            $ws->setCellValue("{$d['route']}{$row}", $t['route'] ?? '');
            $ws->setCellValue("{$d['drivers']}{$row}", implode(', ', $t['drivers']));
            $ws->setCellValue("{$d['pax']}{$row}", $t['pax']);

            $this->put($ws, $d['sell'], $row, $sell['present'] ? $sell['amount'] : null);
            $this->put($ws, $d['sell_cur'], $row, $sell['present'] ? $sell['currency'].(in_array(TransferMoney::FLAG_CURRENCY_ASSUMED, $sell['flags'], true) ? '*' : '') : null);
            $ws->setCellValue("{$d['rev_uzs']}{$row}", $t['revenue_uzs']);
            $ws->setCellValue("{$d['rev_usd']}{$row}", $t['revenue_usd']);
            $this->put($ws, $d['buy'], $row, $buy['present'] ? $buy['amount'] : null);
            $this->put($ws, $d['buy_cur'], $row, $buy['present'] ? $buy['currency'].(in_array(TransferMoney::FLAG_CURRENCY_ASSUMED, $buy['flags'], true) ? '*' : '') : null);

            // Package transfers: their cost lands in the package columns only, never in revenue-side ones.
            $ws->setCellValue("{$d['sup_uzs']}{$row}", $isPackage ? 0 : $buy['uzs']);
            $ws->setCellValue("{$d['sup_usd']}{$row}", $isPackage ? 0 : $buy['usd']);
            $ws->setCellValue("{$d['drv_uzs']}{$row}", $isPackage ? 0 : $t['exp_uzs']);
            $ws->setCellValue("{$d['drv_usd']}{$row}", $isPackage ? 0 : $t['exp_usd']);
            $this->put($ws, $d['profit_uzs'], $row, $t['profit_uzs']);
            $this->put($ws, $d['profit_usd'], $row, $t['profit_usd']);
            $ws->setCellValue("{$d['pkg_uzs']}{$row}", $isPackage ? round($buy['uzs'] + $t['exp_uzs'], 2) : 0);
            $ws->setCellValue("{$d['pkg_usd']}{$row}", $isPackage ? round($buy['usd'] + $t['exp_usd'], 2) : 0);
            $this->put($ws, $d['flags'], $row, $flags);

            $ws->setCellValue("{$d['h_rev']}{$row}", $isPackage ? 0 : 1);
            $ws->setCellValue("{$d['h_sell']}{$row}", $sell['present'] ? 1 : 0);
            $ws->setCellValue("{$d['h_buy']}{$row}", $buy['present'] ? 1 : 0);

            if ($flags !== '' && str_contains($flags, '!')) {
                $ws->getStyle("{$d['flags']}{$row}")->getFont()->getColor()->setRGB('B45309');
            }
            $row++;
        }
        $last = $this->detailLast;

        // Totals that FOLLOW THE FILTER: SUBTOTAL(109) ignores rows hidden by the filter.
        $ws->setCellValue("{$d['drivers']}4", __('analytics.cols.total').' ↓');
        $ws->getStyle("{$d['drivers']}4")->getFont()->setBold(true);
        foreach (['rev_uzs', 'rev_usd', 'sup_uzs', 'sup_usd', 'drv_uzs', 'drv_usd', 'profit_uzs', 'profit_usd', 'pkg_uzs', 'pkg_usd'] as $k) {
            $ws->setCellValue("{$d[$k]}4", "=SUBTOTAL(109,{$d[$k]}{$first}:{$d[$k]}{$last})");
        }
        $ws->setCellValue("{$d['number']}4", "=SUBTOTAL(103,{$d['number']}{$first}:{$d['number']}{$last})");
        $ws->getStyle("{$d['number']}4")->getNumberFormat()->setFormatCode(self::FMT_INT);
        $this->totalRow($ws, "A4:{$d['flags']}4");

        // formats per column
        foreach ($cols as $key => [, , $fmt]) {
            $range = "{$d[$key]}4:{$d[$key]}{$last}";
            $code = match ($fmt) {
                'uzs' => self::FMT_UZS, 'usd' => self::FMT_USD, 'int' => self::FMT_INT,
                'datetime' => self::FMT_DATETIME, 'money' => '#,##0.00', default => null,
            };
            if ($code !== null) {
                $ws->getStyle($range)->getNumberFormat()->setFormatCode($code);
            }
        }
        $ws->getStyle("{$d['datetime']}{$first}:{$d['datetime']}{$last}")->getNumberFormat()->setFormatCode(self::FMT_DATETIME);

        $ws->getStyle("A{$first}:{$d['h_buy']}{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(self::LINE);
        $ws->freezePane("C{$first}");
        $ws->setAutoFilter("A{$headerRow}:{$d['h_buy']}{$last}");
        $this->printSetup($ws);

        // Package-tour rows are visually distinct: they earn no revenue here.
        $rowNo = $first;
        foreach ($this->r['rows'] as $t) {
            if (! $t['counts_revenue']) {
                $ws->getStyle("A{$rowNo}:{$d['flags']}{$rowNo}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRAY);
            }
            $rowNo++;
        }
    }

    private function issuesSheet(Worksheet $ws): void
    {
        $this->sheetTitle($ws, $this->names['issues']);
        $cols = [
            [__('analytics.cols.severity'), 12], [__('analytics.cols.problem'), 46], [__('analytics.cols.number'), 12],
            [__('analytics.cols.datetime'), 17], [__('analytics.cols.source'), 22], [__('analytics.cols.details'), 30],
            [__('analytics.cols.action'), 80],
        ];
        $this->header($ws, 5, array_column($cols, 0));
        foreach ($cols as $i => [, $w]) {
            $ws->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }
        $ws->getRowDimension(5)->setRowHeight(28);

        // most serious first, then by date
        $issues = $this->r['issues'];
        usort($issues, fn ($a, $b) => [$a['severity'] === 'warning' ? 0 : 1, $a['code'], $a['date_time']] <=> [$b['severity'] === 'warning' ? 0 : 1, $b['code'], $b['date_time']]);

        $row = 6;
        foreach ($issues as $i) {
            $ws->setCellValue("A{$row}", __("analytics.severity.{$i['severity']}"));
            $ws->setCellValue("B{$row}", __("analytics.issues.{$i['code']}"));
            $ws->setCellValue("C{$row}", $i['number']);
            $ws->setCellValue("D{$row}", ExcelDate::PHPToExcel(Carbon::parse($i['date_time'])));
            $ws->setCellValue("E{$row}", __("analytics.sources.{$i['source']}"));
            $ws->setCellValue("F{$row}", $this->issueDetail($i));
            $ws->setCellValue("G{$row}", __("analytics.issue_action.{$i['code']}"));
            if ($i['severity'] === 'warning') {
                $ws->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::AMBER_FILL);
                $ws->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setRGB('92400E');
            }
            $row++;
        }
        $last = max(6, $row - 1);
        $ws->getStyle("D6:D{$last}")->getNumberFormat()->setFormatCode(self::FMT_DATETIME);
        $ws->getStyle("G6:G{$last}")->getAlignment()->setWrapText(true);
        $this->box($ws, "A6:G{$last}");

        // counts by problem, to the right
        $counts = [];
        foreach ($issues as $i) {
            $counts[$i['code']] = ($counts[$i['code']] ?? 0) + 1;
        }
        $ws->setCellValue('I5', __('analytics.cols.problem'));
        $ws->setCellValue('J5', __('analytics.cols.count'));
        $this->header($ws, 5, [__('analytics.cols.problem'), __('analytics.cols.count')], 9);
        $r = 6;
        foreach ($counts as $code => $n) {
            $ws->setCellValue("I{$r}", __("analytics.issues.{$code}"));
            $ws->setCellValue("J{$r}", $n);
            $r++;
        }
        $ws->getColumnDimension('H')->setWidth(4);
        $ws->getColumnDimension('I')->setWidth(52);
        $ws->getColumnDimension('J')->setWidth(10);
        $ws->freezePane('A6');
        $ws->setAutoFilter("A5:G{$last}");
        $this->printSetup($ws);
    }

    private function methodSheet(Worksheet $ws): void
    {
        $ws->getColumnDimension('A')->setWidth(4);
        $ws->getColumnDimension('B')->setWidth(130);
        $ws->setCellValue('A1', __('analytics.method.title'));
        $ws->getStyle('A1')->getFont()->setSize(16)->setBold(true)->getColor()->setRGB(self::TEAL);

        $row = 3;
        foreach (__('analytics.method.lines') as $n => $line) {
            $ws->setCellValue("A{$row}", $n + 1);
            $ws->setCellValue("B{$row}", $line);
            $ws->getStyle("B{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $ws->getStyle("A{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $ws->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setRGB(self::TEAL);
            // Excel does not auto-size wrapped merged/long text on load: estimate the height.
            $ws->getRowDimension($row)->setRowHeight(max(20, 17 * (int) ceil(mb_strlen($line) / 120)));
            $row++;
        }
        $this->printSetup($ws);
    }

    // ── charts ────────────────────────────────────────────────────────────

    private function monthChart(Worksheet $ws, string $from, string $to, bool $onMonthsSheet = false): void
    {
        $sheet = $this->q($this->names['months']);
        $n = count($this->r['by_month']);
        $first = 6;
        $last = $first + $n - 1;
        // months sheet columns: A month, B trips, C revenue, D supplier, E driver, F profit
        $series = [['C', __('analytics.cols.revenue_uzs')], ['D', __('analytics.cols.supplier_uzs')], ['E', __('analytics.cols.driver_uzs')], ['F', __('analytics.cols.profit_uzs')]];

        $labels = [];
        $values = [];
        foreach ($series as [$c]) {
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "{$sheet}!\${$c}\$5", null, 1);
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "{$sheet}!\${$c}\${$first}:\${$c}\${$last}", self::FMT_UZS, $n);
        }
        $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "{$sheet}!\$A\${$first}:\$A\${$last}", null, $n)];

        $plot = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, range(0, count($series) - 1), $labels, $categories, $values);
        $plot->setPlotDirection(DataSeries::DIRECTION_COL);
        $chart = new Chart('months_'.($onMonthsSheet ? 'm' : 's'), new Title(__('analytics.summary.chart_months')), new Legend(Legend::POSITION_BOTTOM), new PlotArea(null, [$plot]));
        $chart->setTopLeftPosition($from);
        $chart->setBottomRightPosition($to);
        $ws->addChart($chart);
    }

    private function dayChart(Worksheet $ws, int $first, int $last, string $cRev, string $cProfit, string $from, string $to): void
    {
        $sheet = $this->q($ws->getTitle());
        $n = $last - $first + 1;
        $labels = [];
        $values = [];
        foreach ([$cRev, $cProfit] as $c) {
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "{$sheet}!\${$c}\$5", null, 1);
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "{$sheet}!\${$c}\${$first}:\${$c}\${$last}", self::FMT_UZS, $n);
        }
        $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "{$sheet}!\$A\${$first}:\$A\${$last}", self::FMT_DATE, $n)];

        $plot = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, range(0, 1), $labels, $categories, $values);
        $chart = new Chart('days', new Title(__('analytics.summary.chart_days')), new Legend(Legend::POSITION_BOTTOM), new PlotArea(null, [$plot]));
        $chart->setTopLeftPosition($from);
        $chart->setBottomRightPosition($to);
        $ws->addChart($chart);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function sheet(string $key, string $tab): Worksheet
    {
        $ws = $this->wb->createSheet();
        $ws->setTitle($this->names[$key]);
        $ws->getTabColor()->setRGB($tab);

        return $ws;
    }

    /** Title block used by the table sheets: row 1 title, row 2 period. */
    private function sheetTitle(Worksheet $ws, string $title): void
    {
        $ws->setCellValue('A1', $title);
        $ws->getStyle('A1')->getFont()->setSize(16)->setBold(true)->getColor()->setRGB(self::TEAL);
        $ws->setCellValue('A2', $this->periodText());
        $ws->getStyle('A2')->getFont()->getColor()->setRGB('64748B');
    }

    private function periodText(): string
    {
        return __('analytics.period', ['from' => $this->r['from']->format('d.m.Y'), 'to' => $this->r['to']->format('d.m.Y')]);
    }

    /** ucfirst() only handles ASCII: "май 2026" would stay lower-case. */
    private function capitalize(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    private function locale(): string
    {
        return app()->getLocale() === 'en' ? 'en' : 'ru';
    }

    /** Sheet name quoted for use inside a formula. */
    private function q(string $sheetName): string
    {
        return "'".str_replace("'", "''", $sheetName)."'";
    }

    /** @param  list<string>  $titles */
    private function header(Worksheet $ws, int $row, array $titles, int $startColumn = 1): void
    {
        foreach ($titles as $i => $title) {
            $ws->setCellValueByColumnAndRow($startColumn + $i, $row, $title);
        }
        $range = Coordinate::stringFromColumnIndex($startColumn).$row.':'.Coordinate::stringFromColumnIndex($startColumn + count($titles) - 1).$row;
        $style = $ws->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TEAL);
        $style->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function sectionTitle(Worksheet $ws, int $row, string $title): void
    {
        $ws->setCellValue("A{$row}", $title);
        $ws->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13)->getColor()->setRGB(self::TEAL);
    }

    private function totalRow(Worksheet $ws, string $range): void
    {
        $style = $ws->getStyle($range);
        $style->getFont()->setBold(true);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::TEAL_LIGHT);
        $style->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::TEAL);
    }

    private function box(Worksheet $ws, string $range): void
    {
        $ws->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
    }

    /**
     * Apply per-column number formats to data + total rows.
     *
     * @param  list<array{0: string, 1: int|float, 2: string}>  $cols
     */
    private function formatColumns(Worksheet $ws, array $cols, int $first, int $last): void
    {
        foreach ($cols as $i => [, , $fmt]) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $code = match ($fmt) {
                'uzs' => self::FMT_UZS, 'usd' => self::FMT_USD, 'pct' => self::FMT_PCT, 'int' => self::FMT_INT, default => null,
            };
            if ($code !== null) {
                $ws->getStyle("{$letter}{$first}:{$letter}{$last}")->getNumberFormat()->setFormatCode($code);
            }
        }
    }

    private function printSetup(Worksheet $ws): void
    {
        $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $ws->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $ws->getPageSetup()->setFitToPage(true);
        $ws->getPageSetup()->setFitToWidth(1);
        $ws->getPageSetup()->setFitToHeight(0);
        $ws->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.5)->setBottom(0.5);
        $ws->setShowGridlines(false);
    }

    /** Set a cell, leaving it truly empty (not "0" or "") when there is no value. */
    private function put(Worksheet $ws, string $column, int $row, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $ws->setCellValue("{$column}{$row}", $value);
    }

    /** @return array<int, string> transfer id => a short note listing what to double-check */
    private function issueTextByTransfer(): array
    {
        $out = [];

        foreach ($this->r['issues'] as $i) {
            // "!" marks the ones that make a number unreliable.
            $mark = $i['severity'] === 'warning' ? '! ' : '';
            $out[$i['transfer_id']][] = $mark.__("analytics.issues.{$i['code']}").$this->issueSuffix($i);
        }

        return array_map(fn ($list) => implode('; ', $list), $out);
    }

    /** @param  array<string, mixed>  $i */
    private function issueSuffix(array $i): string
    {
        $detail = $this->issueDetail($i);

        return $detail !== '' ? " ({$detail})" : '';
    }

    /** @param  array<string, mixed>  $i */
    private function issueDetail(array $i): string
    {
        $detail = (string) ($i['detail'] ?? '');

        if ($detail === '') {
            return '';
        }

        // "sell:USD" / "buy" -> a readable side, plus the assumed currency where there is one
        if (str_contains($detail, ':') || in_array($detail, ['sell', 'buy'], true)) {
            [$side, $cur] = array_pad(explode(':', $detail, 2), 2, null);

            return __("analytics.side.{$side}").($cur ? " → {$cur}" : '');
        }

        return number_format((float) $detail, 0, '.', ' ');
    }
}
