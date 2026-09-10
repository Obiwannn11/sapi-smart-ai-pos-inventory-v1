<script setup>
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { useLogoutConfirm } from '@/composables/useLogoutConfirm';

/**
 * Dialog tunggal untuk seluruh cangkang.
 *
 * Dipasang sekali per layout; tombol keluar mana pun cukup memanggil
 * `requestLogout()` dari `useLogoutConfirm` tanpa perlu menyimpan keadaannya
 * sendiri. Judul dan kalimatnya datang dari pemanggil karena taruhannya
 * berbeda — kasir kehilangan cache offline, admin platform tidak.
 */
const { request, isConfirming, processing, confirmLogout, cancelLogout } = useLogoutConfirm();
</script>

<template>
    <ConfirmDialog
        :show="isConfirming"
        :title="request?.title ?? 'Keluar dari akun?'"
        :message="request?.message ?? ''"
        :confirm-text="processing ? 'Keluar…' : 'Keluar'"
        cancel-text="Batal"
        variant="warning"
        @confirm="confirmLogout"
        @cancel="cancelLogout"
    />
</template>
