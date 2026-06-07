<?php

namespace App\Http\Controllers\PriceBook;

use App\Http\Controllers\Controller;
use App\Services\PriceBook\PriceBookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Price book CRUD + import/export. Parity with marluapp/price-book/* + PriceBookImport/Export.
 * Imports/exports run via Maatwebsite/Excel and are queued for large files.
 */
class PriceBookController extends Controller
{
    public function __construct(private PriceBookService $priceBook)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->priceBook->index($request));
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => $this->priceBook->categories()]);
    }

    /** Paginated management list: package nodes + their services (legacy PriceBookDashboard). */
    public function packages(Request $request): JsonResponse
    {
        return response()->json($this->priceBook->packages($request));
    }

    public function deletePackages(Request $request): JsonResponse
    {
        $ids = (array) $request->input('ids', []);
        return response()->json(['data' => ['deleted' => $this->priceBook->deletePackages($ids)]]);
    }

    public function setServices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'string'],
            'service_ids' => ['present', 'array'],
            'service_ids.*' => ['string'],
        ]);
        $totals = $this->priceBook->setPackageServices($validated['package_id'], $validated['service_ids']);
        return response()->json(['data' => $totals]);
    }

    public function removeService(Request $request): JsonResponse
    {
        $validated = $request->validate(['service_id' => ['required', 'string']]);
        $this->priceBook->removeService($validated['service_id']);
        return response()->json(['data' => ['ok' => true]]);
    }

    /** Hierarchy + category nodes for the estimate package builder, plus this user's price limits. */
    public function estimateStructure(): JsonResponse
    {
        $user = JWTAuth::user();
        $data = $this->priceBook->estimateStructure();
        // Legacy default is 100 (= no adjustment allowed) when the employee has no permit value.
        $data['priceAdjustment'] = [
            'highest' => (float) ($user->Employee_User_Packages_Price_Adjustment_Highest ?? 100),
            'lowest' => (float) ($user->Employee_User_Packages_Price_Adjustment_Lowest ?? 100),
        ];
        return response()->json(['data' => $data]);
    }

    /** Services for a price-book category node (the selected package/difficulty). */
    public function packageServices(string $id): JsonResponse
    {
        return response()->json(['data' => $this->priceBook->packageServices($id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);
        return response()->json(['data' => $this->priceBook->create($payload)], 201);
    }

    public function update(string $id, Request $request): JsonResponse
    {
        return response()->json(['data' => $this->priceBook->update($id, $request->all())]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->priceBook->delete($id);
        return response()->json(['data' => ['ok' => true]]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);
        $count = $this->priceBook->import($request->file('file'));
        return response()->json(['data' => ['imported' => $count]]);
    }

    /** Stream the whole price book as a CSV download. */
    public function export(): Response
    {
        return response($this->priceBook->exportCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="PriceBook.csv"',
        ]);
    }

    /** Downloadable import template (the pre-built XLSX shipped in public/assets/etc). */
    public function template(): BinaryFileResponse
    {
        return response()->download(
            public_path('assets/etc/Import_Price_Book_Format.xlsx'),
            'Import_Price_Book_Format.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
