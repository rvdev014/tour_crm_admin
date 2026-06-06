<?php

namespace App\Services;

use App\Models\Transfer;
use Carbon\Carbon;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Table;

class ExportTransferService
{
    public static function generate(Transfer $transfer): PhpWord
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');

        $section = $phpWord->addSection([
            'pageSizeW'    => 11906,
            'pageSizeH'    => 16838,
            'marginTop'    => 1134,
            'marginRight'  => 1134,
            'marginBottom' => 1134,
            'marginLeft'   => 1134,
        ]);

        // ── Header ──────────────────────────────────────────────────
        $headerRun = $section->addTextRun(['alignment' => Jc::CENTER]);
        $headerRun->addText('TRANSFER VOUCHER', [
            'name'  => 'Calibri',
            'size'  => 20,
            'bold'  => true,
            'color' => '1F3864',
        ]);

        $section->addTextBreak(1);

        $numberRun = $section->addTextRun(['alignment' => Jc::CENTER]);
        $numberRun->addText('# ' . (1000 + $transfer->id), [
            'name'  => 'Calibri',
            'size'  => 14,
            'bold'  => true,
            'color' => '2E75B6',
        ]);

        $section->addTextBreak(1);

        // ── Details table ────────────────────────────────────────────
        $tableStyle = [
            'borderSize'  => 6,
            'borderColor' => 'D0D0D0',
            'cellMargin'  => 80,
            'unit'        => TblWidth::PERCENT,
            'width'       => 100 * 50,
        ];

        $labelFont  = ['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => '2E75B6'];
        $valueFont  = ['name' => 'Calibri', 'size' => 11];
        $labelCell  = ['bgColor' => 'EBF3FB', 'valign' => 'center'];
        $valueCell  = ['bgColor' => 'FFFFFF', 'valign' => 'center'];

        $table = $section->addTable($tableStyle);

        $dateTime  = $transfer->date_time
            ? Carbon::parse($transfer->date_time)->format('d M Y — H:i')
            : '—';
        $route     = $transfer->route ?: '—';
        $company   = $transfer->company?->name ?? '—';
        $transport = $transfer->transport_type?->getLabel() ?? '—';
        $pax       = $transfer->pax ?? '—';
        $nameplate = $transfer->nameplate ?: '—';
        $driver    = $transfer->driver_name ?: '—';
        $driverPh  = $transfer->driver_phone ?: '—';
        $mark      = $transfer->mark ?: '—';
        $comment   = $transfer->comment ?: '—';

        $rows = [
            ['Date & Time',   $dateTime],
            ['Route',         $route],
            ['Company',       $company],
            ['Transport',     $transport],
            ['PAX',           (string)$pax],
            ['Passenger / Tablet', $nameplate],
            ['Driver',        $driver],
            ['Driver Phone',  $driverPh],
            ['Car (Mark)',    $mark],
            ['Comment',       $comment],
        ];

        foreach ($rows as [$label, $value]) {
            $table->addRow(400);
            $table->addCell(3000, $labelCell)->addText($label, $labelFont);
            $table->addCell(7000, $valueCell)->addText($value, $valueFont);
        }

        $section->addTextBreak(2);

        // ── Footer ───────────────────────────────────────────────────
        $footerRun = $section->addTextRun(['alignment' => Jc::CENTER]);
        $footerRun->addText('Thank you for choosing our service', [
            'name'      => 'Calibri',
            'size'      => 9,
            'italic'    => true,
            'color'     => '888888',
        ]);

        return $phpWord;
    }

    public static function save(PhpWord $phpWord, string $filePath): void
    {
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filePath);
    }
}
