<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({
  tournament: { type: Object, required: true },
  groupTables: { type: Array, default: () => [] },
})

const headToHeadCellClass = (cell) => {
  switch (cell?.result) {
    case 'win':
      return 'bg-emerald-50 text-emerald-700'
    case 'loss':
      return 'bg-rose-50 text-rose-700'
    case 'draw':
      return 'bg-slate-100 text-slate-700'
    case 'self':
      return 'bg-slate-200 text-slate-400'
    default:
      return 'bg-white text-slate-400 border border-slate-200'
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <Head :title="`Личные встречи — ${tournament.title}`" />

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">
            Личные встречи
          </h1>
          <p class="text-sm text-gray-600">
            Турнир: {{ tournament.title }}
          </p>
        </div>
        <div class="flex items-center gap-4 text-sm">
          <Link
            :href="route('tournaments.matches-history', tournament.id)"
            class="text-sky-500 hover:text-sky-400"
          >
            История матчей
          </Link>
          <Link
            :href="route('tournaments.show', tournament.id)"
            class="text-sky-500 hover:text-sky-400"
          >
            Назад к турниру
          </Link>
        </div>
      </div>

      <div v-if="groupTables.length" class="space-y-6">
        <section
          v-for="gt in groupTables"
          :key="gt.stage_id"
          class="bg-white shadow-sm rounded-xl p-4"
        >
          <h2 class="text-lg font-semibold text-gray-900 mb-3">
            Личные встречи — {{ gt.stage_name }}
          </h2>

          <div v-if="gt.head_to_head?.rows?.length" class="overflow-x-auto">
            <table class="min-w-full text-xs border border-slate-200">
              <thead class="bg-slate-50 text-gray-600">
                <tr>
                  <th class="px-2 py-2 text-left">
                    Игрок
                  </th>
                  <th
                    v-for="p in gt.head_to_head.participants"
                    :key="p.participant_id"
                    class="px-2 py-2 text-left whitespace-nowrap"
                  >
                    <div class="flex items-center gap-2">
                      <span class="text-[11px] font-semibold text-gray-500 w-5 text-right">
                        {{ p.pos ?? '—' }}
                      </span>
                      <div class="w-6 h-6 rounded bg-slate-100 flex items-center justify-center overflow-hidden">
                        <img
                          v-if="p.team?.logo_url"
                          :src="p.team.logo_url"
                          :alt="p.team.name"
                          class="w-full h-full object-contain"
                        />
                        <span v-else class="text-[9px] text-slate-400">
                          —
                        </span>
                      </div>
                      <span class="text-xs text-gray-700">
                        {{ p.name }}
                      </span>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr
                  v-for="row in gt.head_to_head.rows"
                  :key="row.participant.participant_id"
                >
                  <td class="px-2 py-2 text-left whitespace-nowrap">
                    <div class="flex items-center gap-2">
                      <span class="text-[11px] font-semibold text-gray-500 w-5 text-right">
                        {{ row.participant.pos ?? '—' }}
                      </span>
                      <div class="w-6 h-6 rounded bg-slate-100 flex items-center justify-center overflow-hidden">
                        <img
                          v-if="row.participant.team?.logo_url"
                          :src="row.participant.team.logo_url"
                          :alt="row.participant.team.name"
                          class="w-full h-full object-contain"
                        />
                        <span v-else class="text-[9px] text-slate-400">
                          —
                        </span>
                      </div>
                      <span class="text-xs text-gray-700">
                        {{ row.participant.name }}
                      </span>
                    </div>
                  </td>
                  <td
                    v-for="(cell, ci) in row.cells"
                    :key="ci"
                    class="px-2 py-2 text-center"
                  >
                    <span
                      class="inline-flex min-w-[46px] items-center justify-center rounded-md px-2 py-1"
                      :class="headToHeadCellClass(cell)"
                    >
                      {{ cell.value }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-else
            class="border border-dashed border-slate-200 rounded-lg p-4 text-sm text-gray-600"
          >
            Матчи ещё не сыграны — данные личных встреч появятся после первых результатов.
          </div>
        </section>
      </div>

      <div
        v-else
        class="border border-dashed border-slate-200 rounded-lg p-4 text-sm text-gray-600"
      >
        Групповые стадии турнира не найдены.
      </div>
    </div>
  </AuthenticatedLayout>
</template>
