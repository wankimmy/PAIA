<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'onboarding_completed',
        'preferences',
        'ai_context',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_completed' => 'boolean',
            'preferences' => 'array',
            'ai_context' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

