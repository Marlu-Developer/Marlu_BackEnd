<?php

namespace App\Repositories\Employees;

use App\Models\UserPermitsByTypeDatabaseCollection;
use Illuminate\Database\Eloquent\Collection;

class PermitsRepository
{
    public function all(): Collection
    {
        return UserPermitsByTypeDatabaseCollection::orderBy('Employee_User_Type')
            ->orderBy('Employee_User_SubType')
            ->get();
    }

    public function findByType(string $userType, string $userSubType): ?UserPermitsByTypeDatabaseCollection
    {
        return UserPermitsByTypeDatabaseCollection::where('Employee_User_Type', $userType)
            ->where('Employee_User_SubType', $userSubType)
            ->first();
    }

    public function updateField(string $userType, string $userSubType, string $key, mixed $value): bool
    {
        return UserPermitsByTypeDatabaseCollection::where('Employee_User_Type', $userType)
            ->where('Employee_User_SubType', $userSubType)
            ->update([$key => $value]) > 0;
    }
}
