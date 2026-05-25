<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function ping(Request $request)
    {
        $user = $request->user();
        $user->forceFill(['last_seen_at' => now()])->save();

        return response()->json(['ok' => true, 'last_seen_at' => $user->last_seen_at]);
    }
}