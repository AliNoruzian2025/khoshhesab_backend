<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingsGoal extends Model
{
    protected $fillable = ['user_id', 'title', 'target_amount', 'saved_amount', 'target_date', 'color', 'is_completed'];
    protected $casts = ['target_amount' => 'integer', 'saved_amount' => 'integer', 'target_date' => 'date', 'is_completed' => 'boolean'];
}
