<?php

namespace App\Services;

use App\Models\Tour;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExportHotelService
{
    public static function getExport(Tour $tour): Spreadsheet
    {


        return $spreadsheet;
    }
}
