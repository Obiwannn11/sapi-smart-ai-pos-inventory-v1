import { computed, toValue } from 'vue';

/**
 * Membaca satu blok kuota AI (`AiQuota::snapshotFor()`) menjadi keadaan yang
 * bisa dipakai layar.
 *
 * Berdiri sendiri, bukan di dalam `AiQuotaMeter.vue`, karena keadaannya dibaca
 * DUA hal yang harus sepakat: meternya, dan tombol "Analisa" yang dimatikan
 * saat jatahnya nol. Selama keduanya menyimpulkan sendiri-sendiri, cepat atau
 * lambat akan ada layar yang menampilkan "kuota habis" di atas tombol yang
 * masih bisa ditekan — versi kecil dari cacat yang sama seperti `[BL-062]`.
 *
 * @param {import('vue').MaybeRefOrGetter<object|null>} quota
 */
export function useAiQuota(quota) {
    const snapshot = computed(() => toValue(quota) ?? {});

    const limit = computed(() => Number(snapshot.value.daily_limit ?? 0));
    const used = computed(() => Number(snapshot.value.used ?? 0));
    const remaining = computed(() => Number(snapshot.value.remaining ?? 0));

    // `!== false`, bukan `=== true`: bila blok kuotanya belum sampai (prop
    // partial reload, atau layar lama yang belum dikirimi), anggap tenant ini
    // memakai jatah gratis. Salah menduga BYOK berarti menyembunyikan meternya
    // dari orang yang justru dijatah.
    const usingFreeTier = computed(() => snapshot.value.using_free_tier !== false);

    /**
     * Lima keadaan, dan urutan pemeriksaannya menentukan: BYOK lebih dulu
     * daripada apa pun, karena tenant berkunci sendiri tidak punya batas untuk
     * dihabiskan. "Sisa 0 dari 5" di layarnya adalah cacat yang sudah
     * diperingatkan lebih dulu di `[BL-062]`.
     */
    const state = computed(() => {
        if (!usingFreeTier.value) {
            return 'byok';
        }
        if (limit.value <= 0) {
            return 'unavailable';
        }
        if (remaining.value <= 0) {
            return 'empty';
        }

        return remaining.value / limit.value <= 0.34 ? 'low' : 'ok';
    });

    /** Permintaan baru pasti ditolak — tombolnya tidak boleh bisa ditekan. */
    const isBlocked = computed(() => state.value === 'empty' || state.value === 'unavailable');

    const percentUsed = computed(() => {
        if (limit.value <= 0) {
            return 100;
        }

        return Math.min(100, Math.round((used.value / limit.value) * 100));
    });

    return { snapshot, limit, used, remaining, usingFreeTier, state, isBlocked, percentUsed };
}
