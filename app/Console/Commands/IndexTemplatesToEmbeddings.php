<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class IndexTemplatesToEmbeddings extends Command
{
    protected $signature = 'embeddings:index-templates
                            {--subject= : Only index a single subject (human readable, e.g. "Software Testing")}
                            {--batch=20 : How many embeddings to process before sleeping briefly}
                            {--dry-run : Do everything except write to DB}
                            {--force : Re-index even if entry_ref already exists (will create duplicates if true)}';

    protected $description = 'Index template JSON files into the embeddings DB table (uses EmbeddingService + VectorStore).';

    public function handle()
    {
        $templatesDir = base_path('study_resource_note_ai/study_agent/templates');

        if (! File::exists($templatesDir)) {
            $this->error("Templates directory not found: {$templatesDir}");
            return 1;
        }

        $subjectFilter = $this->option('subject') ? Str::slug($this->option('subject'), '-') : null;
        $batch = (int) $this->option('batch') ?: 20;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info("Indexing templates from: {$templatesDir}");
        if ($subjectFilter) $this->info("Subject filter: {$subjectFilter}");
        $this->info("Batch size: {$batch}  Dry-run: " . ($dryRun ? 'yes' : 'no') . "  Force: " . ($force ? 'yes' : 'no'));

        $files = glob($templatesDir . DIRECTORY_SEPARATOR . '*.json');
        if (empty($files)) {
            $this->info("No JSON files found in templates directory.");
            return 0;
        }

        $embeddingSvc = app(\App\Services\EmbeddingService::class);
        $vectorStore = app(\App\Helpers\VectorStore::class);

        $totalProcessed = 0;
        $totalAdded = 0;
        $totalSkipped = 0;
        $errors = [];

        foreach ($files as $file) {
            $basename = basename($file);
            $subjectSlug = pathinfo($basename, PATHINFO_FILENAME);

            if ($subjectFilter && $subjectSlug !== $subjectFilter) {
                continue;
            }

            $this->line("Processing subject file: {$basename}");
            try {
                $raw = File::get($file);
                $doc = json_decode($raw, true);
                if (! is_array($doc)) {
                    $this->warn("  -> invalid JSON, skipping {$basename}");
                    continue;
                }

                $subjectName = $doc['subject'] ?? str_replace('-', ' ', $subjectSlug);
                $topics = $doc['topics'] ?? [];

                foreach ($topics as $topicSlug => $tdata) {
                    $topicTitle = $tdata['title'] ?? $topicSlug;

                    foreach (($tdata['templates'] ?? []) as $tplIndex => $tpl) {
                        $text = trim(implode(' | ', array_filter([
                            $topicTitle,
                            $tpl['course'] ?? ($doc['course'] ?? ''),
                            $tpl['prompt'] ?? '',
                            json_encode($tpl['metadata'] ?? []),
                        ])));

                        if ($text === '') {
                            $totalSkipped++;
                            continue;
                        }

                        $entryRef = 'template:' . md5($text);
                        $entryType = 'template';
                        $snippet = mb_substr($tpl['prompt'] ?? ($tpl['course'] ?? ''), 0, 1000);

                        $shouldAdd = $this->shouldAddEntry($subjectSlug, $entryRef, $force);
                        if (! $shouldAdd) {
                            $totalSkipped++;
                            $this->line("  - skip existing template: {$topicSlug} (#{$tplIndex})");
                        } else {
                            $totalProcessed++;
                            try {
                                $vector = $embeddingSvc->embedText($text);
                                if (is_array($vector) && count($vector) > 0) {
                                    $this->maybeInsertVector($vectorStore, $subjectSlug, $topicSlug, $vector, $entryType, $entryRef, $snippet, $dryRun);
                                    $totalAdded++;
                                    $this->line("  + indexed template: {$topicSlug} (#{$tplIndex})");
                                } else {
                                    $this->warn("  ! embedding empty for template: {$topicSlug} (#{$tplIndex})");
                                    $errors[] = "Empty vector for template {$file} / {$topicSlug} / index {$tplIndex}";
                                }
                            } catch (\Throwable $e) {
                                $this->warn("  ! error embedding template: ".$e->getMessage());
                                $errors[] = $e->getMessage();
                            }
                        }

                        if ($totalProcessed % $batch === 0) {
                            usleep(200000);
                        }
                    }

                    foreach (($tdata['conversations'] ?? []) as $convIndex => $conv) {
                        $text = trim(implode(' | ', array_filter([
                            $topicTitle,
                            $conv['course'] ?? '',
                            $conv['user_prompt'] ?? '',
                            $conv['ai_response'] ?? '',
                        ])));

                        if ($text === '') {
                            $totalSkipped++;
                            continue;
                        }

                        $entryRef = 'conv:' . md5(($conv['created_at'] ?? '') . '|' . ($conv['user_prompt'] ?? '') . '|' . ($conv['ai_response'] ?? ''));
                        $entryType = 'conversation';
                        $snippet = mb_substr(($conv['ai_response'] ?? $conv['user_prompt'] ?? ''), 0, 1000);

                        $shouldAdd = $this->shouldAddEntry($subjectSlug, $entryRef, $force);
                        if (! $shouldAdd) {
                            $totalSkipped++;
                            $this->line("  - skip existing conversation: {$topicSlug} (#{$convIndex})");
                        } else {
                            $totalProcessed++;
                            try {
                                $vector = $embeddingSvc->embedText($text);
                                if (is_array($vector) && count($vector) > 0) {
                                    $this->maybeInsertVector($vectorStore, $subjectSlug, $topicSlug, $vector, $entryType, $entryRef, $snippet, $dryRun);
                                    $totalAdded++;
                                    $this->line("  + indexed conversation: {$topicSlug} (#{$convIndex})");
                                } else {
                                    $this->warn("  ! embedding empty for conversation: {$topicSlug} (#{$convIndex})");
                                    $errors[] = "Empty vector for conversation {$file} / {$topicSlug} / index {$convIndex}";
                                }
                            } catch (\Throwable $e) {
                                $this->warn("  ! error embedding conversation: ".$e->getMessage());
                                $errors[] = $e->getMessage();
                            }
                        }

                        if ($totalProcessed % $batch === 0) {
                            usleep(200000);
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->error("Failed to process file {$basename}: " . $e->getMessage());
                Log::error("IndexTemplatesToEmbeddings error processing {$file}: ".$e->getMessage());
                $errors[] = $e->getMessage();
            }
        }

        $this->info("Finished. added={$totalAdded} skipped={$totalSkipped} processed={$totalProcessed}");
        if (! empty($errors)) {
            $this->warn("There were some errors (see log): " . count($errors));
            foreach (array_slice($errors, 0, 10) as $err) {
                $this->line("  - " . (string) $err);
            }
        }

        return 0;
    }

    protected function shouldAddEntry(string $subjectSlug, string $entryRef, bool $force = false): bool
    {
        if ($force) return true;
        $exists = DB::table('embeddings')->where('subject', $subjectSlug)->where('entry_ref', $entryRef)->exists();
        return ! $exists;
    }

    protected function maybeInsertVector($vectorStore, string $subjectSlug, string $topicSlug, array $vector, string $entryType, string $entryRef, ?string $snippet, bool $dryRun)
    {
        if ($dryRun) {
            $this->line("    (dry-run) would insert entry_ref={$entryRef} subject={$subjectSlug}");
            return;
        }

        $vectorStore->add($subjectSlug, $topicSlug, $vector, $entryType, $entryRef, $snippet);
    }
}
