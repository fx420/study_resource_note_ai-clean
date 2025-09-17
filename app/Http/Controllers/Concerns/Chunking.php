<?php

namespace App\Http\Controllers\Concerns;

trait Chunking
{

    protected function chunkText(string $text, int $maxChars = 10000, int $overlapChars = 400): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $chunks = [];
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

            $chunks[] = trim($chunk);

            if ($overlapChars > 0) {
                $pos = max(0, $pos - $overlapChars);
            }
        }

        return $chunks;
    }
}
