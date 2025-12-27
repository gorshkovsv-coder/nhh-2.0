<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({
  tournaments: { type: Array, default: () => [] },
})

const FORMAT_LABELS = {
  groups_playoff: 'Группы + плей-офф',
  group_only:     'Только группы',
  playoff:        'Только плей-офф',
}


const STATUS_LABELS = {
  draft:         'Черновик',
  registration:  'Идёт регистрация',
  active:        'Активен',
  archived:      'Завершён',
}

// цвета бейджа
const STATUS_BADGE = {
  registration: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
  active:       'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
  draft:        'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
  archived:     'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200',
}

const badgeClass = (s) => STATUS_BADGE[s] ?? 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-200'

const fmtDate = (iso) => {
  try {
    return new Intl.DateTimeFormat('ru-RU').format(new Date(iso))
  } catch { return '' }
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Турниры" />

    <main class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h1 class="text-2xl font-bold mb-6">Турниры</h1>

				<div v-for="t in tournaments" :key="t.id" class="border rounded-lg p-4 mb-4 bg-white">
				<div class="flex items-start justify-between gap-3">
					<!-- ЛЕВАЯ колонка -->
					<div class="flex items-start gap-3 min-w-0">
						<div
							v-if="t.logo_url"
							class="w-12 h-12 rounded-lg overflow-hidden border bg-white shrink-0"
						>
							<img :src="t.logo_url" alt="" class="w-full h-full object-contain" />
						</div>
						<div class="min-w-0">
							<div class="text-lg font-semibold truncate">{{ t.title }}</div>
				
					<div class="text-sm text-gray-600 mt-2">Сезон: {{ t.season ?? '—' }}</div>
					<div class="text-sm text-gray-600">Формат: {{ FORMAT_LABELS[t.format] ?? t.format }}</div>
					<div class="text-sm text-gray-600">Число участников: {{ t.participants_count ?? 0 }}</div>
					<div class="text-sm text-gray-500">Создан: {{ fmtDate(t.created_at) }}</div>
				
					<!-- Кнопка регистрации при открытой регистрации -->
					<div v-if="t.status === 'registration'" class="mt-3">
						<Link :href="`/tournaments/${t.id}#register`"
							class="inline-block px-3 py-1.5 rounded bg-emerald-600 text-white hover:bg-emerald-700">
						Зарегистрироваться
						</Link>
						</div>
					</div>
					</div>
				
					<!-- ПРАВАЯ колонка: Бейдж статуса + кнопка -->
					<div class="shrink-0 flex flex-col items-end gap-2">
					<span :class="`px-2 py-1 rounded-full text-xs font-medium ${badgeClass(t.status)}`">
						{{ STATUS_LABELS[t.status] ?? t.status }}
					</span>

					<Link :href="`/tournaments/${t.id}`"
							class="px-4 py-2 rounded bg-slate-900 text-white hover:bg-slate-800">
						Открыть
					</Link>
					</div>
				</div>
				</div>

            <div v-if="!tournaments?.length" class="text-gray-500">
              Турниров пока нет.
            </div>
          </div>
        </div>
      </div>
    </main>
  </AuthenticatedLayout>
</template>
