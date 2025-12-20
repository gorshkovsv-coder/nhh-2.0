<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import { formatDateTime } from '@/utils/datetime'

const props = defineProps({
  matches: {
    type: Object,
    default: () => ({
      data: [],
      links: [],
      meta: {},
    }),
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  filterOptions: {
    type: Object,
    default: () => ({
      tournaments: [],
      stages: [],
      players: [],
      teams: [],
    }),
  },
  statuses: {
    type: Array,
    default: () => [],
  },
})

// Локальное состояние фильтров (для v-model)
const localFilters = reactive({
  tournament_id: props.filters.tournament_id || '',
  stage_id: props.filters.stage_id || '',
  player_id: props.filters.player_id || '',
  team_id: props.filters.team_id || '',
  status: props.filters.status || '',
})

// Стадии только для выбранного турнира (если выбран)
const stagesForSelectedTournament = computed(() => {
  const all = props.filterOptions.stages || []
  const tId = localFilters.tournament_id
  if (!tId) return all
  return all.filter((s) => String(s.tournament_id) === String(tId))
})

const applyFilters = () => {
  router.get(
    '/admin/matches',
    {
      tournament_id: localFilters.tournament_id || '',
      stage_id: localFilters.stage_id || '',
      player_id: localFilters.player_id || '',
      team_id: localFilters.team_id || '',
      status: localFilters.status || '',
    },
    {
      preserveState: true,
      replace: true,
      preserveScroll: true,
    },
  )
}

const resetFilters = () => {
  localFilters.tournament_id = ''
  localFilters.stage_id = ''
  localFilters.player_id = ''
  localFilters.team_id = ''
  localFilters.status = ''
  applyFilters()
}

// Раскрытый матч
const expandedMatchId = ref(null)

// Форма редактирования текущего матча
const editForm = reactive({
  id: null,
  status: '',
  score_home: '',
  score_away: '',
  ot: false,
  so: false,
})

const openMatch = (match) => {
  expandedMatchId.value = match.id
  editForm.id = match.id
  editForm.status = match.status || 'scheduled'
  editForm.score_home = match.score_home ?? ''
  editForm.score_away = match.score_away ?? ''
  editForm.ot = !!match.ot
  editForm.so = !!match.so
}

const closeMatch = () => {
  expandedMatchId.value = null
  editForm.id = null
}

// 🔹 Новая функция переключения
const toggleMatch = (match) => {
  if (expandedMatchId.value === match.id) {
    closeMatch()
  } else {
    openMatch(match)
  }
}

const saveMatch = () => {
  if (!editForm.id) return

  router.put(
    `/admin/matches/${editForm.id}`,
    {
      status: editForm.status,
      score_home: editForm.score_home !== '' ? Number(editForm.score_home) : null,
      score_away: editForm.score_away !== '' ? Number(editForm.score_away) : null,
      ot: editForm.ot ? 1 : 0,
      so: editForm.so ? 1 : 0,
    },
    {
      preserveScroll: true,
    },
  )
}

const deleteMatch = (match) => {
  if (!confirm(`Удалить матч #${match.id}?`)) return
  router.delete(`/admin/matches/${match.id}`, {
    preserveScroll: true,
  })
}

const deleteReport = (match, report) => {
  if (!confirm(`Удалить репорт #${report.id} для матча #${match.id}?`)) return
  router.delete(`/admin/matches/${match.id}/reports/${report.id}`, {
    preserveScroll: true,
  })
}

const deleteAttachment = (match, report, index) => {
  if (!confirm('Удалить это вложение (скрин)?')) return
  router.delete(
    `/admin/matches/${match.id}/reports/${report.id}/attachments/${index}`,
    {
      preserveScroll: true,
    },
  )
}

const confirmReport = (match, report) => {
  if (!confirm(`Подтвердить репорт #${report.id} для матча #${match.id}?`)) return

  router.post(
    `/admin/matches/${match.id}/reports/${report.id}/confirm`,
    {},
    {
      preserveScroll: true,
    },
  )
}


// Вспомогательные отображения
const statusLabel = (value) => {
  const s = props.statuses.find((s) => s.value === value)
  return s ? s.label : value
}

const matchTitle = (m) => {
  const homeName =
    m?.home?.display_name ||
    m?.home?.user?.psn ||
    m?.home?.user?.name ||
    '—'
  const awayName =
    m?.away?.display_name ||
    m?.away?.user?.psn ||
    m?.away?.user?.name ||
    '—'
  return `${homeName} vs ${awayName}`
}

const matchTournamentTitle = (m) => {
  const t = m?.stage?.tournament
  if (!t) return '—'
  if (t.title) return t.title
  if (t.name && t.season) return `${t.name} (${t.season})`
  if (t.name) return t.name
  return '—'
}

const matchStageName = (m) => m?.stage?.name || '—'

const matchScore = (m) => {
  if (m.score_home === null || m.score_home === undefined) return '—'
  if (m.score_away === null || m.score_away === undefined) return '—'
  let s = `${m.score_home} : ${m.score_away}`
  if (m.ot) s += ' OT'
  if (m.so) s += ' SO'
  return s
}

// Берём «актуальный» репорт для отображения результата в списке матчей.
// В списке нам нужны счёт/победитель/проигравший/подтверждающий даже для статуса match.status === 'reported'.
const primaryReport = (m) => {
  const list = m?.reports || []
  // 1) Сначала ищем pending/confirmed — это «живые» репорты
  const alive = list.find((r) => r && (r.status === 'pending' || r.status === 'confirmed'))
  if (alive) return alive
  // 2) Фолбэк: самый последний (в контроллере уже latest('created_at'))
  return list.length ? list[0] : null
}

const formatScore = (homeScore, awayScore, ot, so) => {
  if (homeScore === null || homeScore === undefined) return '—'
  if (awayScore === null || awayScore === undefined) return '—'
  let s = `${homeScore} : ${awayScore}`
  if (ot) s += ' OT'
  if (so) s += ' SO'
  return s
}

// В таблице (свернутая строка):
// - если match.score_* уже заполнен — используем его
// - иначе (обычно при match.status === 'reported') берём из актуального репорта
const matchScoreForList = (m) => {
  if (m?.score_home !== null && m?.score_home !== undefined && m?.score_away !== null && m?.score_away !== undefined) {
    return formatScore(m.score_home, m.score_away, m.ot, m.so)
  }

  // Быстрый анализ нужен только для «Ожидает подтверждения» и «Подтверждён»
  if (!['reported', 'confirmed'].includes(m?.status)) return '—'

  const r = primaryReport(m)
  return formatScore(r?.score_home, r?.score_away, r?.ot, r?.so)
}

const participantDisplay = (tp) => {
  if (!tp) return '—'
  return tp.display_name || tp.user?.psn || tp.user?.name || '—'
}

const teamOnly = (tp) => {
  if (!tp) return '—'
  return tp?.nhl_team?.short_name || tp?.nhl_team?.name || '—'
}

const teamLabel = (team) => {
  if (!team) return '—'
  return team.short_name || team.code || team.name || '—'
}

const winnerTeamForList = (m) => {
  if (!shouldShowQuickResult(m)) return null
  const r = primaryReport(m)
  const sh = m?.score_home ?? r?.score_home
  const sa = m?.score_away ?? r?.score_away
  if (sh === null || sh === undefined || sa === null || sa === undefined) return null
  if (sh === sa) return null
  return (sh > sa ? m?.home : m?.away)?.nhl_team || null
}

const loserTeamForList = (m) => {
  if (!shouldShowQuickResult(m)) return null
  const r = primaryReport(m)
  const sh = m?.score_home ?? r?.score_home
  const sa = m?.score_away ?? r?.score_away
  if (sh === null || sh === undefined || sa === null || sa === undefined) return null
  if (sh === sa) return null
  return (sh > sa ? m?.away : m?.home)?.nhl_team || null
}

const winnerParticipantForList = (m) => {
  if (!shouldShowQuickResult(m)) return null
  const r = primaryReport(m)
  const sh = m?.score_home ?? r?.score_home
  const sa = m?.score_away ?? r?.score_away
  if (sh === null || sh === undefined || sa === null || sa === undefined) return null
  if (sh === sa) return null
  return sh > sa ? m?.home : m?.away
}

const loserParticipantForList = (m) => {
  if (!shouldShowQuickResult(m)) return null
  const r = primaryReport(m)
  const sh = m?.score_home ?? r?.score_home
  const sa = m?.score_away ?? r?.score_away
  if (sh === null || sh === undefined || sa === null || sa === undefined) return null
  if (sh === sa) return null
  return sh > sa ? m?.away : m?.home
}

const winnerPlayerNameForList = (m) => participantDisplay(winnerParticipantForList(m)) || '—'
const loserPlayerNameForList = (m) => participantDisplay(loserParticipantForList(m)) || '—'

const participantWithTeam = (tp) => {
  if (!tp) return '—'
  const name = participantDisplay(tp)
  const team = tp?.nhl_team?.short_name || tp?.nhl_team?.name
  return team ? `${name} (${team})` : name
}

const homeName = (match) => participantDisplay(match?.home)
const awayName = (match) => participantDisplay(match?.away)

const reporterLabel = (report) => participantDisplay(report?.reporter)

const confirmerLabel = (match, report) => {
  // 1) Если уже есть явный подтверждающий — показываем его
  if (report?.confirmer) {
    return participantDisplay(report.confirmer)
  }

  // 2) Иначе считаем, что подтверждающий — соперник отправителя
  const reporterPid = report?.reporter_participant_id
  if (!reporterPid || !match?.home || !match?.away) {
    return '—'
  }

  const homePid = match.home.id
  const awayPid = match.away.id

  if (String(reporterPid) === String(homePid)) {
    // репорт отправил хозяин -> подтверждающий гость
    return participantDisplay(match.away)
  }
  if (String(reporterPid) === String(awayPid)) {
    // репорт отправил гость -> подтверждающий хозяин
    return participantDisplay(match.home)
  }

  return '—'
}

const shouldShowQuickResult = (m) => ['reported', 'confirmed'].includes(m?.status)

const winnerLabelForList = (m) => {
  if (!shouldShowQuickResult(m)) return '—'
  const r = primaryReport(m)
  const sh = m?.score_home ?? r?.score_home
  const sa = m?.score_away ?? r?.score_away

  if (sh === null || sh === undefined || sa === null || sa === undefined) return '—'
  if (sh === sa) return '—'

  return sh > sa ? teamOnly(m?.home) : teamOnly(m?.away)
}

const loserLabelForList = (m) => {
  if (!shouldShowQuickResult(m)) return '—'
  const r = primaryReport(m)
  const sh = m?.score_home ?? r?.score_home
  const sa = m?.score_away ?? r?.score_away

  if (sh === null || sh === undefined || sa === null || sa === undefined) return '—'
  if (sh === sa) return '—'

  return sh > sa ? teamOnly(m?.away) : teamOnly(m?.home)
}

const confirmerLabelForList = (m) => {
  if (!shouldShowQuickResult(m)) return '—'
  if (isAutoConfirmed(m)) return 'Авто'
  const r = primaryReport(m)
  return confirmerLabel(m, r)
}

// Список матчей с учётом того, что matches теперь paginator-объект
const matchItems = computed(() => {
  // На всякий случай поддержим старый формат (если вдруг массив)
  if (Array.isArray(props.matches)) {
    return props.matches
  }
  return props.matches?.data ?? []
})

const hasMatches = computed(() => matchItems.value.length > 0)

const paginationLabel = (link) => {
  if (!link || !link.label) return ''

  if (link.label === 'pagination.previous') {
    return '‹ Предыдущая'
  }
  if (link.label === 'pagination.next') {
    return 'Следующая ›'
  }

  // для страниц "1", "2", "..." и т.п. просто отдадим как есть
  return link.label
}

const isAutoConfirmed = (match) => {
  return !!(match.meta && match.meta.auto_confirmed)
}

</script>


<template>
  <AuthenticatedLayout>
    <Head title="Админка — матчи" />

    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Админка (матчи)
      </h2>
    </template>

    <main class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Фильтры -->
        <div class="bg-white shadow-sm sm:rounded-xl p-4 sm:p-6 space-y-4">
          <div class="flex flex-wrap gap-4">
            <!-- Турнир -->
            <div class="flex flex-col gap-1">
              <label class="text-xs font-medium text-gray-500 uppercase">
                Турнир
              </label>
              <select
                v-model="localFilters.tournament_id"
                class="rounded-lg border-gray-300 text-sm"
                @change="
                  () => {
                    localFilters.stage_id = ''
                    applyFilters()
                  }
                "
              >
                <option value="">Все</option>
                <option
                  v-for="t in filterOptions.tournaments"
                  :key="t.id"
                  :value="t.id"
                >
                  {{ t.title || t.name }}<span v-if="t.season">
                    ({{ t.season }})</span
                  >
                </option>
              </select>
            </div>

            <!-- Стадия -->
            <div class="flex flex-col gap-1">
              <label class="text-xs font-medium text-gray-500 uppercase">
                Стадия
              </label>
              <select
                v-model="localFilters.stage_id"
                class="rounded-lg border-gray-300 text-sm"
                @change="applyFilters"
              >
                <option value="">Все</option>
                <option
                  v-for="s in stagesForSelectedTournament"
                  :key="s.id"
                  :value="s.id"
                >
                  {{ s.name }}
                </option>
              </select>
            </div>

            <!-- Игрок -->
            <div class="flex flex-col gap-1">
              <label class="text-xs font-medium text-gray-500 uppercase">
                Игрок
              </label>
              <select
                v-model="localFilters.player_id"
                class="rounded-lg border-gray-300 text-sm min-w-[180px]"
                @change="applyFilters"
              >
                <option value="">Все</option>
                <option
                  v-for="p in filterOptions.players"
                  :key="p.id"
                  :value="p.id"
                >
                  {{ p.name }}
                  <span v-if="p.psn"> (PSN: {{ p.psn }})</span>
                </option>
              </select>
            </div>

            <!-- Команда -->
            <div class="flex flex-col gap-1">
              <label class="text-xs font-medium text-gray-500 uppercase">
                Команда
              </label>
              <select
                v-model="localFilters.team_id"
                class="rounded-lg border-gray-300 text-sm"
                @change="applyFilters"
              >
                <option value="">Все</option>
                <option
                  v-for="team in filterOptions.teams"
                  :key="team.id"
                  :value="team.id"
                >
                  {{ team.short_name || team.name }}
                </option>
              </select>
            </div>

            <!-- Статус -->
            <div class="flex flex-col gap-1">
              <label class="text-xs font-medium text-gray-500 uppercase">
                Статус
              </label>
              <select
                v-model="localFilters.status"
                class="rounded-lg border-gray-300 text-sm"
                @change="applyFilters"
              >
                <option value="">Все</option>
                <option
                  v-for="s in statuses"
                  :key="s.value"
                  :value="s.value"
                >
                  {{ s.label }}
                </option>
              </select>
            </div>
          </div>

          <div class="flex justify-between items-center pt-2">
			<p class="text-xs text-gray-500">
			Показано матчей на странице:
			<strong>{{ matchItems.length }}</strong>
			<span
				v-if="matches && matches.total"
				class="text-gray-400"
			>
				· Всего: {{ matches.total }}
			</span>
			</p>
            <button
              type="button"
              class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium text-gray-700 border border-gray-300 hover:bg-gray-50"
              @click="resetFilters"
            >
              Сбросить фильтры
            </button>
          </div>
        </div>

        <!-- Таблица матчей -->
        <div class="bg-white shadow-sm sm:rounded-xl overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th
                    class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    #
                  </th>
                  <th
                    class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    Турнир / стадия
                  </th>
                  <th
                    class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    Команды
                  </th>
                  <th
                    class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    Статус
                  </th>
                  <th
                    class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    Счёт
                  </th>
                  <th
                    class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    Итог
                  </th>
                  <th
                    class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase"
                  >
                    Действия
                  </th>
                </tr>
              </thead>

              <tbody
                v-if="hasMatches"
                class="divide-y divide-gray-200 bg-white"
              >
                <template
				  v-for="match in matchItems"
                  :key="match.id"
                >
                  <!-- Основная строка -->
                  <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 text-sm text-gray-500">
                      #{{ match.id }}
                    </td>
                    <td class="px-3 py-2 text-sm text-gray-700">
                      <div class="font-medium">
                        {{ matchTournamentTitle(match) }}
                      </div>
                      <div class="text-xs text-gray-500">
                        {{ matchStageName(match) }}
                      </div>
                    </td>
                    <td class="px-3 py-2 text-sm text-gray-700">
                      <div class="flex flex-col gap-1">
                        <div>
                          <span class="text-xs text-gray-400">Дом:</span>
                          <span class="ml-1">
                            {{ match.home?.display_name ||
                            match.home?.user?.psn ||
                            match.home?.user?.name ||
                            '—' }}
                          </span>
                          <span
                            v-if="match.home?.nhl_team"
                            class="ml-1 text-xs text-gray-500"
                          >
                            ({{ match.home.nhl_team.short_name ||
                            match.home.nhl_team.name }})
                          </span>
                        </div>
                        <div>
                          <span class="text-xs text-gray-400">Гости:</span>
                          <span class="ml-1">
                            {{ match.away?.display_name ||
                            match.away?.user?.psn ||
                            match.away?.user?.name ||
                            '—' }}
                          </span>
                          <span
                            v-if="match.away?.nhl_team"
                            class="ml-1 text-xs text-gray-500"
                          >
                            ({{ match.away.nhl_team.short_name ||
                            match.away.nhl_team.name }})
                          </span>
                        </div>
                      </div>
                    </td>
<td class="px-3 py-2 text-sm text-gray-700">
  <div class="flex flex-wrap items-center gap-2">
    <StatusBadge :status="match.status" />

    <span
      v-if="isAutoConfirmed(match)"
      class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600 border border-dashed border-slate-200"
    >
      подтверждено автоматически
    </span>
  </div>
</td>

                    <td class="px-3 py-2 text-sm text-gray-700">
					  {{ matchScoreForList(match) }}
                    </td>
                    <td class="px-3 py-2 text-sm text-gray-700">
  <div v-if="shouldShowQuickResult(match)" class="space-y-2">
    <div class="flex flex-col gap-1 text-xs">
      <div class="flex items-center gap-1 leading-none">
        <img
          v-if="winnerTeamForList(match)?.logo_url"
          :src="winnerTeamForList(match).logo_url"
          alt=""
		  class="w-4 h-4 object-contain shrink-0"
        />
        <span class="px-2 py-0.5 rounded-md border border-green-500 text-green-700 font-bold whitespace-nowrap">
          {{ winnerPlayerNameForList(match) }}
        </span>
      </div>

	  <div class="flex items-center gap-1 leading-none">
        <img
          v-if="loserTeamForList(match)?.logo_url"
          :src="loserTeamForList(match).logo_url"
          alt=""
		  class="w-4 h-4 object-contain shrink-0"
        />
        <span class="px-2 py-0.5 rounded-md border border-red-500 text-red-700 font-bold whitespace-nowrap">
          {{ loserPlayerNameForList(match) }}
        </span>
      </div>
    </div>

    <div class="text-xs mt-3">
      <span class="text-gray-400">Подтверждающий:</span>
      <span class="ml-1">{{ confirmerLabelForList(match) }}</span>
    </div>
  </div>
						<div v-else class="text-sm text-gray-400">
						—
                      </div>
                    </td>
                    <td class="px-3 py-2 text-right text-sm">
<button
  type="button"
  class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-medium border border-gray-300 text-gray-700 hover:bg-gray-50 mr-2"
  @click="toggleMatch(match)"
>
  {{ expandedMatchId === match.id ? 'Скрыть' : 'Открыть' }}
</button>

                      <button
                        type="button"
                        class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-medium border border-red-200 text-red-700 hover:bg-red-50"
                        @click="deleteMatch(match)"
                      >
                        Удалить
                      </button>
                    </td>
                  </tr>

                  <!-- Детали матча: редактирование + история репортов -->
                  <tr v-if="expandedMatchId === match.id">
                    <td
                      colspan="7"
                      class="bg-gray-50 px-4 py-4 text-sm"
                    >
                      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <!-- Редактирование матча -->
                        <div class="lg:col-span-1 space-y-3">
                          <h3 class="font-medium text-gray-800 text-sm">
                            Редактирование матча
                          </h3>

                          <div class="space-y-2">
                            <div>
                              <label
                                class="block text-xs font-medium text-gray-500 uppercase mb-1"
                              >
                                Статус
                              </label>
                              <select
                                v-model="editForm.status"
                                class="w-full rounded-lg border-gray-300 text-sm"
                              >
                                <option
                                  v-for="s in statuses"
                                  :key="s.value"
                                  :value="s.value"
                                >
                                  {{ s.label }}
                                </option>
                              </select>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                              <div>
                                <label
                                  class="block text-xs font-medium text-gray-500 uppercase mb-1"
                                >
                                  Счёт (дом)
                                </label>
                                <input
                                  v-model="editForm.score_home"
                                  type="number"
                                  min="0"
                                  max="99"
                                  class="w-full rounded-lg border-gray-300 text-sm"
                                />
                              </div>
                              <div>
                                <label
                                  class="block text-xs font-medium text-gray-500 uppercase mb-1"
                                >
                                  Счёт (гости)
                                </label>
                                <input
                                  v-model="editForm.score_away"
                                  type="number"
                                  min="0"
                                  max="99"
                                  class="w-full rounded-lg border-gray-300 text-sm"
                                />
                              </div>
                            </div>

                            <div class="flex items-center gap-4">
                              <label class="inline-flex items-center gap-1 text-xs">
                                <input
                                  v-model="editForm.ot"
                                  type="checkbox"
                                  class="rounded border-gray-300"
                                />
                                <span>OT</span>
                              </label>
                              <label class="inline-flex items-center gap-1 text-xs">
                                <input
                                  v-model="editForm.so"
                                  type="checkbox"
                                  class="rounded border-gray-300"
                                />
                                <span>SO</span>
                              </label>
                            </div>
                          </div>

                          <div class="flex gap-2 pt-2">
                            <button
                              type="button"
                              class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800 text-white hover:bg-slate-900"
                              @click="saveMatch"
                            >
                              Сохранить
                            </button>
                            <button
                              type="button"
                              class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-300 text-gray-700 hover:bg-gray-50"
                              @click="closeMatch"
                            >
                              Закрыть
                            </button>
                          </div>
                        </div>

                        <!-- История репортов -->
                        <div class="lg:col-span-2 space-y-3">
                          <h3 class="font-medium text-gray-800 text-sm">
                            История репортов
                          </h3>

                          <div
                            v-if="(match.reports || []).length"
                            class="space-y-3"
                          >
                            <div
                              v-for="report in match.reports"
                              :key="report.id"
                              class="rounded-lg border border-gray-200 bg-white p-3"
                            >
                              <div class="flex justify-between gap-2 mb-2">
                                <div>
                                  <div class="text-xs text-gray-500">
                                    Репорт #{{ report.id }}
                                  </div>
                                  <div class="text-sm text-gray-800">
                                    {{ formatDateTime(report.created_at) }}
                                  </div>
                                </div>
<div class="text-right space-y-1">
  <StatusBadge :status="report.status" />

  <!-- Явное соответствие счёта командам -->
  <div class="text-xs text-gray-500">
    {{ homeName(match) }}: {{ report.score_home ?? '—' }}
  </div>
  <div class="text-xs text-gray-500">
    {{ awayName(match) }}: {{ report.score_away ?? '—' }}
  </div>

  <!-- OT / SO отдельной строкой -->
  <div
    v-if="report.ot || report.so"
    class="text-[11px] text-gray-400"
  >
    <span v-if="report.ot">OT</span>
    <span v-if="report.ot && report.so"> · </span>
    <span v-if="report.so">SO</span>
  </div>
</div>

                              </div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-2">
  <div class="text-xs text-gray-600">
    <div class="font-medium text-gray-700 mb-1">
      Отправитель
    </div>
    <div>
      {{ reporterLabel(report) }}
    </div>
  </div>

  <div class="text-xs text-gray-600">
    <div class="font-medium text-gray-700 mb-1">
      Подтверждающий
    </div>
    <div>
      {{ confirmerLabel(match, report) }}
    </div>
  </div>
</div>


                              <div
                                v-if="report.comment"
                                class="text-xs text-gray-700 mb-2"
                              >
                                <span class="font-medium text-gray-600">
                                  Комментарий:
                                </span>
                                {{ report.comment }}
                              </div>

                              <!-- Скриншоты -->
                              <div
                                v-if="report.attachments && report.attachments.length"
                                class="mt-2"
                              >
                                <div class="text-xs font-medium text-gray-600 mb-1">
                                  Скриншоты:
                                </div>
                                <div class="flex flex-wrap gap-2">
                                  <div
                                    v-for="(src, idx) in report.attachments"
                                    :key="idx"
                                    class="relative"
                                  >
                                    <a
                                      :href="src"
                                      target="_blank"
                                      class="block w-28 h-20 rounded-lg overflow-hidden border bg-slate-100 hover:ring-2 hover:ring-sky-500 transition"
                                    >
                                      <img
                                        :src="src"
                                        :alt="`Скриншот ${idx + 1}`"
                                        class="w-full h-full object-cover"
                                      />
                                    </a>
                                    <button
                                      type="button"
                                      class="absolute -top-1 -right-1 inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/90 text-[10px] text-red-700 border border-red-200 hover:bg-red-50"
                                      @click="deleteAttachment(match, report, idx)"
                                    >
                                      ×
                                    </button>
                                  </div>
                                </div>
                              </div>

<div class="pt-2 flex justify-end gap-2">
  <!-- Показываем кнопку подтверждения только для ожидающих репортов -->
  <button
    v-if="report.status === 'pending'"
    type="button"
    class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-medium bg-emerald-600 text-white hover:bg-emerald-700"
    @click="confirmReport(match, report)"
  >
    Подтвердить репорт
  </button>

  <button
    type="button"
    class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-medium border border-red-200 text-red-700 hover:bg-red-50"
    @click="deleteReport(match, report)"
  >
    Удалить репорт
  </button>
</div>

                            </div>
                          </div>

                          <div
                            v-else
                            class="text-xs text-gray-500"
                          >
                            Репортов пока нет.
                          </div>
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>

              <tbody
                v-else
                class="bg-white"
              >
                <tr>
                  <td
                    colspan="7"
                    class="px-4 py-6 text-center text-sm text-gray-500"
                  >
                    Под критерии фильтра не найдено ни одного матча.
                  </td>
                </tr>
              </tbody>
            </table>
			
            <!-- Пагинация -->
            <div
			  v-if="matches && Array.isArray(matches.links) && matches.links.length > 1"
			  class="border-t border-gray-200 px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-3 bg-gray-50"
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
          </div>
        </div>
      </div>
    </main>
  </AuthenticatedLayout>
</template>
