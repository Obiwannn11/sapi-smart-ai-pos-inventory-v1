<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, router, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import AiQuotaMeter from '@/Components/AiQuotaMeter.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { formatBusinessDate, formatBusinessDateTime } from '@/support/date';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    tenant: Object,
    // Blok yang sama yang dibaca AI Analysis, ditampilkan di sini dalam versi
    // lengkapnya (`[BL-062]`): di halaman ini angkanya adalah konteks untuk
    // keputusan BYOK, jadi ia dibedah, bukan cuma diringkas satu baris.
    aiQuota: Object,
    mcp: Object,
    // Link konektor [BL-102]: endpoint, pilihan masa berlaku, dan link aktif.
    // Rahasianya tidak pernah ikut di sini, hanya di flash sesaat setelah dibuat.
    connector: Object,
});

// Label penyedia ditulis sekali di sini supaya kolomnya bisa memakai
// SelectDropdown yang sama dengan halaman pengaturan lain.
const providerOptions = [
    { value: '',          label: 'Default (SumoPod gratis)' },
    { value: 'sumopod',   label: 'SumoPod' },
    { value: 'gemini',    label: 'Gemini' },
    { value: 'openai',    label: 'OpenAI' },
    { value: 'anthropic', label: 'Anthropic' },
];

const form = useForm({
    ai_provider: props.tenant.ai_provider ?? '',
    ai_model:    props.tenant.ai_model ?? '',
    ai_api_key:  '',
});

const submit = () => {
    form.patch('/owner/settings/integrations', { preserveScroll: true });
};

// --- MCP Access ---
const page = usePage();
const mcpToken = computed(() => page.props.flash?.mcpToken);
const generatingToken = ref(false);
const copied = ref('');

const copy = (text, label) => {
    navigator.clipboard?.writeText(text);
    copied.value = label;
    setTimeout(() => (copied.value = ''), 1500);
};

const generateMcpToken = () => {
    router.post('/owner/settings/integrations/mcp-token', {}, {
        preserveScroll: true,
        onStart: () => (generatingToken.value = true),
        onFinish: () => (generatingToken.value = false),
    });
};

const revokeMcpToken = () => {
    if (!confirm('Cabut token MCP? AI client yang memakainya akan langsung kehilangan akses.')) return;
    router.delete('/owner/settings/integrations/mcp-token', { preserveScroll: true });
};

// --- Link data untuk AI tanpa pemasangan ([BL-102]) ---
const connectorLink = computed(() => page.props.flash?.connectorLink);

const formatLinkDate = (value) =>
    formatBusinessDate(value, { day: 'numeric', month: 'short', year: 'numeric' });

const formatLinkDateTime = (value) =>
    formatBusinessDateTime(value, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

const expiryAfter = (days) => formatLinkDate(Date.now() + days * 86400000);

const showLinkDialog = ref(false);

const linkForm = useForm({
    label: '',
    lifetime_days: 30,
    acknowledged: false,
});

// Keputusan pemilik 2026-09-15: yang dicentang owner adalah tanggal
// sungguhan, bukan "7 atau 30 hari". Kalimatnya ikut berubah saat pilihannya
// berubah.
const linkExpiryPreview = computed(() => expiryAfter(linkForm.lifetime_days));

const openLinkDialog = () => {
    linkForm.reset();
    linkForm.clearErrors();
    showLinkDialog.value = true;
};

const createLink = () => {
    linkForm.post('/owner/settings/integrations/connector-links', {
        preserveScroll: true,
        onSuccess: () => {
            showLinkDialog.value = false;
            linkForm.reset();
        },
    });
};

// Prompt bawaan. Pembacanya AI pengguna, bukan owner. Keputusan pemilik
// 2026-09-15: dikirim sebagai SATU pesan tanpa pertanyaan, dan ditutup tes baca
// (nama usaha + produk) supaya owner tahu link benar-benar terbuka sebelum
// bertanya di pesan kedua. Kalimat "jangan menebak" yang membuat tes itu
// berarti: AI yang gagal membuka link bisa saja mengarang jawaban meyakinkan.
// Masih draf sampai diuji dengan link sungguhan di Claude, ChatGPT, dan Gemini.
const connectorPrompt = computed(() => {
    if (!connectorLink.value) {
        return '';
    }

    return [
        'Bantu saya menganalisis toko saya. Data penjualan, profit, produk terlaris, dan menu toko saya ada di link ini:',
        connectorLink.value.url,
        '',
        'Buka link itu dulu, lalu pakai hanya isinya untuk menjawab semua pertanyaan saya di chat ini. Kalau datanya tidak cukup untuk menjawab, katakan terus terang. Jangan tampilkan ulang link ini di jawaban.',
        '',
        'Untuk memastikan link sudah terbaca, balas pesan ini dengan:',
        '1. Nama usaha saya yang tercatat di data itu.',
        '2. Daftar produk yang saya jual.',
        '',
        'Kalau link gagal dibuka, katakan bahwa link gagal dibuka. Jangan menebak nama usaha atau produknya.',
        '',
        'Setelah itu, katakan bahwa kamu siap menerima pertanyaan. Pertanyaan pertama akan saya kirim di pesan berikutnya.',
    ].join('\n');
});

const linkToRevoke = ref(null);

const revokeLink = () => {
    router.delete(`/owner/settings/integrations/connector-links/${linkToRevoke.value.id}`, {
        preserveScroll: true,
        onFinish: () => (linkToRevoke.value = null),
    });
};
</script>

<template>
    <Head title="Integrasi & Kredensial" />

    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Integrasi &amp; Kredensial</h1>
            <p class="text-sm text-gray-500 mt-1">Kunci API penyedia AI dan token akses untuk AI client eksternal</p>
        </div>

        <SettingsNav current="integrations" />

        <!-- Modul mati: kunci yang tersimpan di sini tidak akan dipakai.
             Dikatakan terus terang supaya owner tidak menghabiskan waktu
             mencari kesalahan pada kuncinya. -->
        <div v-if="!tenant.ai_enabled" class="mb-6 flex gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
            <svg class="w-4 h-4 shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z" />
            </svg>
            <span>
                Modul <strong>AI Analysis &amp; MCP</strong> sedang mati, jadi apa pun yang disimpan di halaman ini belum dipakai.
                Nyalakan dulu di <a href="/owner/settings/operations" class="font-medium underline">Cara Kerja Sistem</a>.
            </span>
        </div>

        <!-- AI Analysis -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900">AI Analysis</h2>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">
                Pakai kunci API sendiri (BYOK) untuk pemakaian tanpa batas, atau biarkan kosong untuk memakai kuota gratis harian.
            </p>

            <!-- Keadaan kuota, versi lengkap. Tidak lagi disembunyikan
                 saat kunci BYOK terisi: justru di keadaan itu owner perlu
                 diberi tahu apa yang ia lepaskan, dan apa yang kembali
                 berlaku bila kolom kuncinya dikosongkan. -->
            <AiQuotaMeter :quota="aiQuota" variant="detailed" class="mb-5" />

            <form @submit.prevent="submit">
                <!-- Provider -->
                <div class="mb-4">
                    <SelectDropdown
                        v-model="form.ai_provider"
                        :options="providerOptions"
                        label="Provider"
                        :error="form.errors.ai_provider"
                    />
                </div>

                <!-- API Key -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">API Key (opsional)</label>
                    <input
                        v-model="form.ai_api_key"
                        type="password"
                        autocomplete="off"
                        :placeholder="tenant.ai_key_set ? '•••• tersimpan' : 'Masukkan API key Anda'"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        :class="{ 'border-destructive/50': form.errors.ai_api_key }"
                    />
                    <p v-if="form.errors.ai_api_key" class="mt-1 text-xs text-destructive">{{ form.errors.ai_api_key }}</p>
                    <p v-else class="mt-1 text-xs text-gray-400">
                        {{ tenant.ai_key_set ? 'Kunci sudah tersimpan. Kosongkan untuk mempertahankannya.' : 'Kosongkan untuk memakai kuota gratis.' }}
                    </p>
                </div>

                <!-- Model -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Model (opsional)</label>
                    <input
                        v-model="form.ai_model"
                        type="text"
                        placeholder="Contoh: gpt-4o-mini"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        :class="{ 'border-destructive/50': form.errors.ai_model }"
                    />
                    <p v-if="form.errors.ai_model" class="mt-1 text-xs text-destructive">{{ form.errors.ai_model }}</p>
                </div>

                <!-- Submit -->
                <div class="flex justify-end pt-5">
                    <Button type="submit" size="lg" :loading="form.processing">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Kredensial' }}
                    </Button>
                </div>
            </form>
        </div>

        <!-- MCP Access Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
            <h2 class="text-base font-semibold text-gray-900">Akses MCP (AI Client)</h2>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">
                Hubungkan AI client Anda (mis. Claude Desktop) ke data bisnis ini secara <strong>read-only</strong>.
                Buat token, lalu pasang di client dengan header <code class="px-1 bg-gray-100 rounded">Authorization: Bearer</code>.
            </p>

            <!-- Endpoint -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Endpoint</label>
                <div class="flex gap-2">
                    <input
                        type="text"
                        :value="mcp.endpoint"
                        readonly
                        class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600 font-mono"
                    />
                    <Button
                        variant="secondary"
                        class="shrink-0"
                        @click="copy(mcp.endpoint, 'endpoint')"
                    >
                        {{ copied === 'endpoint' ? 'Tersalin!' : 'Salin' }}
                    </Button>
                </div>
            </div>

            <!-- Freshly generated token (shown once) -->
            <div v-if="mcpToken" class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-3 py-3">
                <p class="text-xs font-medium text-amber-800 mb-2">
                    Token baru — salin sekarang, tidak akan ditampilkan lagi:
                </p>
                <div class="flex gap-2">
                    <input
                        type="text"
                        :value="mcpToken"
                        readonly
                        class="w-full px-3 py-2 bg-white border border-amber-300 rounded-lg text-sm text-gray-800 font-mono"
                    />
                    <Button
                        variant="secondary"
                        class="shrink-0"
                        @click="copy(mcpToken, 'token')"
                    >
                        {{ copied === 'token' ? 'Tersalin!' : 'Salin' }}
                    </Button>
                </div>
            </div>

            <!-- Status + actions -->
            <div class="flex items-center justify-between pt-1">
                <span v-if="mcp.token_set" class="inline-flex items-center gap-1.5 text-xs font-medium text-green-700">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    Token aktif
                </span>
                <span v-else class="text-xs text-gray-400">Belum ada token</span>

                <div class="flex gap-2">
                    <Button :loading="generatingToken" @click="generateMcpToken">
                        {{ mcp.token_set ? 'Buat Ulang Token' : 'Generate Token' }}
                    </Button>
                    <Button v-if="mcp.token_set" variant="destructiveSoft" @click="revokeMcpToken">
                        Cabut
                    </Button>
                </div>
            </div>
        </div>

        <!-- Link data untuk AI tanpa pemasangan ([BL-102]) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
            <h2 class="text-base font-semibold text-gray-900">Link Data untuk AI</h2>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">
                Tempel satu link dan prompt ke ChatGPT, Claude, atau Gemini. AI itu membaca penjualan, profit, dan menu toko Anda,
                lalu menjawab pertanyaan Anda. Tidak perlu memasang apa pun.
            </p>

            <!-- Bahaya disebut sebelum tombolnya, bukan sesudah link jadi. -->
            <div class="mb-4 flex gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                <svg class="w-4 h-4 shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z" />
                </svg>
                <span>
                    Link ini adalah kunci. Siapa pun yang memegangnya bisa melihat data toko Anda sampai link mati, termasuk orang
                    yang bisa membuka riwayat chat tempat link ditempel. Untuk pemakaian rutin, <strong>Akses MCP</strong> di atas lebih aman.
                </span>
            </div>

            <!-- Link baru (tampil sekali) -->
            <div v-if="connectorLink" class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-3 py-3">
                <p class="text-xs font-medium text-amber-800">
                    Link “{{ connectorLink.label }}” dibuat. Salin sekarang, link tidak akan ditampilkan lagi.
                </p>
                <p class="text-xs text-amber-800 mt-0.5 mb-2">
                    Kirim sebagai satu pesan. Kalau AI menyebut nama usaha dan produk Anda dengan benar, ajukan pertanyaan di pesan berikutnya.
                </p>
                <textarea
                    :value="connectorPrompt"
                    readonly
                    rows="12"
                    aria-label="Prompt dan link untuk ditempel ke AI"
                    class="w-full px-3 py-2 bg-white border border-amber-300 rounded-lg text-xs text-gray-800 font-mono resize-none"
                />
                <div class="mt-2 flex flex-wrap gap-2">
                    <Button @click="copy(connectorPrompt, 'connector-prompt')">
                        {{ copied === 'connector-prompt' ? 'Tersalin!' : `Salin prompt + link (berlaku sampai ${formatLinkDate(connectorLink.expires_at)})` }}
                    </Button>
                    <Button variant="secondary" @click="copy(connectorLink.url, 'connector-url')">
                        {{ copied === 'connector-url' ? 'Tersalin!' : 'Salin link saja' }}
                    </Button>
                </div>
            </div>

            <!-- Link aktif -->
            <div class="mb-4">
                <p class="text-sm font-medium text-gray-700 mb-2">Link aktif</p>
                <ul v-if="connector.links.length" class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                    <li
                        v-for="link in connector.links"
                        :key="link.id"
                        class="flex items-center justify-between gap-3 px-3 py-2.5"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ link.label }}</p>
                            <p class="text-xs text-gray-500">
                                Berlaku sampai {{ formatLinkDate(link.expires_at) }}
                                <span aria-hidden="true">·</span>
                                <!-- Pemakaian yang tidak dikenali owner adalah peringatan
                                     yang paling mungkin ia baca. -->
                                {{ link.last_used_at ? `terakhir dibuka ${formatLinkDateTime(link.last_used_at)}` : 'belum pernah dibuka' }}
                            </p>
                        </div>
                        <Button variant="destructiveSoft" size="sm" class="shrink-0" @click="linkToRevoke = link">
                            Cabut
                        </Button>
                    </li>
                </ul>
                <p v-else class="text-xs text-gray-400">Belum ada link aktif.</p>
            </div>

            <div class="flex justify-end">
                <Button @click="openLinkDialog">Buat Link</Button>
            </div>
        </div>

        <!-- Tidak ada tombol "Salin URL" langsung: link hanya lahir dari dialog
             yang meminta masa berlaku dan persetujuan bertanggal. -->
        <Modal
            :show="showLinkDialog"
            title="Buat link data untuk AI"
            description="Link hanya tampil sekali setelah dibuat."
            @close="showLinkDialog = false"
        >
            <form id="connector-link-form" class="space-y-4" @submit.prevent="createLink">
                <div>
                    <label for="connector-link-label" class="block text-sm font-medium text-gray-700 mb-1">Nama link</label>
                    <input
                        id="connector-link-label"
                        v-model="linkForm.label"
                        type="text"
                        maxlength="40"
                        placeholder="Contoh: ChatGPT"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        :class="{ 'border-destructive/50': linkForm.errors.label }"
                    />
                    <p v-if="linkForm.errors.label" class="mt-1 text-xs text-destructive">{{ linkForm.errors.label }}</p>
                    <p v-else class="mt-1 text-xs text-gray-400">Supaya Anda tahu link mana yang perlu dicabut nanti.</p>
                </div>

                <fieldset>
                    <legend class="block text-sm font-medium text-gray-700 mb-1">Masa berlaku</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <label
                            v-for="days in connector.lifetimes"
                            :key="days"
                            class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                            :class="linkForm.lifetime_days === days
                                ? 'border-primary bg-primary/10'
                                : 'border-gray-200 hover:bg-gray-50'"
                        >
                            <input
                                v-model="linkForm.lifetime_days"
                                type="radio"
                                name="connector-lifetime"
                                :value="days"
                                class="mt-0.5 h-4 w-4 shrink-0"
                            />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-gray-900">{{ days }} hari</span>
                                <span class="block text-xs text-gray-500 mt-0.5">sampai {{ expiryAfter(days) }}</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="linkForm.errors.lifetime_days" class="mt-1 text-xs text-destructive">{{ linkForm.errors.lifetime_days }}</p>
                </fieldset>

                <div>
                    <Checkbox v-model="linkForm.acknowledged" variant="card" align="start">
                        <span class="block text-sm text-gray-700">
                            Siapa pun yang memegang link ini bisa melihat penjualan dan profit toko saya sampai
                            <strong>{{ linkExpiryPreview }}</strong>.
                        </span>
                    </Checkbox>
                    <p v-if="linkForm.errors.acknowledged" class="mt-1 text-xs text-destructive">{{ linkForm.errors.acknowledged }}</p>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button variant="secondary" @click="showLinkDialog = false">Batal</Button>
                    <Button
                        type="submit"
                        form="connector-link-form"
                        :disabled="!linkForm.acknowledged"
                        :loading="linkForm.processing"
                    >
                        Buat Link
                    </Button>
                </div>
            </template>
        </Modal>

        <ConfirmDialog
            :show="linkToRevoke !== null"
            title="Cabut link?"
            :message="linkToRevoke ? `AI yang memakai link “${linkToRevoke.label}” langsung tidak bisa membaca data toko lagi.` : ''"
            confirm-text="Cabut"
            @confirm="revokeLink"
            @cancel="linkToRevoke = null"
        />
    </div>
</template>
