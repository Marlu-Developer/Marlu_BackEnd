<?php

namespace App\Services\Sales;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * In-PHP "formula" (numeric range) and estimate-version filters for the sales dashboard.
 *
 * These can't be expressed as Mongo queries because the financial fields live nested under
 * `JobCollection_Job` and are stored as loosely-typed values (often strings), while the
 * estimate version is the *count* of an embedded array. The legacy app therefore fetched the
 * pre-filtered set and applied these predicates in PHP — see marluapp
 * `SalesDashboard::applyFormulaFiltersAndPaginate()` (:1887) / `applyFormulaFilters()` (:1941)
 * and `parseFormulaString()` (:2013). This class reproduces that behavior verbatim.
 */
class SalesPostFilter
{
    /**
     * Query-param => document path (dot notation) of the numeric field it filters.
     * Mirrors the legacy field map at SalesDashboard.php:266–304.
     */
    private const FORMULA_FIELDS = [
        'estimateprice' => 'JobCollection_Estimate_Price',
        'sellprice' => 'JobCollection_Job.Job_Booked',
        'deposits' => 'JobCollection_Job.Job_Deposits_Subtotal',
        'discounts' => 'JobCollection_Job.Job_Discounts',
        'upsells' => 'JobCollection_Job.Job_Upsells',
        'invoicedprice' => 'JobCollection_Job.Job_Subtotal_Less_Discounts',
        'collected' => 'JobCollection_Job.Job_Overall_Subtotal_Payments',
        'due' => 'JobCollection_Job.Job_Pending_Subtotal_Balance',
    ];

    public function hasPostFilters(Request $request): bool
    {
        return $this->collectFormulaFilters($request) !== []
            || $this->collectVersionFilter($request) !== [];
    }

    /**
     * @return array<int, array{0:string,1:string,2:string,3:string,4:string,5:string}>
     *         Each item: [fieldPath, operator1, value1, logic, operator2, value2]
     */
    public function collectFormulaFilters(Request $request): array
    {
        $filters = [];
        foreach (self::FORMULA_FIELDS as $param => $path) {
            if (!$request->filled($param)) {
                continue;
            }
            $p = $this->parseFormula((string) $request->query($param));
            // Skip a formula with no usable left-hand value (matches legacy: value1 === '').
            if ($p['value1'] === '') {
                continue;
            }
            $filters[] = [$path, $p['operator1'], $p['value1'], $p['logic'], $p['operator2'], $p['value2']];
        }
        return $filters;
    }

    /**
     * @return array<int, int> allowed estimate-version counts from `eversion`
     */
    public function collectVersionFilter(Request $request): array
    {
        if (!$request->filled('eversion')) {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', (string) $request->query('eversion'))), fn ($v) => $v !== '');
        return array_values(array_map('intval', $parts));
    }

    /**
     * Apply version then formula predicates to a fetched collection (legacy order).
     *
     * @param array<int, array<int, mixed>> $formulaFilters
     * @param array<int, int> $allowedVersions
     */
    public function apply(Collection $items, array $formulaFilters, array $allowedVersions): Collection
    {
        if ($allowedVersions !== []) {
            $items = $items->filter(fn ($item) => $this->matchesVersion($item, $allowedVersions));
        }
        if ($formulaFilters !== []) {
            $items = $items->filter(fn ($item) => $this->matchesFormula($item, $formulaFilters));
        }
        return $items->values();
    }

    private function matchesVersion(mixed $item, array $allowedVersions): bool
    {
        $info = data_get($item, 'JobCollection_Estimate.JobCollection_Estimate_Information');
        $version = is_array($info) ? count($info) : 0;
        return in_array($version, $allowedVersions, true);
    }

    /**
     * @param array<int, array<int, mixed>> $formulaFilters
     */
    private function matchesFormula(mixed $item, array $formulaFilters): bool
    {
        foreach ($formulaFilters as $filter) {
            [$path, $operator1, $value1, $logic, $operator2, $value2] = $filter;

            // Legacy: a missing field defaults to -1; a present field is floatval()'d.
            $raw = data_get($item, $path);
            $fieldValue = $raw === null ? -1.0 : (float) $raw;

            $condition1 = false;
            if ($operator1 === '>=') {
                $condition1 = $fieldValue >= (float) $value1;
            } elseif ($operator1 === '=') {
                $condition1 = abs($fieldValue - (float) $value1) < 0.01;
            }

            if ($value2 !== '') {
                $condition2 = false;
                if ($operator2 === '<=') {
                    $condition2 = $fieldValue <= (float) $value2;
                } elseif ($operator2 === '=') {
                    $condition2 = abs($fieldValue - (float) $value2) < 0.01;
                }

                if ($logic === 'AND') {
                    if (!($condition1 && $condition2)) {
                        return false;
                    }
                } else { // OR
                    if (!($condition1 || $condition2)) {
                        return false;
                    }
                }
            } elseif (!$condition1) {
                return false;
            }
        }
        return true;
    }

    /**
     * Parse a formula string: ">= 50" or ">= 50 AND <= 1000" (logic AND/OR).
     * Mirrors marluapp SalesDashboard::parseFormulaString (:2013).
     *
     * @return array{operator1:string,value1:string,logic:string,operator2:string,value2:string}
     */
    public function parseFormula(string $text): array
    {
        $result = [
            'operator1' => '>=',
            'value1' => '',
            'logic' => 'AND',
            'operator2' => '<=',
            'value2' => '',
        ];

        $trimmed = trim($text);
        if ($trimmed === '') {
            return $result;
        }

        if (preg_match('/^\s*(>=|=)\s*([-+]?\d+(?:\.\d{1,2})?)\s*(?:(AND|OR)\s*(=|<=)\s*([-+]?\d+(?:\.\d{1,2})?))?\s*$/i', $trimmed, $m)) {
            $result['operator1'] = $m[1];
            $result['value1'] = $m[2] ?? '';
            if (isset($m[3])) {
                $result['logic'] = strtoupper($m[3]);
            }
            if (isset($m[4])) {
                $result['operator2'] = $m[4];
            }
            if (isset($m[5])) {
                $result['value2'] = $m[5];
            }
        }

        return $result;
    }
}
