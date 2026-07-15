<script setup>
import ErrorState from '@/Components/ErrorState.vue';

/**
 * Error page shown to admin (owner) users. Primary action returns to the
 * owner dashboard.
 */
defineProps({
    status: { type: Number, default: 500 },
});
</script>

<template>
    <ErrorState
        :status="status"
        home-href="/owner/dashboard"
        home-label="Kembali ke Dashboard"
    />
</template>
