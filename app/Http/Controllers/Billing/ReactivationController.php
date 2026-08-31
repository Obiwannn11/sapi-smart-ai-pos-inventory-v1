<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Permintaan tenant yang ditangguhkan untuk mendapatkan tagihan pemulihannya —
 * `[BL-051]`, opsi (ii); keputusan pemilik 2026-08-31.
 *
 * Berdiri sendiri, tidak menumpang `UpgradeController`, meski alurnya dipinjam
 * dari sana. Yang di sana menaikkan kapasitas tenant yang sedang berjalan; yang
 * di sini adalah satu-satunya tindakan yang bisa diambil tenant yang seluruh
 * aplikasinya sudah tertutup. Menyatukan keduanya berarti satu penjaga salah
 * pasang di kelas itu bisa menutup jalan pulang tanpa ada yang menyadarinya.
 *
 * Seluruh keputusan — boleh atau tidak, berapa, periode mana — ada di
 * `SubscriptionService::issueReactivationInvoice()`. Yang tinggal di sini hanya
 * penerjemahan hasilnya menjadi kalimat, dan itu disengaja: keadaan yang
 * ditolak ada empat, masing-masing dengan jalan keluar yang berbeda, dan satu
 * kalimat untuk keempatnya akan salah pada tiga di antaranya.
 */
class ReactivationController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $result = $this->subscriptions->issueReactivationInvoice($user->tenant, $user);

        return match ($result['status']) {
            SubscriptionService::REACTIVATION_ISSUED => back()->with('success', sprintf(
                'Tagihan pemulihan sebesar %s sudah terbit untuk periode %s. Begitu pembayarannya masuk, '
                .'akses Anda terbuka kembali dan periode langganan berlanjut — data Anda tetap seperti semula.',
                'Rp '.number_format((float) $result['invoice']->amount, 0, ',', '.'),
                $result['invoice']->period,
            )),

            // Bukan kegagalan, dan tidak diberi nada kegagalan: tenant menekan
            // tombol yang memang sudah tidak ia butuhkan.
            SubscriptionService::REACTIVATION_ALREADY_INVOICED => back()->with(
                'error',
                'Anda sudah punya tagihan untuk periode ini — ada di daftar Tagihan di bawah. '
                .'Selesaikan yang itu, dan akses Anda terbuka kembali.',
            ),

            SubscriptionService::REACTIVATION_NOT_SUSPENDED => back()->with(
                'error',
                'Langganan Anda tidak sedang ditangguhkan, jadi tidak ada yang perlu dipulihkan.',
            ),

            // Tiga sisa di bawah adalah tempat opsi (i) `[BL-051]` tetap
            // berlaku: yang menghalangi tenant ini bukan uang, jadi tak ada
            // tagihan yang bisa menjawabnya. Kalimatnya menyebut apa yang
            // sebenarnya terjadi, bukan "terjadi kesalahan" — tenant yang tidak
            // tahu apa yang harus ia katakan saat menghubungi pengelola akan
            // menghabiskan satu putaran percakapan hanya untuk sampai ke sini.
            SubscriptionService::REACTIVATION_NOT_READY => back()->with(
                'error',
                'Tarif Anda belum bisa dihitung untuk periode ini karena ringkasan omzet penentunya belum ada. '
                .'Hubungi pengelola layanan — kami tidak ingin menerbitkan tagihan dengan angka yang belum tentu benar.',
            ),

            // `unpriced`, `free`, dan apa pun yang kelak ditambahkan tanpa
            // sempat sampai ke sini. Satu kalimat untuk ketiganya, dan itu
            // bukan kemalasan: dari tempat duduk tenant, "tarifnya nol" dan
            // "tarifnya tidak ada" adalah keadaan yang sama persis — tak ada
            // angka yang bisa ia transfer — dan jalan keluarnya pun sama.
            default => back()->with(
                'error',
                'Tarif langganan Anda belum ditetapkan, jadi belum ada tagihan yang bisa diterbitkan. '
                .'Hubungi pengelola layanan untuk mengaktifkan kembali akun Anda.',
            ),
        };
    }
}
