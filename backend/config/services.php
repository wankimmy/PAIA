<?php

return [

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://host.docker.internal:11434'),
        'model' => env('OLLAMA_MODEL', 'gemma3:1b'),
    ],

];

