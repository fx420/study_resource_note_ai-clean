<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromptTemplate extends Model
{
    protected $fillable = [
        'course',
        'subject',
        'topic',
        'metadata',
        'mode',
        'prompt',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
