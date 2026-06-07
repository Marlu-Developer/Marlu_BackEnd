<?php

namespace Tests\Unit;

use App\Services\Sales\SalesPostFilter;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the in-PHP formula/version filter logic ported from the legacy
 * SalesDashboard (parseFormulaString / applyFormulaFilters / version count).
 */
class SalesPostFilterTest extends TestCase
{
    private SalesPostFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new SalesPostFilter();
    }

    public function test_parses_single_operator(): void
    {
        $p = $this->filter->parseFormula('>= 50');
        $this->assertSame('>=', $p['operator1']);
        $this->assertSame('50', $p['value1']);
        $this->assertSame('', $p['value2']);
    }

    public function test_parses_dual_operator_with_logic(): void
    {
        $p = $this->filter->parseFormula('>= 50 AND <= 1000');
        $this->assertSame('>=', $p['operator1']);
        $this->assertSame('50', $p['value1']);
        $this->assertSame('AND', $p['logic']);
        $this->assertSame('<=', $p['operator2']);
        $this->assertSame('1000', $p['value2']);
    }

    public function test_parses_or_logic_and_equals(): void
    {
        $p = $this->filter->parseFormula('= 100 OR = 200');
        $this->assertSame('=', $p['operator1']);
        $this->assertSame('100', $p['value1']);
        $this->assertSame('OR', $p['logic']);
        $this->assertSame('200', $p['value2']);
    }

    public function test_invalid_formula_yields_empty_value(): void
    {
        $p = $this->filter->parseFormula('not a formula');
        $this->assertSame('', $p['value1']);
    }

    public function test_formula_range_filters_rows(): void
    {
        $rows = new Collection([
            ['_id' => 'a', 'JobCollection_Job' => ['Job_Booked' => '500']],   // in range
            ['_id' => 'b', 'JobCollection_Job' => ['Job_Booked' => '700']],   // above
            ['_id' => 'c', 'JobCollection_Job' => ['Job_Booked' => '100']],   // at lower bound
            ['_id' => 'd', 'JobCollection_Job' => []],                         // missing -> -1
        ]);

        // sellprice: >= 100 AND <= 600
        $formula = [['JobCollection_Job.Job_Booked', '>=', '100', 'AND', '<=', '600']];
        $kept = $this->filter->apply($rows, $formula, [])->pluck('_id')->all();

        $this->assertEqualsCanonicalizing(['a', 'c'], $kept);
    }

    public function test_equals_uses_epsilon(): void
    {
        $rows = new Collection([
            ['_id' => 'x', 'JobCollection_Estimate_Price' => '100.00'],
            ['_id' => 'y', 'JobCollection_Estimate_Price' => '100.5'],
        ]);
        $formula = [['JobCollection_Estimate_Price', '=', '100', 'AND', '<=', '']];
        $kept = $this->filter->apply($rows, $formula, [])->pluck('_id')->all();
        $this->assertSame(['x'], $kept);
    }

    public function test_version_filter_counts_estimate_information(): void
    {
        $mk = fn (string $id, int $n) => [
            '_id' => $id,
            'JobCollection_Estimate' => [
                'JobCollection_Estimate_Information' => array_fill(0, $n, ['v' => $id]),
            ],
        ];
        $rows = new Collection([$mk('one', 1), $mk('two', 2), $mk('three', 3)]);

        $kept = $this->filter->apply($rows, [], [2])->pluck('_id')->all();
        $this->assertSame(['two'], $kept);
    }
}
