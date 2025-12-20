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
            Участников:
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
            В турнире нет активных участников.
          </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-xs border">
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
                  class="border-t"
                  :class="{
                    'bg-emerald-50': hasAssignments && currentIndex === idx
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
                      <span class="font-semibold">
                        {{ revealedAssignments[idx].team.code }}
                      </span>
                      —
                      {{ revealedAssignments[idx].team.name }}
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
            Нет участников для жеребьёвки.
          </div>

          <div v-else class="flex-1 flex flex-col items-center justify-center gap-4">
            <!-- Текущий игрок -->
            <div class="text-center">
              <div class="text-xs text-gray-500 mb-1">Текущий игрок</div>
              <div class="text-lg font-semibold">
                {{ currentParticipantLabel }}
              </div>
            </div>

            <!-- "Колесо" -->
            <div
              class="w-full max-w-sm h-40 rounded-2xl border flex items-center justify-center bg-slate-900 text-white shadow-inner"
            >
              <div class="text-center">
                <div class="text-xs uppercase tracking-wide text-gray-300 mb-1">
                  Команда
                </div>
                <div
                  class="text-2xl font-bold transition-transform duration-150"
                  :class="{ 'scale-110': isSpinning }"
                >
                  {{ displayTeamName }}
                </div>
                <div class="text-xs text-gray-400 mt-1">
                  {{ displayTeamCode }}
                </div>
              </div>
            </div>

            <!-- Кнопки -->
            <div class="flex flex-wrap justify-center gap-3">
              <button
                v-if="!hasAssignments"
                type="button"
                class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700 disabled:opacity-50"
                @click="startDraft"
                :disabled="draftRunning"
              >
                Начать жеребьёвку
              </button>

              <button
                v-else
                type="button"
                class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700 disabled:opacity-50"
                @click="spinForCurrent"
                :disabled="spinDisabled"
              >
                {{
                  currentIndex >= assignments.length
                    ? 'Жеребьёвка завершена'
                    : 'Запустить вращение'
                }}
              </button>
            </div>

            <div class="text-xs text-gray-500 text-center">
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
  assignments:  { type: Array, default: () => [] }, // <- добавили
})

const tournament   = props.tournament
const participants = computed(() => props.participants ?? [])
const draftTeams   = computed(() => props.draftTeams ?? [])

// assignments от backend (полное распределение)
const assignments = ref([...(props.assignments ?? [])])

watch(
  () => props.assignments,
  (val) => {
    assignments.value = [...(val ?? [])]
    // при новом распределении сбрасываем анимацию
    revealedAssignments.value = {}
    currentIndex.value = 0
    spinningTeamName.value = '—'
    spinningTeamCode.value = ''
  },
)

// Какие уже "раскрыты" на экране (по индексу участника)
const revealedAssignments = ref({})

// Текущий индекс участника
const currentIndex = ref(0)

const draftRunning = ref(false)
const isSpinning   = ref(false)

const spinningTeamName = ref('—')
const spinningTeamCode = ref('')
const spinningInterval = ref(null)

const hasAssignments = computed(() => assignments.value.length > 0)

const currentParticipantLabel = computed(() => {
  if (!participants.value.length) return '—'
  const idx = Math.min(currentIndex.value, participants.value.length - 1)
  const p = participants.value[idx]
  return p.display_name || p.user?.name || `User #${p.user_id}`
})

const displayTeamName = computed(() => spinningTeamName.value || '—')
const displayTeamCode = computed(() => spinningTeamCode.value || '')

const spinDisabled = computed(() => {
  if (!hasAssignments.value) return true
  if (currentIndex.value >= assignments.value.length) return true
  if (isSpinning.value) return true
  return false
})

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
        // После поста Inertia перерендерит страницу Draft
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
    : assignments.value.map(a => a.team)

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

    spinningTeamName.value = currentAssignment.team.name
    spinningTeamCode.value = currentAssignment.team.code

    // Запоминаем, что эта команда "вытащена"
    revealedAssignments.value = {
      ...revealedAssignments.value,
      [idx]: currentAssignment,
    }

    currentIndex.value += 1
  }, 1800)
}
</script>
