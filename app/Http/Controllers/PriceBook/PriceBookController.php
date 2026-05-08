<?php

namespace App\Http\Controllers\PriceBook;

use App\Http\Controllers\Controller;
use App\Services\PriceBook\PriceBookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv']]);
        $count = $this->priceBook->import($request->file('file'));
        return response()->json(['data' => ['imported' => $count]]);
    }

    public function export(): JsonResponse
    {
        return response()->json(['data' => ['url' => $this->priceBook->exportCsvUrl()]]);
    }
}
