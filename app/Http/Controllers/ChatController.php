<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\EmbeddingService;
use App\Helpers\VectorStore;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\PromptTemplate;
use App\Services\ChatService;
use App\Helpers\JsonFileLockStore;
use App\Models\Question;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    protected ChatService $chat;

    public function __construct(ChatService $chat)
    {
        $this->chat = $chat;
    }

    protected function findSimilarEntriesForTopic(string $subject, string $topic, int $noteLevel, int $limit = 3): array
    {
        $subjectSlug = Str::slug(strtolower($subject), '-');
        $templatesDir = base_path('study_resource_note_ai/study_agent/templates');
        $subjectFile = $templatesDir . DIRECTORY_SEPARATOR . $subjectSlug . '.json';

        if (! File::exists($subjectFile)) {
            return [];
        }

        $data = json_decode(File::get($subjectFile), true) ?: [];
        $topics = $data['topics'] ?? [];

        $topicSlug = Str::slug($topic, '-');

        $searchPool = [];
        if (isset($topics[$topicSlug])) {
            $t = $topics[$topicSlug];
            foreach (['templates','conversations'] as $listKey) {
                foreach ($t[$listKey] ?? [] as $entry) {
                    $entry['_topic_slug'] = $topicSlug;
                    $entry['_topic_title'] = ($t['title'] ?? $topic);
                    $searchPool[] = $entry;
                }
            }
        } else {
            foreach ($topics as $tslug => $t) {
                foreach (['templates','conversations'] as $listKey) {
                    foreach ($t[$listKey] ?? [] as $entry) {
                        $entry['_topic_slug'] = $tslug;
                        $entry['_topic_title'] = ($t['title'] ?? $tslug);
                        $searchPool[] = $entry;
                    }
                }
            }
        }

        $candidates = [];
        foreach ($searchPool as $entry) {
            if (isset($entry['template'])) {
                $tpl = $entry['template'];
                $textToCompare = implode(' | ', [
                    $tpl['topic'] ?? '',
                    $tpl['prompt'] ?? '',
                    $tpl['course'] ?? '',
                ]);
                $entryLevel = (int) ($tpl['metadata']['note_level'] ?? 0);
                $entryPrior = $tpl['metadata']['prior_knowledge'] ?? '';
            } else {
                $textToCompare = implode(' | ', [
                    $entry['topic'] ?? '',
                    substr($entry['user_prompt'] ?? '', 0, 200),
                    $entry['course'] ?? '',
                ]);
                $entryLevel = (int) ($entry['metadata']['note_level'] ?? 0);
                $entryPrior = $entry['metadata']['prior_knowledge'] ?? '';
            }

            similar_text(strtolower($topic), strtolower($textToCompare), $topicPercent);

            similar_text(strtolower($entryPrior), strtolower($entry['topic'] ?? $topic), $priorPercent);

            $levelPenalty = min(25, abs($entryLevel - $noteLevel) * 6);

            $score = ($topicPercent * 1.0) + ($priorPercent * 0.4) - $levelPenalty;

            $candidates[] = ['score' => $score, 'entry' => $entry];
        }

        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($candidates, 0, $limit);

        return array_map(fn($c) => $c['entry'], $top);
    }

    protected function compactEntrySummary(array $entry): string
    {
        $topic = $entry['_topic_title'] ?? ($entry['topic'] ?? '(no topic)');
        if (isset($entry['template'])) {
            $t = $entry['template'];
            $md = $t['metadata'] ?? [];
            return sprintf(
                "- Topic: %s | Level: %s | Prior: %s | PromptExcerpt: %s",
                $topic,
                $md['note_level'] ?? 'n/a',
                $md['prior_knowledge'] ?? '',
                $t['prompt'] ? substr($t['prompt'], 0, 120) : '(direct)'
            );
        }

        $md = $entry['metadata'] ?? [];
        return sprintf(
            "- Topic: %s | Level: %s | Prior: %s | PromptExcerpt: %s",
            $topic,
            $md['note_level'] ?? 'n/a',
            $md['prior_knowledge'] ?? '',
            isset($entry['user_prompt']) ? substr($entry['user_prompt'], 0, 120) : '(none)'
        );
    }

    public function createSession(Request $r)
    {
        $rules = [
            'education_level' => 'required|string',
            'course' => 'required|string',
            'subject' => 'required|string',
            'topic' => 'required|string',
            'prior_knowledge' => 'nullable|string',
            'learning_goal' => 'nullable|string',
            'note_level' => 'required|integer|min:1|max:5',
            'examples_count' => 'nullable|integer|min:0',
            'content_format' => 'required|string|in:text,bullet,table',
            'mode' => 'required|string|in:direct,prompt',
            'prompt' => 'nullable|string|max:2000',
            'file' => 'nullable|file|mimes:txt,pdf,docx,jpg,jpeg,png|max:204800',
            'file_prompt' => 'nullable|string|max:204800',
            'file_snippet' => 'nullable|string|max:204800',
            'file_original_name' => 'nullable|string|max:255',
        ];

        $validator = Validator::make($r->all(), $rules);
        $validator->after(function ($validator) use ($r) {
            if ($r->input('mode') === 'prompt' && trim($r->input('prompt', '')) === '' && ! $r->hasFile('file')) {
                $validator->errors()->add('prompt', 'When Mode is "prompt" you must provide a custom prompt or upload a file.');
            }
        });

        if ($validator->fails()) {
            if ($r->wantsJson() || $r->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $data['note_level'] = (int) ($data['note_level'] ?? 0);
        $data['examples_count'] = (int) ($data['examples_count'] ?? 0);

        $storedFilePath = null;
        $originalFileName = null;
        $filePaths = [];
        $fileSnippet = null;

        $clientFilePath = trim((string) $r->input('file_path', ''));
        $clientSnippet = trim((string) $r->input('file_snippet', ''));
        $clientOrigName = trim((string) $r->input('file_original_name', ''));

        if ($clientSnippet !== '') {
            $fileSnippet = mb_substr($clientSnippet, 0, 200000);
            $storedFilePath = $clientFilePath ?: null;
            $originalFileName = $clientOrigName ?: null;
            if ($storedFilePath) $filePaths[] = $storedFilePath;
        }

        if ($fileSnippet === null && $clientFilePath !== '') {
            $storedFilePath = $clientFilePath;
            $originalFileName = $clientOrigName ?: null;

            $relative = preg_replace('#^/storage/#', '', ltrim($storedFilePath, '/'));
            $localFull = storage_path('app/public/' . $relative);

            if (!file_exists($localFull)) {
                $alt = storage_path('app/public/' . ltrim($storedFilePath, '/'));
                if (file_exists($alt)) $localFull = $alt;
            }

            if (file_exists($localFull) && is_readable($localFull)) {
                try {
                    $raw = $this->extractTextFromFile($localFull);
                    $clean = $this->safeUtf8($raw, 2000);
                    $fileSnippet = $this->isMostlyPrintableText($clean) ? $clean : null;
                    $filePaths[] = $localFull;
                } catch (\Throwable $e) {
                    Log::warning("[createSession] read client file_path failed: " . $e->getMessage());
                    $fileSnippet = null;
                }
            } else {
                Log::warning("[createSession] file_path provided but file not found/readable: {$localFull}");
            }
        }

        if ($fileSnippet === null && $r->hasFile('file')) {
            $stored = $r->file('file')->store('uploads', 'public');
            $fullPath = storage_path('app/public/' . $stored);
            $filePaths[] = $fullPath;
            $storedFilePath = 'storage/' . $stored;
            $originalFileName = $r->file('file')->getClientOriginalName();
            try {
                $raw = $this->extractTextFromFile($fullPath);
                $clean = $this->safeUtf8($raw, 2000);
                $fileSnippet = $this->isMostlyPrintableText($clean) ? $clean : null;
            } catch (\Throwable $e) {
                Log::warning("[createSession] file extraction failed: " . $e->getMessage());
                $fileSnippet = null;
            }
        }

        if ($r->filled('file_prompt')) {
            $storedFilePath = $r->input('file_prompt');
            $originalFileName = $r->input('file_original_name', null);
            $localFull = public_path($storedFilePath);
            try {
                if (file_exists($localFull)) {
                    $ext = strtolower(pathinfo($localFull, PATHINFO_EXTENSION));
                    if (in_array($ext, ['docx','pdf','jpg','jpeg','png'])) {
                        $raw = $this->extractTextFromFile($localFull);
                    } else {
                        $raw = file_get_contents($localFull);
                    }
                    $clean = $this->safeUtf8($raw, 2000);
                    $fileSnippet = $this->isMostlyPrintableText($clean) ? $clean : null;
                    $filePaths[] = $localFull;
                }
            } catch (\Throwable $e) {
                Log::warning("[createSession] read file_prompt failed: " . $e->getMessage());
            }
        }

        if ($r->hasFile('file')) {
            $stored = $r->file('file')->store('uploads', 'public');
            $fullPath = storage_path('app/public/' . $stored);
            $filePaths[] = $fullPath;
            $storedFilePath = 'storage/' . $stored;
            $originalFileName = $r->file('file')->getClientOriginalName();
            try {
                $raw = $this->extractTextFromFile($fullPath);
                $clean = $this->safeUtf8($raw, 2000);
                $fileSnippet = $this->isMostlyPrintableText($clean) ? $clean : null;
            } catch (\Throwable $e) {
                Log::warning("[createSession] file extraction failed: " . $e->getMessage());
                $fileSnippet = null;
            }
        }

        $template = [
            'course' => $data['course'],
            'topic' => $data['topic'],
            'mode' => $data['mode'],
            'prompt' => $data['mode'] === 'prompt' ? ($data['prompt'] ?? '') : null,
            'metadata' => [
                'education_level' => $data['education_level'],
                'prior_knowledge' => $data['prior_knowledge'] ?? '',
                'learning_goals' => $data['learning_goal'] ?? '',
                'note_level' => (int) $data['note_level'],
                'example_count' => (int) $data['examples_count'],
                'preferred_format'=> $data['content_format'],
            ],
        ];

        PromptTemplate::updateOrCreate(
            [
                'course' => $data['course'],
                'subject' => $data['subject'],
                'topic' => $data['topic'],
            ],
            [
                'metadata' => [
                    'education_level' => $template['metadata']['education_level'],
                    'prior_knowledge' => $template['metadata']['prior_knowledge'],
                    'learning_goals' => $template['metadata']['learning_goals'],
                    'note_level' => (int) ($template['metadata']['note_level'] ?? 0),
                    'example_count' => (int) ($template['metadata']['example_count'] ?? 0),
                    'preferred_format'=> $template['metadata']['preferred_format'],
                ],
                'mode' => $template['mode'],
                'prompt' => $template['prompt'] ?? null,
            ]
        );

        $session = ChatSession::create([
            'user_id' => auth()->id(),
            'title' => Str::limit($data['topic'], 50),
            'education_level' => $data['education_level'],
            'course' => $data['course'],
            'subject' => $data['subject'],
            'topic' => $data['topic'],
            'prior_knowledge' => $data['prior_knowledge'] ?? '',
            'learning_goal' => $data['learning_goal'] ?? '',
            'difficulty' => $data['note_level'],
            'examples_count' => $data['examples_count'] ?? 0,
            'content_format' => $data['content_format'],
            'mode' => $data['mode'],
            'text_prompt' => $data['prompt'] ?? '',
            'file_prompt' => $storedFilePath,
        ]);

        $metadataLines = [
            "Education Level: {$data['education_level']}",
            "Course: {$data['course']}",
            "Subject: {$data['subject']}",
            "Topic: {$data['topic']}",
            "Prior Knowledge: " . ($data['prior_knowledge'] ?? ''),
            "Learning Goal: " . ($data['learning_goal'] ?? ''),
            "Difficulty: {$data['note_level']}",
            sprintf("Examples Count:  %s", $data['examples_count'] ?? 0),
            "Format: {$data['content_format']}",
            "Mode: {$data['mode']}",
        ];

        $session->messages()->create([
            'sender' => 'user',
            'message' => implode("\n", $metadataLines),
        ]);

        if (!empty($data['prompt'])) {
            $session->messages()->create([
                'sender' => 'user',
                'message' => "User prompt:\n" . $data['prompt'],
            ]);
        }

        if ($storedFilePath) {
            $fileInfo = "Uploaded file: " . ($originalFileName ?? basename($storedFilePath)) . " (".$storedFilePath.")";
            if (!empty($fileSnippet)) {
                $fileInfo .= "\n\nExcerpt:\n" . $fileSnippet;
            }
            $session->messages()->create([
                'sender' => 'user',
                'message' => $fileInfo,
            ]);
        }

        $systemContext = implode("\n", $metadataLines);

        if ($data['mode'] === 'prompt' && ! empty($data['prompt'])) {
            $userPrompt = $data['prompt'];
        } else {
            $userPrompt = "Generate study notes for the topic '{$data['topic']}' in {$data['course']}."
                . " Use the following metadata: Education level: {$data['education_level']}; Prior knowledge: " . ($data['prior_knowledge'] ?? '') . "; Learning goal: " . ($data['learning_goal'] ?? '') . "; Difficulty level: {$data['note_level']}; Examples: " . ($data['examples_count'] ?? 0) . "; Format: {$data['content_format']}."
                . " Output structure: 1) Overview (paragraph), 2) Key Points (point form), 3) Examples (point form), 4) Summary (paragraph).";
        }

        if (! empty($fileSnippet)) {
            $userPrompt .= "\n\nIMPORTANT: Use the UPLOADED FILE CONTENT below as the PRIMARY SOURCE for facts and examples. "
                . "Do not invent content not supported by the file unless necessary to clarify concepts. "
                . "When the file content is short or incomplete, supplement with general knowledge but indicate so.\n\n"
                . "File excerpt:\n" . $fileSnippet . "\n\n";
        } else {
            $userPrompt .= "\n\nNote: no file excerpt was available. Use your knowledge but respect the course: {$data['course']}, subject: {$data['subject']}.\n\n";
        }

        $userPrompt .= "Always format the notes according to the stated 'Format' and keep language and examples appropriate for the requested education level and difficulty (level {$data['note_level']}).";

        try {
            $subjectSlug = Str::slug($data['subject'], '-');
            $topicSlug = Str::slug($data['topic'], '-');

            $embeddingSvc = app(EmbeddingService::class);
            $vectorStore = app(VectorStore::class);

            $queryText = $data['topic'] . ' | ' . ($data['prior_knowledge'] ?? '') . ' | ' . mb_substr($userPrompt, 0, 300);

            $queryVec = null;
            try {
                $queryVec = $embeddingSvc->embedText($queryText);
            } catch (\Throwable $e) {
                Log::warning("[createSession] embedding failed for augmentation query: ".$e->getMessage());
                $queryVec = null;
            }

            if (is_array($queryVec) && count($queryVec) > 0) {
                $matches = $vectorStore->search($subjectSlug, $queryVec, 3, $topicSlug);

                if (! empty($matches)) {
                    $lines = [];
                    $count = 0;
                    foreach ($matches as $m) {
                        $count++;
                        $row = $m['row'] ?? [];
                        $score = round($m['score'] ?? 0, 4);
                        $snip = $row['snippet'] ?? ($row['user_prompt'] ?? '[no snippet]');
                        $snip = trim(mb_substr($snip, 0, 250));
                        $lines[] = "{$count}. (sim={$score}) {$snip}";
                    }
                    if (! empty($lines)) {
                        $augmentExamplesText = "\n\n# Similar examples found in the subject (top {$count}):\n"
                            . implode("\n", $lines)
                            . "\n\nPlease use these examples as reference when generating the notes (do not copy verbatim).";
                        $userPrompt .= $augmentExamplesText;
                        Log::info("[createSession] appended {$count} similar examples to prompt for subject={$subjectSlug} topic={$topicSlug}");
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[createSession] embedding augmentation failed: " . $e->getMessage());
        }

        $messagesForAi = [
            ['role' => 'system', 'content' => 'You are a helpful study assistant. Follow instructions precisely.'],
            ['role' => 'system', 'content' => $systemContext],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        try {
            $reply = $this->chat->chat($messagesForAi);
            $reply = $this->safeUtf8($reply, 8000);
            if (! trim($reply)) {
                throw new \Exception('Empty AI response');
            }
        } catch (\Exception $e) {
            Log::error('AI chat error in createSession: ' . $e->getMessage());
            $reply = "⚠️ Sorry, I couldn't reach the AI right now. Please try again.";
        }

        $split = $this->extractNoteAndSuggestions($reply);

        $noteToSave = trim($split['note'] ?? $reply);
        if ($noteToSave === '') $noteToSave = $reply;
        $session->messages()->create([
            'sender' => 'system',
            'message' => $this->safeUtf8($noteToSave, 8000),
        ]);

        try {
            $sug = $this->generateAndSaveStudySuggestions($noteToSave, $session);
            if (is_array($sug)) {
                $suggestionsTextToSave = $sug['text'] ?? ($suggestionsTextToSave ?? null);
                $suggestionsJson = $sug['json'] ?? ($suggestionsJson ?? null);
            }
        } catch (\Throwable $e) {
            Log::warning("[createSession] generateAndSaveStudySuggestions failed: " . $e->getMessage());
        }

        $suggestionsJson = $split['suggestions_json'] ?? null;
        $suggestionsTextToSave = null;

        if (is_array($suggestionsJson)) {
            $sTitle = $suggestionsJson['title'] ?? 'Study Note Suggestion';
            $parts = [];
            if (!empty($suggestionsJson['focus_points']) && is_array($suggestionsJson['focus_points'])) {
                $parts[] = "Focus Points:\n- " . implode("\n- ", $suggestionsJson['focus_points']);
            }
            if (!empty($suggestionsJson['related_topics']) && is_array($suggestionsJson['related_topics'])) {
                $rtLines = [];
                foreach ($suggestionsJson['related_topics'] as $rt) {
                    $rtLines[] = ($rt['topic'] ?? '') . (!empty($rt['note']) ? (': '.$rt['note']) : '');
                }
                if ($rtLines) $parts[] = "Related Topics:\n- " . implode("\n- ", $rtLines);
            }
            if (!empty($suggestionsJson['study_plan']) && is_array($suggestionsJson['study_plan'])) {
                $parts[] = "Study Plan:\n1. " . implode("\n2. ", $suggestionsJson['study_plan']);
            }
            $sText = trim(($sTitle ? $sTitle . "\n\n" : '') . implode("\n\n", $parts));
            if ($sText === '') $sText = $this->safeUtf8(json_encode($suggestionsJson, JSON_UNESCAPED_UNICODE), 2000);
            $suggestionsTextToSave = $sText;
        } elseif (!empty($split['suggestions_text'])) {
            $suggestionsTextToSave = $split['suggestions_text'];
        }

        if (!empty($suggestionsTextToSave)) {
            if (strpos($suggestionsTextToSave, 'Study Note Suggestion') === false) {
                $suggestionsTextToSave = "Study Note Suggestion\n\n" . $suggestionsTextToSave;
            }
            $session->messages()->create([
                'sender' => 'system',
                'message' => $this->safeUtf8($suggestionsTextToSave, 4000),
            ]);
        }

        try {
            $embeddingSvc = app(EmbeddingService::class);

            $embedTextParts = [
                $data['topic'] ?? '',
                $data['course'] ?? '',
                ($data['prior_knowledge'] ?? ''),
                mb_substr($userPrompt, 0, 300),
                (trim($reply) ? "\nAI_REPLY_EXCERPT:\n" . mb_substr($reply, 0, 800) : ''),
            ];
            $embedText = trim(implode(' | ', array_filter($embedTextParts, fn($x) => trim((string)$x) !== '')));

            if ($embedText !== '') {
                $vector = $embeddingSvc->embedText($embedText);
            } else {
                $vector = null;
            }

            if (is_array($vector) && count($vector) > 0) {
                $vectorStore = app(VectorStore::class);

                $subjectSlug = Str::slug($data['subject'], '-');
                $topicSlug = Str::slug($data['topic'], '-');

                $snippet = mb_substr(trim($reply), 0, 1000);

                $vectorStore->add($subjectSlug, $topicSlug, $vector, 'conversation', (string)$session->id, $snippet);

                Log::info("[createSession] stored embedding for session {$session->id} subject={$subjectSlug} topic={$topicSlug}");
            } else {
                Log::warning("[createSession] embedding service returned empty vector for session {$session->id}");
            }
        } catch (\Throwable $e) {
            Log::warning("[createSession] failed to store embedding: " . $e->getMessage());
        }

        $record = [
            'type' => 'conversation',
            'created_at' => now()->toDateTimeString(),
            'user_id' => auth()->id(),
            'course' => $data['course'],
            'topic' => $data['topic'],
            'mode' => $data['mode'],
            'metadata' => $template['metadata'],
            'text_prompt' => $data['prompt'] ?? '',
            'file_prompt' => $storedFilePath,
            'user_prompt' => $userPrompt,
            'ai_response' => $reply,
            'files' => $filePaths,
        ];

        $subjectSlug = Str::slug($data['subject'], '-');
        $topicSlug   = Str::slug($data['topic'], '-');
        $templatesDir = base_path('study_resource_note_ai/study_agent/templates');
        $subjectFile  = $templatesDir . DIRECTORY_SEPARATOR . $subjectSlug . '.json';

        $store = app(JsonFileLockStore::class);
        $ok = $store->updateAtomic($subjectFile, function ($current) use ($data, $topicSlug, $record) {
            if (! is_array($current)) {
                $current = ['subject' => $data['subject'] ?? '', 'topics' => []];
            }
            if (! isset($current['topics'][$topicSlug])) {
                $current['topics'][$topicSlug] = [
                    'title' => $data['topic'] ?? $topicSlug,
                    'description' => '',
                    'templates' => [],
                    'conversations' => []
                ];
            }
            $current['topics'][$topicSlug]['conversations'][] = $record;
            return $current;
        });

        if (! $ok) {
            Log::error("JsonFileLockStore failed to update subject file: {$subjectFile}");
        } else {
            Log::info("Saved conversation to {$subjectFile} (atomic)");
        }

        if ($r->wantsJson() || $r->ajax()) {
            return response()->json([
                'session_id' => $session->id,
                'reply' => $this->safeUtf8($noteToSave, 8000),
                'study_suggestions_text' => $suggestionsTextToSave ?? null,
                'study_suggestions_json' => $suggestionsJson ?? null,
                'redirect' => route('chat.session.show', $session),
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        return redirect()->route('chat.session.show', $session);
    }

    public function showSession(ChatSession $session)
    {
        $session->load(['messages' => function($q) {
            $q->orderBy('created_at');
        }]);
        return view('chat.show', compact('session'));
    }

    public function showCreateForm()
    {
        $templates = PromptTemplate::latest()->limit(10)->get();
        return view('chat.create', compact('templates'));
    }

    public function submitSession(Request $r, ChatSession $session)
    {
        $this->authorize('view', $session);

        $r->validate([
            'prompt' => 'nullable|string|max:10000',
            'file' => 'nullable|file|mimes:txt,pdf,docx,jpg,jpeg,png|max:204800',
            'file_path' => 'nullable|string|max:10000',
            'file_snippet' => 'nullable|string|max:200000',
            'file_original_name' => 'nullable|string|max:255',
        ]);

        $userMsg = $r->input('prompt', '');

        $fileSnippet = trim((string) $r->input('file_snippet', ''));
        $filePath = trim((string) $r->input('file_path', ''));
        $fileOrig = trim((string) $r->input('file_original_name', ''));

        if ($fileSnippet !== '') {
            $snippet = mb_substr($fileSnippet, 0, 20000);
            $userMsg .= "\n\n[File: " . ($fileOrig ?: 'uploaded file') . "]\n" . $snippet;
        } elseif ($filePath !== '') {
            $relative = preg_replace('#^/storage/#', '', $filePath);
            $full = storage_path('app/public/' . $relative);

            if (file_exists($full) && is_readable($full)) {
                try {
                    $raw = $this->extractTextFromFile($full);
                    $raw = $this->safeUtf8($raw, 200000);
                    $raw = preg_replace("/\r\n|\r/", "\n", $raw);
                    $raw = preg_replace("/\n{3,}/", "\n\n", $raw);
                    $raw = trim($raw);
                    if ($raw !== '') {
                        $preview = mb_substr($raw, 0, 20000);
                        $userMsg .= "\n\n[File: " . ($fileOrig ?: basename($relative)) . "]\n" . $preview;
                    }
                } catch (\Throwable $e) {
                    Log::warning("[submitSession] Failed to extract text from uploaded file ({$filePath}): " . $e->getMessage());
                }
            } else {
                Log::warning("[submitSession] file_path provided but file not found or unreadable: {$full}");
            }
        } elseif ($r->hasFile('file')) {
            try {
                $txt = substr(file_get_contents($r->file('file')->getRealPath()), 0, 2000);
                if ($txt) $userMsg .= "\n\n[File content]\n{$txt}";
            } catch (\Throwable $e) {
                Log::warning("[submitSession] direct file read failed: " . $e->getMessage());
            }
        }

        $session->messages()->create([
            'sender' => 'user',
            'message' => $userMsg,
        ]);

        $messages = [
            ['role'=>'system','content'=>'You are a helpful study assistant.'],
            ['role'=>'system','content'=>"Education Level: {$session->education_level}"],
            ['role'=>'system','content'=>"Course: {$session->course}"],
            ['role'=>'system','content'=>"Subject: {$session->subject}"],
            ['role'=>'system','content'=>"Topic: {$session->topic}"],
            ['role'=>'system','content'=>"Prior Knowledge: {$session->prior_knowledge}"],
            ['role'=>'system','content'=>"Learning Goal: {$session->learning_goal}"],
            ['role'=>'system','content'=>"Difficulty Level: {$session->difficulty}"],
            ['role'=>'system','content'=>"Examples Count: {$session->examples_count}"],
            ['role'=>'system','content'=>"Content Format: {$session->content_format}"],
            ['role'=>'system','content'=>"Mode: {$session->mode}"],
        ];

        foreach ($session->messages()->orderBy('created_at')->get() as $msg) {
            $messages[] = [
                'role'    => $msg->sender === 'user' ? 'user' : 'assistant',
                'content' => $msg->message,
            ];
        }

        try {
            $reply = $this->chat->chat($messages);
            $reply = $this->safeUtf8($reply, 8000);
            if (! trim($reply)) {
                throw new \Exception('Empty AI response');
            }
        } catch (\Exception $e) {
            Log::error("[submitSession] AI chat error: " . $e->getMessage());
            return response()->json([
                'reply' => $this->safeUtf8("⚠️ AI service error: " . $e->getMessage(), 1000)
            ], 500, [], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        $session->messages()->create([
            'sender' => 'system',
            'message' => $reply,
        ]);

        $reply = $this->safeUtf8($reply, 8000);
        $split = $this->extractNoteAndSuggestions($reply);

        $noteToSave = trim($split['note'] ?? $reply);
        if ($noteToSave === '') $noteToSave = $reply;
        $session->messages()->create([
            'sender' => 'system',
            'message' => $this->safeUtf8($noteToSave, 8000),
        ]);

        $suggestionsJson = $split['suggestions_json'] ?? null;
        $suggestionsTextToSave = null;
        if (is_array($suggestionsJson)) {
            $sTitle = $suggestionsJson['title'] ?? 'Study Note Suggestion';
            $parts = [];
            if (!empty($suggestionsJson['focus_points']) && is_array($suggestionsJson['focus_points'])) {
                $parts[] = "Focus Points:\n- " . implode("\n- ", $suggestionsJson['focus_points']);
            }

            if (!empty($suggestionsJson['related_topics']) && is_array($suggestionsJson['related_topics'])) {
                $rtLines = [];
                foreach ($suggestionsJson['related_topics'] as $rt) {
                    $rtLines[] = ($rt['topic'] ?? '') . (!empty($rt['note']) ? (': '.$rt['note']) : '');
                }
                if ($rtLines) $parts[] = "Related Topics:\n- " . implode("\n- ", $rtLines);
            }

            if (!empty($suggestionsJson['study_plan']) && is_array($suggestionsJson['study_plan'])) {
                $parts[] = "Study Plan:\n1. " . implode("\n2. ", $suggestionsJson['study_plan']);
            }

            $sText = trim(($sTitle ? $sTitle . "\n\n" : '') . implode("\n\n", $parts));
            if ($sText === '') $sText = $this->safeUtf8(json_encode($suggestionsJson, JSON_UNESCAPED_UNICODE), 2000);

            $suggestionsTextToSave = $sText;

        } elseif (!empty($split['suggestions_text'])) {
            $suggestionsTextToSave = $split['suggestions_text'];
        }

        if (!empty($suggestionsTextToSave)) {
            if (strpos($suggestionsTextToSave, 'Study Note Suggestion') === false) {
                $suggestionsTextToSave = "Study Note Suggestion\n\n" . $suggestionsTextToSave;
            }
            $session->messages()->create([
                'sender' => 'system',
                'message' => $this->safeUtf8($suggestionsTextToSave, 4000),
            ]);
        }

        $suggestions = ['text' => $suggestionsTextToSave ?? null, 'json' => $suggestionsJson ?? null];

        return response()->json([
            'reply' => $noteToSave,
            'study_suggestions_text' => $suggestions['text'],
            'study_suggestions_json' => $suggestions['json'],
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    public function generateQuestions(Request $r, ChatSession $session)
    {

        $data = $r->validate([
            'count' => 'required|integer|min:1|max:50',
            'difficulty' => 'nullable|string|in:easy,medium,hard',
            'type' => 'nullable|string|in:qa,mcq,mix',
            'context' => 'nullable|string',
            'message_id' => 'nullable|integer|exists:chat_messages,id',
        ]);

        $count = (int)($data['count'] ?? 5);
        $difficulty = $data['difficulty'] ?? 'medium';
        $type = $data['type'] ?? 'mix';
        $context = trim($data['context'] ?? '');
        $originMessageId = $data['message_id'] ?? null;

        $prompt = "You are an instructional AI that generates practice questions.\n\n" .
            "Requirements:\n" .
            " - Return a JSON array named \"questions\".\n" .
            " - Each question must be an object with fields: \"question_text\" (string), \"question_type\" (\"qa\" or \"mcq\"), \"choices\" (array of strings) for mcq (or empty), \"answer\" (string), \"difficulty\" (easy|medium|hard), \"order\" (integer).\n" .
            " - Do NOT include any other top-level keys.\n\n" .
            "Task: generate {$count} questions of type '{$type}' (or mix) at difficulty '{$difficulty}' based on the following context:\n\n" .
            ($context ?: '[NO CONTEXT PROVIDED]') . "\n\n" .
            "Return only valid JSON (no surrounding markdown).";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a helpful AI tutor generating practice questions.'],
                ['role' => 'user', 'content' => $prompt],
            ];

            $reply = $this->chat->chat($messages);
            $reply = $this->safeUtf8($reply, 12000);
        } catch (\Throwable $e) {
            Log::error("generateQuestions: AI error - " . $e->getMessage());
            return response()->json(['message' => 'AI error: ' . $e->getMessage()], 500);
        }

        $chatMsg = $session->messages()->create([
            'sender' => 'system',
            'message' => $reply,
        ]);

        if (preg_match('/\{.*"questions".*\}|\[.*\]/s', $reply, $m)) {
            $jsonCandidate = $m[0];
        } else {
            $jsonCandidate = $reply;
        }

        $jsonCandidate = trim($jsonCandidate);
        $decoded = json_decode($jsonCandidate, true);

        $questionsJson = null;
        if (is_array($decoded) && isset($decoded['questions']) && is_array($decoded['questions'])) {
            $questionsJson = $decoded['questions'];
        } elseif (is_array($decoded) && array_values($decoded) === $decoded && !empty($decoded)) {
            $questionsJson = $decoded;
        } else {
            $questionsJson = null;
        }

        $created = [];
        if (is_array($questionsJson) && count($questionsJson) > 0) {
            $i = 0;
            foreach ($questionsJson as $q) {
                $i++;
                $questionText = trim($q['question_text'] ?? ($q['q'] ?? ''));
                if ($questionText === '') continue;

                $questionType = in_array($q['question_type'] ?? '', ['qa', 'mcq']) ? $q['question_type'] : (in_array($type, ['qa','mcq']) ? $type : 'qa');
                $choices = is_array($q['choices'] ?? null) ? array_values($q['choices']) : null;
                $answer = trim($q['answer'] ?? ($q['ans'] ?? ''));
                $diff = in_array($q['difficulty'] ?? '', ['easy','medium','hard']) ? $q['difficulty'] : $difficulty;
                $order = isset($q['order']) ? (int)$q['order'] : $i;

                $question = \App\Models\Question::create([
                    'session_id' => $session->id,
                    'message_id' => $chatMsg->id,
                    'user_id' => auth()->id(),
                    'question_text' => $questionText,
                    'question_type' => $questionType,
                    'choices' => $choices,
                    'answer' => $answer,
                    'difficulty' => $diff,
                    'order' => $order,
                    'ai_generated' => true,
                ]);

                $created[] = $question->id;
            }
        } else {
            $q = \App\Models\Question::create([
                'session_id' => $session->id,
                'message_id' => $chatMsg->id,
                'user_id' => auth()->id(),
                'question_text' => 'Generated questions (raw) — see AI message.',
                'question_type' => 'qa',
                'choices' => null,
                'answer' => $reply,
                'difficulty' => $difficulty,
                'order' => 0,
                'ai_generated' => true,
            ]);
            $created[] = $q->id;
        }

        return response()->json([
            'message_id' => $chatMsg->id,
            'questions_created' => $created,
            'questions_text' => $reply,
            'questions_json' => $questionsJson,
        ]);
    }

    public function generateKeyConcepts(Request $r, ChatSession $session)
    {

        $contextFromClient = $r->input('context', null);

        $context = '';
        if ($contextFromClient) {
            $context = (string)$contextFromClient;
        } else {
            $aiMsg = $session->messages()->where('sender', 'system')->orderBy('created_at', 'desc')->first();
            if ($aiMsg) $context = $aiMsg->message;
            elseif ($session->file_prompt) {
                $local = public_path($session->file_prompt);
                if (file_exists($local)) {
                    try { $context = file_get_contents($local) ?: ''; } catch (\Throwable $e) { $context = ''; }
                }
            }
        }

        $context = $this->safeUtf8((string)$context, 6000);
        if (trim($context) === '') {
            return response()->json(['error' => 'No context available to extract key concepts.'], 422);
        }

        $userPrompt = "Extract up to 12 key concepts from the text below. For each concept, give a short definition (1-2 sentences)."
            . " Return first as a simple bullet list, and AFTER the list, output a JSON array under the heading 'JSON_OUTPUT' like this:\n\n"
            . "JSON_OUTPUT:\n[{\"concept\":\"...\",\"definition\":\"...\"}, ...]\n\n"
            . "Do not include extra commentary. Now analyze the text:\n\n" . $context;

        $messagesForAi = [
            ['role' => 'system', 'content' => 'You are a concise study assistant that extracts key points.'],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        try {
            $reply = $this->chat->chat($messagesForAi);
            $reply = $this->safeUtf8($reply, 4000);
            if (! trim($reply)) {
                throw new \Exception('Empty AI response');
            }
        } catch (\Exception $e) {
            Log::error("[generateKeyConcepts] AI error: " . $e->getMessage());
            return response()->json(['error' => "AI service error: " . $e->getMessage()], 500);
        }

        $saved = $session->messages()->create([
            'sender' => 'system',
            'message' => $reply,
        ]);

        $conceptsJson = null;
        if (preg_match('/JSON_OUTPUT\s*:\s*(\[[\s\S]*\])/i', $reply, $m)) {
            $candidate = trim($m[1]);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                $conceptsJson = array_map(function($it) {
                    return [
                        'concept' => isset($it['concept']) ? (string)$it['concept'] : (isset($it['title']) ? (string)$it['title'] : ''),
                        'definition' => isset($it['definition']) ? (string)$it['definition'] : (isset($it['def']) ? (string)$it['def'] : ''),
                    ];
                }, $decoded);
            }
        } else {

            if (preg_match('/(\[[\s\S]*\]|\{[\s\S]*\})/m', $reply, $m2)) {
                $try = json_decode($m2[0], true);
                if (is_array($try)) {
                    if (isset($try['concepts']) && is_array($try['concepts'])) {
                        $conceptsJson = $try['concepts'];
                    } else {
                        $hasConcepts = array_values($try)[0] ?? null;
                        if (is_array($hasConcepts)) {
                            $conceptsJson = $try;
                        }
                    }
                }
            }
        }

        return response()->json([
            'concepts' => $reply,
            'concepts_json' => $conceptsJson,
            'message_id' => $saved->id,
        ]);
    }

    public function generateStudySuggestions(Request $r, ChatSession $session)
    {
        $this->authorize('view', $session);

        $contextFromClient = $r->input('context', null);
        $context = '';
        if ($contextFromClient) {
            $context = (string)$contextFromClient;
        } else {
            $aiMsg = $session->messages()->where('sender', 'system')->orderBy('created_at', 'desc')->first();
            if ($aiMsg) $context = $aiMsg->message;
            elseif ($session->file_prompt) {
                $local = public_path($session->file_prompt);
                if (file_exists($local)) {
                    try { $context = file_get_contents($local) ?: ''; } catch (\Throwable $e) { $context = ''; }
                }
            }
        }

        if (trim((string)$context) === '') {
            return response()->json(['error' => 'No context available for study suggestions.'], 422);
        }

        $result = $this->generateAndSaveStudySuggestions($context, $session);

        return response()->json([
            'message_id' => $result['message_id'],
            'suggestions_text' => $result['text'],
            'suggestions_json' => $result['json'],
        ]);
    }

    protected function generateAndSaveStudySuggestions(string $context, ChatSession $session): array
    {
        $context = $this->safeUtf8((string)$context, 8000);

        $userPrompt = "You are an assistant that creates concise study suggestions for students.\n\n"
            . "Based on the study notes below, produce:\n"
            . " 1) A short human-friendly title and a few concise bullet 'focus points' (3-8 items).\n"
            . " 2) A list of up to 6 related topics (title + one-sentence why it's relevant).\n"
            . " 3) A short 3-step 'Study Plan' with suggested activities (e.g., reread, practice problems, spaced repetition).\n\n"
            . "OUTPUT FORMAT:\n"
            . "First output a short readable bullet list (for humans). AFTER that, output valid JSON under the heading 'JSON_OUTPUT:' exactly like this:\n\n"
            . "JSON_OUTPUT:\n"
            . "{\"title\":\"...\",\"focus_points\": [\"...\", ...], \"related_topics\": [{\"topic\":\"...\",\"note\":\"...\"}, ...], \"study_plan\": [\"step 1\", \"step 2\"]}\n\n"
            . "Do not include different top-level keys. Now analyze the text below:\n\n"
            . $context;

        $messagesForAi = [
            ['role' => 'system', 'content' => 'You are a concise study assistant. Provide clear study suggestions.'],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        try {
            $reply = $this->chat->chat($messagesForAi);
            $reply = $this->safeUtf8($reply, 8000);
            if (! trim($reply)) {
                throw new \Exception('Empty AI response');
            }
        } catch (\Exception $e) {
            Log::error("[generateAndSaveStudySuggestions] AI error: " . $e->getMessage());
            return ['text' => '', 'json' => null, 'message_id' => null];
        }

        $suggestions_json = null;
        if (preg_match('/JSON_OUTPUT\s*:\s*([\s\S]*)/i', $reply, $m)) {
            $rest = trim($m[1]);
            if (preg_match('/(\{[\s\S]*\})/', $rest, $m2)) {
                $candidate = $m2[1];
                $decoded = json_decode($candidate, true);
                if (is_array($decoded)) $suggestions_json = $decoded;
            }
        }

        if ($suggestions_json === null) {
            if (preg_match('/(\{[\s\S]*\})/', $reply, $m)) {
                $decoded = json_decode($m[1], true);
                if (is_array($decoded)) $suggestions_json = $decoded;
            }
        }

        $displayLines = [];

        if (is_array($suggestions_json)) {
            $title = 'Study Note Suggestion';
            if ($title !== '') {
                $displayLines[] = "Title: {$title}";
            }

            if (!empty($suggestions_json['focus_points']) && is_array($suggestions_json['focus_points'])) {
                $displayLines[] = 'Focus Points:';
                foreach ($suggestions_json['focus_points'] as $fp) {
                    $fp = trim((string)$fp);
                    if ($fp !== '') $displayLines[] = "•  {$fp}";
                }
            }

            if (!empty($suggestions_json['related_topics']) && is_array($suggestions_json['related_topics'])) {
                $displayLines[] = 'Related Topics:';
                foreach ($suggestions_json['related_topics'] as $rt) {
                    $topic = trim((string)($rt['topic'] ?? ''));
                    $note = trim((string)($rt['note'] ?? ''));
                    if ($topic !== '') {
                        $line = "•  {$topic}";
                        if ($note !== '') $line .= ': ' . $note;
                        $displayLines[] = $line;
                    }
                }
            }

            if (!empty($suggestions_json['study_plan']) && is_array($suggestions_json['study_plan'])) {
                $displayLines[] = 'Study Plan:';
                $i = 1;
                foreach ($suggestions_json['study_plan'] as $step) {
                    $s = trim((string)$step);
                    if ($s !== '') {
                        $displayLines[] = "  {$i}.  {$s}";
                        $i++;
                    }
                }
            }

            $displayText = trim(implode("\n", $displayLines));
        } else {
            $displayText = $reply;
            $displayText = preg_replace('/JSON_OUTPUT\s*:\s*[\s\S]*$/i', '', $displayText);
            $displayText = preg_replace('/(\{[\s\S]*\}|\[[\s\S]*\])\s*$/', '', $displayText);
            $displayText = trim($displayText);
        }

        if ($displayText === '') {
            $displayText = trim(preg_replace('/JSON_OUTPUT\s*:\s*[\s\S]*$/i', '', $reply));
            $displayText = trim(preg_replace('/(\{[\s\S]*\}|\[[\s\S]*\])\s*$/', '', $displayText));
            $displayText = $displayText === '' ? $reply : $displayText;
        }

        $saved = $session->messages()->create([
            'sender' => 'system',
            'message' => $this->safeUtf8($displayText, 4000),
        ]);

        return [
            'text' => $displayText,
            'json' => $suggestions_json,
            'message_id' => $saved->id,
        ];
    }

    protected function extractNoteAndSuggestions(string $reply): array
    {
        $reply = (string) $reply;
        $result = [
            'note' => '',
            'suggestions_text' => null,
            'suggestions_json' => null,
        ];

        $reply = trim($reply);

        if (preg_match('/(\{(?:[^{}]|(?R))*\})/s', $reply, $jsonMatch)) {
            $jsonString = $jsonMatch[1] ?? '';
            $decoded = json_decode($jsonString, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $result['suggestions_json'] = $decoded;
                $replyWithoutJson = str_replace($jsonString, '', $reply);
                $reply = trim($replyWithoutJson);
            }
        }

        $separators = [
            "\n\nStudy suggestion\n\n",
            "\n\nStudy suggestion:\n\n",
            "\n\nSuggestions:\n\n",
            "\n\nStudy Suggestions:\n\n",
            "\n\n---\n\n"
        ];
        foreach ($separators as $sep) {
            $pos = mb_stripos($reply, $sep);
            if ($pos !== false) {
                $notePart = mb_substr($reply, 0, (int)$pos);
                $suggestPart = mb_substr($reply, (int)($pos + mb_strlen($sep)));
                $result['note'] = trim($notePart);
                $sText = trim($suggestPart);
                if ($sText !== '') $result['suggestions_text'] = $sText;
                return $result;
            }
        }

        if (preg_match('/(.*?)(\n+Suggestions?:\s*\n+)(.*)/is', $reply, $parts)) {
            $result['note'] = trim($parts[1] ?? '');
            $sText = trim($parts[3] ?? '');
            if ($sText !== '') $result['suggestions_text'] = $sText;
            return $result;
        }

        $result['note'] = trim($reply);

        return $result;
    }

    public function sessionQuestions(ChatSession $session)
    {
        $this->authorize('view', $session);
        $qs = Question::where('session_id', $session->id)->orderBy('order')->get();
        return response()->json($qs);
    }

    public function regenerate(Request $request, ChatSession $session)
    {
        try {
            $user = Auth::user();

            if ($session->user_id !== $user->id) {
                return response()->json(['error' => 'Not authorized'], 403);
            }

            Log::info("Attempting regenerate for session {$session->id}");

            $replyText = app(ChatService::class)->regenerateForSession($session);

            return response()->json(['ok' => true, 'reply' => $replyText], 200);

        } catch (\Throwable $e) {
            Log::error("Regenerate failed for session {$session->id}: ".$e->getMessage());
            Log::error($e->getTraceAsString());

            if (config('app.debug')) {
                return response()->json([
                    'error' => 'Failed to regenerate',
                    'exception_message' => $e->getMessage(),
                    'trace' => explode("\n", $e->getTraceAsString(), 20)
                ], 500);
            }

            return response()->json(['error' => 'Failed to regenerate'], 500);
        }
    }

    public function history()
    {
        $sessions = ChatSession::where('user_id', auth()->id())
            ->with(['messages' => function($q) {
                $q->where('sender', 'system')->orderBy('created_at', 'desc');
            }])
            ->latest()
            ->get()
            ->map(function($s) {
                $s->latest_ai = optional($s->messages->first())->message;
                return $s;
            });

        return view('chat.history', compact('sessions'));
    }

    public function downloadSession(ChatSession $session)
    {
        $this->authorize('view', $session);

        $aiMessage = $session->messages()->where('sender', 'system')->orderBy('created_at', 'desc')->first();

        if (! $aiMessage) {
            abort(404, 'No AI response found for this session');
        }

        $filename = Str::slug($session->title ?: 'note') . '-' . $session->id . '.txt';
        $content = $aiMessage->message;

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/plain; charset=utf-8'
        ]);
    }
}
