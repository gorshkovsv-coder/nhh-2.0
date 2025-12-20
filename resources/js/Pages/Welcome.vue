<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

import GuestLayout from '@/Layouts/GuestLayout.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  canLogin: Boolean,
  canRegister: Boolean,
  laravelVersion: String,
  phpVersion: String,
})

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)
const isAuth = computed(() => !!user.value)
const isAdmin = computed(() => !!user.value?.is_admin)

// На будущее: эти пропсы можно будет передавать из роута / контроллера
const nextMatches = computed(() => page.props.nextMatches ?? [])
const activeTournaments = computed(() => page.props.activeTournaments ?? [])
</script>

<template>
  <Head title="Главная" />

  <!-- ====== Дашборд для авторизованного игрока ====== -->
  <AuthenticatedLayout v-if="isAuth">
    <main class="py-6">
	  <div class="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8 space-y-6">
        <!-- Hero / карточка игрока -->
        <section
          class="bg-white shadow-sm sm:rounded-lg p-6 flex flex-col md:flex-row gap-6 md:items-center"
        >
          <div class="flex items-center gap-4 flex-1">
            <div
              class="w-20 h-20 rounded-full bg-slate-100 flex items-center justify-center overflow-hidden"
            >
              <img
                v-if="user.avatar_url"
                :src="user.avatar_url"
                :alt="user.name"
                class="w-full h-full object-cover"
              />
              <span
                v-else
                class="text-lg font-semibold text-slate-500"
              >
                {{ user.name?.charAt(0)?.toUpperCase() ?? 'U' }}
              </span>
            </div>

            <div class="min-w-0">
              <h1 class="text-xl md:text-2xl font-semibold text-gray-900 truncate">
                Добро пожаловать, {{ user.name }}!
              </h1>
              <p class="text-sm text-gray-600 mt-1">
                PSN:
                <span class="font-medium">
                  {{ user.psn || 'не указан' }}
                </span>
              </p>
              <p class="text-xs text-gray-500 mt-0.5">
                Email: {{ user.email }}
              </p>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row md:flex-col gap-3">
            <Link
              href="/tournaments"
              class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium bg-slate-900 text-white hover:bg-slate-800"
            >
              Турниры
            </Link>
            <Link
              href="/my/matches"
              class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium border border-slate-200 text-slate-800 hover:bg-slate-50"
            >
              Мои матчи
            </Link>
            <Link
              href="/profile"
              class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium border border-slate-200 text-slate-800 hover:bg-slate-50"
            >
              Профиль игрока
            </Link>
          </div>
        </section>


        <!-- Мои активные турниры -->
        <section class="bg-white shadow-sm sm:rounded-lg p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">
              Мои активные турниры
            </h2>
            <Link
              href="/tournaments"
              class="text-xs text-slate-600 hover:text-slate-900"
            >
              Все турниры
            </Link>
          </div>

          <div v-if="activeTournaments.length" class="space-y-3">
            <div
              v-for="t in activeTournaments"
              :key="t.id"
              class="border rounded-lg px-4 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-2"
            >
              <div>
                <p class="text-sm font-medium text-gray-900">
                  {{ t.name }}
                </p>
                <p class="text-xs text-gray-500 mt-0.5">
                  Сезон {{ t.season }} · Формат: {{ t.format_label }}
                </p>
<div
  v-if="t.team_label"
  class="flex items-center gap-2 text-xs text-gray-600 mt-1"
>
  <span>Ты играешь за:</span>

  <div class="flex items-center gap-2">
    <!-- аватар команды -->
    <div
      class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center overflow-hidden"
    >
      <img
        v-if="t.team_logo_url"
        :src="t.team_logo_url"
        alt=""
        class="w-full h-full object-cover"
      />
      <span
        v-else
        class="text-[10px] font-semibold text-slate-500"
      >
        {{ t.team_code || '?' }}
      </span>
    </div>

    <!-- подпись команды -->
    <span class="font-medium">
      <span v-if="t.team_code">{{ t.team_code }}</span>
      <span v-if="t.team_code && t.team_name"> · </span>
      <span v-if="t.team_name">{{ t.team_name }}</span>
      <span v-else-if="!t.team_code">{{ t.team_label }}</span>
    </span>
  </div>
</div>

              </div>

              <div class="flex items-center gap-2">
                <span
                  v-if="t.status_label"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border border-emerald-200 text-emerald-700 bg-emerald-50"
                >
                  {{ t.status_label }}
                </span>
                <Link
                  :href="`/tournaments/${t.id}`"
                  class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-900 text-white hover:bg-slate-800"
                >
                  Открыть
                </Link>
              </div>
            </div>
          </div>

          <div
            v-else
            class="border border-dashed border-slate-200 rounded-lg p-4 text-sm text-gray-600"
          >
            Ты ещё не участвуешь ни в одном турнире. Выбери турнир в разделе
            <Link href="/tournaments" class="text-slate-900 font-medium hover:underline">
              «Турниры»
            </Link>
            и запишись.
          </div>
        </section>

        <!-- Ближайший матч -->
<section class="bg-white shadow-sm sm:rounded-lg p-6">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold text-gray-900">
      Ближайшие матчи
    </h2>
    <Link
      href="/my/matches"
      class="text-xs text-slate-600 hover:text-slate-900"
    >
      Перейти к списку матчей
    </Link>
  </div>

  <!-- Список до 5 ближайших матчей -->
  <div v-if="nextMatches.length" class="space-y-3">
    <div
      v-for="m in nextMatches"
      :key="m.id"
      class="border rounded-lg p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4"
    >
      <div>
        <p class="text-sm text-gray-500">
          Турнир
        </p>
        <p class="text-base font-semibold text-gray-900">
          {{ m.tournament_name }}
        </p>
        <p class="text-xs text-gray-500 mt-2">
          Стадия: {{ m.stage_name }}
        </p>
      </div>

<div class="flex-1 min-w-0">
  <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">
    Пара матча
  </p>
  <div class="flex items-center gap-4">
    <!-- Хозяева -->
    <div class="flex items-center gap-2 min-w-0">
      <div
        class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center overflow-hidden"
      >
        <img
          v-if="m.home_team_logo_url"
          :src="m.home_team_logo_url"
          alt=""
          class="w-full h-full object-cover"
        />
        <span
          v-else
          class="text-[11px] font-semibold text-slate-500"
        >
          {{ m.home_team_code || '?' }}
        </span>
      </div>
      <div class="min-w-0">
        <p class="text-xs font-semibold text-gray-900">
          {{ m.home_team_code || 'HOME' }}
        </p>
        <p class="text-[11px] text-gray-500 truncate">
          {{ m.home_player_name || 'Игрок' }}
        </p>
      </div>
    </div>

    <span class="text-xs font-semibold text-gray-500">
      vs
    </span>

    <!-- Гости -->
    <div class="flex items-center gap-2 min-w-0">
      <div
        class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center overflow-hidden"
      >
        <img
          v-if="m.away_team_logo_url"
          :src="m.away_team_logo_url"
          alt=""
          class="w-full h-full object-cover"
        />
        <span
          v-else
          class="text-[11px] font-semibold text-slate-500"
        >
          {{ m.away_team_code || '?' }}
        </span>
      </div>
      <div class="min-w-0">
        <p class="text-xs font-semibold text-gray-900">
          {{ m.away_team_code || 'AWAY' }}
        </p>
        <p class="text-[11px] text-gray-500 truncate">
          {{ m.away_player_name || 'Игрок' }}
        </p>
      </div>
    </div>
  </div>
</div>


      <div class="flex flex-col items-start md:items-end gap-2">
        <p v-if="m.status_label" class="text-xs text-gray-500">
          {{ m.status_label }}
        </p>
        <Link
          :href="`/matches/${m.id}`"
          class="inline-flex items-center justify-center px-3 py-2 rounded-lg text-sm font-medium bg-slate-900 text-white hover:bg-slate-800"
        >
          Открыть матч
        </Link>
      </div>
    </div>
  </div>

  <!-- Заглушка, если ближайших матчей нет -->
  <div
    v-else
    class="border border-dashed border-slate-200 rounded-lg p-4 text-sm text-gray-600"
  >
    У тебя пока нет ближайших матчей. Запишись в турнир или загляни в раздел
    <Link href="/tournaments" class="text-slate-900 font-medium hover:underline">
      «Турниры»
    </Link>.
  </div>
</section>

        <!-- Панель администратора -->
        <section v-if="isAdmin" class="bg-white shadow-sm sm:rounded-lg p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-3">
            Панель администратора
          </h2>
          <div class="flex flex-wrap gap-3">
            <Link
              href="/admin/tournaments"
              class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium bg-slate-900 text-white hover:bg-slate-800"
            >
              Управление турнирами
            </Link>
            <Link
              href="/admin/nhl-teams"
              class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium border border-slate-200 text-slate-800 hover:bg-slate-50"
            >
              Реестр команд NHL
            </Link>
          </div>
        </section>
      </div>
    </main>
  </AuthenticatedLayout>

  <!-- ====== Лендинг для гостей ====== -->
  <GuestLayout v-else>
    <div class="min-h-screen flex items-center justify-center bg-neutral-950 px-4">
      <div class="max-w-xl w-full bg-white rounded-3xl shadow-xl p-8 space-y-6">
        <div>
          <h1 class="text-2xl font-bold text-center mb-2">
            NHL26 Tournaments
          </h1>
          <p class="text-sm text-gray-600 text-center">
            Онлайн-турниры по NHL 26 с турнирными таблицами, плей-офф и статистикой игроков.
          </p>
        </div>

        <div class="space-y-4">
          <div class="flex items-start gap-3">
            <span
			  class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white"
            >
              1
            </span>
            <p class="text-sm text-gray-700">
              Создай аккаунт и укажи свой
              <span class="font-medium">PSN</span>.
            </p>
          </div>
          <div class="flex items-start gap-3">
            <span
			  class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white"
            >
              2
            </span>
            <p class="text-sm text-gray-700">
              Выбери турнир и команду NHL, за которую будешь играть.
            </p>
          </div>
          <div class="flex items-start gap-3">
            <span
			  class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white"
            >
              3
            </span>
            <p class="text-sm text-gray-700">
              Играй матчи, отправляй результаты — система сама ведёт таблицы и плей-офф.
            </p>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
          <Link
            v-if="canRegister"
            href="/register"
            class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium bg-slate-900 text-white hover:bg-slate-800"
          >
            Зарегистрироваться
          </Link>
          <Link
            v-if="canLogin"
            href="/login"
            class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium border border-slate-200 text-slate-800 hover:bg-slate-50"
          >
            Войти
          </Link>
        </div>
      </div>
    </div>
  </GuestLayout>
</template>
