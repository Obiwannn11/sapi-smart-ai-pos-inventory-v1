<script setup>
/**
 * Nav tab untuk halaman rincian panel platform.
 *
 * Murni di sisi klien — berpindah tab tidak memuat ulang apa pun, karena
 * seluruh isinya sudah ikut di payload halaman. Yang tidak boleh dilihat
 * pembacanya tidak dikirim sama sekali, jadi tab-nya pun tidak ada untuk
 * ditekan; menyaringnya di sini bukan tugas komponen ini.
 */
defineProps({
    // [{ key, label, badge? }] — `badge` untuk angka kecil di sebelah label.
    tabs: { type: Array, required: true },
    modelValue: { type: String, required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="border-b border-border overflow-x-auto">
        <nav class="flex gap-1 -mb-px" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="modelValue === tab.key"
                :class="[
                    'inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors',
                    modelValue === tab.key
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border',
                ]"
                @click="$emit('update:modelValue', tab.key)"
            >
                {{ tab.label }}

                <span
                    v-if="tab.badge"
                    :class="[
                        'inline-flex items-center justify-center min-w-5 px-1.5 rounded-full text-xs tabular-nums',
                        modelValue === tab.key ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground',
                    ]"
                >
                    {{ tab.badge }}
                </span>
            </button>
        </nav>
    </div>
</template>
