<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pesan Validasi
    |--------------------------------------------------------------------------
    |
    | Terjemahan lengkap pesan validasi bawaan Laravel. Nada yang dipakai:
    | menyebut APA yang salah dan APA yang diharapkan, tanpa menyalahkan.
    | "Email tidak valid" bukan "Anda salah memasukkan email".
    |
    | Nama field yang muncul di kalimat ini datang dari daftar `attributes` di
    | bawah. Tanpa daftar itu, pesannya akan berbunyi "Kolom business_name wajib
    | diisi" — bahasanya Indonesia tapi field-nya masih bahasa mesin.
    |
    */

    'accepted' => 'Kolom :attribute harus disetujui.',
    'accepted_if' => 'Kolom :attribute harus disetujui bila :other bernilai :value.',
    'active_url' => 'Kolom :attribute harus berupa URL yang valid.',
    'after' => 'Kolom :attribute harus berisi tanggal setelah :date.',
    'after_or_equal' => 'Kolom :attribute harus berisi tanggal setelah atau sama dengan :date.',
    'alpha' => 'Kolom :attribute hanya boleh berisi huruf.',
    'alpha_dash' => 'Kolom :attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => 'Kolom :attribute hanya boleh berisi huruf dan angka.',
    'any_of' => 'Kolom :attribute tidak valid.',
    'array' => 'Kolom :attribute harus berupa larik.',
    'ascii' => 'Kolom :attribute hanya boleh berisi karakter dan simbol satu-byte.',
    'before' => 'Kolom :attribute harus berisi tanggal sebelum :date.',
    'before_or_equal' => 'Kolom :attribute harus berisi tanggal sebelum atau sama dengan :date.',
    'between' => [
        'array' => 'Kolom :attribute harus berisi antara :min sampai :max item.',
        'file' => 'Ukuran berkas :attribute harus antara :min sampai :max kilobyte.',
        'numeric' => 'Kolom :attribute harus bernilai antara :min sampai :max.',
        'string' => 'Kolom :attribute harus berisi antara :min sampai :max karakter.',
    ],
    'boolean' => 'Kolom :attribute harus bernilai ya atau tidak.',
    'can' => 'Kolom :attribute berisi nilai yang tidak diizinkan.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'contains' => 'Kolom :attribute belum memuat nilai yang diwajibkan.',
    'current_password' => 'Kata sandi yang Anda masukkan salah.',
    'date' => 'Kolom :attribute harus berisi tanggal yang valid.',
    'date_equals' => 'Kolom :attribute harus berisi tanggal yang sama dengan :date.',
    'date_format' => 'Kolom :attribute harus mengikuti format :format.',
    'decimal' => 'Kolom :attribute harus memiliki :decimal angka di belakang koma.',
    'declined' => 'Kolom :attribute harus ditolak.',
    'declined_if' => 'Kolom :attribute harus ditolak bila :other bernilai :value.',
    'different' => 'Kolom :attribute dan :other harus berbeda.',
    'digits' => 'Kolom :attribute harus terdiri dari :digits angka.',
    'digits_between' => 'Kolom :attribute harus terdiri dari :min sampai :max angka.',
    'dimensions' => 'Dimensi gambar :attribute tidak sesuai.',
    'distinct' => 'Kolom :attribute berisi nilai yang terduplikasi.',
    'doesnt_contain' => 'Kolom :attribute tidak boleh memuat salah satu dari: :values.',
    'doesnt_end_with' => 'Kolom :attribute tidak boleh diakhiri dengan salah satu dari: :values.',
    'doesnt_start_with' => 'Kolom :attribute tidak boleh diawali dengan salah satu dari: :values.',
    'email' => 'Kolom :attribute harus berupa alamat email yang valid.',
    'encoding' => 'Kolom :attribute harus memakai pengodean :encoding.',
    'ends_with' => 'Kolom :attribute harus diakhiri dengan salah satu dari: :values.',
    'enum' => 'Pilihan :attribute tidak valid.',
    'exists' => 'Pilihan :attribute tidak valid.',
    'extensions' => 'Berkas :attribute harus berekstensi salah satu dari: :values.',
    'file' => 'Kolom :attribute harus berupa berkas.',
    'filled' => 'Kolom :attribute wajib diisi.',
    'gt' => [
        'array' => 'Kolom :attribute harus berisi lebih dari :value item.',
        'file' => 'Ukuran berkas :attribute harus lebih dari :value kilobyte.',
        'numeric' => 'Kolom :attribute harus bernilai lebih dari :value.',
        'string' => 'Kolom :attribute harus berisi lebih dari :value karakter.',
    ],
    'gte' => [
        'array' => 'Kolom :attribute harus berisi :value item atau lebih.',
        'file' => 'Ukuran berkas :attribute harus :value kilobyte atau lebih.',
        'numeric' => 'Kolom :attribute harus bernilai :value atau lebih.',
        'string' => 'Kolom :attribute harus berisi :value karakter atau lebih.',
    ],
    'hex_color' => 'Kolom :attribute harus berupa kode warna heksadesimal yang valid.',
    'image' => 'Kolom :attribute harus berupa gambar.',
    'in' => 'Pilihan :attribute tidak valid.',
    'in_array' => 'Kolom :attribute harus ada di dalam :other.',
    'in_array_keys' => 'Kolom :attribute harus memuat setidaknya salah satu kunci berikut: :values.',
    'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
    'ip' => 'Kolom :attribute harus berupa alamat IP yang valid.',
    'ipv4' => 'Kolom :attribute harus berupa alamat IPv4 yang valid.',
    'ipv6' => 'Kolom :attribute harus berupa alamat IPv6 yang valid.',
    'json' => 'Kolom :attribute harus berupa teks JSON yang valid.',
    'list' => 'Kolom :attribute harus berupa daftar.',
    'lowercase' => 'Kolom :attribute harus ditulis dengan huruf kecil.',
    'lt' => [
        'array' => 'Kolom :attribute harus berisi kurang dari :value item.',
        'file' => 'Ukuran berkas :attribute harus kurang dari :value kilobyte.',
        'numeric' => 'Kolom :attribute harus bernilai kurang dari :value.',
        'string' => 'Kolom :attribute harus berisi kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => 'Kolom :attribute tidak boleh berisi lebih dari :value item.',
        'file' => 'Ukuran berkas :attribute tidak boleh lebih dari :value kilobyte.',
        'numeric' => 'Kolom :attribute tidak boleh bernilai lebih dari :value.',
        'string' => 'Kolom :attribute tidak boleh berisi lebih dari :value karakter.',
    ],
    'mac_address' => 'Kolom :attribute harus berupa alamat MAC yang valid.',
    'max' => [
        'array' => 'Kolom :attribute tidak boleh berisi lebih dari :max item.',
        'file' => 'Ukuran berkas :attribute tidak boleh lebih dari :max kilobyte.',
        'numeric' => 'Kolom :attribute tidak boleh bernilai lebih dari :max.',
        'string' => 'Kolom :attribute tidak boleh lebih dari :max karakter.',
    ],
    'max_digits' => 'Kolom :attribute tidak boleh terdiri dari lebih dari :max angka.',
    'mimes' => 'Berkas :attribute harus bertipe: :values.',
    'mimetypes' => 'Berkas :attribute harus bertipe: :values.',
    'min' => [
        'array' => 'Kolom :attribute harus berisi setidaknya :min item.',
        'file' => 'Ukuran berkas :attribute harus setidaknya :min kilobyte.',
        'numeric' => 'Kolom :attribute harus bernilai setidaknya :min.',
        'string' => 'Kolom :attribute harus berisi setidaknya :min karakter.',
    ],
    'min_digits' => 'Kolom :attribute harus terdiri dari setidaknya :min angka.',
    'missing' => 'Kolom :attribute tidak boleh ada.',
    'missing_if' => 'Kolom :attribute tidak boleh ada bila :other bernilai :value.',
    'missing_unless' => 'Kolom :attribute tidak boleh ada kecuali :other bernilai :value.',
    'missing_with' => 'Kolom :attribute tidak boleh ada bila :values terisi.',
    'missing_with_all' => 'Kolom :attribute tidak boleh ada bila :values semuanya terisi.',
    'multiple_of' => 'Kolom :attribute harus berupa kelipatan dari :value.',
    'not_in' => 'Pilihan :attribute tidak valid.',
    'not_regex' => 'Format kolom :attribute tidak sesuai.',
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'password' => [
        'letters' => 'Kolom :attribute harus memuat setidaknya satu huruf.',
        'mixed' => 'Kolom :attribute harus memuat setidaknya satu huruf besar dan satu huruf kecil.',
        'numbers' => 'Kolom :attribute harus memuat setidaknya satu angka.',
        'symbols' => 'Kolom :attribute harus memuat setidaknya satu simbol.',
        'uncompromised' => ':attribute yang Anda pilih pernah muncul dalam kebocoran data. Silakan pilih yang lain.',
    ],
    'present' => 'Kolom :attribute harus ada.',
    'present_if' => 'Kolom :attribute harus ada bila :other bernilai :value.',
    'present_unless' => 'Kolom :attribute harus ada kecuali :other bernilai :value.',
    'present_with' => 'Kolom :attribute harus ada bila :values terisi.',
    'present_with_all' => 'Kolom :attribute harus ada bila :values semuanya terisi.',
    'prohibited' => 'Kolom :attribute tidak diizinkan.',
    'prohibited_if' => 'Kolom :attribute tidak diizinkan bila :other bernilai :value.',
    'prohibited_if_accepted' => 'Kolom :attribute tidak diizinkan bila :other disetujui.',
    'prohibited_if_declined' => 'Kolom :attribute tidak diizinkan bila :other ditolak.',
    'prohibited_unless' => 'Kolom :attribute tidak diizinkan kecuali :other termasuk dalam :values.',
    'prohibits' => 'Kolom :attribute membuat :other tidak boleh diisi.',
    'regex' => 'Format kolom :attribute tidak sesuai.',
    'required' => 'Kolom :attribute wajib diisi.',
    'required_array_keys' => 'Kolom :attribute harus memuat entri untuk: :values.',
    'required_if' => 'Kolom :attribute wajib diisi bila :other bernilai :value.',
    'required_if_accepted' => 'Kolom :attribute wajib diisi bila :other disetujui.',
    'required_if_declined' => 'Kolom :attribute wajib diisi bila :other ditolak.',
    'required_unless' => 'Kolom :attribute wajib diisi kecuali :other termasuk dalam :values.',
    'required_with' => 'Kolom :attribute wajib diisi bila :values terisi.',
    'required_with_all' => 'Kolom :attribute wajib diisi bila :values semuanya terisi.',
    'required_without' => 'Kolom :attribute wajib diisi bila :values tidak terisi.',
    'required_without_all' => 'Kolom :attribute wajib diisi bila tidak satu pun dari :values terisi.',
    'same' => 'Kolom :attribute harus sama dengan :other.',
    'size' => [
        'array' => 'Kolom :attribute harus berisi :size item.',
        'file' => 'Ukuran berkas :attribute harus :size kilobyte.',
        'numeric' => 'Kolom :attribute harus bernilai :size.',
        'string' => 'Kolom :attribute harus berisi :size karakter.',
    ],
    'starts_with' => 'Kolom :attribute harus diawali dengan salah satu dari: :values.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'timezone' => 'Kolom :attribute harus berupa zona waktu yang valid.',
    'unique' => ':attribute sudah terpakai.',
    'uploaded' => 'Berkas :attribute gagal diunggah.',
    'uppercase' => 'Kolom :attribute harus ditulis dengan huruf besar.',
    'url' => 'Kolom :attribute harus berupa URL yang valid.',
    'ulid' => 'Kolom :attribute harus berupa ULID yang valid.',
    'uuid' => 'Kolom :attribute harus berupa UUID yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Validasi Khusus
    |--------------------------------------------------------------------------
    |
    | Untuk kasus yang kalimat umumnya terasa kaku atau kurang menolong.
    | Gunakan sehemat mungkin: makin banyak kekhususan di sini, makin mudah
    | pesan yang satu berbeda nada dari yang lain.
    |
    */

    'custom' => [
        'email' => [
            'unique' => 'Email ini sudah dipakai akun lain.',
        ],
        'password' => [
            'confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'min' => [
                'string' => 'Kata sandi harus terdiri dari setidaknya :min karakter.',
            ],
        ],
        'agreed' => [
            'accepted' => 'Anda perlu mencentang persetujuan untuk melanjutkan.',
        ],
        'proof' => [
            'required' => 'Pilih berkas bukti transfer terlebih dahulu.',
            'mimes' => 'Bukti transfer harus berupa gambar (JPG/PNG) atau PDF.',
            'max' => [
                'file' => 'Ukuran bukti transfer tidak boleh lebih dari :max kilobyte.',
            ],
        ],
        'effective_from' => [
            'after_or_equal' => 'Tanggal berlaku tidak boleh mundur ke masa lalu — aturan yang berlaku surut akan mengubah dasar harga periode yang sudah ditagihkan.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nama Kolom
    |--------------------------------------------------------------------------
    |
    | Menggantikan nama teknis dengan nama yang dikenali pengguna. Tanpa ini,
    | pesan berbahasa Indonesia tetap akan menyebut "business_name" atau
    | "opening_amount" — separuh terjemahan yang justru terasa lebih janggal
    | daripada tidak diterjemahkan sama sekali.
    |
    | Disusun dari kolom yang benar-benar divalidasi di aplikasi ini, bukan
    | daftar umum.
    |
    */

    'attributes' => [

        // Akun & autentikasi
        'name' => 'Nama',
        'email' => 'Email',
        'password' => 'Kata Sandi',
        'password_confirmation' => 'Konfirmasi Kata Sandi',
        'token' => 'Token',
        'business_name' => 'Nama Usaha',
        'role_name' => 'Role',
        'modules' => 'Modul',
        'modules.*' => 'Modul',
        'is_active' => 'Status Aktif',

        // Profil usaha
        'address' => 'Alamat',
        'phone' => 'Nomor Telepon',
        'logo' => 'Logo',
        'ai_provider' => 'Penyedia AI',
        'ai_api_key' => 'Kunci API AI',
        'ai_model' => 'Model AI',

        // Produk & katalog
        'category_id' => 'Kategori',
        'product_id' => 'Produk',
        'variant_id' => 'Varian',
        'sku' => 'SKU',
        'price' => 'Harga',
        'cost_price' => 'Harga Modal',
        'stock' => 'Stok',
        'min_stock' => 'Stok Minimum',
        'expiry_date' => 'Tanggal Kedaluwarsa',
        'is_required' => 'Wajib Dipilih',
        'is_multiple' => 'Boleh Pilih Banyak',
        'extra_price' => 'Harga Tambahan',
        'modifier_group_ids' => 'Grup Modifier',
        'variants' => 'Varian',
        'modifiers' => 'Modifier',

        // Transaksi & kas
        'items' => 'Item',
        'qty' => 'Jumlah',
        'unit_price' => 'Harga Satuan',
        'total_amount' => 'Total',
        'payments' => 'Pembayaran',
        'payment_method_id' => 'Metode Pembayaran',
        'amount' => 'Nominal',
        'reference_code' => 'Kode Referensi',
        'notes' => 'Catatan',
        'reason' => 'Alasan',
        'customer_name' => 'Nama Pelanggan',
        'is_open_bill' => 'Bill Terbuka',
        'client_uuid' => 'ID Perangkat',
        'occurred_at' => 'Waktu Transaksi',
        'device_id' => 'ID Perangkat',
        'transactions' => 'Transaksi',
        'opening_amount' => 'Modal Awal Kas',
        'closing_amount' => 'Kas Akhir',

        // Laporan & AI
        'type' => 'Jenis',
        'from' => 'Dari Tanggal',
        'to' => 'Sampai Tanggal',
        'prompt' => 'Pertanyaan',
        'action' => 'Aksi',
        'severity' => 'Derajat',

        // Langganan & pembayaran
        'tenant_id' => 'Tenant',
        'period' => 'Periode',
        'due_date' => 'Jatuh Tempo',
        'proof' => 'Bukti Transfer',
        'additional_seats' => 'Jumlah Pengguna Tambahan',
        'version' => 'Versi Dokumen',
        'agreed' => 'Persetujuan',

        // Aturan harga
        'label' => 'Kelompok',
        'slug' => 'Slug',
        'min_revenue' => 'Omzet Minimum',
        'max_revenue' => 'Omzet Maksimum',
        'base_price' => 'Tarif Dasar',
        'included_seats' => 'Seat Termasuk',
        'extra_seat_price' => 'Tarif Seat Tambahan',
        'effective_from' => 'Tanggal Berlaku',
    ],

];
