<template>
  <AuthenticatedLayout>
    <Head :title="`Жеребьёвка команд — ${tournament.title}`" />

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
      <!-- Заголовок -->
      <div class="flex items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold">Жеребьёвка команд</h1>
          <p class="text-sm text-gray-600">
            Турнир:
            <span class="font-semibold">{{ tournament.title }}</span>
          </p>
        </div>

        <div class="text-xs text-gray-500 text-right">
          <div>
            Участников в жеребьёвке:
            <span class="font-semibold">{{ participants.length }}</span>
          </div>
          <div>
            Команд для жеребьёвки:
            <span class="font-semibold">{{ draftTeams.length }}</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Список игроков и итог распределения -->
        <div class="bg-white shadow-sm rounded-xl p-4 space-y-3">
          <h2 class="text-base font-semibold">Участники и команды</h2>

          <div v-if="participants.length === 0" class="text-sm text-gray-500">
            В жеребьёвке нет участников без назначенной команды.
          </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-xs border rounded-lg overflow-hidden">
              <thead class="bg-slate-50 uppercase">
                <tr>
                  <th class="border px-2 py-1 text-left">#</th>
                  <th class="border px-2 py-1 text-left">Игрок</th>
                  <th class="border px-2 py-1 text-left">Команда</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(p, idx) in participants"
                  :key="p.id"
                  class="border-t transition-colors"
                  :class="{
                    'bg-emerald-50/60': hasAssignments && currentIndex === idx && !isSpinning,
                    'bg-emerald-50/30': revealedAssignments[idx],
                  }"
                >
                  <td class="border px-2 py-1 text-[11px] text-gray-500">
                    {{ idx + 1 }}
                  </td>
                  <td class="border px-2 py-1 text-[11px]">
                    <div class="font-semibold">
                      {{ p.display_name || p.user?.name || `User #${p.user_id}` }}
                    </div>
                    <div class="text-[10px] text-gray-500">
                      ID участника: {{ p.id }}, user_id: {{ p.user_id }}
                    </div>
                  </td>
<td class="border px-2 py-1 text-[11px]">
  <div v-if="revealedAssignments[idx]">
    <div class="flex items-center gap-2">
      <!-- Лого команды -->
      <img
        v-if="revealedAssignments[idx].team.logo_url"
        :src="revealedAssignments[idx].team.logo_url"
        :alt="revealedAssignments[idx].team.name"
        class="w-6 h-6 rounded-full border border-slate-200 bg-white object-contain"
      />

      <!-- Код + название -->
      <span class="inline-flex items-center gap-1">
        <span
          class="inline-flex items-center justify-center h-5 px-2 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-semibold"
        >
          {{ revealedAssignments[idx].team.code }}
        </span>
        <span>{{ revealedAssignments[idx].team.name }}</span>
      </span>
    </div>
  </div>
  <div v-else class="text-gray-400 italic">
    Ещё не разыграна
  </div>
</td>

                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Блок жеребьёвки -->
        <div class="bg-white shadow-sm rounded-xl p-4 flex flex-col gap-4">
          <h2 class="text-base font-semibold">Колесо фортуны</h2>

          <div v-if="participants.length === 0" class="text-sm text-gray-500">
            Нет участников для жеребьёвки. Добавьте новых игроков или очистите команды.
          </div>

          <div
            v-else
            class="flex-1 flex flex-col items-center justify-center gap-4"
          >
            <!-- Текущий игрок -->
		<div class="text-center">
		<div class="text-xs text-gray-500 mb-1">
			{{
			lastResult
				? 'Игрок, для которого определена команда'
				: 'Текущий игрок'
			}}
		</div>
		<div class="text-lg font-semibold">
			{{ currentParticipantLabel }}
		</div>
		</div>

            <!-- "Колесо" -->
            <div
              class="w-full max-w-sm h-44 rounded-2xl border border-slate-700/60 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white shadow-xl flex items-center justify-center relative overflow-hidden"
              :class="{
                'ring-2 ring-emerald-400/80 ring-offset-2 ring-offset-slate-900':
                  isSpinning,
              }"
            >
              <!-- декоративный "маркер сверху" -->
              <div
                class="absolute top-2 left-1/2 -translate-x-1/2 w-20 h-1 rounded-full bg-emerald-400/80"
              ></div>

              <div class="text-center px-4">
                <div class="mb-2">
                  <span
                    v-if="isSpinning"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-amber-400 text-amber-900 uppercase font-semibold animate-pulse"
                  >
                    🎰 Жеребьёвка...
                  </span>
                  <span
                    v-else-if="lastResult"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-emerald-400 text-emerald-900 uppercase font-semibold"
                  >
                    ✅ Команда определена
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-slate-700 text-slate-200 uppercase font-semibold"
                  >
                    🏒 Готовы к жеребьёвке
                  </span>
                </div>

                <div
                  class="text-2xl font-extrabold transition-transform duration-150 tracking-wide"
                  :class="{ 'scale-110': isSpinning }"
                >
                  {{ displayTeamName }}
                </div>
                <div class="text-xs text-gray-300 mt-1 tracking-widest">
                  {{ displayTeamCode }}
                </div>
              </div>
            </div>

            <!-- Результат для текущего игрока -->
            <div
              v-if="lastResult"
              class="w-full max-w-sm border border-emerald-200 bg-emerald-50/70 rounded-xl px-4 py-3 text-xs text-emerald-900 flex items-start gap-2"
            >
              <div class="mt-[2px]">
                🎉
              </div>
					<div>
						<div class="font-semibold text-[13px] mb-1">
						Результат жеребьёвки
						</div>
						<div>
						<span class="font-semibold">
							{{ lastResult.playerName }}
						</span>
						будет играть за
						<span class="font-semibold">
							{{ lastResult.team.code }}
						</span>
						— {{ lastResult.team.name }}.
						</div>

						<div
						v-if="nextParticipantLabel"
						class="mt-2 text-[11px] text-emerald-800/80"
						>
						Следующий игрок:
						<span class="font-semibold">{{ nextParticipantLabel }}</span>
						</div>
					</div>

            </div>

            <!-- Кнопки -->
            <div class="flex flex-wrap justify-center gap-3 mt-2">
              <button
                v-if="!hasAssignments"
                type="button"
                class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700 disabled:opacity-50 flex items-center gap-2"
                @click="startDraft"
                :disabled="draftRunning"
              >
                <span v-if="draftRunning" class="animate-spin text-xs">
                  ⏳
                </span>
                <span>Начать жеребьёвку</span>
              </button>

              <button
                v-else
                type="button"
                class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2"
                @click="spinForCurrent"
                :disabled="spinDisabled"
              >
                <span v-if="isSpinning" class="animate-spin text-xs">
                  🔄
                </span>
                <span>
                  {{
                    currentIndex >= assignments.length
                      ? 'Жеребьёвка завершена'
                      : 'Запустить вращение'
                  }}
                </span>
              </button>
            </div>

            <div class="text-xs text-gray-500 text-center mt-1">
              Шаг {{ Math.min(currentIndex + 1, participants.length) }}
              из
              {{ participants.length }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  tournament:   { type: Object, required: true },
  participants: { type: Array, default: () => [] },
  draftTeams:   { type: Array, default: () => [] },
  assignments:  { type: Array, default: () => [] }, // массив распределения от сервера
})

const tournament   = props.tournament
const participants = computed(() => props.participants ?? [])
const draftTeams   = computed(() => props.draftTeams ?? [])

// assignments от backend (полное распределение)
const assignments = ref([...(props.assignments ?? [])])

// какие уже "раскрыты" на экране (по индексу участника)
const revealedAssignments = ref({})

// текущий индекс участника (которого сейчас крутим)
const currentIndex = ref(0)

const draftRunning = ref(false)
const isSpinning   = ref(false)

const spinningTeamName = ref('—')
const spinningTeamCode = ref('')
const spinningInterval = ref(null)

const hasAssignments = computed(() => assignments.value.length > 0)

const currentParticipantLabel = computed(() => {
  if (!participants.value.length) return '—'

  // Пока нет распределения или идёт вращение —
  // показываем того, кто прямо сейчас "крутится"
  if (!hasAssignments.value || isSpinning.value) {
    const idx = Math.min(currentIndex.value, participants.value.length - 1)
    const p = participants.value[idx]
    return p.display_name || p.user?.name || `User #${p.user_id}`
  }

  // Жеребьёвка уже была хотя бы один раз и сейчас не крутится —
  // показываем игрока, для которого только что определилась команда
  const idx = Math.min(
    Math.max(currentIndex.value - 1, 0),
    participants.value.length - 1,
  )
  const p = participants.value[idx]
  return p.display_name || p.user?.name || `User #${p.user_id}`
})

const displayTeamName = computed(() => spinningTeamName.value || '—')
const displayTeamCode = computed(() => spinningTeamCode.value || '')

// Последний успешно раскрытый результат (для красивого блока "Результат жеребьёвки")
const lastResult = computed(() => {
  if (!participants.value.length) return null
  const idx = currentIndex.value - 1
  if (idx < 0) return null

  const assignment = revealedAssignments.value[idx]
  if (!assignment) return null

  const p = participants.value[idx]

  return {
    playerName: p.display_name || p.user?.name || `User #${p.user_id}`,
    team: assignment.team,
  }
})

// Следующий игрок в очереди (для подписи "Следующий игрок")
const nextParticipantLabel = computed(() => {
  // Показываем только после того, как есть первый результат
  if (!lastResult.value) return null
  if (!participants.value.length) return null

  // currentIndex всегда указывает на "следующего"
  if (currentIndex.value >= participants.value.length) return null

  const p = participants.value[currentIndex.value]
  return p.display_name || p.user?.name || `User #${p.user_id}`
})


// Блокировка кнопки "Запустить вращение"
const spinDisabled = computed(() => {
  if (!hasAssignments.value) return true
  if (currentIndex.value >= assignments.value.length) return true
  if (isSpinning.value) return true
  return false
})

// Если backend прислал новые assignments (после "Начать жеребьёвку"),
// подхватываем их и сбрасываем состояние
watch(
  () => props.assignments,
  (val) => {
    assignments.value = [...(val ?? [])]
    revealedAssignments.value = {}
    currentIndex.value = 0
    spinningTeamName.value = '—'
    spinningTeamCode.value = ''
  },
)

// Старт: вызываем backend для формирования assignments
const startDraft = () => {
  if (!participants.value.length) {
    alert('Нет участников для жеребьёвки.')
    return
  }
  if (!draftTeams.value.length) {
    alert('Не выбраны команды для жеребьёвки в настройках турнира.')
    return
  }

  draftRunning.value = true

  router.post(
    `/admin/tournaments/${tournament.id}/draft/run`,
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        draftRunning.value = false
        // После POST Inertia перерендерит страницу Draft
        // с новыми props.assignments, watcher выше их подхватит
      },
    },
  )
}

const spinForCurrent = () => {
  if (spinDisabled.value) return

  const idx = currentIndex.value
  const currentAssignment = assignments.value[idx]
  if (!currentAssignment) return

  // Пул для "мигания" — либо все draftTeams, либо команды из assignments
  const pool = draftTeams.value.length
    ? draftTeams.value
    : assignments.value.map((a) => a.team)

  isSpinning.value = true

  spinningInterval.value = setInterval(() => {
    const randomTeam =
      pool[Math.floor(Math.random() * pool.length)] || currentAssignment.team
    spinningTeamName.value = randomTeam.name
    spinningTeamCode.value = randomTeam.code
  }, 120)

  setTimeout(() => {
    if (spinningInterval.value) {
      clearInterval(spinningInterval.value)
      spinningInterval.value = null
    }
    isSpinning.value = false

    // фиксируем выпавшую команду
    spinningTeamName.value = currentAssignment.team.name
    spinningTeamCode.value = currentAssignment.team.code

    revealedAssignments.value = {
      ...revealedAssignments.value,
      [idx]: currentAssignment,
    }

    currentIndex.value += 1
  }, 1800)
}
</script>
