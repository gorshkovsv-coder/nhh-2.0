<template>
  <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', toneClass]">
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, default: '' },
})

// Человекочитаемые названия
const LABELS = {
  scheduled:  'Запланирован',
  reported:   'Ожидает подтверждения',
  confirmed:  'Подтверждён',
  canceled:   'Отменён',
  pending:    'Ожидает',
  in_progress:'Идёт',
}

// Цветовые тона Tailwind
const TONES = {
  scheduled:   'bg-slate-50 text-slate-700 ring-slate-600/20',
  reported:    'bg-amber-50 text-amber-700 ring-amber-600/20',
  confirmed:   'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  canceled:    'bg-rose-50 text-rose-700 ring-rose-600/20',
  pending:     'bg-gray-50 text-gray-700 ring-gray-600/20',
  in_progress: 'bg-blue-50 text-blue-700 ring-blue-600/20',
  _default:    'bg-gray-50 text-gray-700 ring-gray-600/20',
}

const normalized = computed(() => String(props.status || '').toLowerCase())
const label     = computed(() => LABELS[normalized.value] ?? (props.status || '—'))
const toneClass = computed(() => TONES[normalized.value] ?? TONES._default)
</script>
