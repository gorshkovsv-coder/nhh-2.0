<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  match: { type: Object, required: true },
  myParticipant: { type: Object, default: null },
})

const match = computed(() => props.match)
const myParticipant = computed(() => props.myParticipant)

const pageTitle = computed(() => {
  const m = match.value
  const homeTeamName =
    m?.home?.nhl_team?.name ||
    m?.home?.display_name ||
    m?.home?.user?.name
  const awayTeamName =
    m?.away?.nhl_team?.name ||
    m?.away?.display_name ||
    m?.away?.user?.name

  if (homeTeamName && awayTeamName) {
    return `${homeTeamName} vs ${awayTeamName}`
  }
  return m?.stage?.tournament?.title || 'Матч'
})

// последний репорт (берём первый в массиве, если на бэке уже отсортировано)
const lastReport = computed(() => {
  const reports = match.value?.reports || []
  return reports.length ? reports[0] : null
})

const lastReportAttachments = computed(() => {
  if (!lastReport.value) return []
  const a = lastReport.value.attachments
  if (!a) return []
  // attachments может быть либо массивом строк, либо null
  return Array.isArray(a) ? a : []
})


const myParticipantId = computed(() => myParticipant.value?.id ?? null)

const isClosed = computed(() => {
  const s = match.value?.status

  // Матч закрыт для отправки результата, если он уже отменён / завершён / подтверждён / в споре / ожидает подтверждения
  if (['canceled', 'confirmed', 'finished', 'reported', 'disputed'].includes(s)) {
    return true
  }

  // Если уже есть репорт в статусе pending – тоже не даём отправлять ещё один
  const lr = lastReport.value
  if (lr && lr.status === 'pending') {
    return true
  }

  return false
})

const canSubmitReport = computed(() => !isClosed.value)

const canConfirmOrDispute = computed(() => {
  const lr = lastReport.value
  if (!lr || lr.status !== 'pending') return false
  if (!myParticipantId.value) return false
  // подтверждать / спорить может только соперник, не автор репорта
  return lr.reporter_participant_id !== myParticipantId.value
})

const homeTeam = computed(() => match.value?.home?.nhl_team || null)
const awayTeam = computed(() => match.value?.away?.nhl_team || null)

const homePlayerName = computed(
  () =>
    match.value?.home?.display_name ||
    match.value?.home?.user?.name ||
    'Home',
)

const awayPlayerName = computed(
  () =>
    match.value?.away?.display_name ||
    match.value?.away?.user?.name ||
    'Away',
)

// Форма отправки результата — под StoreMatchReportRequest
const reportForm = useForm({
  score_home: '',
  score_away: '',
  ot: false,
  so: false,
  comment: '',
  attachments: [],
})

const handleAttachmentsChange = (event) => {
  const files = Array.from(event.target.files || [])
  reportForm.attachments = files
}

const submitReport = () => {
  if (!match.value) return
  reportForm.post(route('matches.report.store', match.value.id), {
    preserveScroll: true,
    forceFormData: true,
  })
}

const confirmReport = () => {
  const lr = lastReport.value
  if (!lr) return
  router.post(route('reports.confirm', lr.id), {}, { preserveScroll: true })
}

const disputeReport = () => {
  const lr = lastReport.value
  if (!lr) return
  router.post(route('reports.reject', lr.id), {}, { preserveScroll: true })
}
</script>

<template>
  <GuestLayout>
    <Head :title="pageTitle" />

    <main class="max-w-4xl mx-auto py-6 space-y-6">
      <!-- ВЕРХНИЙ БЛОК: турнир + статус + Команда+Игрок+Лого -->
      <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border p-4 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="min-w-0 space-y-1">
            <div class="text-sm text-gray-500">
              {{ match.stage?.tournament?.title || 'Турнир' }}
              <span v-if="match.stage"> — {{ match.stage.name }}</span>
            </div>
            <div v-if="match.scheduled_at" class="text-xs text-gray-500">
              Запланирован: {{ match.scheduled_at }}
            </div>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-600">Статус:</span>
            <StatusBadge :status="match.status" />
          </div>
        </div>

        <!-- Карточки команд + игроков -->
        <div class="mt-2 flex items-center justify-center gap-6">
          <!-- HOME -->
          <div class="flex items-center gap-2 min-w-0">
            <div
              class="w-12 h-12 rounded bg-slate-100 flex items-center justify-center overflow-hidden"
            >
              <img
                v-if="homeTeam?.logo_url"
                :src="homeTeam.logo_url"
                :alt="homeTeam.name"
                class="w-full h-full object-contain"
              />
              <span v-else class="text-[10px] text-gray-400 text-center px-1">
                logo
              </span>
            </div>
            <div class="min-w-0">
              <div class="text-sm font-semibold leading-tight truncate">
                {{ homeTeam?.name || 'Без команды' }}
              </div>
              <div class="text-xs text-gray-600 leading-tight truncate">
                {{ homePlayerName }}
              </div>
            </div>
          </div>

          <!-- VS -->
          <div class="text-xs font-semibold text-gray-400 uppercase">
            vs
          </div>

          <!-- AWAY -->
          <div class="flex items-center gap-2 min-w-0">
            <div
              class="w-12 h-12 rounded bg-slate-100 flex items-center justify-center overflow-hidden"
            >
              <img
                v-if="awayTeam?.logo_url"
                :src="awayTeam.logo_url"
                :alt="awayTeam.name"
                class="w-full h-full object-contain"
              />
              <span v-else class="text-[10px] text-gray-400 text-center px-1">
                logo
              </span>
            </div>
            <div class="min-w-0">
              <div class="text-sm font-semibold leading-tight truncate">
                {{ awayTeam?.name || 'Без команды' }}
              </div>
              <div class="text-xs text-gray-600 leading-tight truncate">
                {{ awayPlayerName }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ПОСЛЕДНИЙ РЕПОРТ -->
      <div
        v-if="lastReport"
        class="bg-white overflow-hidden shadow-sm sm:rounded-lg border p-4 space-y-3"
      >
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-lg font-semibold">
            Последний репорт
          </h2>
          <div class="text-xs text-gray-500">
            Статус репорта:
            <span class="font-medium">
              {{ lastReport.status }}
            </span>
          </div>
        </div>

        <div class="text-sm text-gray-700">
          Счёт:
          <span class="font-semibold">
            {{ lastReport.score_home }} : {{ lastReport.score_away }}
          </span>
          <span v-if="lastReport.ot" class="ml-2 text-xs text-gray-500">OT</span>
          <span v-if="lastReport.so" class="ml-1 text-xs text-gray-500">SO</span>
        </div>

        <div v-if="lastReport.comment" class="text-sm text-gray-600">
          Комментарий: {{ lastReport.comment }}
        </div>

        <div class="text-xs text-gray-500">
          Отправил:
          <span class="font-medium">
            {{ lastReport.reporter?.user?.name || 'Неизвестно' }}
          </span>
        </div>
		
		  <!-- NEW: превью вложений -->
			<div
				v-if="lastReportAttachments.length"
				class="mt-4"
			>
				<h3 class="text-sm font-semibold text-gray-700 mb-2">
				Вложения (скриншоты)
				</h3>

				<div class="flex flex-wrap gap-3">
				<a
					v-for="(src, idx) in lastReportAttachments"
					:key="idx"
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
				</div>
			</div>
			<!-- /NEW -->

        <!-- Кнопки подтверждения / спора для соперника -->
        <div
          v-if="canConfirmOrDispute"
          class="pt-2 border-t border-dashed border-gray-200 mt-2 flex flex-wrap items-center justify-between gap-3"
        >
          <div class="text-xs text-gray-600">
            Репорт ожидает вашего решения.
          </div>
          <div class="flex gap-2">
            <button
              type="button"
              @click="confirmReport"
              class="px-4 py-2 rounded bg-emerald-600 text-white text-sm hover:bg-emerald-700"
            >
              Подтвердить
            </button>
            <button
              type="button"
              @click="disputeReport"
              class="px-4 py-2 rounded bg-red-600 text-white text-sm hover:bg-red-700"
            >
              Отклонить
            </button>
          </div>
        </div>
      </div>

      <!-- ОТПРАВКА РЕЗУЛЬТАТА -->
      <div
        v-if="canSubmitReport"
        class="bg-white overflow-hidden shadow-sm sm:rounded-lg border p-4 space-y-4"
      >
        <h2 class="text-lg font-semibold">
          Отправить результат
        </h2>

        <form @submit.prevent="submitReport" class="space-y-4">
		<!-- ГЛОБАЛЬНЫЙ БЛОК ОШИБОК -->
			<div
				v-if="Object.keys($page.props.errors || {}).length"
				class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700"
			>
				<p class="font-semibold mb-1">
				Исправьте ошибки в форме:
				</p>
				<ul class="list-disc list-inside space-y-0.5">
				<li
					v-for="(message, field) in $page.props.errors"
					:key="field"
				>
					{{ message }}
				</li>
			</ul>
		</div>

          <!-- Голы: показываем ТОЛЬКО команду + лого -->
          <div class="grid gap-4 md:grid-cols-2">
            <!-- HOME -->
            <div>
              <div class="flex items-center gap-2 mb-1">
                <div
                  class="w-9 h-9 rounded bg-slate-100 flex items-center justify-center overflow-hidden"
                >
                  <img
                    v-if="homeTeam?.logo_url"
                    :src="homeTeam.logo_url"
                    :alt="homeTeam.name"
                    class="w-full h-full object-contain"
                  />
                  <span v-else class="text-[9px] text-gray-400 text-center px-1">
                    logo
                  </span>
                </div>
                <div class="text-sm font-semibold leading-tight truncate">
                  {{ homeTeam?.name || 'Хозяева' }}
                </div>
              </div>
              <label class="block text-xs text-gray-500 mb-1">
                Голы этой команды
              </label>
              <input
                v-model.number="reportForm.score_home"
                type="number"
                min="0"
                max="99"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
              />
              <div v-if="reportForm.errors.score_home" class="mt-1 text-xs text-red-600">
                {{ reportForm.errors.score_home }}
              </div>
            </div>

            <!-- AWAY -->
            <div>
              <div class="flex items-center gap-2 mb-1">
                <div
                  class="w-9 h-9 rounded bg-slate-100 flex items-center justify-center overflow-hidden"
                >
                  <img
                    v-if="awayTeam?.logo_url"
                    :src="awayTeam.logo_url"
                    :alt="awayTeam.name"
                    class="w-full h-full object-contain"
                  />
                  <span v-else class="text-[9px] text-gray-400 text-center px-1">
                    logo
                  </span>
                </div>
                <div class="text-sm font-semibold leading-tight truncate">
                  {{ awayTeam?.name || 'Гости' }}
                </div>
              </div>
              <label class="block text-xs text-gray-500 mb-1">
                Голы этой команды
              </label>
              <input
                v-model.number="reportForm.score_away"
                type="number"
                min="0"
                max="99"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
              />
              <div v-if="reportForm.errors.score_away" class="mt-1 text-xs text-red-600">
                {{ reportForm.errors.score_away }}
              </div>
            </div>
          </div>

          <!-- OT / SO + комментарий -->
          <div class="grid gap-4 md:grid-cols-[minmax(0,1fr),minmax(0,1fr)]">
            <div class="space-y-2">
              <div class="flex items-center gap-4">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                  <input
                    v-model="reportForm.ot"
                    type="checkbox"
                    class="rounded border-gray-300 text-slate-600 focus:ring-slate-500"
                  />
                  <span>Овертайм (OT)</span>
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                  <input
                    v-model="reportForm.so"
                    type="checkbox"
                    class="rounded border-gray-300 text-slate-600 focus:ring-slate-500"
                  />
                  <span>Серия буллитов (SO)</span>
                </label>
              </div>
              <div class="text-xs text-gray-500">
                Нельзя одновременно OT и SO — валидатор не пропустит.
              </div>
            </div>

            <div class="space-y-1">
              <label class="block text-sm font-medium text-gray-700">
                Комментарий (опционально)
              </label>
              <textarea
                v-model="reportForm.comment"
                rows="3"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                placeholder="Например: 'Выиграл в овертайме, без разрывов соединения'"
              />
              <div v-if="reportForm.errors.comment" class="mt-1 text-xs text-red-600">
                {{ reportForm.errors.comment }}
              </div>
            </div>
          </div>

          <!-- Вложения -->
			<div class="mt-6">
			<p class="text-sm font-medium text-gray-700 mb-1">
				Вложения (скриншоты) — 
				<span class="text-red-600 font-semibold">обязательно</span>
			</p>

			<input
				type="file"
				name="attachments[]"
				multiple
				@change="handleAttachmentsChange"
				class="text-sm"
			/>

			<!-- подсказка по формату -->
			<p class="mt-1 text-xs text-gray-400">
				Приложите хотя бы один скриншот счёта или статистики. JPG / PNG, до 4&nbsp;МБ каждый.
			</p>

			<!-- вывод ошибки с бэка -->
			<p
				v-if="reportForm.errors.attachments"
				class="mt-1 text-xs text-red-600"
			>
				{{ reportForm.errors.attachments }}
			</p>
			</div>

          <div class="flex justify-end gap-2">
            <button
              type="submit"
              class="inline-flex items-center px-4 py-2 rounded-md bg-slate-800 text-white text-sm hover:bg-slate-900 disabled:opacity-60"
              :disabled="reportForm.processing || isClosed"
            >
              Отправить результат
            </button>
          </div>
        </form>
      </div>
    </main>
  </GuestLayout>
</template>
