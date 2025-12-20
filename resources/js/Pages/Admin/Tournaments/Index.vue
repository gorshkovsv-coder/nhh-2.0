<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  tournaments: {
    type: Array,
    default: () => [],
  },
})

const createForm = useForm({
  title: '',
  season: new Date().getFullYear(),   // по умолчанию текущий год
  format: 'groups_playoff',           // корректные значения для бэка
  status: 'draft',                    // можно сразу слать статус
})

const submitCreate = () => {
  createForm.post('/admin/tournaments', {
    preserveScroll: true,
    onSuccess: () => createForm.reset('title'), // имя очистим, остальное удобно оставить
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Админка — Турниры" />

    <main>
      <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          <!-- Форма создания турнира -->
          <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Создать турнир</h2>
            <form @submit.prevent="submitCreate" class="grid gap-4 md:grid-cols-4 items-end">
              <div class="md:col-span-2">
                <label class="block text-sm text-gray-700 mb-1">Название</label>
                <input v-model="createForm.title" type="text" class="border rounded w-full px-2 py-1" required />
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Сезон</label>
                <input v-model.number="createForm.season" type="number" class="border rounded w-full px-2 py-1" />
              </div>
              <div>
                <label class="block text-sm text-gray-700 mb-1">Формат</label>
                <select v-model="createForm.format" class="border rounded w-full px-2 py-1">
                  <option value="groups_playoff">Группы + плей-офф</option>
                  <option value="group_only">Только группы</option>
                  <option value="playoff">Только плей-офф</option>
                </select>
              </div>
              <div class="md:col-span-4">
                <button
                  type="submit"
                  class="px-4 py-2 bg-slate-900 text-white rounded hover:bg-slate-800 transition"
                  :disabled="createForm.processing"
                >
                  Создать
                </button>
              </div>
            </form>
          </div>

          <!-- Список турниров -->
          <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Турниры</h2>
            <div v-if="tournaments.length">
              <ul class="divide-y divide-gray-200">
                <li
                  v-for="t in tournaments"
                  :key="t.id"
                  class="py-3 flex items-center justify-between"
                >
                  <div>
                    <div class="font-semibold">
                      {{ t.title }}
                    </div>
                    <div class="text-sm text-gray-500">
                      Сезон: {{ t.season || '—' }} · Формат: {{ t.format }} · Статус: {{ t.status }}
                    </div>
                  </div>
                  <div>
                    <Link
                      :href="`/admin/tournaments/${t.id}/edit`"
                      class="px-3 py-1 text-sm rounded bg-slate-900 text-white hover:bg-slate-800 transition"
                    >
                      Управлять
                    </Link>
                  </div>
                </li>
              </ul>
            </div>
            <div v-else class="text-gray-500">
              Турниров пока нет.
            </div>
          </div>
        </div>
      </div>
    </main>
  </AuthenticatedLayout>
</template>
