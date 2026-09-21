<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Zona waktu bisnis aplikasi ini, dan satu-satunya jawaban atas "hari
    | ini" ([BL-082]). Bukan UTC: laporan harian, kartu dashboard, dan rekap
    | bulanan semuanya mengelompokkan per HARI, dan hari yang dipakai harus
    | hari toko — bukan hari server. Batas hari UTC jatuh pukul 08.00 WITA,
    | jadi penjualan jam pertama tiap pagi akan tercatat di hari kemarin.
    |
    | Nilainya dibaca dari env supaya produksi bisa berbeda dari lokal tanpa
    | mengubah kode. Satu zona untuk seluruh aplikasi (keputusan pemilik
    | 2026-08-22); bila suatu hari dibutuhkan per tenant, BusinessClock
    | adalah satu-satunya tempat yang perlu berubah.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'Asia/Makassar'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    // Default 'id', bukan 'en': seluruh antarmuka aplikasi ini berbahasa
    // Indonesia, jadi pesan validasi bawaan Laravel pun harus mengikutinya.
    // Ditaruh sebagai default di config — bukan hanya di .env — supaya berlaku
    // juga saat test berjalan dan di pemasangan yang lupa mengisinya.
    'locale' => env('APP_LOCALE', 'id'),

    // Fallback tetap 'en'. Bila suatu saat ada kunci terjemahan yang terlewat,
    // pengguna melihat kalimat bahasa Inggris — janggal, tapi masih terbaca.
    // Tanpa fallback, yang muncul adalah kunci mentahnya seperti
    // "validation.required".
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
