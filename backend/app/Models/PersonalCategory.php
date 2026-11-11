<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalCategory extends Model
{
    protected $fillable = ['user_id', 'type', 'name'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
