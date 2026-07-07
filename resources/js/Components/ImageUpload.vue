<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
    modelValue: { type: [File, null], default: null },
    currentImage: { type: String, default: null },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const preview = ref(props.currentImage);
const dragActive = ref(false);
const fileInput = ref(null);
const validationError = ref(null);

// True when the preview comes from a freshly picked file (not the saved image).
const hasNewFile = computed(() => !!props.modelValue);

watch(() => props.currentImage, (val) => {
    if (!props.modelValue) {
        preview.value = val;
    }
});

const handleFile = (file) => {
    if (!file) {
        return;
    }

    validationError.value = null;

    // Validate type
    const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
        validationError.value = 'Format tidak didukung. Gunakan JPG, PNG, atau WEBP.';
        return;
    }

    // Validate size (5MB)
    if (file.size > 5 * 1024 * 1024) {
        validationError.value = 'Ukuran gambar maksimal 5 MB.';
        return;
    }

    emit('update:modelValue', file);

    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
        preview.value = e.target.result;
    };
    reader.readAsDataURL(file);
};

const onFileChange = (e) => {
    handleFile(e.target.files[0]);
};

const onDrop = (e) => {
    dragActive.value = false;
    handleFile(e.dataTransfer.files[0]);
};

const removeImage = () => {
    preview.value = null;
    validationError.value = null;
    emit('update:modelValue', null);
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const openPicker = () => {
    fileInput.value?.click();
};
</script>

<template>
    <div>
        <input
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/jpg,image/png,image/webp"
            class="hidden"
            @change="onFileChange"
        />

        <!-- Drop zone (always active — supports drag & drop even when an image exists) -->
        <div
            @click="openPicker"
            @dragover.prevent="dragActive = true"
            @dragleave.prevent="dragActive = false"
            @drop.prevent="onDrop"
            :class="[
                'relative w-full rounded-xl border-2 border-dashed transition-colors cursor-pointer',
                dragActive
                    ? 'border-primary bg-primary/5'
                    : 'border-gray-300 hover:border-primary/60 bg-gray-50/60',
            ]"
        >
            <!-- With preview -->
            <div v-if="preview" class="flex items-center gap-4 p-4">
                <div class="relative flex-shrink-0">
                    <img
                        :src="preview"
                        alt="Preview gambar produk"
                        class="w-24 h-24 object-cover rounded-lg border border-gray-200 bg-white"
                    />
                    <button
                        type="button"
                        @click.stop="removeImage"
                        class="absolute -top-2 -right-2 w-6 h-6 bg-destructive text-destructive-foreground rounded-full flex items-center justify-center hover:bg-destructive/90 shadow"
                        aria-label="Hapus gambar"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="min-w-0">
                    <span
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium"
                        :class="hasNewFile ? 'bg-success/10 text-success' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ hasNewFile ? 'Gambar baru dipilih' : 'Gambar saat ini' }}
                    </span>
                    <p class="text-sm text-gray-600 mt-1.5">
                        Tarik &amp; lepas gambar baru di sini, atau
                        <span class="text-primary font-medium">klik untuk mengganti</span>.
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">JPG, PNG, WEBP. Maks 5 MB.</p>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="flex flex-col items-center justify-center text-center py-8 px-4">
                <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <p class="text-sm text-gray-600 mt-3">
                    <span class="text-primary font-medium">Klik untuk unggah</span> atau tarik &amp; lepas
                </p>
                <p class="text-xs text-gray-400 mt-0.5">JPG, PNG, WEBP. Maks 5 MB.</p>
            </div>
        </div>

        <p v-if="error || validationError" class="mt-1.5 text-xs text-red-600">{{ error || validationError }}</p>
    </div>
</template>
