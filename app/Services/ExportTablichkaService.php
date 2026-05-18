<?php

namespace App\Services;

use App\Models\Transfer;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class ExportTablichkaService
{
    public static function generate(Transfer $transfer): PhpWord
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Aptos');

        // Landscape A4, matching exact margins from the example file (in twips)
        $section = $phpWord->addSection([
            'orientation'  => 'landscape',
            'paperSize'    => 'A4',
            'marginTop'    => 1701,
            'marginRight'  => 1134,
            'marginBottom' => 850,
            'marginLeft'   => 1134,
        ]);

        // Nameplate — 90pt, bold, centered
        $namePara = $section->addTextRun(['alignment' => Jc::CENTER]);
        $namePara->addText($transfer->nameplate ?? '', [
            'name'  => 'Aptos',
            'size'  => 90,
            'bold'  => true,
        ]);

        // Vertical spacing (12 blank lines, matching the example)
        $section->addTextBreak(12);

        // Pickup Location + Route line — 11pt, bold, centered
        $parts = [];
        if (!empty($transfer->place_of_submission)) {
            $parts[] = 'Откуда: ' . $transfer->place_of_submission;
        }
        if (!empty($transfer->route)) {
            $parts[] = 'куда: ' . $transfer->route;
        }
        $infoLine = implode('   ', $parts);

        $infoPara = $section->addTextRun(['alignment' => Jc::CENTER]);
        $infoPara->addText($infoLine, [
            'name' => 'Aptos',
            'size' => 11,
            'bold' => true,
        ]);

        return $phpWord;
    }

    public static function save(PhpWord $phpWord, string $filePath): void
    {
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filePath);
    }
}
