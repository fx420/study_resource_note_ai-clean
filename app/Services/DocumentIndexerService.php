<?php

namespace App\Services;

use App\Helpers\VectorStore;
use Illuminate\Support\Facades\Log;

class DocumentIndexerService
{
    protected EmbeddingService $embeddingSvc;
    protected VectorStore $vectorStore;

    public function __construct(EmbeddingService $embeddingSvc, VectorStore $vectorStore)
    {
        $this->embeddingSvc = $embeddingSvc;
        $this->vectorStore = $vectorStore;
    }

    public function indexDocumentFromText(string $subjectSlug, string $topicSlug, string $docId, string $fullText, int $maxChars = 5000, int $overlapChars = 200): int
    {
        $fullText = trim($fullText);
        if ($fullText === '') {
            return 0;
        }

        $storedCount = 0;
        $idx = 0;

        foreach ($this->chunkGenerator($fullText, $maxChars, $overlapChars) as $chunkText) {
            $snippetPreview = mb_substr(trim($chunkText), 0, 800);

            try {
                $vec = $this->embeddingSvc->embedText($chunkText);
            } catch (\Throwable $e) {
                Log::warning("[DocumentIndexerService] embedding failed for doc {$docId} chunk {$idx}: " . $e->getMessage());
                unset($vec, $chunkText);
                gc_collect_cycles();
                $idx++;
                continue;
            }

            if (!is_array($vec) || count($vec) === 0) {
                Log::warning("[DocumentIndexerService] empty vector for doc {$docId} chunk {$idx}");
                unset($vec, $chunkText);
                gc_collect_cycles();
                $idx++;
                continue;
            }

            try {
                $vectorId = $docId . ':chunk:' . $idx;
                $this->vectorStore->add($subjectSlug, $topicSlug, $vec, 'document', $vectorId, $snippetPreview);
                $storedCount++;
            } catch (\Throwable $e) {
                Log::warning("[DocumentIndexerService] failed to store vector for doc {$docId} chunk {$idx}: " . $e->getMessage());
            }

            unset($vec, $chunkText);
            gc_collect_cycles();
            $idx++;
        }

        Log::info("[DocumentIndexerService] indexed {$storedCount} chunks for doc {$docId}");
        return $storedCount;
    }

    protected function chunkGenerator(string $text, int $maxChars = 5000, int $overlapChars = 200): \Generator
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $pos = 0;
        $len = mb_strlen($text);

        while ($pos < $len) {
            $remaining = mb_substr($text, $pos);

            $snippet = mb_substr($remaining, 0, $maxChars + 200);
            $cutPos = mb_strrpos($snippet, '.');

            if ($cutPos === false || $cutPos < (int)($maxChars * 0.5)) {
                $chunk = mb_substr($remaining, 0, $maxChars);
                $pos += mb_strlen($chunk);
            } else {
                $chunk = mb_substr($remaining, 0, $cutPos + 1);
                $pos += mb_strlen($chunk);
            }

            yield trim($chunk);

            if ($overlapChars > 0) {
                $pos = max(0, $pos - $overlapChars);
            }
        }
    }
}
