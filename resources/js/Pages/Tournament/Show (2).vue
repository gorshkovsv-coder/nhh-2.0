<template>
  <AuthenticatedLayout>
    <Head :title="tournament.title" />
	<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <h1 class="text-2xl font-bold">{{ tournament.title }}</h1>

<!-- HERO / ПАСПОРТ -->
<div class="bg-white shadow-sm rounded-2xl p-5 border">
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">


      <!-- Чипы-краткие сведения -->
      <div class="mt-2 flex flex-wrap gap-2 text-sm">
        <span class="inline-flex items-center rounded-full bg-slate-50 px-2.5 py-1 text-slate-700 ring-1 ring-inset ring-slate-200">
          Сезон: <span class="font-medium ml-1">{{ tournament.season || '—' }}</span>
        </span>
        <span class="inline-flex items-center rounded-full bg-slate-50 px-2.5 py-1 text-slate-700 ring-1 ring-inset ring-slate-200">
          Формат: <span class="font-medium ml-1">{{ formatLabel }}</span>
        </span>
        <span class="inline-flex items-center rounded-full bg-slate-50 px-2.5 py-1 text-slate-700 ring-1 ring-inset ring-slate-200">
          Участников: <span class="font-medium ml-1">{{ participantsCount }}</span>
        </span>
        <span class="inline-flex items-center rounded-full bg-slate-50 px-2.5 py-1 text-slate-700 ring-1 ring-inset ring-slate-200">
          Создан: <span class="font-medium ml-1">{{ createdAt }}</span>
        </span>
      </div>
    </div>

    <!-- Статус-бейдж -->
    <span
      :class="[
        'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset',
        statusBadgeClass
      ]"
      title="Текущий статус турнира"
    >
      {{ statusLabel }}
    </span>
		</div>
    </div>
		  
		 <!-- плейсхолдер, если стадий ещё нет -->
		<div v-if="!stages?.length" class="max-w-6xl mx-auto text-gray-500">
		Стадии турнира пока не созданы.
		</div>

	  <!-- Саморегистрация -->
		<div v-if="selfReg?.enabled" class="mb-4">
		<template v-if="auth?.user">
			<button
			v-if="!selfReg.registered"
			@click="doRegister"
			:disabled="regLoading"
			class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-60">
			Записаться на турнир
			</button>
			<button
			v-else
			@click="doUnregister"
			:disabled="regLoading"
			class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700 disabled:opacity-60">
			Отменить регистрацию
			</button>
			</template>
		<p v-else class="text-sm text-gray-600">Чтобы зарегистрироваться, войдите в систему.</p>
		</div> 

        <div class="flex justify-end mb-4">
          <Link
            :href="route('tournaments.matches-history', tournament.id)"
            class="inline-flex items-center text-sm font-medium text-sky-500 hover:text-sky-400"
          >
            История матчей
            <span class="ml-1">→</span>
          </Link>
        </div>

      <!-- ===== Турнирная таблица(ы) Регулярки ===== -->

      <div v-if="groupTables && groupTables.length" class="space-y-6">
        <div v-for="gt in groupTables" :key="gt.stage_id" class="bg-white shadow-sm rounded-xl p-4">
          <div class="text-lg font-semibold mb-3">Турнирная таблица — {{ gt.stage_name }}</div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="text-gray-500">
                <tr class="[&>th]:px-3 [&>th]:py-2 text-left">
                  <th>#</th>
				  <th>Игрок</th>
				  <th>И</th>
				  <th>В</th>
				  <th>ВОТ</th>
				  <th>ВБ</th>   <!-- NEW -->
				  <th>ПОТ</th>
				  <th>ПБ</th>   <!-- NEW -->
				  <th>П</th>
                  <th>З</th>
				  <th>П</th>
				  <th>+/-</th>
				  <th>WIN%</th>
				  <th>СИ%</th>
				  <th>Очки</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="r in gt.rows" :key="r.participant_id" class="[&>td]:px-3 [&>td]:py-2">
                  <td class="text-gray-500">{{ r.pos }}</td>
                   <td>
					<div class="flex items-center gap-2">
						<!-- Логотип команды -->
						<div class="w-10 h-10 rounded bg-slate-100 flex items-center justify-center overflow-hidden">
						<img
							v-if="r.team?.logo_url"
							:src="r.team.logo_url"
							:alt="r.team.name"
							class="w-full h-full object-contain"
						/>
						<span v-else class="text-[10px] text-gray-400 text-center px-1">
							без логотипа
						</span>
						</div>

					<!-- Название команды + ник игрока -->
					<div class="min-w-0">
					<div class="text-sm font-semibold leading-tight truncate">
						{{ r.team?.name || 'Без команды' }}
					</div>
					<div class="text-xs text-gray-600 leading-tight truncate">
						<Link
						v-if="r.user_id"
						:href="route('players.show', r.user_id)"
						class="hover:underline text-blue-700"
						>
						{{ r.name }}
						</Link>
						<span v-else>
						{{ r.name }}
						</span>
					</div>
					</div>

					</div>
					</td>
                  <td>{{ r.gp }}</td>
				  <td>{{ r.w }}</td>
				  <td>{{ r.otw }}</td>
				  <td>{{ r.sow ?? 0 }}</td>  <!-- NEW -->
				  <td>{{ r.otl }}</td>
				  <td>{{ r.sol ?? 0 }}</td>  <!-- NEW -->
				  <td>{{ r.l }}</td>
				  <td>{{ r.gf }}</td>
				  <td>{{ r.ga }}</td>
				  <td :class="r.gd >= 0 ? 'text-green-600' : 'text-red-600'">{{ r.gd }}</td>
				  <td>{{ winPercent(r) }}%</td>
				  <td>{{ siPercent(r) }}%</td>
				  <td class="font-semibold">{{ r.points }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ===== Плей-офф ===== -->
      <div v-if="bracketColumns && bracketColumns.length" class="bg-white shadow-sm rounded-xl p-4">
        <div class="text-lg font-semibold mb-3">Плей-офф</div>
        <div class="overflow-x-auto">
          <div class="grid gap-6" :style="`grid-template-columns: repeat(${bracketColumns.length}, minmax(220px, 1fr));`">
            <div v-for="(col, ci) in bracketColumns" :key="ci" class="space-y-3">
              <div class="text-center font-semibold text-sm text-gray-700">{{ col.title }}</div>

              <div v-for="(series, si) in col.series" :key="si"
                   class="rounded-xl border shadow-sm p-3 min-h-[88px] flex flex-col justify-center bg-white">

				<!-- HOME row -->
				<div class="flex items-center justify-between text-sm px-1 py-0.5">
				<div class="flex items-center gap-2 min-w-0">
					<div class="w-8 h-8 rounded bg-slate-100 flex items-center justify-center overflow-hidden">
					<img
						v-if="series.home?.team?.logo_url"
						:src="series.home.team.logo_url"
						:alt="series.home.team.name"
						class="w-full h-full object-contain"
					/>
					<span v-else class="text-[9px] text-gray-400 text-center px-1">
						logo
					</span>
					</div>
					<div class="min-w-0">
					<div
						:class="isWinner(series,'home') ? 'font-bold text-gray-900 leading-tight truncate' : 'text-gray-700 leading-tight truncate'"
					>
						{{ series.home?.team?.name || 'Без команды' }}
					</div>
<div class="text-xs text-gray-600 leading-tight truncate">
  <Link
    v-if="series.home?.user_id"
    :href="route('players.show', series.home.user_id)"
    class="hover:underline text-blue-700"
  >
    {{ series.home?.display_name || '—' }}
  </Link>
  <span v-else>
    {{ series.home?.display_name || '—' }}
  </span>
</div>

					</div>
				</div>
				<span :class="isWinner(series,'home') ? 'font-bold text-gray-900' : 'text-gray-600'">
					{{ series.wins_home }}
				</span>
				</div>

				<!-- AWAY row -->
				<div class="flex items-center justify-between text-sm px-1 py-0.5">
				<div class="flex items-center gap-2 min-w-0">
					<div class="w-8 h-8 rounded bg-slate-100 flex items-center justify-center overflow-hidden">
					<img
						v-if="series.away?.team?.logo_url"
						:src="series.away.team.logo_url"
						:alt="series.away.team.name"
						class="w-full h-full object-contain"
					/>
					<span v-else class="text-[9px] text-gray-400 text-center px-1">
						logo
					</span>
					</div>
					<div class="min-w-0">
					<div
						:class="isWinner(series,'away') ? 'font-bold text-gray-900 leading-tight truncate' : 'text-gray-700 leading-tight truncate'"
					>
						{{ series.away?.team?.name || 'Без команды' }}
					</div>
<div class="text-xs text-gray-600 leading-tight truncate">
  <Link
    v-if="series.away?.user_id"
    :href="route('players.show', series.away.user_id)"
    class="hover:underline text-blue-700"
  >
    {{ series.away?.display_name || '—' }}
  </Link>
  <span v-else>
    {{ series.away?.display_name || '—' }}
  </span>
</div>

					</div>
				</div>
				<span :class="isWinner(series,'away') ? 'font-bold text-gray-900' : 'text-gray-600'">
					{{ series.wins_away }}
				</span>
				</div>

				<div v-if="series.finished" class="mt-2 text-xs text-emerald-700">Серия завершена</div>

              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref } from 'vue'
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'


const props = defineProps({
  tournament: { type: Object, required: true },
  stages: { type: Array, required: true },
  groupTables: { type: Array, default: () => [] },
  bracketColumns: { type: Array, default: () => [] },
  auth: { type: Object, default: () => ({ user: null }) },
  selfReg: { type: Object, default: () => ({ enabled: false, registered: false }) },
})

const regLoading = ref(false)
const doRegister = () => {
  regLoading.value = true
  router.post(`/tournaments/${props.tournament.id}/register`, {}, {
    preserveScroll: true,
    onFinish: () => (regLoading.value = false),
  })
}

const doUnregister = () => {
  regLoading.value = true
  router.delete(`/tournaments/${props.tournament.id}/register`, {
    preserveScroll: true,
    onFinish: () => (regLoading.value = false),
  })
}

// Сколько участников видно на странице
const participantsCount = computed(
  () => props.tournament?.participants?.length ?? 0
)

const formatMap = {
  groups_playoff: 'Группы + плей-офф',
  group_only:     'Только группы',
  playoff:        'Только плей-офф',
  round_robin:    'Круговой (лига)',
}
const formatLabel = computed(() =>
  formatMap[props.tournament?.format] ?? (props.tournament?.format || '—')
)

// Дата создания
const createdAt = computed(() => {
  const d = props.tournament?.created_at
  return d ? new Date(d).toLocaleDateString('ru-RU') : '—'
})

// Карта статусов → человекочитаемые подписи
const statusMap = {
  draft: 'Черновик',
  registration: 'Идёт регистрация',
  active: 'Активен',
  archived: 'Завершён',
}

const statusLabel = computed(
  () => statusMap[props.tournament?.status] ?? (props.tournament?.status || '—')
)

// Класс бейджа статуса (Tailwind)
const statusBadgeClass = computed(() => {
  switch (props.tournament?.status) {
    case 'registration': return 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200'
    case 'active':       return 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'
    case 'draft':        return 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200'
    case 'archived':     return 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200'
    default:             return 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200'
  }
})

// Процент побед: (все победы / сыгранные матчи) * 100
const winPercent = (row) => {
  const gp = Number(row.gp || 0)
  if (!gp) return 0

  const wins =
    Number(row.w || 0) +
    Number(row.otw || 0) +
    Number(row.sow || 0)

  const val = (wins / gp) * 100
  return Math.round(val) // целое число процентов
}

// Процент сыгранных игр из запланированных на стадии
const siPercent = (row) => {
  const gp = Number(row.gp || 0)
  const planned = Number(row.gp_planned || 0)

  if (!planned || !gp) {
    return 0
  }

  const val = (gp / planned) * 100
  return Math.min(100, Math.round(val)) // не даём выйти за 100%
}


/** Победитель строки в серии (жирное выделение) */
const isWinner = (series, side) => {
  if (!series || !series.finished) return false
  const wh = Number(series.wins_home || 0)
  const wa = Number(series.wins_away || 0)
  const winSide = wh > wa ? 'home' : (wa > wh ? 'away' : null)
  return winSide === side
}
</script>
