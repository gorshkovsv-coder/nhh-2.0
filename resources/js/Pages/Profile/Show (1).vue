<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  user: {
    type: Object,
    required: true,
  },
  mustVerifyEmail: {
    type: Boolean,
    default: false,
  },
  status: {
    type: String,
    default: '',
  },
  playerStats: {
    type: Object,
    default: null,
  },
})


const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success || '')

const stats = computed(() => props.playerStats || null)

const lastMatchDate = computed(() => {
  if (!stats.value || !stats.value.last_match_at) return '-'
  const d = new Date(stats.value.last_match_at)
  if (Number.isNaN(d.getTime())) return '-'
  return d.toLocaleDateString('ru-RU')
})

const currentStreakLabel = computed(() => {
  if (!stats.value || !stats.value.current_streak_type || !stats.value.current_streak_length) {
    return '—'
  }
  const n = stats.value.current_streak_length
  const tail = stats.value.current_streak_type === 'win'
    ? 'побед подряд'
    : 'поражений подряд'
  return `${n} ${tail}`
})


// === Форма профиля ===
const profileForm = useForm({
  name: props.user.name || '',
  email: props.user.email || '',
  psn: props.user.psn || '',
  avatar: null,
})

const onAvatarChange = (e) => {
  const file = e.target.files?.[0]
  profileForm.avatar = file || null
}

const submitProfile = () => {
  profileForm.post(route('profile.update'), {
    preserveScroll: true,
    forceFormData: true,
  })
}


// === Форма смены пароля ===
const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const submitPassword = () => {
  passwordForm.post(route('profile.password'), {
    preserveScroll: true,
    onSuccess: () => {
      passwordForm.reset('current_password', 'password', 'password_confirmation')
    },
  })
}

// === Форма удаления аккаунта ===
const deleteForm = useForm({
  password: '',
})

const confirmDelete = () => {
  if (!confirm('Вы уверены, что хотите безвозвратно удалить аккаунт?')) return
  deleteForm.delete(route('profile.destroy'), {
    preserveScroll: true,
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Профиль игрока" />

    <main class="py-6">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div
          v-if="flashSuccess"
          class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-lg px-4 py-3"
        >
          {{ flashSuccess }}
        </div>

		        <!-- Блок: Рейтинг игрока -->
        <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
          <h1 class="text-xl font-semibold mb-1">
            Рейтинг игрока
          </h1>

          <p v-if="!stats" class="text-sm text-gray-600">
            Пока нет сыгранных матчей. Рейтинг появится после первого подтверждённого матча.
          </p>

          <div v-else class="grid gap-4 sm:grid-cols-3">
            <div>
              <p class="text-xs uppercase tracking-wide text-gray-500">
                Позиция
              </p>
              <p class="mt-1 text-2xl font-bold text-gray-900">
                #{{ stats.rank }}
              </p>
            </div>
            <div>
              <p class="text-xs uppercase tracking-wide text-gray-500">
                Очки рейтинга
              </p>
              <p class="mt-1 text-2xl font-bold text-emerald-600">
                {{ stats.rating_points }}
              </p>
            </div>
            <div>
              <p class="text-xs uppercase tracking-wide text-gray-500">
                Матчей сыграно
              </p>
              <p class="mt-1 text-2xl font-bold text-gray-900">
                {{ stats.matches_played }}
              </p>
            </div>
          </div>
        </section>




        <!-- Блок: Статистика игрока -->
        <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
          <h2 class="text-xl font-semibold mb-1">
            Статистика игрока
          </h2>

          <p v-if="!stats" class="text-sm text-gray-600">
            Статистика появится после того, как вы сыграете первый подтверждённый матч.
          </p>

          <div v-else class="space-y-6">
            <!-- Верхний ряд: ключевые метрики -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <!-- Матчи -->
              <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                <div class="flex items-center justify-between mb-2">
                  <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Матчей сыграно
                  </p>
                  <span
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-base"
                  >
                    🎮
                  </span>
                </div>
                <p class="text-2xl font-bold text-slate-900">
                  {{ stats.matches_played }}
                </p>
              </div>

              <!-- Победы / поражения -->
              <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                <div class="flex items-center justify-between mb-2">
                  <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Баланс
                  </p>
                  <span
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-50 text-base"
                  >
                    ⚖️
                  </span>
                </div>
                <p class="text-lg font-semibold">
                  <span class="text-emerald-700">{{ stats.wins }}</span>
                  <span class="mx-1 text-slate-400">/</span>
                  <span class="text-rose-700">{{ stats.losses }}</span>
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  Победы / поражения
                </p>
              </div>

              <!-- Win% -->
              <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                <div class="flex items-center justify-between mb-2">
                  <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Win%
                  </p>
                  <span
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-50 text-base"
                  >
                    📈
                  </span>
                </div>
                <p class="text-2xl font-bold text-slate-900">
                  {{ stats.win_rate }}%
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  Доля побед во всех матчах
                </p>
              </div>

              <!-- Разница шайб -->
              <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                <div class="flex items-center justify-between mb-2">
                  <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Разница шайб
                  </p>
                  <span
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-base"
                  >
                    🥅
                  </span>
                </div>
                <p class="text-2xl font-bold">
                  <span
                    :class="[
                      stats.goals_diff > 0
                        ? 'text-emerald-700'
                        : stats.goals_diff < 0
                          ? 'text-rose-700'
                          : 'text-slate-700',
                    ]"
                  >
                    {{ stats.goals_diff > 0 ? '+' + stats.goals_diff : stats.goals_diff }}
                  </span>
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  Забитые {{ stats.goals_for }} / пропущенные {{ stats.goals_against }}
                </p>
              </div>
            </div>

            <!-- Нижний ряд: подробности (адаптировано под мобилку) -->
            <div class="grid gap-6 lg:grid-cols-2">
              <!-- Левая колонка: голы + турниры -->
              <div class="space-y-4">
                <!-- Голы -->
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                  <h3 class="text-sm font-semibold text-gray-700 mb-3">
                    Голы
                  </h3>
                  <dl class="space-y-1">
                    <div class="flex items-baseline justify-between">
                      <dt class="text-xs text-gray-500">Забитые голы</dt>
                      <dd class="text-sm font-semibold text-gray-900">
                        {{ stats.goals_for }}
                      </dd>
                    </div>
                    <div class="flex items-baseline justify-between">
                      <dt class="text-xs text-gray-500">Пропущенные голы</dt>
                      <dd class="text-sm font-semibold text-gray-900">
                        {{ stats.goals_against }}
                      </dd>
                    </div>
                    <div class="flex items-baseline justify-between">
                      <dt class="text-xs text-gray-500">Голы за игру</dt>
                      <dd class="text-sm font-semibold text-gray-900">
                        {{ stats.goals_for_per_game }}
                      </dd>
                    </div>
                    <div class="flex items-baseline justify-between">
                      <dt class="text-xs text-gray-500">Пропущено за игру</dt>
                      <dd class="text-sm font-semibold text-gray-900">
                        {{ stats.goals_against_per_game }}
                      </dd>
                    </div>
                  </dl>
                </div>

                <!-- Турниры -->
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                  <h3 class="text-sm font-semibold text-gray-700 mb-3">
                    Турниры
                  </h3>
                  <dl class="space-y-1">
                    <div class="flex items-baseline justify-between">
                      <dt class="text-xs text-gray-500">Победы в турнирах (1 место)</dt>
                      <dd class="text-sm font-semibold text-gray-900">
                        {{ stats.tournaments_won }}
                      </dd>
                    </div>
                    <div class="flex items-baseline justify-between">
                      <dt class="text-xs text-gray-500">Выходы в плей-офф</dt>
                      <dd class="text-sm font-semibold text-gray-900">
                        {{ stats.playoff_appearances }}
                      </dd>
                    </div>
                    <div class="flex items-baseline justify-between">
                      <dt class="text-xs text-gray-500">Выходы в финал</dt>
                      <dd class="text-sm font-semibold text-gray-900">
                        {{ stats.final_appearances }}
                      </dd>
                    </div>
                    <div class="flex items-baseline justify-between">
                      <dt class="text-xs text-gray-500">Среднее место в регулярке</dt>
                      <dd class="text-sm font-semibold text-gray-900">
                        {{ stats.regular_avg_position ?? '—' }}
                      </dd>
                    </div>
                  </dl>
                </div>
              </div>

              <!-- Правая колонка: форма -->
              <div class="space-y-4">
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                  <h3 class="text-sm font-semibold text-gray-700 mb-3">
                    Форма
                  </h3>
                  <dl class="space-y-1">
                    <div class="flex items-baseline justify-between">
                      <dt class="text-xs text-gray-500">Последний матч</dt>
                      <dd class="text-sm font-semibold text-gray-900">
                        {{ lastMatchDate }}
                      </dd>
                    </div>
                    <div class="flex items-baseline justify-between">
                      <dt class="text-xs text-gray-500">Матчей за 30 дней</dt>
                      <dd class="text-sm font-semibold text-gray-900">
                        {{ stats.matches_last_30_days }}
                      </dd>
                    </div>
                    <div class="flex items-baseline justify-between">
                      <dt class="text-xs text-gray-500">Текущая серия</dt>
                      <dd
                        class="text-sm font-semibold"
                        :class="stats.current_streak_type === 'win'
                          ? 'text-emerald-700'
                          : stats.current_streak_type === 'loss'
                            ? 'text-rose-700'
                            : 'text-gray-900'"
                      >
                        {{ currentStreakLabel }}
                      </dd>
                    </div>
                    <div class="flex items-baseline justify-between">
                      <dt class="text-xs text-gray-500">Лучшая серия побед</dt>
                      <dd class="text-sm font-semibold text-gray-900">
                        {{ stats.best_win_streak }}
                      </dd>
                    </div>
                  </dl>
                </div>
              </div>
            </div>
          </div>
        </section>


        <!-- Блок: Информация профиля -->
        <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
          <h1 class="text-xl font-semibold mb-1">
            Информация профиля
          </h1>
          <p class="text-sm text-gray-600 mb-4">
            Здесь можно изменить своё имя, email, никнейм (PSN) и аватар.
          </p>

          <form @submit.prevent="submitProfile" class="grid gap-6 md:grid-cols-2">
            <!-- Левая колонка: текстовые поля -->
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Имя
                </label>
                <input
                  v-model="profileForm.name"
                  type="text"
                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                />
                <p v-if="profileForm.errors.name" class="mt-1 text-xs text-red-600">
                  {{ profileForm.errors.name }}
                </p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Email
                </label>
                <input
                  v-model="profileForm.email"
                  type="email"
                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                />
                <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-600">
                  {{ profileForm.errors.email }}
                </p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Никнейм (PSN)
                </label>
                <input
                  v-model="profileForm.psn"
                  type="text"
                  placeholder="Например, your_psn_id"
                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                />
                <p v-if="profileForm.errors.psn" class="mt-1 text-xs text-red-600">
                  {{ profileForm.errors.psn }}
                </p>
              </div>

              <div class="pt-2">
                <button
                  type="submit"
                  class="inline-flex items-center px-4 py-2 rounded-md bg-slate-800 text-white text-sm hover:bg-slate-900 disabled:opacity-60"
                  :disabled="profileForm.processing"
                >
                  Сохранить профиль
                </button>
              </div>
            </div>

            <!-- Правая колонка: аватар -->
            <div class="space-y-4">
              <label class="block text-sm font-medium text-gray-700">
                Аватар
              </label>

              <div class="flex items-center gap-4">
                <div class="w-20 h-20 rounded-full bg-slate-100 flex items-center justify-center overflow-hidden">
                  <img
                    v-if="user.avatar_url"
                    :src="user.avatar_url"
                    :alt="user.name"
                    class="w-full h-full object-cover"
                  />
                  <span v-else class="text-xs text-gray-400 text-center px-2">
                    нет аватара
                  </span>
                </div>

                <div class="space-y-2 text-sm">
                  <input
                    type="file"
                    accept="image/*"
                    @change="onAvatarChange"
                    class="block w-full text-sm text-gray-700"
                  />
                  <p class="text-xs text-gray-500">
                    JPG / PNG, до 2 МБ.
                  </p>
                  <p v-if="profileForm.errors.avatar" class="mt-1 text-xs text-red-600">
                    {{ profileForm.errors.avatar }}
                  </p>
                </div>
              </div>
            </div>
          </form>
        </section>

        <!-- Блок: Сменить пароль -->
        <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
          <h2 class="text-lg font-semibold">
            Сменить пароль
          </h2>
          <p class="text-sm text-gray-600">
            Укажите текущий пароль и новый пароль. Новый пароль должен быть не короче 8 символов.
          </p>

          <form @submit.prevent="submitPassword" class="space-y-4 max-w-md">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Текущий пароль
              </label>
              <input
                v-model="passwordForm.current_password"
                type="password"
                autocomplete="current-password"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
              />
              <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-red-600">
                {{ passwordForm.errors.current_password }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Новый пароль
              </label>
              <input
                v-model="passwordForm.password"
                type="password"
                autocomplete="new-password"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
              />
              <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-600">
                {{ passwordForm.errors.password }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Подтверждение нового пароля
              </label>
              <input
                v-model="passwordForm.password_confirmation"
                type="password"
                autocomplete="new-password"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
              />
            </div>

            <div>
              <button
                type="submit"
                class="inline-flex items-center px-4 py-2 rounded-md bg-slate-800 text-white text-sm hover:bg-slate-900 disabled:opacity-60"
                :disabled="passwordForm.processing"
              >
                Обновить пароль
              </button>
            </div>
          </form>
        </section>

        <!-- Блок: Удалить аккаунт -->
        <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4 border border-red-100">
          <h2 class="text-lg font-semibold text-red-700">
            Удалить аккаунт
          </h2>
          <p class="text-sm text-red-700">
            Внимание: удаление аккаунта безвозвратно. Все турниры, участие и данные игрока будут потеряны.
          </p>

          <form @submit.prevent="confirmDelete" class="space-y-4 max-w-md">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Пароль для подтверждения
              </label>
              <input
                v-model="deleteForm.password"
                type="password"
                autocomplete="current-password"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
              />
              <p v-if="deleteForm.errors.password" class="mt-1 text-xs text-red-600">
                {{ deleteForm.errors.password }}
              </p>
            </div>

            <button
              type="submit"
              class="inline-flex items-center px-4 py-2 rounded-md bg-red-600 text-white text-sm hover:bg-red-700 disabled:opacity-60"
              :disabled="deleteForm.processing"
            >
              Удалить аккаунт
            </button>
          </form>
        </section>
      </div>
    </main>
  </AuthenticatedLayout>
</template>
