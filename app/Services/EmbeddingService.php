<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class EmbeddingService
{
    protected string $provider;
    protected ?string $apiKey;
    protected string $model;
    protected string $openaiBase;
    protected string $openrouterBase;

    public function __construct()
    {
        $this->provider = config('services.embed.provider', env('EMBEDDING_PROVIDER', 'openai'));
        $this->apiKey = config('services.embed.key', env('EMBEDDING_API_KEY'));
        $this->model = config('services.embed.model', env('EMBEDDING_MODEL', 'text-embedding-3-small'));
        $this->openaiBase = config('services.openai.base', 'https://api.openai.com/v1');
        $this->openrouterBase = config('services.openrouter.base', 'https://openrouter.ai/api/v1');

        if (empty($this->apiKey)) {
            throw new \RuntimeException('EmbeddingService: API key not configured (EMBEDDING_API_KEY).');
        }
    }

    public function embedText(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('embedText: $text cannot be empty');
        }

        if ($this->provider === 'openai') {
            return $this->embedOpenAI($text);
        }

        return $this->embedOpenRouter($text);
    }

    protected function embedOpenAI(string $text): ?array
    {
        $url = rtrim($this->openaiBase, '/') . '/embeddings';

        $resp = Http::withToken($this->apiKey)
            ->accept('application/json')
            ->post($url, [
                'model' => $this->model,
                'input' => $text,
            ]);

        if (! $resp->ok()) {
            $body = $resp->body();
            Log::error("OpenAI embedding error: HTTP {$resp->status()} — {$body}");
            throw new \Exception("OpenAI embedding error: HTTP {$resp->status()} — " . substr($body, 0, 1000));
        }

        $json = $resp->json();
        if (! isset($json['data'][0]['embedding'])) {
            Log::error('OpenAI embedding response missing data.');
            throw new \Exception('OpenAI embedding response missing data.');
        }

        return array_map(function ($v) {
            return (float) $v;
        }, $json['data'][0]['embedding']);
    }

    protected function embedOpenRouter(string $text): ?array
    {
        $url = rtrim($this->openrouterBase, '/') . '/embeddings';

        $resp = Http::withToken($this->apiKey)
            ->accept('application/json')
            ->post($url, [
                'model' => $this->model,
                'input' => $text,
            ]);

        if (! $resp->ok()) {
            $body = $resp->body();
            Log::error("OpenRouter embedding error: HTTP {$resp->status()} — {$body}");
            throw new \Exception("OpenRouter embedding error: HTTP {$resp->status()} — " . substr($body, 0, 1000));
        }

        $json = $resp->json();

        if (isset($json['data'][0]['embedding'])) {
            return array_map(fn($v) => (float)$v, $json['data'][0]['embedding']);
        }

        if (isset($json['embedding']) && is_array($json['embedding'])) {
            return array_map(fn($v) => (float)$v, $json['embedding']);
        }

        Log::error('OpenRouter embedding response missing data: ' . json_encode($json));
        throw new \Exception('OpenRouter embedding response missing embedding vector.');
    }
}
