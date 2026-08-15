<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'debtor_id',
        'creditor_id',
        'amount',
        'receipt_image',
        'status',
        'rejection_reason',
        'settled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:0',
        'settled_at' => 'datetime',
    ];

    // روابط
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function debtor()
    {
        return $this->belongsTo(User::class, 'debtor_id');
    }

    public function creditor()
    {
        return $this->belongsTo(User::class, 'creditor_id');
    }
}