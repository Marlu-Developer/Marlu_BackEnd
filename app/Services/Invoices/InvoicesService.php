<?php

namespace App\Services\Invoices;

use App\Models\InvoicesDatabaseCollection;
use Illuminate\Http\Request;

class InvoicesService
{
    public function paginate(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        return InvoicesDatabaseCollection::query()->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function find(string $id): mixed
    {
        return InvoicesDatabaseCollection::where('_id', $id)->first();
    }

    public function create(array $payload): mixed
    {
        return InvoicesDatabaseCollection::create($payload);
    }

    public function update(string $id, array $payload): mixed
    {
        $doc = InvoicesDatabaseCollection::where('_id', $id)->first();
        if (!$doc) return null;
        foreach ($payload as $k => $v) {
            $doc[$k] = $v;
        }
        $doc->save();
        return $doc;
    }

    public function delete(string $id): bool
    {
        return InvoicesDatabaseCollection::where('_id', $id)->delete() > 0;
    }

    public function generatePdf(string $id): string
    {
        // TODO: dispatch GeneratePdf queued job (use dompdf or browser-shot service).
        // For now return a placeholder URL so the contract is stable.
        return url("/api/v1/invoices/{$id}/pdf-stream");
    }

    public function sendEmail(string $id, array $payload): void
    {
        // TODO: queue Mail::to(...)->queue(new InvoiceMailable($id, $payload)).
        // Logged inline so the FE has feedback during migration.
        $doc = InvoicesDatabaseCollection::where('_id', $id)->first();
        if (!$doc) return;
        $log = (array) ($doc->Invoice_Email_Log ?? []);
        $log[] = array_merge($payload, ['sentAt' => now()->toIso8601String()]);
        $doc->Invoice_Email_Log = $log;
        $doc->save();
    }
}
