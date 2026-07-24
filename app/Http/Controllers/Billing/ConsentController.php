<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\TenantConsent;
use App\Services\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

/**
 * Halaman persetujuan jalur harga normal.
 *
 * Jalur subsidi punya dokumennya sendiri dan menyusul di Tahap C. Dipisah
 * sejak awal, bukan satu dokumen dengan pasal bersyarat: yang paling penting
 * dari masing-masing justru saling bertolak belakang.
 */
class ConsentController extends Controller
{
    public function __construct(private readonly ConsentService $consents) {}

    public function show(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        $document = $this->consents->document(TenantConsent::TYPE_NORMAL);
        $agreed = $this->consents->latestFor($tenant, TenantConsent::TYPE_NORMAL);

        return inertia('Billing/Consent', [
            // Markdown dirender di server dengan league/commonmark bawaan
            // Laravel. Teksnya berkas milik kita sendiri, bukan masukan
            // pengguna, jadi menyuntikkannya sebagai HTML aman di sini — dan
            // jauh lebih baik daripada menyalin penerjemah markdown kedua ke
            // sisi klien.
            'document' => [
                'version' => $document['version'],
                'html' => Str::markdown($document['body']),
            ],
            'agreement' => $agreed === null ? null : [
                'version' => $agreed->version,
                'agreed_at' => $agreed->agreed_at->toDateString(),
                'is_current' => $agreed->version === $document['version'],
            ],
            'can_agree' => $request->user()->isOwner(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            // Dikirim oleh klien dan dicocokkan di server. Tanpa ini, halaman
            // yang dibiarkan terbuka berhari-hari bisa mencatat persetujuan
            // atas versi yang sudah diganti sementara itu.
            'version' => ['required', 'string'],
            'agreed' => ['required', 'accepted'],
        ]);

        $tenant = $request->user()->tenant;
        $current = $this->consents->currentVersion(TenantConsent::TYPE_NORMAL);

        if ($request->input('version') !== $current) {
            return back()->with('error', 'Teks persetujuan sudah diperbarui. Silakan baca ulang versi terbarunya.');
        }

        $this->consents->record(
            $tenant,
            $request->user(),
            TenantConsent::TYPE_NORMAL,
            $request->ip(),
        );

        return redirect()->route('billing.show')->with('success', 'Persetujuan tercatat. Terima kasih.');
    }
}
