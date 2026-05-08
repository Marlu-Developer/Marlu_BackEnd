<?php

namespace App\Services\Mentions;

use App\Models\MentionsDatabaseCollection;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class MentionsService
{
    public function received(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        $user = JWTAuth::user();
        return MentionsDatabaseCollection::query()
            ->where('Mention_To_User_Id', $user?->_id)
            ->orderBy('Mention_Created_At', 'desc')
            ->paginate($perPage);
    }

    public function sent(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        $user = JWTAuth::user();
        return MentionsDatabaseCollection::query()
            ->where('Mention_From_User_Id', $user?->_id)
            ->orderBy('Mention_Created_At', 'desc')
            ->paginate($perPage);
    }

    public function markRead(string $id): mixed
    {
        $doc = MentionsDatabaseCollection::where('_id', $id)->first();
        if (!$doc) return null;
        $doc->Mention_Read = true;
        $doc->Mention_Read_At = now()->toIso8601String();
        $doc->save();
        return $doc;
    }
}
