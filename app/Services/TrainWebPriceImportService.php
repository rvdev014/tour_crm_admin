<?php

namespace App\Services;

use App\Enums\TrainWebClass;
use App\Models\City;
use App\Models\Train;
use App\Models\TrainWebPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Loads the website's "guaranteed ticket" train prices from Excel.
 *
 * The upload replaces every existing website price (the file is the single source of truth),
 * and one row covers both directions of a route. Nothing is saved unless every row is valid.
 *
 * Expected columns (by header name, any order): Train | From | To | Economy | Business | VIP.
 * A single "Route" column ("Tashkent - Bukhara") is accepted instead of From/To, and a blank
 * Train cell repeats the train above it, so the older hand-made sheet with merged cells
 * still loads. Rows without any price (e.g. the return-direction rows) are skipped.
 */
class TrainWebPriceImportService
{
    public const HEADERS = ['Train', 'From', 'To', 'Economy', 'Business', 'VIP'];

    /**
     * @return array{routes: int, trains: int}
     *
     * @throws InvalidArgumentException with one line per problem when the file can't be loaded
     */
    public function import(string $path): array
    {
        $rows = $this->readRows($path);

        $errors = [];
        $parsed = $this->parse($rows, $errors);

        if ($parsed === [] && $errors === []) {
            $errors[] = __('No prices found in the file.');
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode("\n", $errors));
        }

        $trainIds = [];
        DB::transaction(function () use ($parsed, &$trainIds) {
            TrainWebPrice::query()->delete();

            foreach ($parsed as $row) {
                $train = Train::query()->whereRaw('LOWER(TRIM(name)) = ?', [$row['train_key']])->first()
                    ?? Train::create(['name' => $row['train']]);
                $trainIds[$train->id] = true;

                // Both directions get their own row so a lookup never has to flip the route.
                foreach ([[$row['from'], $row['to']], [$row['to'], $row['from']]] as [$from, $to]) {
                    TrainWebPrice::updateOrCreate(
                        ['train_id' => $train->id, 'from_city_id' => $from, 'to_city_id' => $to],
                        [
                            'price_economy' => $row['prices']['economy'],
                            'price_business' => $row['prices']['business'],
                            'price_vip' => $row['prices']['vip'],
                        ]
                    );
                }
            }
        });

        return ['routes' => count($parsed), 'trains' => count($trainIds)];
    }

    /**
     * The current prices in the upload format (one row per route, not per direction), so staff
     * can edit and re-upload. With no prices yet it's an empty template with an example row.
     */
    public function export(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        $seen = [];
        $line = 2;
        $prices = TrainWebPrice::query()->with(['train', 'fromCity', 'toCity'])->orderBy('train_id')->orderBy('id')->get();

        foreach ($prices as $price) {
            $key = $price->train_id.':'.min($price->from_city_id, $price->to_city_id).':'.max($price->from_city_id, $price->to_city_id);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $sheet->fromArray([
                $price->train->name,
                $price->fromCity->name,
                $price->toCity->name,
                $price->price_economy,
                $price->price_business,
                $price->price_vip,
            ], null, 'A'.$line++);
        }

        if ($line === 2) {
            $sheet->fromArray(['Afrosiyob', 'Tashkent', 'Samarkand', 622000, 910000, 1418000], null, 'A2');
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'train-prices-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * @return array<int, array<int, mixed>>  1-based sheet row number => cell values
     */
    private function readRows(string $path): array
    {
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
        } catch (\Throwable) {
            throw new InvalidArgumentException(__('The file could not be read as an Excel sheet.'));
        }

        return $sheet->toArray(null, true, false, false);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $errors
     * @return array<int, array{train: string, train_key: string, from: int, to: int, prices: array<string, float|null>}>
     */
    private function parse(array $rows, array &$errors): array
    {
        [$columns, $headerIndex] = $this->findHeader($rows);

        if ($columns === null) {
            $errors[] = __('Header row not found. Expected columns: :columns', ['columns' => implode(', ', self::HEADERS)]);

            return [];
        }

        $cities = City::query()->get()->keyBy(fn (City $city) => $this->key($city->name));

        $parsed = [];
        $train = null;

        foreach ($rows as $index => $row) {
            if ($index <= $headerIndex) {
                continue;
            }
            $line = $index + 1;

            $trainCell = $this->text($row[$columns['train']] ?? null);
            if ($trainCell !== '') {
                $train = $trainCell;
            }

            $prices = [];
            foreach (TrainWebClass::cases() as $class) {
                $prices[$class->value] = $this->price($row[$columns[$class->value]] ?? null, $line, $class->getLabel(), $errors);
            }

            // Return-direction rows of the old sheet carry no price of their own.
            if (collect($prices)->every(fn ($price) => $price === null)) {
                continue;
            }

            [$fromName, $toName] = isset($columns['route'])
                ? $this->splitRoute($this->text($row[$columns['route']] ?? null))
                : [$this->text($row[$columns['from']] ?? null), $this->text($row[$columns['to']] ?? null)];

            if ($train === null || $fromName === '' || $toName === '') {
                $errors[] = __('Row :line: train and both cities are required.', ['line' => $line]);

                continue;
            }

            $from = $cities->get($this->key($fromName));
            $to = $cities->get($this->key($toName));

            foreach ([[$fromName, $from], [$toName, $to]] as [$name, $city]) {
                if ($city === null) {
                    $errors[] = __('Row :line: city ":name" not found in Cities.', ['line' => $line, 'name' => $name]);
                }
            }

            if ($from === null || $to === null) {
                continue;
            }

            if ($from->id === $to->id) {
                $errors[] = __('Row :line: From and To are the same city.', ['line' => $line]);

                continue;
            }

            $parsed[] = [
                'train' => $train,
                'train_key' => $this->key($train),
                'from' => $from->id,
                'to' => $to->id,
                'prices' => $prices,
            ];
        }

        return $parsed;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: array<string, int>|null, 1: int}
     */
    private function findHeader(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $columns = [];

            foreach ($row as $position => $cell) {
                $label = $this->key($this->text($cell));

                $name = match (true) {
                    $label === 'train' => 'train',
                    $label === 'from' => 'from',
                    $label === 'to' => 'to',
                    $label === 'route' => 'route',
                    str_contains($label, 'economy') => 'economy',
                    str_contains($label, 'business') => 'business',
                    str_contains($label, 'vip') => 'vip',
                    default => null,
                };

                if ($name !== null) {
                    $columns[$name] = $position;
                }
            }

            $hasRoute = isset($columns['route']) || (isset($columns['from']) && isset($columns['to']));

            if (isset($columns['train'], $columns['economy'], $columns['business'], $columns['vip']) && $hasRoute) {
                return [$columns, $index];
            }
        }

        return [null, -1];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitRoute(string $route): array
    {
        $parts = preg_split('/\s+[-–—]\s+/u', $route);

        return count($parts) === 2 ? [trim($parts[0]), trim($parts[1])] : ['', ''];
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function price(mixed $value, int $line, string $class, array &$errors): ?float
    {
        $text = $this->text($value);

        // Empty or a dash means "not sold in this class".
        if ($text === '' || in_array($text, ['-', '–', '—'], true)) {
            return null;
        }

        $number = str_replace([' ', "\u{00A0}", ','], '', $text);

        if (! is_numeric($number) || (float) $number < 0) {
            $errors[] = __('Row :line: invalid :class price ":value".', ['line' => $line, 'class' => $class, 'value' => $text]);

            return null;
        }

        return (float) $number;
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    // Case- and whitespace-insensitive so "Tashkent", " tashkent " and "TASHKENT" match.
    private function key(string $value): string
    {
        return Str::of($value)->lower()->squish()->toString();
    }
}
