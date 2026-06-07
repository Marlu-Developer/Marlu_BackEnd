<?php

namespace App\Services\PriceBook;

use App\Models\PriceBookCategoryDatabaseCollection;
use App\Models\PriceBookDatabaseCollection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class PriceBookService
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 50), 200));
        $q = PriceBookDatabaseCollection::query();
        if ($request->filled('category')) {
            $q->where('category', $request->query('category'));
        }
        if ($request->filled('search')) {
            $q->where('name', 'like', '%' . $request->query('search') . '%');
        }
        return $q->orderBy('name')->paginate($perPage);
    }

    public function categories()
    {
        return PriceBookCategoryDatabaseCollection::orderBy('name')->get();
    }

    /**
     * Paginated Price Book management list (legacy PriceBookDashboard::index :18). Each item is
     * a package node (PriceBookCategory) with its services attached. Searches Unit / Package_Code
     * / Service_Size_Difficulty / Description.
     *
     * @return array{data: array, current_page: int, last_page: int, per_page: int, total: int}
     */
    public function packages(Request $request): array
    {
        $perPage = 20;
        $search = trim((string) $request->query('search', ''));

        $q = PriceBookCategoryDatabaseCollection::query();
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('PriceBookCollection_Unit', 'like', "%{$search}%")
                    ->orWhere('PriceBookCollection_Package_Code', 'like', "%{$search}%")
                    ->orWhere('PriceBookCollection_Service_Size_Difficulty', 'like', "%{$search}%")
                    ->orWhere('PriceBookCollection_Description', 'like', "%{$search}%");
            });
        }
        $paginator = $q->orderBy('PriceBookCollection_Package_Code', 'ASC')
            ->orderBy('PriceBookCollection_Difficulty', 'ASC')
            ->paginate($perPage);

        $items = [];
        foreach ($paginator->items() as $node) {
            $services = PriceBookDatabaseCollection::where('PriceBookCollection_Category_Id', $node->id)
                ->orderBy('PriceBookCollection_Service_Code', 'ASC')
                ->get();
            $row = $node->toArray();
            $row['services'] = $services->toArray();
            $items[] = $row;
        }

        return [
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /** Delete package nodes by id (legacy deletePackages :284). */
    public function deletePackages(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }
        return (int) PriceBookCategoryDatabaseCollection::whereIn('_id', $ids)->delete();
    }

    /**
     * Set the included-service set for a package and recompute its aggregates
     * (legacy addEditServices :294): excluded → all off, chosen → on, then sum the included.
     *
     * @param array<int, string> $serviceIds
     * @return array{unit_price: float, wages_budget: float, times: float}
     */
    public function setPackageServices(string $packageId, array $serviceIds): array
    {
        PriceBookDatabaseCollection::where('PriceBookCollection_Category_Id', $packageId)
            ->update(['PriceBookCollection_Include_Service' => false]);

        $unit = 0.0;
        $wage = 0.0;
        $time = 0.0;
        foreach ($serviceIds as $sid) {
            $s = PriceBookDatabaseCollection::where('_id', $sid)->first();
            if (!$s) {
                continue;
            }
            $s->PriceBookCollection_Include_Service = true;
            $s->save();
            $unit += (float) $s->PriceBookCollection_Unit_Price;
            $wage += (float) $s->PriceBookCollection_Wage_Budget;
            $time += (float) $s->PriceBookCollection_Time;
        }

        PriceBookCategoryDatabaseCollection::where('_id', $packageId)->update([
            'PriceBookCollection_Unit_Price' => $unit,
            'PriceBookCollection_Wages_Budget' => $wage,
            'PriceBookCollection_Times' => $time,
        ]);

        return ['unit_price' => $unit, 'wages_budget' => $wage, 'times' => $time];
    }

    /**
     * Remove a single service from its package (legacy removeServiceFromPackage :274 sets the
     * Include flag off). We also recompute the parent package aggregates so the displayed totals
     * stay correct — a deliberate improvement over the legacy, which left them stale until the
     * next add/edit.
     */
    public function removeService(string $serviceId): void
    {
        $service = PriceBookDatabaseCollection::where('_id', $serviceId)->first();
        if (!$service) {
            return;
        }
        $service->PriceBookCollection_Include_Service = false;
        $service->save();

        $packageId = (string) ($service->PriceBookCollection_Category_Id ?? '');
        if ($packageId === '') {
            return;
        }
        $included = PriceBookDatabaseCollection::where('PriceBookCollection_Category_Id', $packageId)
            ->where('PriceBookCollection_Include_Service', true)
            ->get();
        PriceBookCategoryDatabaseCollection::where('_id', $packageId)->update([
            'PriceBookCollection_Unit_Price' => (float) $included->sum('PriceBookCollection_Unit_Price'),
            'PriceBookCollection_Wages_Budget' => (float) $included->sum('PriceBookCollection_Wage_Budget'),
            'PriceBookCollection_Times' => (float) $included->sum('PriceBookCollection_Time'),
        ]);
    }

    /**
     * Hierarchy for the estimate package builder. Ports marluapp
     * JobsController::getPricebookCategoryData (:1003): returns every category node plus a
     * nested `[Category][Group][Size][Package][Difficulty] => index-into-categoryData` map.
     *
     * @return array{categoryData: array, structured: array}
     */
    public function estimateStructure(): array
    {
        $nodes = PriceBookCategoryDatabaseCollection::orderBy('PriceBookCollection_Package_Code', 'ASC')
            ->orderBy('PriceBookCollection_Difficulty')
            ->get();

        $structured = [];
        $categoryData = [];
        foreach ($nodes as $i => $item) {
            $categoryData[] = $item;
            $cat = (string) ($item['PriceBookCollection_Category'] ?? '');
            $grp = (string) ($item['PriceBookCollection_Group'] ?? '');
            $size = (string) ($item['PriceBookCollection_Size'] ?? '');
            $pack = (string) ($item['PriceBookCollection_Package'] ?? '');
            $diff = (string) ($item['PriceBookCollection_Difficulty'] ?? '');
            // First node wins for a given path (legacy uses `!isset` guards).
            $structured[$cat][$grp][$size][$pack][$diff] ??= $i;
        }

        return ['categoryData' => $categoryData, 'structured' => $structured];
    }

    /**
     * Services attached to a price-book category node (ports getPriceBookData :1031).
     */
    public function packageServices(string $categoryNodeId)
    {
        return PriceBookDatabaseCollection::where('PriceBookCollection_Category_Id', $categoryNodeId)
            ->orderBy('PriceBookCollection_Service_Code', 'ASC')
            ->get();
    }

    public function create(array $payload): mixed
    {
        return PriceBookDatabaseCollection::create($payload);
    }

    public function update(string $id, array $payload): mixed
    {
        $doc = PriceBookDatabaseCollection::where('_id', $id)->first();
        if (!$doc) return null;
        foreach ($payload as $k => $v) {
            $doc[$k] = $v;
        }
        $doc->save();
        return $doc;
    }

    public function delete(string $id): bool
    {
        return PriceBookDatabaseCollection::where('_id', $id)->delete() > 0;
    }

    /**
     * Flat CSV column order shared by export, import and the downloadable template.
     * One row per service; a package with no services is emitted as a single row with
     * the service columns left blank. Package columns repeat on every service row so
     * each row is self-contained and the file round-trips through import.
     */
    private const CSV_HEADERS = [
        'Category', 'Group', 'Size', 'Package', 'Description', 'Package_Code',
        'Unit', 'Qty', 'Difficulty',
        'Service_Code', 'Service_Name', 'Unit_Price', 'Wage_Budget', 'Time_Hours', 'Include',
    ];

    /**
     * Build the full price book as flat CSV rows (header + data). Mirrors the legacy
     * ExportsPriceBook content but in a one-row-per-record layout that re-imports cleanly.
     *
     * @return array<int, array<int, string|int|float>>
     */
    private function exportRows(): array
    {
        $packages = PriceBookCategoryDatabaseCollection::orderBy('PriceBookCollection_Package_Code', 'ASC')
            ->orderBy('PriceBookCollection_Difficulty', 'ASC')
            ->get();

        $rows = [self::CSV_HEADERS];

        foreach ($packages as $pkg) {
            $base = [
                (string) ($pkg->PriceBookCollection_Category ?? ''),
                (string) ($pkg->PriceBookCollection_Group ?? ''),
                (string) ($pkg->PriceBookCollection_Size ?? ''),
                (string) ($pkg->PriceBookCollection_Package ?? ''),
                (string) ($pkg->PriceBookCollection_Description ?? ''),
                (string) ($pkg->PriceBookCollection_Package_Code ?? ''),
                (string) ($pkg->PriceBookCollection_Unit ?? ''),
                (string) ($pkg->PriceBookCollection_Innitial_Qty ?? ''),
                (string) ($pkg->PriceBookCollection_Difficulty ?? '0'),
            ];

            $services = PriceBookDatabaseCollection::where('PriceBookCollection_Category_Id', $pkg->id)
                ->orderBy('PriceBookCollection_Service_Code', 'ASC')
                ->get();

            if ($services->isEmpty()) {
                $rows[] = array_merge($base, ['', '', '', '', '', '']);
                continue;
            }

            foreach ($services as $s) {
                $include = filter_var($s->PriceBookCollection_Include_Service, FILTER_VALIDATE_BOOLEAN);
                $rows[] = array_merge($base, [
                    (string) ($s->PriceBookCollection_Service_Code ?? ''),
                    (string) ($s->PriceBookCollection_Service_Name ?? ''),
                    (string) ((float) $s->PriceBookCollection_Unit_Price),
                    (string) ((float) $s->PriceBookCollection_Wage_Budget),
                    (string) ((float) $s->PriceBookCollection_Time),
                    $include ? 'Yes' : 'No',
                ]);
            }
        }

        return $rows;
    }

    /** Encode rows as a CSV string (RFC 4180 quoting via fputcsv). */
    private function rowsToCsv(array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv;
    }

    /** Full price book as a CSV string for download. */
    public function exportCsv(): string
    {
        return $this->rowsToCsv($this->exportRows());
    }

    /** Empty CSV with the expected headers + one example row, for the "THIS TEMPLATE" link. */
    public function templateCsv(): string
    {
        return $this->rowsToCsv([
            self::CSV_HEADERS,
            [
                'SHOWER', 'Whole Shower', 'Standard', 'Full Restoration',
                'SHOWER: Whole Shower - Full Restoration', '001',
                'Service', '1', '0',
                'Q001', 'Powered CFM Surface Air Mover Drying', '18.34', '2.50', '0.1352', 'Yes',
            ],
        ]);
    }

    /**
     * Import a flat CSV (see CSV_HEADERS). Upserts package nodes (by Package_Code + Difficulty)
     * and their services (by Service_Code), then recomputes each touched package's aggregates
     * from its included services. Returns the number of data rows processed.
     */
    public function import(UploadedFile $file): int
    {
        $fh = fopen($file->getRealPath(), 'r');
        if ($fh === false) {
            return 0;
        }

        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);
            return 0;
        }
        // Map normalized header name => column index, so column order is tolerant.
        $col = [];
        foreach ($header as $i => $name) {
            $key = strtolower(trim(preg_replace('/[\s_]+/', '_', (string) $name)));
            $col[$key] = $i;
        }
        $get = function (array $row, string $key) use ($col): string {
            $i = $col[$key] ?? null;
            return $i !== null && isset($row[$i]) ? trim((string) $row[$i]) : '';
        };

        $count = 0;
        $touched = [];

        while (($row = fgetcsv($fh)) !== false) {
            // Skip fully blank lines.
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $packageCode = $get($row, 'package_code');
            if ($packageCode === '') {
                continue;
            }
            $difficulty = (int) ($get($row, 'difficulty') ?: 0);

            $pkg = PriceBookCategoryDatabaseCollection::where('PriceBookCollection_Package_Code', $packageCode)
                ->where('PriceBookCollection_Difficulty', $difficulty)
                ->first();
            if (!$pkg) {
                $pkg = new PriceBookCategoryDatabaseCollection();
                $pkg->PriceBookCollection_Package_Code = $packageCode;
                $pkg->PriceBookCollection_Difficulty = $difficulty;
                $pkg->PriceBookCollection_Unit_Price = 0;
                $pkg->PriceBookCollection_Wages_Budget = 0;
                $pkg->PriceBookCollection_Times = 0;
            }
            $pkg->PriceBookCollection_Category = $get($row, 'category');
            $pkg->PriceBookCollection_Group = $get($row, 'group');
            $pkg->PriceBookCollection_Size = $get($row, 'size');
            $pkg->PriceBookCollection_Package = $get($row, 'package');
            $pkg->PriceBookCollection_Description = $get($row, 'description');
            $pkg->PriceBookCollection_Unit = $get($row, 'unit');
            $pkg->PriceBookCollection_Innitial_Qty = $get($row, 'qty') ?: 1;
            $pkg->save();
            $touched[(string) $pkg->id] = true;

            $serviceCode = $get($row, 'service_code');
            if ($serviceCode !== '') {
                $service = PriceBookDatabaseCollection::where('PriceBookCollection_Category_Id', $pkg->id)
                    ->where('PriceBookCollection_Service_Code', $serviceCode)
                    ->first() ?? new PriceBookDatabaseCollection();
                $service->PriceBookCollection_Category_Id = (string) $pkg->id;
                $service->PriceBookCollection_Difficulty = $difficulty;
                $service->PriceBookCollection_Service_Code = $serviceCode;
                $service->PriceBookCollection_Service_Name = $get($row, 'service_name');
                $service->PriceBookCollection_Unit_Price = (float) $get($row, 'unit_price');
                $service->PriceBookCollection_Wage_Budget = (float) $get($row, 'wage_budget');
                $service->PriceBookCollection_Time = (float) $get($row, 'time_hours');
                $service->PriceBookCollection_Include_Service =
                    in_array(strtolower($get($row, 'include')), ['yes', 'true', '1', 'y'], true);
                $service->save();
            }

            $count++;
        }
        fclose($fh);

        // Recompute aggregates for every package the import touched.
        foreach (array_keys($touched) as $pid) {
            $included = PriceBookDatabaseCollection::where('PriceBookCollection_Category_Id', $pid)
                ->where('PriceBookCollection_Include_Service', true)
                ->get();
            PriceBookCategoryDatabaseCollection::where('_id', $pid)->update([
                'PriceBookCollection_Unit_Price' => (float) $included->sum('PriceBookCollection_Unit_Price'),
                'PriceBookCollection_Wages_Budget' => (float) $included->sum('PriceBookCollection_Wage_Budget'),
                'PriceBookCollection_Times' => (float) $included->sum('PriceBookCollection_Time'),
            ]);
        }

        return $count;
    }
}
