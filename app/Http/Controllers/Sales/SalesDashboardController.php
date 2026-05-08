<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\AssignSetterRequest;
use App\Http\Requests\Sales\BulkActionRequest;
use App\Services\Sales\SalesService;
use App\Support\Exports\SalesDashboardCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesDashboardController extends Controller
{
    public function __construct(private SalesService $sales)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->sales->dashboard($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->sales->exportRows($request);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, SalesDashboardCsv::HEADERS);
            foreach ($rows as $row) {
                fputcsv($out, SalesDashboardCsv::row($row));
            }
            fclose($out);
        }, 'sales-dashboard.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function assignSetter(AssignSetterRequest $request): JsonResponse
    {
        $updated = $this->sales->assignSetter(
            (array) $request->validated('ids'),
            (string) $request->validated('setter_name'),
        );
        return response()->json(['data' => ['updated' => $updated]]);
    }

    public function bulkAction(BulkActionRequest $request): JsonResponse
    {
        $updated = $this->sales->bulkAction(
            $request,
            (array) $request->validated('fields'),
            (array) ($request->validated('ids') ?? []),
            (array) ($request->validated('filter_query') ?? []),
        );
        return response()->json(['data' => ['updated' => $updated]]);
    }
}
