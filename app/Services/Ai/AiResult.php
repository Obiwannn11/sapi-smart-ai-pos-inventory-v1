<?php

namespace App\Services\Ai;

/**
 * DTO netral-provider untuk hasil pemanggilan LLM.
 */
class AiResult
{
    public function __construct(
        public string $text,
        public ?int $tokensUsed = null,
    ) {}
}
