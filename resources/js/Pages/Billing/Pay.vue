<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';

// Cangkang yang sama dengan halaman langganan — halaman ini kelanjutannya, dan
// pindah ke cangkang lain di tengah alur bayar membuatnya terasa seperti situs
// pihak ketiga. Tenant yang ditangguhkan tetap bisa membukanya: namanya
// `billing.*`, yang selalu diizinkan EnsureSubscriptionActive.
defineOptions({ layout: OwnerLayout });

const props = defineProps({
    invoice: { type: Object, required: true },
    channels: { type: Array, required: true },
    gateway: { type: Object, required: true },
});

const formatRupiah = (value) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);

const formatDate = (value) => {
    if (!value) return null;
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
};

const form = useForm({ channel: props.channels[0]?.code ?? null });

const submit = () => form.post(`/langganan/tagihan/${props.invoice.id}/bayar`, { preserveScroll: true });
</script>

<template>
    <Head title="Bayar Tagihan" />

    <div class="p-4 sm:p-6 lg:p-8">
        <div class="max-w-2xl mx-auto">
            <Link href="/langganan" class="text-sm text-muted-foreground hover:text-foreground">← Kembali ke Langganan</Link>

            <h1 class="mt-3 text-2xl font-semibold text-foreground">Bayar Tagihan</h1>

            <!--
                Peragaan tetap disebut, tapi satu baris. Kotak peringatan besar
                di atas layar bayar membuat seluruh alurnya terbaca sebagai
                maket; yang perlu tersampaikan cuma bahwa uangnya belum nyata.
            -->
            <p v-if="gateway.is_simulated" class="mt-3 text-xs text-muted-foreground">
                Mode peragaan — kanal di bawah ini tiruan dan tidak ada uang yang berpindah.
            </p>

            <div class="mt-4 rounded-xl border border-border bg-card p-5">
                <p class="text-sm text-muted-foreground">
                    {{ invoice.kind === 'upgrade' ? 'Tambah pengguna' : 'Langganan' }} · {{ invoice.period }}
                </p>
                <p class="mt-1 text-3xl font-semibold text-foreground tabular-nums">{{ formatRupiah(invoice.amount) }}</p>
                <p v-if="invoice.due_date" class="mt-1 text-xs text-muted-foreground">
                    Jatuh tempo {{ formatDate(invoice.due_date) }}
                </p>
            </div>

            <form class="mt-4" @submit.prevent="submit">
                <fieldset>
                    <legend class="text-sm font-medium text-foreground">Pilih cara membayar</legend>

                    <div class="mt-2 space-y-2">
                        <label
                            v-for="channel in channels"
                            :key="channel.code"
                            class="flex items-start gap-3 rounded-xl border p-4 cursor-pointer transition-colors"
                            :class="form.channel === channel.code
                                ? 'border-primary bg-primary/5'
                                : 'border-border bg-card hover:bg-accent/40'"
                        >
                            <input
                                v-model="form.channel"
                                type="radio"
                                name="channel"
                                :value="channel.code"
                                class="mt-0.5 accent-primary"
                            />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-foreground">{{ channel.label }}</span>
                                <span class="block text-xs text-muted-foreground">{{ channel.hint }}</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <p v-if="form.errors.channel" role="alert" class="mt-2 text-xs text-destructive">
                    {{ form.errors.channel }}
                </p>

                <button
                    type="submit"
                    :disabled="form.processing || !form.channel"
                    class="mt-5 w-full rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                >
                    {{ form.processing ? 'Menerbitkan instruksi…' : 'Lanjutkan' }}
                </button>
            </form>

            <p class="mt-4 text-xs text-muted-foreground leading-relaxed">
                Lebih suka transfer biasa? Kembali ke halaman langganan dan unggah bukti transfernya — kami
                periksa menyusul.
            </p>
        </div>
    </div>
</template>
