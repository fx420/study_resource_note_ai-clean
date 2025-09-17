<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;

use Smalot\PdfParser\Parser as PdfParser;
use thiagoalessio\TesseractOCR\TesseractOCR;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function extractTextFromFile(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        try {
            if ($extension === 'pdf') {
                $parser = new PdfParser();
                $text = $parser->parseFile($filePath)->getText();
            } elseif (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                $text = (new TesseractOCR($filePath))->run();
            } elseif ($extension === 'docx') {
                $text = $this->extractFromDocx($filePath);
            } elseif ($extension === 'txt') {
                $text = @file_get_contents($filePath) ?: '';
            } else {
                $text = '';
            }
        } catch (\Throwable $e) {
            Log::warning("[extractTextFromFile] failed for {$filePath}: ".$e->getMessage());
            $text = '';
        }

        $clean = $this->safeUtf8((string)$text, 2000);
        return $clean;
    }

    protected function extractFromDocx(string $filePath): string
    {
        $zip = new \ZipArchive;
        if ($zip->open($filePath) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            if ($xml === false || $xml === null) {
                return '';
            }

            libxml_use_internal_errors(true);
            $s = @simplexml_load_string($xml);
            if ($s === false) {
                $text = strip_tags($xml);
            } else {
                $nodes = $s->xpath('//w:t') ?: $s->xpath('//*[local-name()="t"]') ?: [];
                $pieces = [];
                foreach ($nodes as $n) {
                    $pieces[] = (string)$n;
                }
                $text = implode(' ', $pieces);
            }

            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if ($clean === false || $clean === null) {
                $clean = preg_replace('/[^\x00-\x7F]/', '', $text) ?: '';
            }
            return trim(mb_substr($clean, 0, 2000));
        }
        return '';
    }

    protected function safeUtf8(?string $s, int $maxLen = 2000): string
    {
        if ($s === null) return '';

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        if ($clean === false || $clean === null) {
            $enc = mb_detect_encoding($s, ['UTF-8','Windows-1252','ISO-8859-1','ASCII'], true) ?: 'UTF-8';
            $clean = @iconv($enc, 'UTF-8//IGNORE', $s);
        }
        if ($clean === false || $clean === null) {
            $clean = preg_replace('/[^\x00-\x7F]/', '', $s) ?: '';
        }

        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $clean) ?: '';
        $clean = trim($clean);
        if (mb_strlen($clean) > $maxLen) $clean = mb_substr($clean, 0, $maxLen);
        return $clean;
    }

    protected function isMostlyPrintableText(?string $s, int $checkLen = 500): bool
    {
        if ($s === null) return false;
        $s = (string)$s;
        $len = min(strlen($s), $checkLen);
        if ($len === 0) return false;

        $nonPrintable = 0;
        for ($i = 0; $i < $len; $i++) {
            $ord = ord($s[$i]);
            if (($ord >= 32 && $ord <= 126) || $ord === 9 || $ord === 10 || $ord === 13 || $ord > 127) {
            } else {
                $nonPrintable++;
            }
            if (($nonPrintable / ($i + 1)) > 0.4) return false;
        }
        return (($len - $nonPrintable) > 5);
    }
}
