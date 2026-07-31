<script setup>
defineProps({
    autocomplete: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    id: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '',
    },
    step: {
        type: [String, Number],
        default: undefined,
    },
    required: {
        type: Boolean,
        default: true,
    },
    type: {
        type: String,
        default: 'text',
    },
});

const emit = defineEmits(['blur', 'update:modelValue']);
</script>

<template>
    <div class="grid gap-2">
        <label class="text-sm font-medium text-slate-800" :for="id">
            {{ label }}
        </label>
        <input
            :id="id"
            :autocomplete="autocomplete || undefined"
            :aria-describedby="error ? `${id}-error` : undefined"
            :aria-invalid="error ? 'true' : 'false'"
            :name="id"
            :placeholder="placeholder || undefined"
            :required="required"
            :step="step"
            :type="type"
            :value="modelValue"
            class="min-h-11 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-sky-700 focus:ring-4 focus:ring-sky-100"
            @blur="emit('blur', $event)"
            @input="emit('update:modelValue', $event.target.value)"
        />
        <p v-if="error" :id="`${id}-error`" class="text-sm text-red-700" role="alert">
            {{ error }}
        </p>
    </div>
</template>
