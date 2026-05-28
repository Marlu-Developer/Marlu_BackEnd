<?php

namespace App\Http\Controllers\SalesEdit;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesEdit\LockPingRequest;
use App\Http\Requests\SalesEdit\LockReleaseRequest;
use App\Http\Requests\SalesEdit\UpdateCustomerProfileRequest;
use App\Http\Resources\SalesEdit\CustomerRecordResource;
use App\Services\SalesEdit\SalesEditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesEditController extends Controller
{
    public function __construct(private SalesEditService $service)
    {
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->guard(function () use ($id) {
            [$userId, $userName] = $this->actorIdentity();
            $payload = $this->service->open($id, $userId, $userName);
            return response()->json(new CustomerRecordResource($payload));
        });
    }

    public function updateProfile(UpdateCustomerProfileRequest $request, string $id): JsonResponse
    {
        return $this->guard(function () use ($request, $id) {
            [$userId, $userName] = $this->actorIdentity();
            $validated = $request->validated();
            $token = (string) ($validated['lock_token'] ?? '');
            unset($validated['lock_token']);

            $result = $this->service->updateProfile($id, $validated, $userId, $userName, $token);
            return response()->json(['data' => $result]);
        });
    }

    public function lockPing(LockPingRequest $request, string $id): JsonResponse
    {
        [$userId] = $this->actorIdentity();
        $ok = $this->service->pingLock($id, $userId, (string) $request->validated('token'));
        return response()->json(['ok' => $ok, 'lost' => !$ok]);
    }

    public function lockRelease(LockReleaseRequest $request, string $id): JsonResponse
    {
        [$userId] = $this->actorIdentity();
        $this->service->releaseLock($id, $userId, (string) ($request->validated('token') ?? ''));
        return response()->json(['ok' => true]);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function actorIdentity(): array
    {
        $user = $this->authUser();
        return [
            (int) ($user->Employee_ID ?? 0),
            (string) ($user->Employee_Full_Name ?? 'User'),
        ];
    }

    /**
     * Convert \DomainException("…", $status) into a JSON error so the FE gets a typed code+message.
     */
    private function guard(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (\DomainException $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status >= 600) {
                $status = 400;
            }
            return response()->json([
                'message' => $e->getMessage(),
                'code' => match ($status) {
                    404 => 'not_found',
                    409 => 'conflict',
                    422 => 'unprocessable',
                    423 => 'locked',
                    default => 'domain_error',
                },
            ], $status);
        }
    }
}
