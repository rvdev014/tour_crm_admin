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

        // Landscape A4: explicitly set pageSizeW/pageSizeH in twips so PhpWord
        // outputs the correct w:w/w:h/w:orient in the XML (array 'orientation' key
        // alone does not reliably swap dimensions in all PhpWord versions).
        // Values from the example file: w:w="16838" w:h="11906" (twips)
        $section = $phpWord->addSection([
            'pageSizeW'    => 16838,  // 29.7 cm (landscape width)
            'pageSizeH'    => 11906,  // 21 cm   (landscape height)
            'orientation'  => 'landscape',
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

        // Route line — 11pt, bold, centered
        $infoLine = $transfer->route ?: '';

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
