<?php

namespace App\Http\Resources\Employees;

use Illuminate\Http\Resources\Json\JsonResource;

class PermitsDashboardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'userPermits' => $this->resource['userPermits'],
            'employees' => $this->resource['employees'],
        ];
    }
}
