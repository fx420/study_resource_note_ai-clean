<?php
namespace App\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class StudyAgentTemplateStore
{
    protected string $baseDir;

    public function __construct()
    {
        $this->baseDir = base_path('study_resource_note_ai/study_agent/templates');
        if (! File::exists($this->baseDir)) {
            File::makeDirectory($this->baseDir, 0755, true);
        }
    }

    protected function filenameForSubject(string $subject): string
    {
        $slug = Str::slug($subject, '-');
        return $this->baseDir . DIRECTORY_SEPARATOR . $slug . '.json';
    }

    public function load(string $subject): array
    {
        $file = $this->filenameForSubject($subject);
        if (! File::exists($file)) return [];
        $json = File::get($file);
        $arr  = json_decode($json, true);
        return is_array($arr) ? $arr : [];
    }

    public function save(string $subject, array $record): void
    {
        $file = $this->filenameForSubject($subject);
        $all  = $this->load($subject);

        $all[] = $record;

        File::put($file, json_encode($all, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }
}
