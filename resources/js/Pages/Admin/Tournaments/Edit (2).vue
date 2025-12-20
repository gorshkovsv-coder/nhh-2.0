<template>
  <AuthenticatedLayout>
    <Head :title="`Редактирование турнира — ${tForm.title || 'Без названия'}`" />

    <!-- Шапка -->
    <div class="max-w-6xl mx-auto flex items-center justify-between py-5">
      <h1 class="text-xl font-semibold">Редактирование турнира</h1>
      <div class="flex gap-2">
        <button type="button" @click="saveTournament"
                class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">
          Сохранить
        </button>
        <button type="button" @click="removeTournament"
                class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700">
          Удалить турнир
        </button>
      </div>
    </div>

    <div class="max-w-6xl mx-auto space-y-8">

      <!-- ===== Основные настройки ===== -->
      <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <h2 class="text-lg font-semibold mb-4">Основные настройки</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="block text-sm text-gray-700 mb-1">Название</label>
            <input v-model="tForm.title" type="text" class="border rounded w-full px-3 py-2" />
          </div>

          <div>
            <label class="block text-sm text-gray-700 mb-1">Сезон</label>
            <input v-model.number="tForm.season" type="number" class="border rounded w-full px-3 py-2" />
          </div>

          <div>
            <label class="block text-sm text-gray-700 mb-1">Формат</label>
            <select v-model="tForm.format" class="border rounded w-full px-3 py-2">
              <option value="groups_playoff">Группы + плей-офф</option>
              <option value="group_only">Только группы</option>
              <option value="playoff">Только плей-офф</option>
            </select>
          </div>

          <div>
            <label class="block text-sm text-gray-700 mb-1">Статус</label>
            <select v-model="tForm.status" class="border rounded w-full px-3 py-2">
              <option value="draft">Черновик</option>
			  <option value="registration">Идёт регистрация</option>
              <option value="active">Активен</option>
              <option value="archived">Завершён</option>
            </select>
          </div>
        </div>
      </div>

      <!-- ===== Стадии ===== -->
      <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">Стадии</h2>
          <div class="flex items-end gap-2">
            <select v-model="createStageForm.type" class="border rounded px-3 py-2">
              <option value="group">Группа</option>
              <option value="playoff">Плей-офф</option>
            </select>
            <input v-model="createStageForm.name" type="text" placeholder="Название стадии"
                   class="border rounded px-3 py-2" />
            
			<!-- Для групповой стадии указываем, сколько матчей играют участники между собой -->
			<div v-if="createStageForm.type === 'group'">
			<label class="block text-xs text-gray-600 mb-1">Матчей между собой</label>
			<select v-model.number="createStageForm.games_per_pair" class="border rounded px-3 py-2 w-40">
				<option :value="1">1 матч</option>
				<option :value="2">2 матча</option>
				<option :value="3">3 матча</option>
				<option :value="4">4 матча</option>
			</select>
			</div>
			
			<!-- Для плей-офф поясняем, что длина серии задаётся «До поражений» -->
			<div v-else class="text-xs text-gray-500 w-40">
			Длина серии плей-офф задаётся параметром «До поражений» при формировании.
			</div>
			
			<button type="button" @click="createStage"
                    class="px-3 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">
              Создать стадию
            </button>
          </div>
        </div>

        <div v-if="stageList.length === 0" class="text-gray-500 text-sm">
          Стадии ещё не созданы.
        </div>

        <div v-for="stage in stageList" :key="stage.id" class="border rounded-md p-4 mb-3">
          <div class="flex flex-wrap items-center gap-3 justify-between">
            <div>
              <div class="font-semibold">{{ stage.name }}</div>
              <div class="text-xs text-gray-500">Тип: {{ stage.type }}</div>
            </div>

            <div class="flex items-center gap-2">

              <button type="button"
                      class="px-3 py-1 rounded border border-red-300 text-xs hover:bg-red-50"
                      @click="deleteStage(stage)">
                Удалить стадию
              </button>
            </div>
          </div>

          <!-- Управление групповыми стадиями -->
          <template v-if="stage.type === 'group'">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
              <div>
                <label class="block text-xs text-gray-600 mb-1">Сколько команд выходит</label>
                <select v-model.number="advancersMap[stage.id]" class="border rounded px-2 py-1 w-full">
                  <option :value="4">4</option>
                  <option :value="8">8</option>
                  <option :value="16">16</option>
                </select>
              </div>

              <div>
                <label class="block text-xs text-gray-600 mb-1">До поражений</label>
                <select v-model.number="lossesMap[stage.id]" class="border rounded px-2 py-1 w-full">
                  <option :value="1">1 (Bo1)</option>
                  <option :value="2">2 (Bo3)</option>
                  <option :value="3">3 (Bo5)</option>
                  <option :value="4">4 (Bo7)</option>
                </select>
              </div>

              <div>
                <label class="block text-xs text-gray-600 mb-1">Матч за 3-е место</label>
                <select v-model="thirdMap[stage.id]" class="border rounded px-2 py-1 w-full">
                  <option :value="false">Нет</option>
                  <option :value="true">Да</option>
                </select>
              </div>

				<!-- <div>
				<label class="block text-xs text-gray-600 mb-1">Матчей между собой</label>
				<select v-model.number="gpMap[stage.id]" class="border rounded px-2 py-1 w-full">
					<option :value="1">1 матч</option>
					<option :value="2">2 матча</option>
					<option :value="3">3 матча</option>
					<option :value="4">4 матча</option>
				</select>
				<p class="mt-1 text-[11px] text-gray-500">
					Используется при генерации календаря для этой стадии.
				</p>
				</div> -->

              <div class="flex items-end gap-2">
                <button type="button" @click="generateRoundRobin(stage.id)"
                        class="px-3 py-2 rounded bg-slate-600 text-white hover:bg-slate-700">
                  Сгенерировать календарь
                </button>
                <button type="button" @click="generatePlayoffFromStage(stage.id)"
                        class="px-3 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">
                  Сформировать плей-офф
                </button>
              </div>
            </div>
          </template>

          <!-- Управление плей-офф стадиями -->
          <template v-else-if="stage.type === 'playoff'">
            <div class="mt-4 text-sm text-gray-500">
              Настройка плей-офф производится при формировании из групповой стадии.
            </div>
          </template>
        </div>
      </div>

<!-- ===== Участники ===== -->
<div class="bg-white shadow-sm sm:rounded-lg p-6">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold">Участники</h2>
    <div class="flex items-end gap-2">
      <input
        v-model.number="addParticipantForm.user_id"
        type="number"
        placeholder="ID пользователя"
        class="border rounded px-3 py-2 w-44"
      />
      <input
        v-model="addParticipantForm.display_name"
        type="text"
        placeholder="Отображаемое имя (опц.)"
        class="border rounded px-3 py-2 w-64"
      />
      <button
        type="button"
        @click="addParticipant"
        class="px-3 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700"
      >
        Добавить участника
      </button>

      <!-- Кнопка рандомного распределения команд -->
      <button
        type="button"
        @click="randomizeTeams"
        class="px-3 py-2 rounded bg-slate-700 text-white hover:bg-slate-800"
      >
        Рэндом
      </button>
    </div>
  </div>

  <div v-if="participantsList.length === 0" class="text-gray-500 text-sm">
    Участников пока нет.
  </div>

  <div v-else class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="text-gray-500">
        <tr class="[&>th]:px-3 [&>th]:py-2 text-left">
          <th>#</th>
          <th>User ID</th>
          <th>Игрок</th>
          <th>Email</th>
          <th>Команда (NHL)</th>
          <th></th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <tr
          v-for="(p, idx) in participantsList"
          :key="p.id"
          class="[&>td]:px-3 [&>td]:py-2"
        >
          <td class="text-gray-500">
            {{ idx + 1 }}
          </td>
          <td class="text-gray-600">
            {{ p.user_id }}
          </td>
          <td class="font-medium">
            {{ p.display_name }}
          </td>
          <td class="text-gray-600">
            {{ p.user?.email || '—' }}
          </td>
          <td>
            <select
              class="border rounded px-2 py-1 text-xs"
              :value="p.nhl_team_id || ''"
              @change="updateParticipantTeam(p, $event)"
            >
              <option value="">
                — без команды —
              </option>
              <option
                v-for="team in teamsForParticipant(p)"
                :key="team.id"
                :value="team.id"
              >
                {{ team.code }} — {{ team.name }}
              </option>
            </select>
          </td>
          <td class="text-right">
            <button
              type="button"
              @click="removeParticipant(p.id)"
              class="px-3 py-1.5 rounded bg-red-600 text-white hover:bg-red-700"
            >
              Удалить
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>


<!-- ===== Команды для жеребьёвки ===== -->
<div class="bg-white shadow-sm sm:rounded-lg p-6">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold">Команды для жеребьёвки</h2>
    <div class="text-xs text-gray-500 text-right">
      <div>
        Выбрано команд:
        <span class="font-semibold">{{ selectedDraftTeamIds.length }}</span>
      </div>
      <div>
        Активных участников:
        <span class="font-semibold">{{ participantsList.length }}</span>
      </div>
    </div>
  </div>

  <p class="text-sm text-gray-600 mb-3">
    Отметьте команды, которые будут участвовать в жеребьёвке для этого турнира.
    Их количество должно быть не меньше количества активных участников.
  </p>

  <div v-if="nhlTeams.length === 0" class="text-sm text-gray-500">
    Список команд NHL пуст.
  </div>

  <div v-else class="overflow-x-auto max-h-80 border rounded">
    <table class="min-w-full text-xs">
      <thead class="bg-slate-50 uppercase">
        <tr>
          <th class="px-2 py-1 text-left w-10">Выбрать</th>
          <th class="px-2 py-1 text-left">Код</th>
          <th class="px-2 py-1 text-left">Команда</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="team in nhlTeams"
          :key="team.id"
          class="border-t"
        >
          <td class="px-2 py-1">
            <input
              type="checkbox"
              :value="team.id"
              v-model="selectedDraftTeamIds"
              class="rounded"
            />
          </td>
          <td class="px-2 py-1 font-semibold">
            {{ team.code }}
          </td>
          <td class="px-2 py-1">
            {{ team.name }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="mt-4 flex justify-end">
    <button
      type="button"
      class="px-4 py-2 rounded bg-slate-800 text-white text-sm hover:bg-slate-900 disabled:opacity-50"
      @click="saveDraftTeams"
      :disabled="savingDraftTeams"
    >
      Сохранить список команд
    </button>
  </div>
</div>


    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  tournament:   { type: Object, required: true },
  stages:       { type: Array, required: true },
  participants: { type: Array, default: () => [] },
  nhlTeams:     { type: Array, default: () => [] },
  draftTeamIds:   { type: Array, default: () => [] }, // <— добавили
})

/* ===== Локальный стейт ===== */
const tForm = ref({
  id: props.tournament?.id,
  title: props.tournament?.title ?? '',
  season: props.tournament?.season ?? new Date().getFullYear(),
  format: props.tournament?.format ?? 'groups_playoff',
  status: props.tournament?.status ?? 'draft',
})

const stageList = computed(() => props.stages ?? [])
const participantsList = ref([...(props.participants ?? [])])

/** Команды, доступные для выбора именно этому участнику
 *  (исключаем уже занятые другими, но оставляем его текущую команду) */
const teamsForParticipant = (participant) => {
  const takenByOthers = new Set(
    participantsList.value
      .filter(p => p.id !== participant.id && p.nhl_team_id)
      .map(p => p.nhl_team_id)
  )

  return (props.nhlTeams ?? []).filter(team => {
    if (!takenByOthers.has(team.id)) return true
    return team.id === participant.nhl_team_id
  })
}


const selectedDraftTeamIds = ref([...(props.draftTeamIds ?? [])])
const savingDraftTeams = ref(false)

const saveDraftTeams = () => {
  savingDraftTeams.value = true

  router.post(
    `/admin/tournaments/${tForm.value.id}/draft-teams`,
    {
      team_ids: selectedDraftTeamIds.value,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        savingDraftTeams.value = false
        alert('Список команд для жеребьёвки сохранён')
      },
      onError: (errors) => {
        savingDraftTeams.value = false
        const first = errors && Object.values(errors)[0]
        alert(first || 'Ошибка при сохранении списка команд')
      },
    },
  )
}


/* Создание стадии */
const createStageForm = ref({
  type: 'group',
  name: '',
  games_per_pair: 1,
})

/* Параметры генерации плей-офф из групповой стадии */
const advancersMap = ref({})   // { [stageId]: 4|8|16 }
const lossesMap    = ref({})   // { [stageId]: 1..4 }
const thirdMap     = ref({})   // { [stageId]: true|false }
const gpMap       = ref({})   // { [stageId]: 1..4 } — для групповых стадий

/* Добавление участника */
const addParticipantForm = ref({ user_id: null, display_name: '' })

/* ===== Методы ===== */
const saveTournament = () => {
  router.put(`/admin/tournaments/${tForm.value.id}`, {
    title:  (tForm.value.title ?? '').trim(),
    season: String(tForm.value.season ?? ''),     // в БД строка — отправляем строкой
    format: tForm.value.format,                   // groups_playoff | group_only | playoff
    status: tForm.value.status,                   // draft | registration | active | archived
  }, {
    preserveScroll: true,
    onSuccess: () => {
      alert('Турнир сохранён')
    },
    onError: (errors) => {
      // покажем первую ошибку валидации, если есть
      const first = errors && Object.values(errors)[0]
      alert(first ? `Ошибка сохранения: ${first}` : 'Ошибка сохранения')
    },
  })
}

const removeTournament = () => {
  if (!confirm('Удалить турнир безвозвратно?')) return
  router.delete(`/admin/tournaments/${tForm.value.id}`, { preserveScroll: true })
}

const createStage = () => {
  if (!createStageForm.value.name?.trim()) {
    alert('Укажите название стадии'); return
  }
  router.post(`/admin/tournaments/${tForm.value.id}/stages`, {
    type: createStageForm.value.type,
    name: createStageForm.value.name,
    games_per_pair: Number(createStageForm.value.games_per_pair || 1),
  }, { preserveScroll: true })
}

const deleteStage = (stage) => {
  if (!confirm(`Удалить стадию "${stage.name}"?`)) return
  router.delete(`/admin/tournaments/stages/${stage.id}`, {}, { preserveScroll: true })
}

/** Сгенерировать круговой календарь (групповая стадия) */
const generateRoundRobin = (stageId) => {
  const stage = stageList.value.find(s => s.id === stageId)
  const gamesPerPair =
    Number(gpMap.value?.[stageId] ?? stage?.games_per_pair ?? 1)

  router.post(
    `/admin/tournaments/stages/${stageId}/generate-round-robin`,
    { games_per_pair: Math.max(1, Math.min(4, gamesPerPair)) },
    {
      preserveScroll: true,
      onSuccess: () => { alert('Круговой календарь сгенерирован') },
      onError: (errors) => {
        const first = errors && Object.values(errors)[0]
        alert(first ? `Ошибка генерации: ${first}` : 'Ошибка генерации. Попробуйте позже.')
      },
    }
  )
}

/** Сформировать плей-офф из группы (учитывает "Матч за 3-е место") */
const generatePlayoffFromStage = (stageId) => {
  const stage = stageList.value.find(s => s.id === stageId)
  const adv = Number(advancersMap.value?.[stageId] ?? stage?.settings?.advancers ?? 8)
  const size = [4, 8, 16].includes(adv) ? adv : 8
  const losses = Number(lossesMap.value?.[stageId] ?? 1)
  const third = !!(thirdMap.value?.[stageId])

  router.post(`/admin/tournaments/${tForm.value.id}/playoff/generate`, {
    source_stage_id: stageId,
    size,
    losses_to_eliminate: Math.max(1, Math.min(4, losses)),
    third_place: third,
  }, { preserveScroll: true })
}

/* Участники */
const addParticipant = () => {
  const payload = {
    user_id: addParticipantForm.value.user_id || null,
    display_name: addParticipantForm.value.display_name || null,
  }
  router.post(`/admin/tournaments/${tForm.value.id}/participants`, payload, { preserveScroll: true })
  addParticipantForm.value.user_id = null
  addParticipantForm.value.display_name = ''
}

const removeParticipant = (participantId) => {
  if (!confirm('Удалить участника из турнира?')) return

  const idNum = Number(participantId)

  router.delete(
    `/admin/tournaments/${tForm.value.id}/participants/${idNum}`,
    {
      preserveScroll: true,
      preserveState: false,
      onSuccess: () => {
        // Убираем участника из локального списка, чтобы таблица обновилась без F5
        participantsList.value = participantsList.value.filter(
          (p) => p.id !== idNum
        )

        // Простое уведомление для админа
        alert('Участник удалён из турнира.')
      },
    },
  )
}


/** Назначить/сменить команду участнику */
const updateParticipantTeam = (participant, event) => {
  const value = event.target.value
  const teamId = value ? Number(value) : null

  router.post(
    `/admin/tournaments/${tForm.value.id}/participants/${participant.id}/team`,
    { nhl_team_id: teamId },
    {
      preserveScroll: true,
      onSuccess: () => {
        participant.nhl_team_id = teamId
      },
      onError: (errors) => {
        const first = errors && Object.values(errors)[0]
        alert(first || 'Ошибка назначения команды')
      },
    }
  )
}

/** Рандомное распределение команд между участниками без команды */
const randomizeTeams = () => {
  if (!confirm('Распределить команды между участниками случайным образом? Уже назначенные команды изменены не будут.')) {
    return
  }

  router.post(
    `/admin/tournaments/${tForm.value.id}/participants/randomize-teams`,
    {},
    {
      preserveScroll: true,
      onError: (errors) => {
        const first = errors && Object.values(errors)[0]
        alert(first || 'Ошибка при распределении команд')
      },
    }
  )
}

</script>
