<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $fillable = [
        'session_id',
        'message_id',
        'user_id',
        'question_text',
        'question_type',
        'choices',
        'answer',
        'difficulty',
        'order',
        'ai_generated',
        'metadata',
    ];

    protected $casts = [
        'choices' => 'array',
        'metadata' => 'array',
        'ai_generated' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
