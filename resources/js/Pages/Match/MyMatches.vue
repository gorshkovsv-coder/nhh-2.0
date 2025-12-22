<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ref, reactive, computed, watch, onMounted } from 'vue'

const props = defineProps({
  matches: {
    type: [Array, Object],
    required: true,
  },
})

// ===== Текущий пользователь (через Inertia usePage)
const page = usePage()
const meUserId = computed(() => page?.props?.auth?.user?.id ?? null)

// ===== Вспомогательные функции по матчу
const lastReportOf = (m) => {
  const rs = m?.reports || []
  return rs.length ? rs[0] : null
}

const myParticipantId = (m) => {
  // определяем мой participant.id через user.id
  const me = meUserId.value
  if (!me) return null
  if (m?.home?.user?.id === me) return m.home?.id ?? null
  if (m?.away?.user?.id === me) return m.away?.id ?? null
  // запасной вариант, если пришло поле с сервера
  return m?.my_participant_id ?? null
}

const opponentOf = (m) => {
  const me = meUserId.value
  if (m?.home?.user?.id === me) return m?.away ?? null
  if (m?.away?.user?.id === me) return m?.home ?? null
  return null
}

const pendingNoteOf = (m) => {
  const r = lastReportOf(m)
  if (!r || r.status !== 'pending') return ''
  const mePid = myParticipantId(m)
  if (mePid && r.reporter_participant_id === mePid) {
    return 'Ожидает подтверждения соперником'
  }
  return 'Ожидает вашего подтверждения'
}

// ===== Справочники для фильтров
const matchItems = computed(() => {
  if (Array.isArray(props.matches)) {
    return props.matches
  }
  return props.matches?.data ?? []
})

const tournaments = computed(() => {
  const map = new Map()
  for (const m of matchItems.value) {
    const t = m?.stage?.tournament
    if (t && !map.has(t.id)) {
      map.set(t.id, { id: t.id, title: t.title })
    }
  }
  return Array.from(map.values())
})

const opponents = computed(() => {
  const map = new Map()
  for (const m of matchItems.value) {
    const opp = opponentOf(m)
    if (!opp) continue

    const team = opp?.nhl_team
    if (!team) continue

    const id = team.id
    const name = team.name || 'Без команды'

    if (id && !map.has(id)) {
      map.set(id, { id, name })
    }
  }

  return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name, 'ru'))
})


const statuses = computed(() => {
  const set = new Set()
  for (const m of matchItems.value) {
    if (m?.status) set.add(m.status) // scheduled / reported / confirmed
  }
  return Array.from(set)
})

// ===== Состояние фильтров
const q = reactive({
  tournamentId: 'all',
  opponentId: 'all',
  status: 'all',
})

const quick = reactive({
  awaiting: false,  // Ждёт моего подтверждения
  notPlayed: false, // Запланирован (ещё не сыграно)
})

// ===== Сохранение фильтров между переходами (localStorage)
const FILTERS_STORAGE_KEY = 'myMatches.filters'

const loadFiltersFromStorage = () => {
  if (typeof window === 'undefined') return

  try {
    const raw = window.localStorage.getItem(FILTERS_STORAGE_KEY)
    if (!raw) return

    const saved = JSON.parse(raw)

    if (saved?.q) {
      q.tournamentId = saved.q.tournamentId ?? q.tournamentId
      q.opponentId   = saved.q.opponentId ?? q.opponentId
      q.status       = saved.q.status ?? q.status
    }

    if (saved?.quick) {
      quick.awaiting  = !!saved.quick.awaiting
      quick.notPlayed = !!saved.quick.notPlayed
    }
  } catch (e) {
    console.warn('Не удалось загрузить фильтры myMatches', e)
  }
}

const saveFiltersToStorage = () => {
  if (typeof window === 'undefined') return

  try {
    const payload = {
      q: {
        tournamentId: q.tournamentId,
        opponentId: q.opponentId,
        status: q.status,
      },
      quick: {
        awaiting: quick.awaiting,
        notPlayed: quick.notPlayed,
      },
    }

    window.localStorage.setItem(FILTERS_STORAGE_KEY, JSON.stringify(payload))
  } catch (e) {
    console.warn('Не удалось сохранить фильтры myMatches', e)
  }
}

// При первом заходе на страницу поднимаем фильтры из localStorage
onMounted(() => {
  loadFiltersFromStorage()
})

// Следим за изменениями фильтров и сохраняем их
watch(
  [q, quick],
  () => {
    saveFiltersToStorage()
  },
  { deep: true }
)


function toggleAwaiting() {
  quick.awaiting = !quick.awaiting
  if (quick.awaiting) {
    quick.notPlayed = false
    q.status = 'all'
  }
}

function toggleNotPlayed() {
  quick.notPlayed = !quick.notPlayed
  if (quick.notPlayed) {
    quick.awaiting = false
    q.status = 'all'
  }
}

function resetFilters() {
  q.tournamentId = 'all'
  q.opponentId   = 'all'
  q.status       = 'all'
  quick.awaiting = false
  quick.notPlayed = false
  saveFiltersToStorage()
}

// ===== Итоговый список с учётом всех фильтров
const filteredMatches = computed(() => {
  let rows = matchItems.value.slice()

  // Фильтр по турниру
  if (q.tournamentId !== 'all') {
    rows = rows.filter(m => m?.stage?.tournament?.id === q.tournamentId)
  }

// Фильтр по сопернику (по команде)
if (q.opponentId !== 'all') {
  rows = rows.filter(m => {
    const opp = opponentOf(m)
    const teamId = opp?.nhl_team?.id
    return teamId === q.opponentId
  })
}


  // Быстрые фильтры
  if (quick.awaiting) {
    rows = rows.filter(m => {
      const r = lastReportOf(m)
      if (!r || r.status !== 'pending') return false
      const mePid = myParticipantId(m)
      return mePid ? r.reporter_participant_id !== mePid : false // ждёт МОЕГО подтверждения
    })
  } else if (quick.notPlayed) {
    rows = rows.filter(m => m.status === 'scheduled')
  } else if (q.status !== 'all') {
    rows = rows.filter(m => m.status === q.status)
  }

  return rows
})

const isAutoConfirmed = (m) => {
  return !!(m.meta && m.meta.auto_confirmed)
}

const paginationLabel = (link) => {
  if (!link || !link.label) return ''

  if (link.label === 'pagination.previous') {
    return '‹ Предыдущая'
  }
  if (link.label === 'pagination.next') {
    return 'Следующая ›'
  }

  return link.label
}
</script>


<template>
  <AuthenticatedLayout>
    <Head title="Мои матчи" />

    <main>
      <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

          <!-- Если вообще есть матчи — показываем фильтры -->
          <template v-if="matchItems.length">

            <!-- Фильтры -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border p-4 space-y-3 mb-4">
              <div class="grid gap-3 md:grid-cols-3">
                <!-- Турнир -->
                <div>
                  <label class="block text-xs text-gray-500 mb-1">Турнир</label>
                  <select v-model="q.tournamentId" class="w-full border rounded px-2 py-1">
                    <option value="all">Все турниры</option>
                    <option v-for="t in tournaments" :key="t.id" :value="t.id">
                      {{ t.title }}
                    </option>
                  </select>
                </div>

<!-- Соперник -->
<div>
  <label class="block text-xs text-gray-500 mb-1">Команда соперника</label>
  <select v-model="q.opponentId" class="w-full border rounded px-2 py-1">
    <option value="all">Все соперники</option>
    <option v-for="p in opponents" :key="p.id" :value="p.id">
      {{ p.name }}
    </option>
  </select>
</div>

                <!-- Статус -->
                <div>
                  <label class="block text-xs text-gray-500 mb-1">Статус</label>
                  <select
                    v-model="q.status"
                    class="w-full border rounded px-2 py-1"
                    @change="quick = { awaiting:false, notPlayed:false }"
                  >
                    <option value="all">Все статусы</option>
                    <option v-for="s in statuses" :key="s" :value="s">
                      {{ s === 'reported'
                          ? 'Ожидает подтверждения'
                          : s === 'scheduled'
                            ? 'Запланирован'
                            : s === 'confirmed'
                              ? 'Подтверждён'
                              : s === 'canceled'
                                ? 'Отменён'
                                : s }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- Быстрые фильтры + счётчик и сброс -->
              <div class="flex flex-wrap items-center gap-2">
                <button
                  type="button"
                  @click="toggleAwaiting"
                  :class="quick.awaiting ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-800 hover:bg-slate-200'"
                  class="px-3 py-1.5 rounded"
                >
                  Ждёт моего подтверждения
                </button>

                <button
                  type="button"
                  @click="toggleNotPlayed"
                  :class="quick.notPlayed ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-800 hover:bg-slate-200'"
                  class="px-3 py-1.5 rounded"
                >
                  С кем ещё не сыграл
                </button>

                <div class="ml-auto flex items-center gap-3 text-sm text-gray-500">
                  <span>Найдено: <b>{{ filteredMatches.length }}</b></span>
                  <button
                    type="button"
                    class="text-slate-600 hover:text-slate-900 underline"
                    @click="resetFilters"
                  >
                    Сбросить
                  </button>
                </div>
              </div>
            </div>

            <!-- Список матчей по фильтрам -->
            <div v-if="filteredMatches.length" class="space-y-3">
			  <div
				v-for="m in filteredMatches"
				:key="m.id"
				class="bg-white overflow-hidden shadow-sm sm:rounded-lg border p-4
						flex flex-col gap-4
						md:flex-row md:items-center md:justify-between"
				>
                <div class="flex-1 min-w-0">
                  <div class="text-sm text-gray-500">
                    {{ m.stage && m.stage.tournament ? m.stage.tournament.title : '' }}
                    <span v-if="m.stage"> — {{ m.stage.name }}</span>
                  </div>
					<!-- Игроки + команды + логотипы -->
					<div class="mt-1 flex items-center gap-4">
					<!-- HOME -->
					<div class="flex items-center gap-2 min-w-0">
						<div class="w-10 h-10 rounded bg-slate-100 flex items-center justify-center overflow-hidden">
						<img
							v-if="m.home?.nhl_team?.logo_url"
							:src="m.home.nhl_team.logo_url"
							:alt="m.home.nhl_team.name"
							class="w-full h-full object-contain"
						/>
						<span v-else class="text-[9px] text-gray-400 text-center px-1">
							logo
						</span>
						</div>
						<div class="min-w-0">
						<div class="text-sm font-semibold leading-tight truncate">
							{{ m.home?.nhl_team?.name || 'Без команды' }}
						</div>
						<div class="text-xs text-gray-600 leading-tight truncate">
							{{ m.home?.display_name || m.home?.user?.name || 'Home' }}
						</div>
						</div>
					</div>

					<!-- VS -->
					<div class="text-xs font-semibold text-gray-400 uppercase">
						vs
					</div>

					<!-- AWAY -->
					<div class="flex items-center gap-2 min-w-0">
						<div class="w-10 h-10 rounded bg-slate-100 flex items-center justify-center overflow-hidden">
						<img
							v-if="m.away?.nhl_team?.logo_url"
							:src="m.away.nhl_team.logo_url"
							:alt="m.away.nhl_team.name"
							class="w-full h-full object-contain"
						/>
						<span v-else class="text-[9px] text-gray-400 text-center px-1">
							logo
						</span>
						</div>
						<div class="min-w-0">
						<div class="text-sm font-semibold leading-tight truncate">
							{{ m.away?.nhl_team?.name || 'Без команды' }}
						</div>
						<div class="text-xs text-gray-600 leading-tight truncate">
							{{ m.away?.display_name || m.away?.user?.name || 'Away' }}
						</div>
						</div>
					</div>
					</div>

                  <!-- Статус матча (бейдж) -->
                  <div class="mt-2 flex flex-wrap items-center gap-2">
                    <StatusBadge :status="m.status" />

                    <span
                      v-if="isAutoConfirmed(m)"
                      class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600 border border-dashed border-slate-200"
                    >
                      подтверждено автоматически
                    </span>
                  </div>

                  <!-- Комментарий под статусом для pending-репорта -->
                  <div
                    v-if="lastReportOf(m) && lastReportOf(m).status === 'pending'"
                    class="mt-1 text-sm text-amber-900"
                  >
                    {{ pendingNoteOf(m) }}
                  </div>

                  <div v-if="m.status === 'confirmed'" class="mt-1 text-sm">
                    Счёт:
                    <span class="font-semibold">
                      {{ m.score_home }} : {{ m.score_away }}
                    </span>
                    <span v-if="m.ot"> (OT)</span>
                    <span v-if="m.so"> (SO)</span>
                  </div>
                </div>
				<div class="w-full md:w-auto md:flex-shrink-0">
					<Link
						:href="`/matches/${m.id}`"
						class="px-3 py-2 rounded bg-slate-900 text-white text-sm hover:bg-slate-800 transition
							w-full md:w-auto text-center"
					>
						Открыть
					</Link>
					</div>
              </div>
            </div>

            <!-- Пагинация -->
            <div
              v-if="matches && Array.isArray(matches.links) && matches.links.length > 1"
              class="mt-4 border border-gray-200 px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-3 bg-gray-50 rounded-lg"
            >
              <div class="text-xs text-gray-500">
                Страница
                <strong>{{ matches.current_page }}</strong>
                из
                <strong>{{ matches.last_page }}</strong>
                <span v-if="matches && matches.total">
                  · Всего матчей: {{ matches.total }}
                </span>
              </div>

              <nav class="inline-flex -space-x-px rounded-md shadow-sm overflow-hidden">
                <template
                  v-for="(link, idx) in (Array.isArray(matches.links) ? matches.links : [])"
                  :key="idx"
                >
                  <Link
                    v-if="link && link.url"
                    :href="link.url"
                    preserve-state
                    preserve-scroll
                    class="relative inline-flex items-center px-3 py-1.5 border text-xs font-medium"
                    :class="[
                      link.active
                        ? 'z-10 bg-slate-800 border-slate-800 text-white'
                        : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50',
                    ]"
                  >
                    {{ paginationLabel(link) }}
                  </Link>

                  <span
                    v-else
                    class="relative inline-flex items-center px-3 py-1.5 border border-gray-200 bg-gray-50 text-xs font-medium text-gray-400"
                  >
                    {{ paginationLabel(link) }}
                  </span>
                </template>
              </nav>
            </div>

            <!-- Нет результатов по фильтрам -->
            <div
              v-else
              class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-gray-500"
            >
              По выбранным фильтрам матчи не найдены.
            </div>
          </template>

          <!-- Совсем нет матчей -->
          <div
            v-else
            class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-gray-500"
          >
            Матчей пока нет.
          </div>
        </div>
      </div>
    </main>
  </AuthenticatedLayout>
</template>
