<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhoneChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'old_phone',
        'new_phone',
        'expires_at',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
