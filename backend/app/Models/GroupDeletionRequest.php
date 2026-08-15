<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupDeletionRequest extends Model
{
    protected $fillable = ['group_id', 'requester_id', 'status', 'resolved_at'];

    protected $casts = ['resolved_at' => 'datetime'];

    public function group() { return $this->belongsTo(Group::class); }
    public function requester() { return $this->belongsTo(User::class, 'requester_id'); }
    public function votes() { return $this->hasMany(GroupDeletionVote::class, 'deletion_request_id'); }
}
