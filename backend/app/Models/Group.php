<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'avatar',
        'creator_id',
        'invite_code',
        'require_approval',
        'due_date',
        'reminder_date',
        'start_date',
        'end_date',
        'is_active',
        'parent_group_id',
    ];

    protected $casts = [
        'require_approval' => 'boolean',
        'is_active' => 'boolean',
        'due_date' => 'datetime',
        'reminder_date' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    // روابط
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withPivot(['joined_at', 'left_at', 'last_read_chat_message_id']);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function joinRequests()
    {
        return $this->hasMany(JoinRequest::class);
    }

    public function deletionRequests()
    {
        return $this->hasMany(GroupDeletionRequest::class);
    }

    public function parentGroup()
    {
        return $this->belongsTo(Group::class, 'parent_group_id');
    }

    public function childGroups()
    {
        return $this->hasMany(Group::class, 'parent_group_id');
    }
}
