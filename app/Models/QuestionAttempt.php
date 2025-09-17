<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionAttempt extends Model
{
    protected $fillable = ['question_id','session_id','user_id','is_correct','answer_payload','duration_ms'];
    protected $casts = ['answer_payload' => 'array', 'is_correct' => 'boolean'];
}
