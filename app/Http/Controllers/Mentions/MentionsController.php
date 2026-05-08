<?php

namespace App\Http\Controllers\Mentions;

use App\Http\Controllers\Controller;
use App\Services\Mentions\MentionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentionsController extends Controller
{
    public function __construct(private MentionsService $mentions)
    {
    }

    public function received(Request $request): JsonResponse
    {
        return response()->json($this->mentions->received($request));
    }

    public function sent(Request $request): JsonResponse
    {
        return response()->json($this->mentions->sent($request));
    }

    public function markRead(string $id): JsonResponse
    {
        return response()->json(['data' => $this->mentions->markRead($id)]);
    }
}
