<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Manifes Dokumentasi Publik
    |--------------------------------------------------------------------------
    |
    | Satu sumber kebenaran untuk hub dokumentasi, sidebar tiap jalur, dan
    | validasi rute. Menambah halaman = taruh berkas markdown-nya di
    | `resources/docs/{jalur}/{slug}.md` lalu daftarkan di sini.
    |
    | Halaman yang tidak terdaftar tidak bisa dibuka meski berkasnya ada —
    | daftar inilah gerbangnya, bukan keberadaan berkas. Tanpa itu, `{page}`
    | dari URL akan jadi jalan menyusuri sistem berkas.
    |
    | Dua jalur, sesuai dua pembaca yang benar-benar berbeda:
    |   `panduan`   — pemilik usaha dan kasir. Bahasa sehari-hari, urut kerja.
    |   `developer` — yang menyambungkan sistem lain. Istilah teknis boleh.
    |
    */

    'tracks' => [

        'panduan' => [
            'label' => 'Panduan Penggunaan',
            'tagline' => 'Untuk pemilik usaha & kasir',
            'summary' => 'Cara memakai SAPI POS sehari-hari — dari membuka kas pagi hari sampai membaca laporan bulanan.',
            'icon' => 'book',
            'pages' => [
                'mulai' => [
                    'title' => 'Memulai',
                    'summary' => 'Mendaftar, memverifikasi email, dan apa yang terjadi selama masa coba.',
                ],
                'kasir' => [
                    'title' => 'Kasir & Transaksi',
                    'summary' => 'Membuka sesi kas, mencatat penjualan, open bill, dan menutup kas.',
                ],
                'produk-stok' => [
                    'title' => 'Produk & Stok',
                    'summary' => 'Menyusun katalog, varian, modifier, dan menjaga stok tetap benar.',
                ],
                'laporan-ai' => [
                    'title' => 'Laporan & AI Analysis',
                    'summary' => 'Membaca laporan penjualan, dan meminta AI membacakannya untuk Anda.',
                ],
                'staf-akses' => [
                    'title' => 'Staf & Hak Akses',
                    'summary' => 'Menambah kasir, mengatur role, dan menonaktifkan staf yang keluar.',
                ],
                'langganan' => [
                    'title' => 'Langganan & Pembayaran',
                    'summary' => 'Masa coba, tarif, batas pengguna, dan jalur subsidi UMKM.',
                ],
            ],
        ],

        'developer' => [
            'label' => 'Dokumentasi Developer',
            'tagline' => 'Untuk integrator & AI',
            'summary' => 'Menyambungkan sistem lain ke SAPI — REST API kasir mobile, server MCP untuk asisten AI, dan API pesan mandiri.',
            'icon' => 'code',
            'pages' => [
                'mulai' => [
                    'title' => 'Pengantar Integrasi',
                    'summary' => 'Base URL, autentikasi token, versi, dan bentuk jawaban.',
                ],
                'ai-mcp' => [
                    'title' => 'Server MCP untuk AI',
                    'summary' => 'Menyambungkan asisten AI ke data usaha Anda lewat Model Context Protocol.',
                ],
                'self-order' => [
                    'title' => 'API Pesan Mandiri',
                    'summary' => 'Endpoint katalog dan pemesanan untuk kanal luar seperti bot dan n8n.',
                ],
            ],
        ],

    ],

];
