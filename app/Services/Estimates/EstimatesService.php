<?php

namespace App\Services\Estimates;

use App\Repositories\Estimates\EstimatesRepository;
use Illuminate\Http\Request;

class EstimatesService
{
    public function __construct(private EstimatesRepository $repo)
    {
    }

    public function paginate(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        return $this->repo->newQuery()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function find(string $id): ?object
    {
        return $this->repo->newQuery()->where('_id', $id)->first();
    }

    public function create(array $payload): mixed
    {
        return $this->repo->insert($payload);
    }

    public function update(string $id, array $payload): mixed
    {
        return $this->repo->updateById($id, $payload);
    }

    public function delete(string $id): void
    {
        $this->repo->deleteById($id);
    }

    public function sendCustomerEmail(string $id, array $payload): void
    {
        $this->repo->logEmail($id, $payload);
    }

    public function customerResponse(string $id, array $payload): mixed
    {
        return $this->repo->updateById($id, [
            'Estimate_Customer_Response' => $payload['response'],
            'Estimate_Customer_Response_Notes' => $payload['notes'] ?? null,
            'Estimate_Customer_Response_At' => now()->toIso8601String(),
        ]);
    }
}
