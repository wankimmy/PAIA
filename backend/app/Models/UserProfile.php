<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'nickname',
        'ai_name',
        'pronouns',
        'bio',
        'timezone',
        'primary_language',
        'preferred_tone',
        'preferred_answer_length',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

