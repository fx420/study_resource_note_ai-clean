<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Smalot\PdfParser\Parser as PdfParser;
use thiagoalessio\TesseractOCR\TesseractOCR;
use App\Http\Controllers\Concerns\Chunking;
use App\Services\DocumentIndexerService;
use Illuminate\Support\Str;
use App\Services\EmbeddingService;
use App\Helpers\VectorStore;
use Illuminate\Support\Facades\Log;
class FileUploadController extends Controller
{

    use Chunking;

    public function upload(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'file' => 'required|mimes:pdf,docx,jpg,jpeg,png,txt|max:20480',
            'subject' => 'nullable|string',
            'topic' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $stored = $file->store('uploads', 'public');
        $publicPath = '/storage/' . $stored;
        $fullPath = storage_path('app/public/' . $stored);

        $raw = '';
        try {
            $raw = $this->extractTextFromFile($fullPath);
        } catch (\Throwable $e) {
            Log::error('[upload] Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $msg = $e->getMessage();
            $trace = collect(explode("\n", $e->getTraceAsString()))->slice(0, 10)->implode("\n");
            return response()->json([
                'error' => true,
                'message' => $msg,
                'trace' => $trace
            ], 500, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        $snippet = '';
        try {
            $clean = $this->safeUtf8($raw, 2000);
            if ($this->isMostlyPrintableText($clean)) {
                $snippet = $clean;
            } else {
                $snippet = '';
            }
        } catch (\Throwable $e) {
            Log::warning('[upload] snippet sanitize failed: ' . $e->getMessage());
            $snippet = '';
        }

        try {
            $fullText = $this->safeUtf8($raw, 200000);
            if (mb_strlen($fullText) > 200) {
                $subjectSlug = Str::slug($request->input('subject', 'general'), '-');
                $topicSlug = Str::slug($request->input('topic', 'general'), '-');
                $docId = $stored;

                if (class_exists(DocumentIndexerService::class)) {
                    try {
                        $indexer = app(DocumentIndexerService::class);
                        $count = $indexer->indexDocument($subjectSlug, $topicSlug, $docId, $fullText, 10000, 400);
                        Log::info("[upload] DocumentIndexerService indexed {$count} chunks for doc {$docId}");
                    } catch (\Throwable $e) {
                        Log::warning("[upload] DocumentIndexerService failed: " . $e->getMessage());
                    }
                } else {
                    try {
                        $chunks = method_exists($this, 'chunkText') ? $this->chunkText($fullText, 10000, 400) : (function($t){

                            $out = [];
                            $pos = 0; $len = mb_strlen($t);
                            while ($pos < $len) {
                                $out[] = mb_substr($t, $pos, 10000);
                                $pos += 9600;
                            }
                            return $out;
                        })($fullText);

                        $embeddingSvc = app(EmbeddingService::class);
                        $vectorStore = app(VectorStore::class);

                        foreach ($chunks as $idx => $chunkText) {
                            try {
                                $vec = $embeddingSvc->embedText($chunkText);
                            } catch (\Throwable $e) {
                                Log::warning("[upload] embedding failed for {$docId}:chunk:{$idx} — " . $e->getMessage());
                                continue;
                            }
                            if (is_array($vec) && count($vec) > 0) {
                                $snippetPreview = trim(mb_substr($chunkText, 0, 800));
                                $vectorStore->add($subjectSlug, $topicSlug, $vec, 'document', $docId . ':chunk:' . $idx, $snippetPreview);
                            }
                        }
                        Log::info("[upload] Inline index stored " . count($chunks) . " chunks for doc {$docId}");
                    } catch (\Throwable $e) {
                        Log::warning("[upload] inline indexing failed: " . $e->getMessage());
                    }

                    unset($fullText, $raw);
                    gc_collect_cycles();
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[upload] indexing step failed: " . $e->getMessage());
        }

        return response()->json([
            'path' => $publicPath,
            'original_name' => $file->getClientOriginalName(),
            'snippet' => $snippet,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

}
