<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InteractionEventController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->only('storeBatch');
    }

    public function storeBatch(Request $request)
    {
        $payload = $request->validate([
            'events' => 'required|array|max:2000',
            'events.*.event_type' => 'required|string|max:100',
            'events.*.metadata' => 'nullable|array',
            'events.*.created_at' => 'nullable|date',
        ]);

        $rows = [];
        $now = now();
        $uid = Auth::id();

        foreach ($payload['events'] as $ev) {
            $createdAt = $now;
            if (!empty($ev['created_at'])) {
                try {

                    $createdAt = Carbon::parse($ev['created_at'])->toDateTimeString();
                } catch (\Throwable $ex) {
                    $createdAt = $now;
                }
            }

            $rows[] = [
                'user_id' => $uid,
                'session_id' => $ev['metadata']['session_id'] ?? null,
                'message_id' => $ev['metadata']['message_id'] ?? null,
                'event_type' => $ev['event_type'] ?? 'unknown',
                'metadata' => is_null($ev['metadata']) ? null : json_encode($ev['metadata'], JSON_UNESCAPED_UNICODE),
                'created_at' => $ev['created_at'] ?? $now,
            ];
        }

        if (empty($rows)) {
            return response()->json(['ok' => true, 'inserted' => 0], 201);
        }

        try {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('interaction_events')->insert($chunk);
            }
            return response()->json(['ok' => true, 'inserted' => count($rows)], 201);
        } catch (\Throwable $e) {
            Log::error('InteractionEventController::storeBatch failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return response()->json(['ok' => false, 'error' => 'Server error saving events'], 500);
        }
    }

}
