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

    public function import(UploadedFile $file): int
    {
        // TODO: dispatch Maatwebsite\Excel queue import. Stubbed count for now.
        return 0;
    }

    public function exportCsvUrl(): string
    {
        return url('/api/v1/price-book/export-stream');
    }
}
