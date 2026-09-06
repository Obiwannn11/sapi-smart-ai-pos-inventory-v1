<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashDrawerRequest;
use App\Http\Requests\OpenCashDrawerRequest;
use App\Http\Requests\StoreCashDrawerMovementRequest;
use App\Models\CashDrawer;
use App\Models\CashDrawerMovement;
use App\Models\CashDrawerReveal;
use App\Services\CashDrawerReconciliation;
use App\Services\CashMovementProofService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CashDrawerController extends Controller
{
    public function __construct(
        private CashDrawerReconciliation $reconciliation,
        private CashMovementProofService $movementProofs,
    ) {}

    /**
     * Halaman cash drawer — form buka kas atau summary sesi aktif.
     * Sesi kas hanya untuk kasir; owner diarahkan ke POS.
     */
    public function index(): Response|RedirectResponse
    {
        if (! Auth::user()->isCashier()) {
            return redirect()->route('cashier.pos');
        }

        $openDrawer = CashDrawer::openFor(Auth::id());

        return Inertia::render('Cashier/CashDrawer', [
            'openDrawer' => $openDrawer,
            // Angka ini bergerak selama shift berjalan, jadi halaman memuatnya
            // ulang (partial reload) tepat sebelum menampilkan ringkasan —
            // pratinjau yang basi akan berbeda dari hasil close() dan justru
            // membuat kasir tidak percaya keduanya.
            'reconciliation' => $openDrawer
                ? $this->reconciliation->for($openDrawer)
                : null,

            // Umur sesi ([BL-088]). Dikirim sebagai DUA titik waktu, bukan
            // sebagai "sisa berapa jam": angka sisa dihitung saat halaman
            // dirender dan langsung basi pada tab yang dibiarkan terbuka
            // semalaman — persis tab yang paling butuh peringatan ini.
            'sessionLimit' => $openDrawer ? [
                'hours' => CashDrawer::MAX_SESSION_HOURS,
                'expires_at' => $openDrawer->opened_at->copy()->addHours(CashDrawer::MAX_SESSION_HOURS),
                'warn_from' => $openDrawer->opened_at->copy()
                    ->addHours(CashDrawer::MAX_SESSION_HOURS - CashDrawer::STALE_WARNING_HOURS),
            ] : null,

            // Mutasi kas sesi ini ([BL-087]). Dikirim UTUH termasuk yang
            // ditolak: kasir yang pengeluarannya tidak disetujui harus melihat
            // penolakannya di layar yang sama tempat ia mencatatnya, bukan
            // menemukannya sebagai selisih tak terjelaskan saat tutup kas.
            'movements' => $openDrawer
                ? $openDrawer->movements()->with('user:id,name')->latest('id')->get()
                : [],

            // Ambangnya ikut ke layar supaya kasir tahu SEBELUM mengetik bahwa
            // nominal segini akan menunggu persetujuan. Angka yang baru
            // diketahui sesudah tombol simpan ditekan terbaca sebagai
            // penolakan, bukan sebagai aturan.
            'payoutThreshold' => (float) Auth::user()->tenant->cash_payout_approval_threshold,
        ]);
    }

    /**
     * Catat bahwa kasir membuka angka "seharusnya di laci" ([BL-090]).
     *
     * **Mencatat, bukan menolak** — itu keputusan pemilik 2026-08-21 di antara
     * dua bentuk yang ditawarkan entri backlognya. Bentuk yang ditolak
     * (endpoint yang menahan angkanya sampai hitungan fisik masuk) akan
     * mematikan tombol peragaan yang pemilik sendiri minta; bentuk ini
     * membiarkan tombolnya hidup dan membuat pemakaiannya terlihat.
     *
     * Dijawab `back()` tanpa muatan: klien sudah membuka angkanya sendiri dan
     * tidak menunggu apa pun dari sini. Kegagalan mencatat tidak boleh
     * membatalkan pengungkapan yang sudah terjadi di layar — jejak yang salah
     * lebih buruk daripada jejak yang tidak lengkap.
     */
    public function reveal(): RedirectResponse
    {
        $user = Auth::user();

        $openDrawer = $user ? CashDrawer::openFor($user) : null;

        // Tanpa sesi terbuka tidak ada angka yang bisa dibuka, jadi tidak ada
        // yang perlu dicatat. Diam-diam saja: ini bukan kesalahan pengguna,
        // melainkan permintaan yang datang terlambat (sesi baru saja ditutup
        // di perangkat lain).
        if ($openDrawer) {
            CashDrawerReveal::create([
                'tenant_id' => $openDrawer->tenant_id,
                'cash_drawer_id' => $openDrawer->id,
                'user_id' => $user->id,
                'revealed_at' => now(),
            ]);
        }

        return back();
    }

    /**
     * Catat uang keluar dari laci atau masuk ke laci di luar penjualan
     * ([BL-087]).
     *
     * Fotonya OPSIONAL ([BL-093]). Untuk pengeluaran yang punya struk ia
     * mengubah "katanya beli galon" jadi sesuatu yang bisa dicocokkan; untuk
     * yang tidak punya struk — parkir, tukar receh — mewajibkannya hanya akan
     * menghentikan pencatatannya sama sekali.
     *
     * **Kasir selalu boleh mencatat.** Yang bergantung pada ambang tenant
     * hanyalah apakah angkanya langsung menggerakkan `expected_amount` atau
     * menunggu pemilik. Menolak pencatatannya justru akan mengosongkan catatan
     * kas: kasir yang tidak bisa mencatat pengeluaran tetap mengeluarkan
     * uangnya, dan selisihnya muncul di akhir shift tanpa keterangan apa pun —
     * keadaan yang persis kita tinggalkan hari ini.
     */
    public function storeMovement(StoreCashDrawerMovementRequest $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user?->isCashier()) {
            abort(403, 'Hanya kasir yang dapat mencatat mutasi kas.');
        }

        $drawer = CashDrawer::openFor($user);

        if (! $drawer) {
            abort(404, 'Tidak ada sesi kas yang terbuka.');
        }

        $amount = (float) $request->validated('amount');
        $threshold = (float) $user->tenant->cash_payout_approval_threshold;

        // Setoran MASUK laci tidak pernah menunggu persetujuan. Ambangnya ada
        // untuk menahan uang yang KELUAR — menambah uang ke laci tidak bisa
        // dipakai menutupi kekurangan, ia justru memperbesar tuntutan terhadap
        // kasir sendiri.
        $autoApproved = $request->validated('type') === CashDrawerMovement::TYPE_DEPOSIT
            || $amount <= $threshold;

        CashDrawerMovement::create([
            'tenant_id' => $drawer->tenant_id,
            'cash_drawer_id' => $drawer->id,
            'user_id' => $user->id,
            'type' => $request->validated('type'),
            'amount' => $amount,
            'reason' => $request->validated('reason'),
            // Berkasnya ikut permintaan yang sama, bukan lewat unggahan
            // terpisah ([BL-093]). Formulir ini datar, jadi ia memang boleh
            // membawa berkasnya sendiri — dan bersama langkah kedua hilang
            // pula berkas terlantar yang harus dibersihkan belakangan.
            // Alasan lengkapnya di CashMovementProofService.
            'proof_path' => $request->hasFile('proof')
                ? $this->movementProofs->store($request->file('proof'), $drawer->tenant_id)
                : null,
            'status' => $autoApproved
                ? CashDrawerMovement::STATUS_APPROVED
                : CashDrawerMovement::STATUS_PENDING,
            // Dibiarkan kosong saat otomatis — itulah yang membedakan
            // persetujuan mesin dari persetujuan yang dibaca manusia.
            'reviewed_at' => $autoApproved ? now() : null,
        ]);

        return back()->with('success', $autoApproved
            ? 'Mutasi kas dicatat.'
            : 'Mutasi kas dicatat dan menunggu persetujuan pemilik. Uang seharusnya di laci belum berubah.');
    }

    /**
     * Halaman tutup kas — hitungan fisik, ringkasan, lalu konfirmasi.
     *
     * **Halaman tersendiri, bukan bagian bawah halaman sesi ([BL-086] butir 2).**
     * Membuka kas dan mempertanggungjawabkannya adalah dua pekerjaan yang
     * terpisah beberapa jam dan berbeda niat; menumpuknya di satu layar membuat
     * kasir melewati alur tutup kas setiap kali ia sekadar memeriksa sesinya,
     * dan membuat "menutup kas" terlihat seperti hal yang bisa dilakukan
     * sambil lalu.
     */
    public function showClose(): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! $user?->isCashier()) {
            return redirect()->route('cashier.pos');
        }

        $openDrawer = CashDrawer::openFor($user);

        // Tanpa sesi terbuka tidak ada yang bisa ditutup. Dikembalikan ke
        // halaman kas — di sana ada formulir membukanya — alih-alih 404, yang
        // hanya benar secara teknis.
        if (! $openDrawer) {
            return redirect()->route('cashier.cash-drawer.index');
        }

        return Inertia::render('Cashier/CashDrawerClose', [
            'openDrawer' => $openDrawer,
            'reconciliation' => $this->reconciliation->for($openDrawer),
            // Rincian mutasi ikut ke ringkasan ([BL-087]): angka gabungan
            // "uang keluar Rp 150.000" tanpa barisnya tidak bisa
            // dipertanggungjawabkan siapa pun sebulan kemudian.
            'movements' => $openDrawer->movements()->with('user:id,name')->latest('id')->get(),
        ]);
    }

    /**
     * Buka kas baru.
     */
    public function open(OpenCashDrawerRequest $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        // Hanya kasir yang boleh membuka sesi kas; owner tidak mengelola kas.
        if (! $user->isCashier()) {
            abort(403, 'Hanya kasir yang dapat membuka sesi kas.');
        }

        // Validasi: tidak boleh ada sesi terbuka
        if (CashDrawer::openFor($user)) {
            return back()->with('error', 'Anda masih memiliki sesi kas yang terbuka.');
        }

        CashDrawer::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'opening_amount' => $request->validated('opening_amount'),
            'opened_at' => now(),
        ]);

        return redirect()->route('cashier.pos')->with('success', 'Kas berhasil dibuka.');
    }

    /**
     * Tutup kas.
     */
    public function close(CloseCashDrawerRequest $request): RedirectResponse
    {
        $drawer = CashDrawer::openFor(Auth::id());

        if (! $drawer) {
            abort(404, 'Tidak ada sesi kas yang terbuka.');
        }

        $expectedAmount = $this->reconciliation->for($drawer)['expected_amount'];
        $closingAmount = $request->validated('closing_amount');

        $drawer->update([
            'closing_amount' => $closingAmount,
            'expected_amount' => $expectedAmount,
            'difference' => $closingAmount - $expectedAmount,
            'notes' => $request->validated('notes'),
            'closed_at' => now(),
        ]);

        return redirect()->route('cashier.cash-drawer.summary', $drawer)
            ->with('success', 'Kas berhasil ditutup.');
    }

    /**
     * Rekap sesi kas (detail per metode pembayaran).
     */
    public function summary(CashDrawer $cashDrawer): Response
    {
        $user = Auth::user();

        // Authorization: cashier hanya bisa lihat kas sendiri, owner bisa lihat semua
        if (! $user || ($user->isCashier() && $cashDrawer->user_id !== $user->id)) {
            abort(403, 'Anda tidak memiliki akses ke sesi kas ini.');
        }

        // Sumber angka yang sama dengan close(). Kalau rekap menghitung sendiri,
        // ia akan menampilkan angka berbeda dari yang barusan dipakai menutup
        // kas — lebih membingungkan daripada tidak menampilkannya sama sekali.
        $reconciliation = $this->reconciliation->for($cashDrawer);

        return Inertia::render('Cashier/CashDrawerSummary', [
            'cashDrawer' => $cashDrawer,
            'paymentSummary' => $reconciliation['payment_summary'],
            'transactionCount' => $reconciliation['transaction_count'],
            // Kas negatif sesi ini ([BL-031]). Ikut di rekap, bukan cuma di
            // pratinjau: rekap inilah yang dibuka lagi belakangan saat
            // seseorang bertanya ke mana perginya uang shift itu.
            'unsettledCash' => $reconciliation['unsettled_cash'],
            'unsettledCount' => $reconciliation['unsettled_count'],
        ]);
    }
}
