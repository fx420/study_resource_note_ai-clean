<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ConvertTemplatesToTopics extends Command
{

    protected $signature = 'templates:convert-topics {--backup=1}';

    protected $description = 'Convert legacy per-subject template JSON arrays into {subject:..., topics:{...}} format';

    public function handle()
    {
        $dir = base_path('study_resource_note_ai/study_agent/templates');
        if (! File::exists($dir)) {
            $this->error("Templates dir not found: {$dir}");
            return 1;
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json');
        $count = 0;
        $converted = 0;

        foreach ($files as $file) {
            $count++;
            $raw = File::get($file);
            $data = json_decode($raw, true);

            if (! is_array($data)) {
                $this->error("Skipping invalid json: " . basename($file));
                continue;
            }

            if (isset($data['topics']) && isset($data['subject'])) {
                $this->info("Already new format: " . basename($file));
                continue;
            }

            if ($this->option('backup')) {
                try {
                    File::copy($file, $file . '.bak');
                } catch (\Throwable $e) {
                    $this->error("Failed to create backup for " . basename($file) . ": " . $e->getMessage());
                    continue;
                }
            }

            $subjectName = basename($file, '.json');
            $subjectTitle = Str::title(str_replace(['-', '_'], ' ', $subjectName));

            $doc = [
                'subject' => $subjectTitle,
                'topics' => []
            ];

            foreach ($data as $entry) {
                $topicTitle = null;
                if (!empty($entry['template']['topic'] ?? null)) {
                    $topicTitle = $entry['template']['topic'];
                } elseif (!empty($entry['topic'] ?? null)) {
                    $topicTitle = $entry['topic'];
                } else {
                    $topicTitle = 'misc';
                }

                $topicSlug = Str::slug($topicTitle, '-');

                if (! isset($doc['topics'][$topicSlug])) {
                    $doc['topics'][$topicSlug] = [
                        'title' => $topicTitle,
                        'description' => '',
                        'templates' => [],
                        'conversations' => []
                    ];
                }

                $type = $entry['type'] ?? null;
                if ($type === 'template_saved') {
                    $doc['topics'][$topicSlug]['templates'][] = $entry;
                } else {
                    $doc['topics'][$topicSlug]['conversations'][] = $entry;
                }
            }

            try {
                File::put($file, json_encode($doc, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
                $this->info("Converted: " . basename($file));
                $converted++;
            } catch (\Throwable $e) {
                $this->error("Failed to write converted file for " . basename($file) . ": " . $e->getMessage());
                if ($this->option('backup') && File::exists($file . '.bak')) {
                    File::copy($file . '.bak', $file);
                    $this->warn("Restored backup for " . basename($file));
                }
            }
        }

        $this->info("Processed {$count} files, converted {$converted} files.");
        return 0;
    }
}
