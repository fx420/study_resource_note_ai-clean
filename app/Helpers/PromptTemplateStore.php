<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class PromptTemplateStore
{
    protected string $dir = 'prompt_templates';

    public function load(string $subject): array
    {
        $path = "{$this->dir}/{$subject}.json";

        if (! Storage::exists($path)) {
            return [];
        }

        return json_decode(Storage::get($path), true) ?: [];
    }

    public function save(string $subject, array $template): void
    {
        $path = "{$this->dir}/{$subject}.json";
        $all  = $this->load($subject);

        $exists = false;
        foreach ($all as &$t) {
            if (
                $t['course']  === $template['course'] &&
                $t['topic'] === $template['topic'] &&
                $t['mode'] === $template['mode'] &&
                ($template['mode'] === 'direct'
                 || ($template['mode'] === 'prompt' && $t['prompt'] === $template['prompt']))
            ) {
                $t['metadata'] = $template['metadata'];
                $exists = true;
                break;
            }
        }
        unset($t);

        if (! $exists) {
            $all[] = $template;
        }

        Storage::put($path, json_encode($all, JSON_PRETTY_PRINT));
    }
}
