<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [File, null], default: null },
    currentImage: { type: String, default: null },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const preview = ref(props.currentImage);
const dragActive = ref(false);
const fileInput = ref(null);

watch(() => props.currentImage, (val) => {
    if (!props.modelValue) {
        preview.value = val;
    }
});

const handleFile = (file) => {
    if (!file) return;

    // Validate type
    const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
        return;
    }

    // Validate size (5MB)
    if (file.size > 5 * 1024 * 1024) {
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

        <!-- Preview -->
        <div v-if="preview" class="relative inline-block">
            <img
                :src="preview"
                alt="Preview"
                class="w-32 h-32 object-cover rounded-lg border border-gray-200"
            />
            <button
                type="button"
                @click="removeImage"
                class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 shadow"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Drop zone -->
        <div
            v-else
            @click="openPicker"
            @dragover.prevent="dragActive = true"
            @dragleave="dragActive = false"
            @drop.prevent="onDrop"
            :class="[
                'w-32 h-32 border-2 border-dashed rounded-lg flex flex-col items-center justify-center cursor-pointer transition-colors',
                dragActive ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 hover:border-gray-400 bg-gray-50'
            ]"
        >
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span class="text-xs text-gray-500 mt-1">Upload</span>
        </div>

        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
        <p v-else class="mt-1 text-xs text-gray-400">JPG, PNG, WEBP. Maks 5 MB.</p>
    </div>
</template>
