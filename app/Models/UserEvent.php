<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserEvent extends Model
{
    protected $fillable = ['user_id','event_type','session_id_str','chat_message_id','meta','ip'];
    protected $casts = ['meta' => 'array'];
}
