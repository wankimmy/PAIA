<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'email',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function passwordEntries()
    {
        return $this->hasMany(PasswordEntry::class);
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function settings()
    {
        return $this->hasOne(UserSetting::class);
    }

    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function aiMemories()
    {
        return $this->hasMany(AiMemory::class);
    }

    public function aiInteractions()
    {
        return $this->hasMany(AiInteraction::class);
    }

    public function tags()
    {
        return $this->hasMany(Tag::class);
    }
}

