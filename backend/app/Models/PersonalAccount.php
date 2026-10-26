<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalAccount extends Model
{
    protected $fillable = ['user_id', 'name', 'type', 'opening_balance', 'color', 'note', 'is_archived'];
    protected $casts = ['opening_balance' => 'integer', 'is_archived' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }
    public function transactions() { return $this->hasMany(PersonalTransaction::class, 'account_id'); }
    public function incomingTransfers() { return $this->hasMany(PersonalTransaction::class, 'destination_account_id'); }
}
