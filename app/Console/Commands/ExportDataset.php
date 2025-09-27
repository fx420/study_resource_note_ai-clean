<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class ExportDataset extends Command
{
    protected $signature = 'dataset:export {--format=csv} {--path=exports}';
    protected $description = 'Export dataset (sessions, messages, questions, embeddings)';

    public function handle()
    {
        $format = $this->option('format');
        $dir = trim($this->option('path') ?: 'exports', '/');
        $filenameBase = 'dataset_' . date('Ymd_His');
        $storageBase = storage_path('app');

        $outputDir = $storageBase . DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
                $this->error("Failed to create directory: {$outputDir}");
                return 1;
            }
        }

        $possibleMsgCols = ['session_id', 'chat_session_id', 'conversation_id', 'chat_id'];
        $msgFk = null;
        foreach ($possibleMsgCols as $c) {
            if (Schema::hasColumn('chat_messages', $c)) {
                $msgFk = $c;
                break;
            }
        }

        $getMessagesForSession = function($sessionId) use ($msgFk) {
            if ($msgFk) {
                return DB::table('chat_messages')->where($msgFk, $sessionId)->orderBy('created_at')->get();
            } else {
                return collect([]);
            }
        };

        $getMessageIdsForSession = function($sessionId) use ($msgFk) {
            if ($msgFk) {
                return DB::table('chat_messages')->where($msgFk, $sessionId)->pluck('id')->toArray();
            } else {
                return [];
            }
        };

        if ($format === 'jsonl') {
            $path = $dir . '/' . $filenameBase . '.jsonl';
            $fullPath = $storageBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

            $stream = fopen($fullPath, 'w');
            if ($stream === false) {
                $this->error("Failed to open file for writing: {$fullPath}");
                return 1;
            }

            DB::table('chat_sessions')->orderBy('id')->chunk(200, function ($sessions) use ($stream, $getMessagesForSession, $getMessageIdsForSession) {
                foreach ($sessions as $s) {
                    $messages = $getMessagesForSession($s->id);
                    $questions = DB::table('questions')->where('session_id', $s->id)->get();

                    $messageIds = $messages->pluck('id')->toArray();

                    $embeddingsQuery = DB::table('embeddings')->where('entry_ref', (string)$s->id);
                    if (!empty($messageIds)) {
                        $embeddingsQuery = $embeddingsQuery->orWhereIn('entry_ref', $messageIds);
                    }
                    $embeddings = $embeddingsQuery->get();

                    $row = [
                        'session' => (array) $s,
                        'messages' => $messages->map(function($m){ return (array)$m; })->toArray(),
                        'questions' => $questions->map(function($q){ return (array)$q; })->toArray(),
                        'embeddings' => $embeddings->map(function($e){ return [
                            'id'=>$e->id,
                            'entry_type'=>$e->entry_type,
                            'entry_ref'=>$e->entry_ref,
                            'snippet'=>$e->snippet,
                            'vector'=>is_string($e->vector) ? json_decode($e->vector, true) : $e->vector
                        ]; })->toArray(),
                    ];

                    fwrite($stream, json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL);
                }
            });

            fclose($stream);
            $this->info("JSONL export written to storage/app/{$path}");
            return 0;
        }

        $path = $dir . '/' . $filenameBase . '.csv';
        $fullPath = $storageBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

        $stream = fopen($fullPath, 'w');
        if ($stream === false) {
            $this->error("Failed to open file for writing: {$fullPath}");
            return 1;
        }

        $headers = ['session_id','user_id','session_title','session_created_at','message_id','message_sender','message_text','message_created_at','question_id','question_text','embedding_id','embedding_entry_type','embedding_entry_ref','embedding_snippet'];
        fputcsv($stream, $headers);

        DB::table('chat_sessions')->orderBy('id')->chunk(200, function($sessions) use ($stream, $getMessagesForSession) {
            foreach ($sessions as $s) {
                $messages = $getMessagesForSession($s->id);
                $questions = DB::table('questions')->where('session_id',$s->id)->get();

                $messageIds = $messages->pluck('id')->toArray();
                $embeddingsQuery = DB::table('embeddings')->where('entry_ref', (string)$s->id);
                if (!empty($messageIds)) {
                    $embeddingsQuery = $embeddingsQuery->orWhereIn('entry_ref', $messageIds);
                }
                $embeddings = $embeddingsQuery->get();

                if ($messages->isEmpty()) {
                    fputcsv($stream, [$s->id,$s->user_id,$s->title,$s->created_at, '','','','','','','','','','']);
                } else {
                    foreach ($messages as $m) {
                        $q = $questions->first() ? $questions->first()->id : '';
                        $qn = $questions->first() ? $questions->first()->text : '';
                        $emb = $embeddings->where('entry_ref', (string)$m->id)->first() ?? $embeddings->first();

                        fputcsv($stream, [
                            $s->id,
                            $s->user_id,
                            $s->title,
                            $s->created_at,
                            $m->id,
                            $m->sender ?? '',
                            $m->message ?? '',
                            $m->created_at ?? '',
                            $q,
                            $qn,
                            $emb ? $emb->id : '',
                            $emb ? $emb->entry_type : '',
                            $emb ? $emb->entry_ref : '',
                            $emb ? mb_substr($emb->snippet ?: '',0,200) : '',
                        ]);
                    }
                }
            }
        });

        fclose($stream);
        $this->info("CSV export written to storage/app/{$path}");
        return 0;
    }
}
