<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // ✅ اضافه شد

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; // ✅ HasApiTokens اضافه شد

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'avatar',
        'is_completed',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

    // روابط
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_user')
            ->withPivot(['joined_at', 'left_at', 'last_read_chat_message_id']);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'payer_id');
    }

    public function expenseSplits()
    {
        return $this->hasMany(ExpenseSplit::class);
    }

    public function settlementsAsDebtor()
    {
        return $this->hasMany(Settlement::class, 'debtor_id');
    }

    public function settlementsAsCreditor()
    {
        return $this->hasMany(Settlement::class, 'creditor_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    public function personalAccounts() { return $this->hasMany(PersonalAccount::class); }
    public function personalTransactions() { return $this->hasMany(PersonalTransaction::class); }
    public function savingsGoals() { return $this->hasMany(SavingsGoal::class); }

    public function getAuthIdentifierName()
    {
        return 'id';
    }
}
