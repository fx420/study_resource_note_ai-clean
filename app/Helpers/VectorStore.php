<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Support\VectorMath;

class VectorStore
{
    protected string $table = 'embeddings';

    public function add(
        string $subject,
        ?string $topic,
        array $vector,
        string $entryType = 'conversation',
        ?string $entryRef = null,
        ?string $snippet = null
    ): int {
        $payload = [
            'subject' => $subject,
            'topic' => $topic,
            'entry_type' => $entryType,
            'entry_ref' => $entryRef,
            'vector' => json_encode(array_values(array_map('floatval', $vector)), JSON_UNESCAPED_UNICODE),
            'snippet' => $snippet,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return (int) DB::table($this->table)->insertGetId($payload);
    }

    public function deleteById(int $id): bool
    {
        return (bool) DB::table($this->table)->where('id', $id)->delete();
    }

    public function search(
        string $subject,
        array $queryVector,
        int $limit = 5,
        ?string $topic = null,
        int $candidateLimit = 1000
    ): array {
        try {
            $query = DB::table($this->table)->where('subject', $subject);

            if ($topic !== null) {
                $query->where(function ($q) use ($topic) {
                    $q->where('topic', $topic)
                      ->orWhereNull('topic');
                });
            }

            $rows = $query->orderBy('created_at', 'desc')->limit($candidateLimit)->get();

            if ($rows->isEmpty()) {
                return [];
            }

            $results = [];

            foreach ($rows as $row) {
                $rowArr = (array) $row;
                $stored = $rowArr['vector'] ?? null;

                $rowVec = null;
                if (is_string($stored)) {
                    $rowVec = json_decode($stored, true);
                } elseif (is_array($stored)) {
                    $rowVec = $stored;
                }

                if (!is_array($rowVec) || count($rowVec) === 0) {
                    continue;
                }

                if (count($rowVec) !== count($queryVector)) {
                    continue;
                }

                $score = VectorMath::cosineSimilarity(
                    array_map('floatval', $queryVector),
                    array_map('floatval', $rowVec)
                );

                $results[] = [
                    'row' => $rowArr,
                    'score' => $score,
                ];
            }

            usort($results, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            return array_slice($results, 0, max(0, (int) $limit));
        } catch (\Throwable $e) {
            Log::error('VectorStore::search failed: ' . $e->getMessage());
            return [];
        }
    }

    public function countForSubject(string $subject): int
    {
        return (int) DB::table($this->table)->where('subject', $subject)->count();
    }

    public function fetchById(int $id): ?array
    {
        $r = DB::table($this->table)->where('id', $id)->first();
        return $r ? (array) $r : null;
    }

    public function deleteByEntryRef(string $entryRef): int
    {
        try {
            return (int) DB::table($this->table)->where('entry_ref', $entryRef)->delete();
        } catch (\Throwable $e) {
            Log::error("VectorStore::deleteByEntryRef failed (ref={$entryRef}): " . $e->getMessage());
            return 0;
        }
    }

    public function deleteByEntryTypeAndRef(string $entryType, string $entryRef): int
    {
        try {
            return (int) DB::table($this->table)
                ->where('entry_type', $entryType)
                ->where('entry_ref', $entryRef)
                ->delete();
        } catch (\Throwable $e) {
            Log::error("VectorStore::deleteByEntryTypeAndRef failed (type={$entryType}, ref={$entryRef}): " . $e->getMessage());
            return 0;
        }
    }

    public function removeVectorsForMessage(int $messageId): int
    {
        $deleted = 0;
        try {
            $deleted += DB::table($this->table)
                ->where('entry_type', 'message')
                ->where('entry_ref', (string) $messageId)
                ->delete();

            $deleted += DB::table($this->table)
                ->where('entry_ref', (string) $messageId)
                ->delete();
        } catch (\Throwable $e) {
            Log::error("VectorStore::removeVectorsForMessage failed (msg={$messageId}): " . $e->getMessage());
        }

        return (int) $deleted;
    }

    public function removeVectorsForSession(int $sessionId): int
    {
        $totalDeleted = 0;

        try {

            $totalDeleted += DB::table($this->table)
                ->where('entry_type', 'session')
                ->where('entry_ref', (string)$sessionId)
                ->delete();

            $possibleCols = ['session_id', 'chat_session_id', 'conversation_id', 'chat_id'];
            $foundCol = null;
            foreach ($possibleCols as $col) {
                if (Schema::hasColumn('chat_messages', $col)) {
                    $foundCol = $col;
                    break;
                }
            }

            if ($foundCol !== null) {
                $messageIds = DB::table('chat_messages')
                    ->where($foundCol, $sessionId)
                    ->pluck('id')
                    ->toArray();

                if (!empty($messageIds)) {
                    $totalDeleted += DB::table($this->table)
                        ->whereIn('entry_ref', $messageIds)
                        ->delete();
                }
            } else {
                $messageIds = DB::table('chat_messages')->where('session_id', $sessionId)->pluck('id')->toArray();
                if (!empty($messageIds)) {
                    $totalDeleted += DB::table($this->table)
                        ->whereIn('entry_ref', $messageIds)
                        ->delete();
                }
            }

            $totalDeleted += DB::table($this->table)
                ->where('entry_ref', (string) $sessionId)
                ->delete();
        } catch (\Throwable $e) {
            Log::error("VectorStore::removeVectorsForSession failed (session={$sessionId}): " . $e->getMessage());
        }

        return (int) $totalDeleted;
    }
}
