<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SttService
{
    protected string $sttUrl;

    public function __construct()
    {
        $this->sttUrl = env('WHISPER_STT_URL', 'http://host.docker.internal:9000/transcribe');
    }

    public function transcribe(UploadedFile $audioFile): string
    {
        try {
            $response = Http::timeout(60)->attach(
                'audio',
                file_get_contents($audioFile->getRealPath()),
                $audioFile->getClientOriginalName()
            )->post($this->sttUrl);

            if ($response->successful()) {
                return $response->json('text', '');
            }

            Log::error('STT API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return '';
        } catch (\Exception $e) {
            Log::error('STT service exception', ['error' => $e->getMessage()]);
            return '';
        }
    }
}

