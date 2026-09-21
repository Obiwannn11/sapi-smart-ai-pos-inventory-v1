<?php

return [
    'default' => env('AI_DEFAULT_PROVIDER', 'sumopod'),

    'models' => [
        'sumopod' => env('AI_SUMOPOD_MODEL', 'gpt-4o-mini'),
        'gemini' => env('AI_GEMINI_MODEL', 'gemini-2.0-flash'),
        'openai' => env('AI_OPENAI_MODEL', 'gpt-4o-mini'),
        'anthropic' => env('AI_ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batas Baris Konteks
    |--------------------------------------------------------------------------
    |
    | Berapa banyak baris `profit_by_item` yang boleh ikut ke dalam prompt.
    |
    | Ini pagar ONGKOS, bukan pagar tampilan (`[BL-069]`). Pengukuran token
    | menemukan bahwa konteks AI TIDAK tumbuh mengikuti jumlah transaksi —
    | `AiContextService` mengirim data yang sudah diagregasi — kecuali satu
    | bagian: `profit_by_item` mengirim satu baris per produk. Tenant dengan 200
    | produk menambah 5.000-8.000 token masukan, tiga sampai empat kali lipat
    | dari 1.300 token yang terukur, dan harga kuota AI yang ditetapkan atas
    | angka tak terbatas akan selalu salah untuk tenant terbesar — yaitu justru
    | yang paling mungkin membeli kuota tambahan.
    |
    | Sisanya tidak dibuang diam-diam: yang tidak muat diringkas jadi satu baris
    | agregat, supaya model tahu ada produk lain dan tidak menyimpulkan bahwa
    | yang dikirim adalah seluruh katalog.
    |
    */

    'context' => [
        'profit_by_item_limit' => env('AI_CONTEXT_PROFIT_ITEMS', 20),
    ],

    'free_tier' => [
        'key' => env('AI_FREE_TIER_KEY'),   // shared key milik app (provider = ai.default, mis. SumoPod)
        'daily_limit' => env('AI_FREE_TIER_DAILY_LIMIT', 5),
    ],
];
