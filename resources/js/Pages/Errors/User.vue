<script setup>
import ErrorState from '@/Components/ErrorState.vue';

/**
 * Error page shown to cashier (user) staff. Larger touch targets suit the POS
 * context; the primary action returns to the cashier screen.
 */
defineProps({
    status: { type: Number, default: 500 },
});
</script>

<template>
    <ErrorState
        :status="status"
        home-href="/cashier/pos"
        home-label="Kembali ke Kasir"
        large
    />
</template>
