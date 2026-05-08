<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\CreateEmployeeRequest;
use App\Http\Requests\Employees\ResetEmployeeToTypeDefaultRequest;
use App\Http\Requests\Employees\UpdateEmployeePermissionRequest;
use App\Http\Requests\Employees\UpdatePermitPermissionRequest;
use App\Http\Requests\Employees\UploadPdfRequest;
use App\Http\Resources\Employees\PermitsDashboardResource;
use App\Services\Employees\EmployeesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeesController extends Controller
{
    public function __construct(private EmployeesService $employees)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $data = $this->employees->list(is_string($search) ? $search : null);
        return response()->json(['data' => $data]);
    }

    public function permitsDashboard(): JsonResponse
    {
        $data = $this->employees->permitsDashboard();
        return response()->json(['data' => (new PermitsDashboardResource($data))->resolve()]);
    }

    public function updateEmployeePermission(UpdateEmployeePermissionRequest $request): JsonResponse
    {
        $this->employees->updateEmployeePermission(
            (int) $request->validated('employee_id'),
            (string) $request->validated('permission_key'),
            $request->validated('permission_value'),
        );
        return response()->json(['data' => ['ok' => true]]);
    }

    public function updatePermitPermission(UpdatePermitPermissionRequest $request): JsonResponse
    {
        $this->employees->updatePermitPermission(
            (string) $request->validated('permit_user_type'),
            (string) $request->validated('permit_user_subtype'),
            (string) $request->validated('permission_key'),
            $request->validated('permission_value'),
        );
        return response()->json(['data' => ['ok' => true]]);
    }

    public function resetEmployeeToTypeDefault(ResetEmployeeToTypeDefaultRequest $request): JsonResponse
    {
        try {
            $updated = $this->employees->resetEmployeeToTypeDefault(
                (int) $request->validated('employee_id'),
                (array) $request->validated('mappings'),
            );
            return response()->json(['data' => ['updated' => $updated]]);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'domain_error',
            ], $e->getCode() ?: 400);
        }
    }

    public function createEmployee(CreateEmployeeRequest $request): JsonResponse
    {
        try {
            $this->employees->createEmployee($request->validated());
            return response()->json(['data' => ['ok' => true]], 201);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'domain_error',
            ], $e->getCode() ?: 400);
        }
    }

    public function uploadPdf(UploadPdfRequest $request): JsonResponse
    {
        $data = $this->employees->uploadPdf(
            (string) $request->validated('name'),
            (string) $request->validated('content'),
        );
        return response()->json(['data' => $data]);
    }
}
