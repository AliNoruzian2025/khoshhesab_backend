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
        'payment_method',
        'debtor_personal_account_id',
        'creditor_personal_account_id',
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

    public function debtorPersonalAccount() { return $this->belongsTo(PersonalAccount::class, 'debtor_personal_account_id'); }
    public function creditorPersonalAccount() { return $this->belongsTo(PersonalAccount::class, 'creditor_personal_account_id'); }
}
