<script setup>
const modelValue = defineModel({ type: Number, default: 1 })
const options = [1,2,3,4]
</script>

<template>
  <div class="space-y-2">
    <label class="block text-sm font-medium text-gray-700">Количество игр</label>
    <div class="flex gap-2">
      <label
        v-for="v in options"
        :key="v"
        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border text-sm cursor-pointer"
        :class="modelValue === v ? 'bg-black text-white border-black' : 'bg-white text-gray-700 hover:bg-gray-50'"
      >
        <input type="radio" class="sr-only" :value="v" v-model="modelValue" />
        {{ v }}
      </label>
    </div>
    <p class="text-xs text-gray-500">Сколько раз соперники играют друг с другом в этой стадии (1–4).</p>
  </div>
</template>
