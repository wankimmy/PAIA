<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'username',
        'encrypted_password',
        'encrypted_notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

