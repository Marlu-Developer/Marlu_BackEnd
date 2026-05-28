<?php

namespace App\Http\Resources\SalesEdit;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerRecordResource extends JsonResource
{
    public function toArray($request): array
    {
        $job = (array) $this->resource['job'];

        return [
            'job' => $job,
            'related' => $this->resource['related'] ?? [],
            'company' => $this->resource['company'] ?? null,
            'lock' => $this->resource['lock'] ?? null,
            'permissions' => $this->resource['permissions'] ?? [],
        ];
    }
}
