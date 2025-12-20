<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'

const props = defineProps({
  tournament: {
    type: Object,
    required: true,
  },
  matches: {
    type: Object,
    default: () => ({
      data: [],
      links: [],
      meta: {},
    }),
  },
  stages: {
    type: Array,
    default: () => [],
  },
  teams: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({
      stage_id: null,
      team_id: null,
    }),
  },
})

const pageTitle = computed(() => {
  return props.tournament?.title
    ? `История матчей — ${props.tournament.title}`
    : 'История матчей'
})

// Локальное состояние фильтров (строки, чтобы удобно работать с <select>)
const localFilters = reactive({
  stage_id: props.filters.stage_id ? String(props.filters.stage_id) : '',
  team_id: props.filters.team_id ? String(props.filters.team_id) : '',
})

// Применить фильтры: уходим на тот же маршрут с query-параметрами
const applyFilters = () => {
  router.get(
    route('tournaments.matches-history', props.tournament.id),
    {
      stage_id: localFilters.stage_id || undefined,
      team_id: localFilters.team_id || undefined,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  )
}

// Подпись для кнопок пагинации (как в админке пользователей)
const pageLabel = (link) => {
  if (!link || !link.label) return ''
  const label = String(link.label).toLowerCase()

  if (label.includes('pagination.previous') || label.includes('previous')) {
    return 'Предыдущая'
  }
  if (label.includes('pagination.next') || label.includes('next')) {
    return 'Следующая'
  }
  return link.label
}

const totalMatches = computed(
  () => props.matches?.meta?.total ?? props.matches?.data?.length ?? 0,
)
</script>


<template>
  <AuthenticatedLayout>
    <Head :title="pageTitle" />

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <!-- Заголовок + кнопка назад -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">
            История матчей
          </h1>
          <p class="mt-1 text-sm text-gray-500">
            Турнир: <span class="font-medium">{{ tournament.title }}</span>
          </p>
        </div>

        <Link
          :href="route('tournaments.show', tournament.id)"
          class="inline-flex items-center text-sm font-medium text-sky-500 hover:text-sky-400"
        >
          ← Назад к турниру
        </Link>
      </div>

      <!-- Таблица матчей -->
      <div class="bg-white shadow-sm rounded-2xl border overflow-hidden">
<div class="px-4 py-3 border-b flex flex-wrap items-center justify-between gap-3">
  <p class="text-sm text-gray-600">
    Всего матчей: <span class="font-semibold">{{ totalMatches }}</span>
  </p>

  <div class="flex flex-wrap items-center gap-3">
    <!-- Фильтр по стадии -->
    <div class="flex items-center gap-2">
      <span class="text-xs text-gray-500">Стадия:</span>
      <select
        v-model="localFilters.stage_id"
        @change="applyFilters"
        class="text-xs rounded-md border-gray-300 focus:border-sky-500 focus:ring-sky-500"
      >
        <option value="">Все</option>
        <option
          v-for="s in stages"
          :key="s.id"
          :value="String(s.id)"
        >
          {{ s.name }}
        </option>
      </select>
    </div>

    <!-- Фильтр по команде -->
    <div class="flex items-center gap-2">
      <span class="text-xs text-gray-500">Команда:</span>
      <select
        v-model="localFilters.team_id"
        @change="applyFilters"
        class="text-xs rounded-md border-gray-300 focus:border-sky-500 focus:ring-sky-500"
      >
        <option value="">Все</option>
        <option
          v-for="t in teams"
          :key="t.id"
          :value="String(t.id)"
        >
          {{ t.name }}
        </option>
      </select>
    </div>
  </div>
</div>


        <div v-if="matches.data && matches.data.length" class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-500">
              <tr class="[&>th]:px-3 [&>th]:py-2 text-left">
                <th class="w-40">Стадия</th>
                <th>Хозяева</th>
                <th class="w-24 text-center">Счёт</th>
                <th>Гости</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr
                v-for="m in matches.data"
                :key="m.id"
                class="[&>td]:px-3 [&>td]:py-2"
              >
                <!-- Стадия -->
                <td class="align-top">
                  <div class="text-xs font-medium text-gray-700 truncate">
                    {{ m.stage?.name || 'Стадия' }}
                  </div>
                </td>

                <!-- Хозяева -->
                <td class="align-top">
                  <div class="flex items-center gap-2">
                    <div
                      class="w-9 h-9 rounded bg-slate-100 flex items-center justify-center overflow-hidden"
                    >
                      <img
                        v-if="m.home?.nhl_team?.logo_url"
                        :src="m.home.nhl_team.logo_url"
                        :alt="m.home.nhl_team.name"
                        class="w-full h-full object-contain"
                      />
                      <span
                        v-else
                        class="text-[9px] text-gray-400 text-center px-1"
                      >
                        logo
                      </span>
                    </div>
                    <div class="min-w-0">
                      <div class="text-sm font-semibold leading-tight truncate">
                        {{ m.home?.nhl_team?.name || 'Без команды' }}
                      </div>
                      <div class="text-xs text-gray-600 leading-tight truncate">
                        {{
                          m.home?.display_name
                            || m.home?.user?.name
                            || 'Хозяева'
                        }}
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Счёт -->
                <td class="align-top text-center">
                  <div class="font-semibold">
                    <span v-if="m.score_home != null && m.score_away != null">
                      {{ m.score_home }} : {{ m.score_away }}
                    </span>
                    <span v-else class="text-gray-400 text-xs">
                      —
                    </span>
                  </div>
                  <div class="text-[10px] text-gray-500">
                    <span v-if="m.ot">OT</span>
                    <span v-if="m.so">SO</span>
                  </div>
                </td>

                <!-- Гости -->
                <td class="align-top">
                  <div class="flex items-center gap-2">
                    <div
                      class="w-9 h-9 rounded bg-slate-100 flex items-center justify-center overflow-hidden"
                    >
                      <img
                        v-if="m.away?.nhl_team?.logo_url"
                        :src="m.away.nhl_team.logo_url"
                        :alt="m.away.nhl_team.name"
                        class="w-full h-full object-contain"
                      />
                      <span
                        v-else
                        class="text-[9px] text-gray-400 text-center px-1"
                      >
                        logo
                      </span>
                    </div>
                    <div class="min-w-0">
                      <div class="text-sm font-semibold leading-tight truncate">
                        {{ m.away?.nhl_team?.name || 'Без команды' }}
                      </div>
                      <div class="text-xs text-gray-600 leading-tight truncate">
                        {{
                          m.away?.display_name
                            || m.away?.user?.name
                            || 'Гости'
                        }}
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="px-4 py-6 text-sm text-gray-500">
          В этом турнире пока нет подтверждённых матчей.
        </div>

        <!-- Пагинация -->
        <div
          v-if="matches.meta && matches.meta.last_page && matches.meta.last_page > 1"
          class="px-4 py-3 border-t flex items-center justify-between gap-2"
        >
          <div class="text-xs text-gray-500">
            Страница {{ matches.meta.current_page }} из
            {{ matches.meta.last_page }} · Всего матчей:
            {{ matches.meta.total }}
          </div>

          <nav
            class="isolate inline-flex -space-x-px rounded-md shadow-sm"
            aria-label="Pagination"
          >
            <Link
              v-for="(link, idx) in matches.links"
              :key="idx"
              v-if="link.url"
              :href="link.url"
              preserve-scroll
              preserve-state
              class="relative inline-flex items-center px-3 py-1.5 border text-xs font-medium"
              :class="[
                link.active
                  ? 'z-10 bg-slate-800 border-slate-800 text-white'
                  : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50',
              ]"
              v-html="pageLabel(link)"
            />
            <span
              v-else-if="link"
              class="relative inline-flex items-center px-3 py-1.5 border border-gray-200 bg-gray-50 text-xs font-medium text-gray-400"
              v-html="pageLabel(link)"
            />
          </nav>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
