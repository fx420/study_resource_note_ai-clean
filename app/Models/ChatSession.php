<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    protected $fillable = [
        'user_id', 'title', 'education_level', 'course', 'subject', 'topic',
        'prior_knowledge', 'learning_goal', 'difficulty', 'examples_count',
        'content_format', 'mode', 'text_prompt', 'file_prompt'
    ];

    protected $casts = [
        'examples_count' => 'integer',
        'difficulty' => 'integer',
    ];
    
    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'chat_session_id')->orderBy('created_at');
    }

    public function showSession(ChatSession $session)
    {
        $this->authorize('view', $session);
        
        $session->load('messages');
        return view('chat.show', [
        'session'  => $session,
        'messages' => $session->messages,
        ]);
    }
}
