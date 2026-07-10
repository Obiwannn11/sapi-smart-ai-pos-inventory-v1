<?php

return [
    'default' => env('AI_DEFAULT_PROVIDER', 'sumopod'),

    'models' => [
        'sumopod' => env('AI_SUMOPOD_MODEL', 'gpt-4o-mini'),
        'gemini' => env('AI_GEMINI_MODEL', 'gemini-2.0-flash'),
        'openai' => env('AI_OPENAI_MODEL', 'gpt-4o-mini'),
        'anthropic' => env('AI_ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
    ],

    'free_tier' => [
        'key' => env('AI_FREE_TIER_KEY'),   // shared key milik app (provider = ai.default, mis. SumoPod)
        'daily_limit' => env('AI_FREE_TIER_DAILY_LIMIT', 5),
    ],
];
