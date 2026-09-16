<script setup>
import { ref, computed } from 'vue';
import { useThermalPrinter } from '@/composables/useThermalPrinter';

const props = defineProps({
    show: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const printer = useThermalPrinter();
const { config, supports, isConfigured } = printer;

const feedback = ref({ type: '', message: '' });
const working = ref(false);

const anySupported = computed(() => supports.bluetooth || supports.usb);

const setFeedback = (type, message) => {
    feedback.value = { type, message };
};

const selectTransport = (kind) => {
    printer.saveConfig({ transport: kind });
    setFeedback('', '');
};

const selectWidth = (w) => printer.saveConfig({ paperWidth: w });

const onField = (key, value) => printer.saveConfig({ [key]: value });

const pair = async () => {
    working.value = true;
    setFeedback('', '');
    try {
        await printer.pair();
        setFeedback('success', `Terhubung ke "${config.deviceName}".`);
    } catch (err) {
        // A user cancelling the chooser throws NotFoundError — treat as benign.
        if (err?.name === 'NotFoundError') {
            setFeedback('', '');
        } else {
            setFeedback('error', err?.message || 'Gagal menghubungkan printer.');
        }
    } finally {
        working.value = false;
    }
};

const testPrint = async () => {
    working.value = true;
    setFeedback('', '');
    try {
        await printer.testPrint();
        setFeedback('success', 'Tes cetak terkirim ke printer.');
    } catch (err) {
        setFeedback('error', err?.message || 'Gagal mencetak. Coba hubungkan ulang.');
    } finally {
        working.value = false;
    }
};

const close = () => emit('close');
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="close" />

                <div class="relative bg-card text-card-foreground rounded-xl shadow-2xl w-full max-w-md max-h-[92vh] flex flex-col">
                    <!-- Header -->
                    <div class="px-5 py-4 border-b border-border flex items-center justify-between shrink-0">
                        <h2 class="text-base font-bold">Pengaturan Printer Thermal</h2>
                        <button @click="close" class="text-foreground/40 hover:text-foreground" aria-label="Tutup">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                        <!-- Unsupported browser notice -->
                        <div v-if="!anySupported" class="rounded-lg bg-warning/10 border border-warning/30 p-3 text-sm text-warning-foreground">
                            Browser ini tidak bisa terhubung langsung ke printer. Pakai <strong>Chrome</strong> atau <strong>Edge</strong>.
                            Tombol <strong>Cetak Struk</strong> tetap bisa dipakai lewat dialog cetak browser.
                        </div>

                        <template v-else>
                            <!-- Connection type -->
                            <div>
                                <p class="text-sm font-medium mb-2">Jenis koneksi</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        :disabled="!supports.bluetooth"
                                        @click="selectTransport('bluetooth')"
                                        :class="[
                                            'px-3 py-2.5 rounded-lg border text-sm font-medium transition disabled:opacity-40 disabled:cursor-not-allowed',
                                            config.transport === 'bluetooth' ? 'bg-primary/10 border-primary/40 text-primary' : 'border-border hover:bg-muted',
                                        ]"
                                    >
                                        Bluetooth
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="!supports.usb"
                                        @click="selectTransport('usb')"
                                        :class="[
                                            'px-3 py-2.5 rounded-lg border text-sm font-medium transition disabled:opacity-40 disabled:cursor-not-allowed',
                                            config.transport === 'usb' ? 'bg-primary/10 border-primary/40 text-primary' : 'border-border hover:bg-muted',
                                        ]"
                                    >
                                        USB
                                    </button>
                                </div>
                            </div>

                            <!-- Paper width -->
                            <div>
                                <p class="text-sm font-medium mb-2">Lebar kertas</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        v-for="w in [58, 80]"
                                        :key="w"
                                        @click="selectWidth(w)"
                                        :class="[
                                            'px-3 py-2.5 rounded-lg border text-sm font-medium transition',
                                            config.paperWidth === w ? 'bg-primary/10 border-primary/40 text-primary' : 'border-border hover:bg-muted',
                                        ]"
                                    >
                                        {{ w }}mm
                                    </button>
                                </div>
                            </div>

                            <!-- Receipt header / footer -->
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-medium block mb-1">Nama toko di struk</label>
                                    <input
                                        :value="config.header"
                                        @input="onField('header', $event.target.value)"
                                        type="text"
                                        placeholder="Kosongkan untuk memakai nama toko"
                                        class="w-full px-3 py-2 rounded-lg border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    />
                                </div>
                                <div>
                                    <label class="text-sm font-medium block mb-1">Alamat atau telepon</label>
                                    <input
                                        :value="config.subheader"
                                        @input="onField('subheader', $event.target.value)"
                                        type="text"
                                        placeholder="Opsional"
                                        class="w-full px-3 py-2 rounded-lg border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    />
                                </div>
                                <div>
                                    <label class="text-sm font-medium block mb-1">Teks penutup</label>
                                    <input
                                        :value="config.footer"
                                        @input="onField('footer', $event.target.value)"
                                        type="text"
                                        placeholder="Terima Kasih"
                                        class="w-full px-3 py-2 rounded-lg border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    />
                                </div>
                            </div>

                            <!-- Paired device status -->
                            <div class="rounded-lg border border-border p-3 flex items-center justify-between">
                                <div class="min-w-0">
                                    <p class="text-xs text-foreground/50">Printer terhubung</p>
                                    <p class="text-sm font-medium truncate">
                                        {{ isConfigured ? config.deviceName : 'Belum ada' }}
                                    </p>
                                </div>
                                <span
                                    class="w-2.5 h-2.5 rounded-full shrink-0"
                                    :class="printer.state.connected ? 'bg-success' : 'bg-border'"
                                    :title="printer.state.connected ? 'Terhubung' : 'Terputus'"
                                />
                            </div>

                            <!-- Feedback -->
                            <p
                                v-if="feedback.message"
                                :class="[
                                    'text-sm rounded-lg px-3 py-2',
                                    feedback.type === 'error' ? 'bg-destructive/10 text-destructive' : 'bg-success/10 text-success',
                                ]"
                            >
                                {{ feedback.message }}
                            </p>
                        </template>
                    </div>

                    <!-- Actions -->
                    <div v-if="anySupported" class="px-5 py-4 border-t border-border flex gap-3 shrink-0">
                        <button
                            @click="pair"
                            :disabled="working"
                            class="flex-1 py-2.5 bg-primary text-primary-foreground font-semibold rounded-lg hover:bg-primary/90 transition text-sm disabled:opacity-60"
                        >
                            {{ isConfigured ? 'Hubungkan Ulang' : 'Hubungkan Printer' }}
                        </button>
                        <button
                            @click="testPrint"
                            :disabled="working"
                            class="flex-1 py-2.5 bg-white border border-border text-foreground font-medium rounded-lg hover:bg-muted transition text-sm disabled:opacity-60"
                        >
                            Tes Cetak
                        </button>
                    </div>
                    <div v-else class="px-5 py-4 border-t border-border shrink-0">
                        <button @click="close" class="w-full py-2.5 bg-white border border-border rounded-lg hover:bg-muted text-sm font-medium">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
