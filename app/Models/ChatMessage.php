<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatMessage extends Model
{
    use HasFactory;

    protected $table = 'chat_messages';
    protected $fillable = ['session_id', 'sender', 'message', 'created_at', 'updated_at'];

    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }
}
