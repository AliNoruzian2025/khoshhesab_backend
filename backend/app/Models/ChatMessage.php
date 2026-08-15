<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id',
        'sender_id',
        'message',
        'reply_to_id',
        'sticker',
        'receipt_image',
        'is_seen',
        'is_system',
        'edited_at',
    ];

    protected $casts = [
        'is_seen' => 'boolean',
        'is_system' => 'boolean',
        'edited_at' => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(ChatMessage::class, 'reply_to_id')->withTrashed();
    }
}
