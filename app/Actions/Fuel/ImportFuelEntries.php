<?php

namespace App\Actions\Fuel;

use App\Http\Requests\StoreFuelEntryRequest;
use App\Models\FuelEntry;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ImportFuelEntries
{
    public function __construct(private RecalculateVehicleFuelEconomy $recalculateVehicleFuelEconomy) {}

    /**
     * @return array{status: string, imported: int, failed: int, failures: list<array{row: int, values: array<string, string>, errors: list<string>}>}
     */
    public function handle(Vehicle $vehicle, UploadedFile $file): array
    {
        $parsed = $this->readRows($file);

        return DB::transaction(function () use ($vehicle, $parsed): array {
            $lockedVehicle = Vehicle::query()->whereKey($vehicle->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('create', [FuelEntry::class, $lockedVehicle]);
            $entries = $lockedVehicle->fuelEntries()->chronological()->lockForUpdate()->get()->all();
            $failures = $parsed['failures'];
            $imported = 0;

            foreach ($parsed['rows'] as $line => $row) {
                $values = $row['values'];
                $entry = new FuelEntry([
                    ...$values,
                    ...FuelEntryValues::normalize($values),
                    'vehicle_id' => $lockedVehicle->getKey(),
                ]);
                $entry->forceFill(['id' => (string) Str::ulid()]);
                $position = $this->insertionPosition($entries, $entry);
                $previous = $entries[$position - 1] ?? null;
                $next = $entries[$position] ?? null;

                if (($previous !== null && $entry->odometer <= $previous->odometer)
                    || ($next !== null && $entry->odometer >= $next->odometer)) {
                    $failures[] = [
                        'row' => $line,
                        'values' => $row['source'],
                        'errors' => ['Duplicate or conflicting fill-up: the odometer must increase with the date, including existing history and other imported rows.'],
                    ];

                    continue;
                }

                $entry->save();
                array_splice($entries, $position, 0, [$entry]);
                $imported++;
            }

            if ($imported > 0) {
                $this->recalculateVehicleFuelEconomy->handle($lockedVehicle);
            }

            usort($failures, fn (array $left, array $right): int => $left['row'] <=> $right['row']);

            return [
                'status' => $imported === 0 ? 'failure' : ($failures === [] ? 'success' : 'partial_success'),
                'imported' => $imported,
                'failed' => count($failures),
                'failures' => $failures,
            ];
        });
    }

    /** @param list<FuelEntry> $entries */
    private function insertionPosition(array $entries, FuelEntry $entry): int
    {
        $lower = 0;
        $upper = count($entries);
        $key = [$entry->fueled_at->format('Y-m-d H:i:s'), $entry->id];

        while ($lower < $upper) {
            $middle = intdiv($lower + $upper, 2);
            $candidate = $entries[$middle];

            if ([$candidate->fueled_at->format('Y-m-d H:i:s'), $candidate->id] < $key) {
                $lower = $middle + 1;
            } else {
                $upper = $middle;
            }
        }

        return $lower;
    }

    /**
     * @return array{rows: array<int, array{values: array<string, mixed>, source: array<string, string>}>, failures: list<array{row: int, values: array<string, string>, errors: list<string>}>}
     */
    private function readRows(UploadedFile $file): array
    {
        $stream = fopen($file->getRealPath(), 'r');

        try {
            $headers = fgetcsv($stream, null, ',', '"', '');
            if ($headers === false) {
                throw ValidationException::withMessages(['file' => 'The CSV is empty.']);
            }

            $headers = array_map(fn (?string $header): string => mb_strtolower(trim($header ?? '')), $headers);
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            $required = ['model', 'odometer', 'gallons', 'price', 'fuelup_date'];

            if (array_diff($required, $headers) !== [] || count(array_unique($headers)) !== count($headers)) {
                throw ValidationException::withMessages(['file' => 'Use unique column headers including: '.implode(', ', $required).'.']);
            }

            $rows = [];
            $line = 1;
            $count = 0;
            $failures = [];
            $rules = (new StoreFuelEntryRequest)->rules();
            $rules['odometer'][] = 'max:999999999';
            $rules['gallons'][] = 'max:9999999.999';
            $rules['price_per_gallon'] = ['required', 'numeric', 'min:0', 'max:9999999.999', 'decimal:0,3'];
            $rules['model'] = ['required', 'string', 'max:255'];

            while (($cells = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $line++;
                if (count($cells) === 1 && trim($cells[0] ?? '') === '') {
                    continue;
                }
                if (++$count > 5000) {
                    throw ValidationException::withMessages(['file' => 'Import at most 5,000 fill-ups at a time.']);
                }
                if (count($cells) !== count($headers)) {
                    $failures[] = ['row' => $line, 'values' => [], 'errors' => ['The number of values does not match the headers.']];

                    continue;
                }

                $row = array_combine($headers, array_map(fn (?string $cell): string => trim($cell ?? ''), $cells));
                $source = array_intersect_key($row, array_flip($required));
                $values = [
                    'model' => $row['model'],
                    'odometer' => $row['odometer'],
                    'gallons' => $row['gallons'],
                    'price_per_gallon' => $row['price'],
                    'fueled_at' => $row['fuelup_date'],
                ];
                $validator = Validator::make($values, $rules, [], ['fueled_at' => 'fuelup_date', 'price_per_gallon' => 'price']);
                if ($validator->fails()) {
                    $failures[] = ['row' => $line, 'values' => $source, 'errors' => $validator->errors()->all()];

                    continue;
                }

                unset($values['model']);
                $values['fueled_at'] = Carbon::parse($values['fueled_at'])->format('Y-m-d H:i:s');
                if ((float) $values['gallons'] * (float) $values['price_per_gallon'] > 99999999.99) {
                    $failures[] = ['row' => $line, 'values' => $source, 'errors' => ['The calculated total cost is too large.']];

                    continue;
                }
                $rows[$line] = ['values' => $values, 'source' => $source];
            }

            if ($count === 0) {
                throw ValidationException::withMessages(['file' => 'The CSV must contain at least one fill-up.']);
            }

            uasort($rows, fn (array $left, array $right): int => [$left['values']['fueled_at'], (int) $left['values']['odometer']] <=> [$right['values']['fueled_at'], (int) $right['values']['odometer']]);

            return ['rows' => $rows, 'failures' => $failures];
        } finally {
            fclose($stream);
        }
    }
}
