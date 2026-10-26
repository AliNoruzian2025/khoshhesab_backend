<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalTransaction extends Model
{
    protected $fillable = [
        'user_id', 'account_id', 'destination_account_id', 'type', 'amount', 'category',
        'title', 'note', 'transaction_date', 'reference_type', 'reference_id',
    ];
    protected $casts = ['amount' => 'integer', 'transaction_date' => 'date'];

    public function account() { return $this->belongsTo(PersonalAccount::class, 'account_id'); }
    public function destinationAccount() { return $this->belongsTo(PersonalAccount::class, 'destination_account_id'); }
}
