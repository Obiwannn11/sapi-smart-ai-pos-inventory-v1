<?php

namespace App\Services\Ai;

/**
 * SumoPod adalah gateway AI yang OpenAI-compatible (persis OpenAI, hanya beda
 * base URL). Cukup extend OpenAiProvider dan override endpoint chat completions.
 *
 * @see https://ai.sumopod.com/v1
 */
class SumoPodProvider extends OpenAiProvider
{
    protected function endpoint(): string
    {
        return 'https://ai.sumopod.com/v1/chat/completions';
    }

    protected function providerLabel(): string
    {
        return 'SumoPod';
    }
}
