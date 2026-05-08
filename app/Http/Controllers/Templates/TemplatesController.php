<?php

namespace App\Http\Controllers\Templates;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplateDatabaseCollection;
use App\Models\EstimateEmailInfoDatabaseCollection;
use App\Models\InvoiceEmailTemplateDatabaseCollection;
use App\Models\InvoicePDFTemplateDatabaseCollection;
use App\Models\PDFTemplateDatabaseCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estimate / invoice template editors (HTML + variables). Parity with
 * marluapp templates section.
 */
class TemplatesController extends Controller
{
    private const KINDS = [
        'estimate-email' => EmailTemplateDatabaseCollection::class,
        'estimate-pdf' => PDFTemplateDatabaseCollection::class,
        'estimate-info' => EstimateEmailInfoDatabaseCollection::class,
        'invoice-email' => InvoiceEmailTemplateDatabaseCollection::class,
        'invoice-pdf' => InvoicePDFTemplateDatabaseCollection::class,
    ];

    public function show(string $kind): JsonResponse
    {
        $model = self::KINDS[$kind] ?? null;
        if (!$model) {
            return response()->json(['message' => 'Unknown template kind', 'code' => 'not_found'], 404);
        }
        return response()->json(['data' => $model::first()]);
    }

    public function update(string $kind, Request $request): JsonResponse
    {
        $model = self::KINDS[$kind] ?? null;
        if (!$model) {
            return response()->json(['message' => 'Unknown template kind', 'code' => 'not_found'], 404);
        }
        $payload = $request->validate([
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string'],
        ]);
        $doc = $model::first();
        if (!$doc) {
            $doc = $model::create($payload);
        } else {
            foreach ($payload as $k => $v) {
                $doc[$k] = $v;
            }
            $doc->save();
        }
        return response()->json(['data' => $doc]);
    }
}
