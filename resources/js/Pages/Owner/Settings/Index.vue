<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, router, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    tenant: Object,
    aiFreeTier: Object,
    mcp: Object,
});

const form = useForm({
    address:     props.tenant.address ?? '',
    phone:       props.tenant.phone ?? '',
    ai_provider: props.tenant.ai_provider ?? '',
    ai_model:    props.tenant.ai_model ?? '',
    ai_api_key:  '',
});

const submit = () => {
    form.patch('/owner/settings', { preserveScroll: true });
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
    router.post('/owner/settings/mcp-token', {}, {
        preserveScroll: true,
        onStart: () => (generatingToken.value = true),
        onFinish: () => (generatingToken.value = false),
    });
};

const revokeMcpToken = () => {
    if (!confirm('Cabut token MCP? AI client yang memakainya akan langsung kehilangan akses.')) return;
    router.delete('/owner/settings/mcp-token', { preserveScroll: true });
};
</script>

<template>
    <Head title="Pengaturan Usaha" />

    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Pengaturan Usaha</h1>
            <p class="text-sm text-gray-500 mt-1">Informasi usaha yang tampil di header struk</p>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <!-- Nama usaha (read-only) -->
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Usaha</label>
                <input
                    type="text"
                    :value="tenant.name"
                    disabled
                    class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-500 cursor-not-allowed"
                />
                <p class="mt-1 text-xs text-gray-400">Nama usaha diatur saat pendaftaran.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <!-- Alamat -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                    <textarea
                        v-model="form.address"
                        rows="3"
                        placeholder="Contoh: Jl. Merdeka No. 1, Jakarta Pusat"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                        :class="{ 'border-red-300': form.errors.address }"
                    />
                    <p v-if="form.errors.address" class="mt-1 text-xs text-red-600">{{ form.errors.address }}</p>
                </div>

                <!-- No. Telepon -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon</label>
                    <input
                        v-model="form.phone"
                        type="text"
                        placeholder="Contoh: 021-1234567"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        :class="{ 'border-red-300': form.errors.phone }"
                    />
                    <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
                </div>

                <!-- AI Analysis -->
                <div class="pt-5 border-t border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">AI Analysis</h2>
                    <p class="text-xs text-gray-500 mt-0.5 mb-4">
                        Pakai kunci API sendiri (BYOK) untuk pemakaian tanpa batas, atau biarkan kosong untuk memakai kuota gratis harian.
                    </p>

                    <!-- Sisa kuota free tier (hanya bila key belum diisi) -->
                    <div
                        v-if="!tenant.ai_key_set"
                        class="mb-4 flex items-center gap-2 rounded-lg bg-blue-50 border border-blue-100 px-3 py-2 text-xs text-blue-700"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Sisa kuota gratis hari ini: <strong>{{ aiFreeTier.remaining }}</strong> dari {{ aiFreeTier.daily_limit }}.</span>
                    </div>

                    <!-- Provider -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
                        <select
                            v-model="form.ai_provider"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            :class="{ 'border-red-300': form.errors.ai_provider }"
                        >
                            <option value="">Default (SumoPod gratis)</option>
                            <option value="sumopod">SumoPod</option>
                            <option value="gemini">Gemini</option>
                            <option value="openai">OpenAI</option>
                            <option value="anthropic">Anthropic</option>
                        </select>
                        <p v-if="form.errors.ai_provider" class="mt-1 text-xs text-red-600">{{ form.errors.ai_provider }}</p>
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
                            :class="{ 'border-red-300': form.errors.ai_api_key }"
                        />
                        <p v-if="form.errors.ai_api_key" class="mt-1 text-xs text-red-600">{{ form.errors.ai_api_key }}</p>
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
                            :class="{ 'border-red-300': form.errors.ai_model }"
                        />
                        <p v-if="form.errors.ai_model" class="mt-1 text-xs text-red-600">{{ form.errors.ai_model }}</p>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 disabled:opacity-50 transition-colors"
                    >
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                    </button>
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
                    <button
                        type="button"
                        @click="copy(mcp.endpoint, 'endpoint')"
                        class="shrink-0 px-3 py-2 text-xs font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        {{ copied === 'endpoint' ? 'Tersalin!' : 'Salin' }}
                    </button>
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
                    <button
                        type="button"
                        @click="copy(mcpToken, 'token')"
                        class="shrink-0 px-3 py-2 text-xs font-medium text-amber-800 border border-amber-300 rounded-lg hover:bg-amber-100 transition-colors"
                    >
                        {{ copied === 'token' ? 'Tersalin!' : 'Salin' }}
                    </button>
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
                    <button
                        type="button"
                        @click="generateMcpToken"
                        :disabled="generatingToken"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 disabled:opacity-50 transition-colors"
                    >
                        {{ mcp.token_set ? 'Buat Ulang Token' : 'Generate Token' }}
                    </button>
                    <button
                        v-if="mcp.token_set"
                        type="button"
                        @click="revokeMcpToken"
                        class="inline-flex items-center px-4 py-2 text-destructive bg-destructive/10 border border-destructive/20 text-sm font-medium rounded-lg hover:bg-destructive/20 transition-colors"
                    >
                        Cabut
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
