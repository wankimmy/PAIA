<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiMemory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category',
        'key',
        'value',
        'importance',
        'source',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

