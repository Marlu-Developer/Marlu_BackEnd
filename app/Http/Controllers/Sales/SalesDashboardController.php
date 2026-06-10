<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\AssignSetterRequest;
use App\Http\Requests\Sales\BulkActionRequest;
use App\Http\Requests\Sales\UpdateSssRequest;
use App\Http\Requests\Sales\UploadAudioRequest;
use App\Http\Requests\Sales\VoiceNotesRequest;
use App\Repositories\Employees\PermitsRepository;
use App\Services\Sales\SalesService;
use App\Support\Exports\SalesDashboardCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesDashboardController extends Controller
{
    public function __construct(private SalesService $sales)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->sales->dashboard($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->sales->exportRows($request);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, SalesDashboardCsv::HEADERS);
            foreach ($rows as $row) {
                fputcsv($out, SalesDashboardCsv::row($row));
            }
            fclose($out);
        }, 'sales-dashboard.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function assignSetter(AssignSetterRequest $request): JsonResponse
    {
        $updated = $this->sales->assignSetter(
            (array) $request->validated('ids'),
            (string) $request->validated('setter_name'),
        );
        return response()->json(['data' => ['updated' => $updated]]);
    }

    /**
     * The current user's permitted-visible Stage-Status-Substatus set, as a
     * comma-separated list of "i-j-k" index codes. Mirrors the legacy
     * sales-view resolution: the employee's own value if set, otherwise the
     * default for their user type/subtype. Empty string means "no restriction".
     */
    public function sssVisible(PermitsRepository $permits): JsonResponse
    {
        $user = JWTAuth::user();

        if ($user && isset($user->Employee_User_SSS_Visible)) {
            $visible = (string) $user->Employee_User_SSS_Visible;
        } else {
            $permit = $user
                ? $permits->findByType(
                    (string) ($user->Employee_User_Type ?? ''),
                    (string) ($user->Employee_User_SubType ?? ''),
                )
                : null;
            $visible = (string) ($permit->Permit_SSS_Visible ?? '');
        }

        return response()->json(['data' => ['sss_visible' => $visible]]);
    }

    public function updateSss(UpdateSssRequest $request): JsonResponse
    {
        $user = JWTAuth::user();
        $updated = $this->sales->updateSss(
            (string) $request->validated('id'),
            (string) $request->validated('stage'),
            (string) $request->validated('status'),
            (string) $request->validated('substatus'),
            (string) ($user->Employee_Full_Name ?? 'User'),
        );
        return response()->json(['data' => ['updated' => $updated]]);
    }

    public function uploadAudio(UploadAudioRequest $request): JsonResponse
    {
        $ok = $this->sales->uploadAudio(
            $request->file('audio'),
            (string) $request->validated('name'),
        );
        return response()->json(['data' => ['ok' => $ok]], $ok ? 200 : 422);
    }

    public function deleteAudio(string $name): JsonResponse
    {
        $ok = $this->sales->deleteAudio($name);
        return response()->json(['data' => ['ok' => $ok]], $ok ? 200 : 422);
    }

    public function updateVoiceNotes(VoiceNotesRequest $request): JsonResponse
    {
        $updated = $this->sales->updateVoiceNotes(
            (string) $request->validated('id'),
            (array) ($request->validated('notes') ?? []),
        );
        return response()->json(['data' => ['updated' => $updated]]);
    }

    public function bulkAction(BulkActionRequest $request): JsonResponse
    {
        $updated = $this->sales->bulkAction(
            $request,
            (array) $request->validated('fields'),
            (array) ($request->validated('ids') ?? []),
            (array) ($request->validated('filter_query') ?? []),
        );
        return response()->json(['data' => ['updated' => $updated]]);
    }
}
