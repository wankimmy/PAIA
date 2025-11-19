<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'due_at',
        'tag',
        'created_via',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }
}

