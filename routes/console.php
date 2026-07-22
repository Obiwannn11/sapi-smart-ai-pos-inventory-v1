<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pemangkasan jejak audit platform. Harian dan di jam sepi — tabelnya tumbuh
// terus dan tanpa ini tak ada yang pernah membersihkannya.
Schedule::command('platform:prune-audit-logs')->dailyAt('03:10');
