<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Services\Invoices\InvoicesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Invoices CRUD + PDF/email + templates (parity with marluapp/invoices/*).
 * PDF generation moves to a queued job; email goes through Mail facade
 * with a dedicated Mailable for retry & logging.
 */
class InvoicesController extends Controller
{
    public function __construct(private InvoicesService $invoices)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->invoices->paginate($request));
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => $this->invoices->find($id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'customer_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
        ]);
        return response()->json(['data' => $this->invoices->create($payload)], 201);
    }

    public function update(string $id, Request $request): JsonResponse
    {
        return response()->json(['data' => $this->invoices->update($id, $request->all())]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->invoices->delete($id);
        return response()->json(['data' => ['ok' => true]]);
    }

    public function pdf(string $id): JsonResponse
    {
        return response()->json(['data' => ['url' => $this->invoices->generatePdf($id)]]);
    }

    public function email(string $id, Request $request): JsonResponse
    {
        $payload = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
        ]);
        $this->invoices->sendEmail($id, $payload);
        return response()->json(['data' => ['ok' => true]]);
    }
}
