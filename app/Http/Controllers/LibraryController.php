<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Helpers\VectorStore;

class LibraryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $userId = Auth::id();

        $sessions = ChatSession::where('user_id', $userId)
            ->with(['messages' => function ($q) {
                $q->where('sender', 'system')->orderBy('created_at', 'desc');
            }])
            ->latest('created_at')
            ->get();

        $notes = $sessions->map(function ($s) {
            $aiMessage = optional($s->messages->first())->message ?? '';

            return [
                'id' => $s->id,
                'title' => $s->title ?: ('Chat ' . $s->id),
                'content' => $aiMessage,
                'created_at' => $s->created_at->toDateTimeString(),
            ];
        })->values();

        return view('library.library', ['notes' => $notes]);
    }

    public function destroy(Request $request, $id)
    {
        $userId = Auth::id();

        $session = ChatSession::where('id', $id)
            ->where('user_id', $userId)
            ->with('messages')
            ->firstOrFail();

        try {
            DB::transaction(function () use ($session) {
                $vectorStore = new VectorStore();

                foreach ($session->messages as $msg) {
                    if (!empty($msg->attachment_path)) {
                        Storage::disk('public')->delete($msg->attachment_path);
                    }

                    if (!empty($msg->file_path)) {
                        Storage::disk('public')->delete($msg->file_path);
                    }

                    try {
                        $vectorStore->removeVectorsForMessage((int)$msg->id);
                    } catch (\Throwable $e) {
                        Log::warning("Vector removal failed for message {$msg->id}: " . $e->getMessage());
                    }
                }

                try {
                    $vectorStore->removeVectorsForSession((int)$session->id);
                } catch (\Throwable $e) {
                    Log::warning("Vector removal failed for session {$session->id}: " . $e->getMessage());
                }

                $session->messages()->delete();

                $session->delete();
            }, 5);
        } catch (\Throwable $e) {
            Log::error("Failed deleting chat session {$id} for user {$userId}: " . $e->getMessage());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'Failed to delete chat session.'], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete chat session.');
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => 'Chat session and related data deleted.'], 200);
        }

        return redirect()->route('library.index')->with('success', 'Chat session deleted.');
    }
}
