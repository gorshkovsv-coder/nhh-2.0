<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
  players: {
    type: Array,
    default: () => [],
  },
})

const isAvatarPreviewOpen = ref(false)
const avatarPreviewUrl = ref('')
const avatarPreviewName = ref('')

const openAvatarPreview = (url, name) => {
  if (!url) return
  avatarPreviewUrl.value = url
  avatarPreviewName.value = name || 'Аватар'
  isAvatarPreviewOpen.value = true
}

const closeAvatarPreview = () => {
  isAvatarPreviewOpen.value = false
  avatarPreviewUrl.value = ''
  avatarPreviewName.value = ''
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Рейтинг игроков" />

    <main class="py-10">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <header>
          <h1 class="text-2xl font-bold text-gray-900">
            Рейтинг игроков
          </h1>
          <p class="mt-1 text-sm text-gray-600">
            Глобальный рейтинг по всем подтверждённым матчам во всех турнирах.
          </p>
        </header>

        <!-- Подсказка: как считается рейтинг -->
        <section
          class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 sm:px-5 sm:py-4 text-xs sm:text-sm text-gray-800"
        >
          <h2 class="text-xs font-semibold text-blue-800 tracking-wide uppercase mb-1.5">
            Как считается рейтинг
          </h2>
          <ul class="list-disc pl-4 space-y-0.5">
            <li>+2 очка за каждую победу в подтверждённом матче.</li>
            <li>+1 очко за поражение в овертайме или по буллитам.</li>
            <li>+10 очков за победу в турнире (чемпион плей-офф).</li>
          </ul>
          <p class="mt-2 text-xs sm:text-[0.8rem] text-gray-600">
            В зачёт идут только подтверждённые матчи во всех турнирах. При равенстве очков места
            распределяются по количеству побед, проценту побед, разнице шайб и количеству сыгранных матчей.
          </p>
        </section>

        <div
          v-if="!players.length"
          class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-600"
        >
          Пока нет сыгранных матчей. Рейтинг будет доступен после проведения первых турниров.
        </div>

        <div
          v-else
          class="bg-white shadow-sm sm:rounded-lg overflow-hidden"
        >
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th
                    scope="col"
                    class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
                  >
                    #
                  </th>
                  <th
                    scope="col"
                    class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
                  >
                    Игрок
                  </th>
                  <th
                    scope="col"
                    class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider"
                  >
                    Матчи
                  </th>
                  <th
                    scope="col"
                    class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider"
                  >
                    Победы
                  </th>
                  <th
                    scope="col"
                    class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider"
                  >
                    Поражения
                  </th>
                  <th
                    scope="col"
                    class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider"
                  >
                    Разница шайб
                  </th>
                  <th
                    scope="col"
                    class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider"
                  >
                    Титулы
                  </th>
                  <th
                    scope="col"
                    class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider"
                  >
                    Рейтинг
                  </th>
                  <th
                    scope="col"
                    class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider"
                  >
                    Win%
                  </th>
                </tr>
              </thead>

              <tbody class="bg-white divide-y divide-gray-100">
                <tr
                  v-for="player in players"
                  :key="player.user_id"
                  class="hover:bg-gray-50"
                >
                  <!-- Позиция -->
                  <td class="px-3 py-2 whitespace-nowrap text-gray-700 font-semibold">
                    {{ player.rank }}
                  </td>

                  <!-- Игрок -->
                  <td class="px-3 py-2 whitespace-nowrap">
                    <div class="flex items-center gap-3">
                      <button
                        v-if="player.avatar_url"
                        type="button"
                        class="h-8 w-8 rounded-full"
                        @click="openAvatarPreview(player.avatar_url, player.user_name)"
                      >
                        <img
                          :src="player.avatar_url"
                          :alt="player.user_name"
                          class="h-8 w-8 rounded-full object-cover"
                        />
                      </button>
                      <div>
                        <div class="font-medium text-gray-900">
                          <Link
                            v-if="player.user_id"
                            :href="route('players.show', player.user_id)"
                            class="hover:underline text-blue-700"
                          >
                            {{ player.user_name }}
                          </Link>
                          <span v-else>
                            {{ player.user_name }}
                          </span>
                        </div>
                        <div
                          v-if="player.user_psn"
                          class="text-xs text-gray-500"
                        >
                          PSN: {{ player.user_psn }}
                        </div>
                      </div>
                    </div>
                  </td>

                  <!-- Матчи -->
                  <td class="px-3 py-2 text-center">
                    {{ player.matches_played }}
                  </td>

                  <!-- Победы -->
                  <td class="px-3 py-2 text-center text-emerald-700 font-medium">
                    {{ player.wins }}
                  </td>

                  <!-- Поражения -->
                  <td class="px-3 py-2 text-center text-rose-700">
                    {{ player.losses }}
                  </td>

                  <!-- Разница шайб -->
                  <td class="px-3 py-2 text-center">
                    <span
                      :class="[
                        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                        player.goals_diff > 0
                          ? 'bg-emerald-50 text-emerald-700'
                          : player.goals_diff < 0
                            ? 'bg-rose-50 text-rose-700'
                            : 'bg-gray-50 text-gray-600',
                      ]"
                    >
                      {{ player.goals_diff > 0 ? '+' + player.goals_diff : player.goals_diff }}
                    </span>
                  </td>

                  <!-- Титулы -->
                  <td class="px-3 py-2 text-center">
                    {{ player.tournaments_won }}
                  </td>

                  <!-- Рейтинг (очки рейтинга) -->
                  <td class="px-3 py-2 text-center font-semibold text-emerald-700">
                    {{ player.rating_points }}
                  </td>

                  <!-- Win% -->
                  <td class="px-3 py-2 text-center">
                    {{ player.win_rate }}%
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <Modal
          :show="isAvatarPreviewOpen"
          maxWidth="2xl"
          @close="closeAvatarPreview"
        >
          <div class="bg-black p-4">
            <img
              v-if="avatarPreviewUrl"
              :src="avatarPreviewUrl"
              :alt="avatarPreviewName"
              class="mx-auto max-h-[80vh] w-auto max-w-full object-contain"
            />
          </div>
        </Modal>
      </div>
    </main>
  </AuthenticatedLayout>
</template>
