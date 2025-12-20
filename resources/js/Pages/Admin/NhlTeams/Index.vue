<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  teams: {
    type: Array,
    default: () => [],
  },
})

// Форма создания команды
const createForm = useForm({
  code: '',
  name: '',
  logo: null,
})

// Отдельные формы для загрузки логотипов по строкам
const logoForms = reactive({})

const ensureLogoForm = (id) => {
  if (!logoForms[id]) {
    logoForms[id] = useForm({
      logo: null,
    })
  }
  return logoForms[id]
}

const handleCreateLogoChange = (event) => {
  const file = event.target.files?.[0] ?? null
  createForm.logo = file
}

const handleRowLogoChange = (teamId, event) => {
  const file = event.target.files?.[0] ?? null
  const form = ensureLogoForm(teamId)
  form.logo = file
}

const createTeam = () => {
  createForm.post(route('admin.nhl-teams.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      createForm.reset()
    },
  })
}

const uploadLogo = (teamId) => {
  const form = ensureLogoForm(teamId)

  form.post(route('admin.nhl-teams.update', teamId), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      form.reset('logo')
    },
  })
}

const page = usePage()
const flash = computed(() => page.props.flash || {})

const csrfToken =
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

const deleteTeam = (teamId) => {
  if (!confirm('Удалить эту команду?')) return

  const form = useForm({})

  form.delete(route('admin.nhl-teams.destroy', teamId), {
    preserveScroll: true,
  })
}

const updateTeamName = (team, url) => {
  router.post(url, { name: team.name }, {
    preserveScroll: true,
  })
}


</script>

<template>
  <AuthenticatedLayout>
    <Head title="Админка — Команды NHL" />

    <main class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Описание раздела -->
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <h1 class="text-xl font-semibold mb-2">Админка — Команды NHL</h1>
          <p class="text-sm text-gray-600">
            Здесь хранится реестр команд NHL (32 команды). Для каждой команды можно загрузить логотип, который потом
            будет использоваться в турнирной таблице и плей-офф.
          </p>
        </div>

        <!-- Форма создания команды (на всякий случай) -->
        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
          <h2 class="text-lg font-semibold">Добавить команду</h2>

          <form
            @submit.prevent="createTeam"
            class="grid gap-4 md:grid-cols-[110px,minmax(0,1fr),220px] items-end"
          >
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Код команды
              </label>
              <input
                v-model="createForm.code"
                type="text"
                maxlength="3"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm uppercase"
                placeholder="DET"
              />
              <p v-if="createForm.errors.code" class="mt-1 text-xs text-red-600">
                {{ createForm.errors.code }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Название команды
              </label>
              <input
                v-model="createForm.name"
                type="text"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                placeholder="Detroit Red Wings"
              />
              <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-600">
                {{ createForm.errors.name }}
              </p>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-gray-700">
                Логотип (опционально)
              </label>
              <input
                type="file"
                accept="image/*"
                @change="handleCreateLogoChange"
                class="block w-full text-sm text-gray-700"
              />
              <button
                type="submit"
                class="inline-flex items-center justify-center px-3 py-2 rounded-md bg-slate-700 text-white text-sm hover:bg-slate-800 disabled:opacity-60"
                :disabled="createForm.processing"
              >
                Сохранить
              </button>
            </div>
          </form>
        </div>

        <!-- Таблица реестра команд -->
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
          <h2 class="text-lg font-semibold mb-4">
            Список команд NHL
          </h2>

    <div
      v-if="flash.success"
      class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"
    >
      {{ flash.success }}
    </div>

          <div v-if="props.teams.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-700 whitespace-nowrap">
                    Код
                  </th>
                  <th class="px-3 py-2 text-left font-medium text-gray-700">
                    Название команды
                  </th>
                  <th class="px-3 py-2 text-left font-medium text-gray-700">
                    Логотип
                  </th>
                  <th class="px-3 py-2 text-right font-medium text-gray-700">
                    Действия
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="team in props.teams" :key="team.id">
                  <td class="px-3 py-2 whitespace-nowrap font-mono text-xs text-gray-800">
                    {{ team.code }}
                  </td>
<td class="px-4 py-3 text-sm text-gray-900">
  <form
    @submit.prevent="updateTeamName(team, route('admin.nhl-teams.update', team.id))"
    class="flex items-center gap-2"
  >
    <input
      type="text"
      v-model="team.name"
      class="border rounded px-2 py-1 text-sm w-64"
    />
    <button
      type="submit"
      class="text-xs font-medium text-slate-700 hover:text-slate-900"
    >
      Сохранить
    </button>
  </form>
</td>


                  <td class="px-3 py-2">
                    <div class="flex items-center gap-3">
                      <div class="w-12 h-12 rounded-md bg-gray-100 flex items-center justify-center overflow-hidden">
                        <img
                          v-if="team.logo_url"
                          :src="team.logo_url"
                          :alt="team.name"
                          class="w-full h-full object-contain"
                        />
                        <span v-else class="text-[10px] text-gray-400 text-center px-1">
                          нет логотипа
                        </span>
                      </div>
                      <form
                        @submit.prevent="uploadLogo(team.id)"
                        class="space-y-1"
                      >
                        <input
                          type="file"
                          accept="image/*"
                          @change="handleRowLogoChange(team.id, $event)"
                          class="block w-full text-xs text-gray-700"
                        />
                        <button
                          type="submit"
                          class="inline-flex items-center justify-center px-2 py-1 rounded-md bg-slate-600 text-white text-xs hover:bg-slate-700 disabled:opacity-60"
                          :disabled="!logoForms[team.id] || !logoForms[team.id].logo || logoForms[team.id].processing"
                        >
                          Обновить логотип
                        </button>
                      </form>
                    </div>
                  </td>
                  <td class="px-3 py-2 text-right">
                    <button
                      type="button"
                      class="text-xs text-red-600 hover:text-red-800"
                      @click="deleteTeam(team.id)"
                    >
                      Удалить
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p v-else class="text-sm text-gray-500">
            Команды ещё не заведены. Выполни сидер <code class="font-mono">NhlTeamsSeeder</code>, чтобы загрузить
            стандартный список из 32 команд NHL.
          </p>
        </div>
      </div>
    </main>
  </AuthenticatedLayout>
</template>
