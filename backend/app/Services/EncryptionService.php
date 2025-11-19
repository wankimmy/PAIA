<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class EncryptionService
{
    public function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    public function decrypt(string $encryptedValue): string
    {
        try {
            return Crypt::decryptString($encryptedValue);
        } catch (\Exception $e) {
            return '';
        }
    }
}

