<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupDeletionVote extends Model
{
    protected $fillable = ['deletion_request_id', 'user_id', 'decision'];

    public function request() { return $this->belongsTo(GroupDeletionRequest::class, 'deletion_request_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
