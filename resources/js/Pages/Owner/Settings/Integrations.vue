<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, router, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import AiQuotaMeter from '@/Components/AiQuotaMeter.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import Button from '@/Components/Button.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    tenant: Object,
    // Blok yang sama yang dibaca AI Analysis, ditampilkan di sini dalam versi
    // lengkapnya (`[BL-062]`): di halaman ini angkanya adalah konteks untuk
    // keputusan BYOK, jadi ia dibedah, bukan cuma diringkas satu baris.
    aiQuota: Object,
    mcp: Object,
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
    </div>
</template>
