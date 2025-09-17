<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatService
{
    protected string $provider;
    protected string $key;
    protected string $model;

    public function __construct()
    {
        $this->provider = config('services.chat.provider', 'openai');
        $this->key = config('services.chat.key');
        $this->model = config('services.chat.model');
    }

    public function chat(array $messages): string
    {
        if ($this->provider === 'openai') {
            return $this->chatOpenAI($messages);
        }
        return $this->chatOpenRouter($messages);
    }

    protected function chatOpenRouter(array $messages): string
    {
        $base = config('services.openrouter.base', 'https://openrouter.ai/api/v1');
        $resp = Http::withToken($this->key)
            ->post("{$base}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages,
            ]);

        if ($resp->ok()) {
            return $resp->json('choices.0.message.content') ?? '';
        }
        Log::error('OpenRouter error: '.$resp->body());
        throw new \Exception("OpenRouter error: ".$resp->body());
    }

    protected function chatOpenAI(array $messages): string
    {
        $base = config('services.openai.base', 'https://api.openai.com/v1');
        $resp = Http::withToken($this->key)
            ->post("{$base}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages,
            ]);

        if ($resp->ok()) {
            return $resp->json('choices.0.message.content') ?? '';
        }
        Log::error('OpenAI chat error: '.$resp->body());
        throw new \Exception("OpenAI chat error: ".$resp->body());
    }
}
