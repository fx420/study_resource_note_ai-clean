<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InteractionEvent extends Model
{
    public $timestamps = false;
    protected $table = 'interaction_events';
    protected $fillable = ['user_id','session_id','message_id','event_type','metadata','created_at'];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];
}
