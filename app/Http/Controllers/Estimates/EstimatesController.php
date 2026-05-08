<?php

namespace App\Http\Controllers\Estimates;

use App\Http\Controllers\Controller;
use App\Services\Estimates\EstimatesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estimates feature: dashboard + CRUD + customer-facing email/results
 * (parity with marluapp/estimates/* controllers).
 */
class EstimatesController extends Controller
{
    public function __construct(private EstimatesService $estimates)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->estimates->paginate($request));
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => $this->estimates->find($id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'customer_job_id' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'information' => ['nullable', 'array'],
        ]);
        return response()->json(['data' => $this->estimates->create($payload)], 201);
    }

    public function update(string $id, Request $request): JsonResponse
    {
        $payload = $request->validate([
            'price' => ['nullable', 'numeric', 'min:0'],
            'information' => ['nullable', 'array'],
        ]);
        return response()->json(['data' => $this->estimates->update($id, $payload)]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->estimates->delete($id);
        return response()->json(['data' => ['ok' => true]]);
    }

    public function sendEmail(string $id, Request $request): JsonResponse
    {
        $payload = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
        ]);
        $this->estimates->sendCustomerEmail($id, $payload);
        return response()->json(['data' => ['ok' => true]]);
    }

    public function customerResult(string $id, Request $request): JsonResponse
    {
        $payload = $request->validate([
            'response' => ['required', 'in:accepted,rejected,questions'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        return response()->json(['data' => $this->estimates->customerResponse($id, $payload)]);
    }
}
