<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Кабинет" />

    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Личный кабинет
      </h2>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
          <p class="text-gray-800 text-lg font-semibold mb-2">
            Привет, {{ $page.props.auth.user.name }}!
          </p>
          <p class="text-gray-600">
            Здесь ты можешь перейти к своим матчам, активным турнирам и профилю игрока.
          </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5 flex flex-col justify-between">
            <div>
              <h3 class="font-semibold text-gray-900 mb-1">Мои матчи</h3>
              <p class="text-sm text-gray-600">
                Список всех матчей, где ты участвуешь. Отправляй и подтверждай результаты.
              </p>
            </div>
            <div class="mt-4">
              <Link
                :href="route('my.matches')"
                class="inline-flex px-3 py-1.5 text-sm rounded bg-slate-900 text-white hover:bg-slate-800 transition"
              >
                Открыть
              </Link>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5 flex flex-col justify-between">
            <div>
              <h3 class="font-semibold text-gray-900 mb-1">Турниры</h3>
              <p class="text-sm text-gray-600">
                Посмотри список доступных турниров и их турнирные таблицы.
              </p>
            </div>
            <div class="mt-4">
              <Link
                :href="route('tournaments.index')"
                class="inline-flex px-3 py-1.5 text-sm rounded bg-slate-900 text-white hover:bg-slate-800 transition"
              >
                Перейти к турнирам
              </Link>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5 flex flex-col justify-between">
            <div>
              <h3 class="font-semibold text-gray-900 mb-1">Профиль игрока</h3>
              <p class="text-sm text-gray-600">
                Измени никнейм, почту и пароль, чтобы профиль соответствовал твоему игровому аккаунту.
              </p>
            </div>
            <div class="mt-4">
              <Link
                :href="route('profile.edit')"
                class="inline-flex px-3 py-1.5 text-sm rounded bg-slate-900 text-white hover:bg-slate-800 transition"
              >
                Настроить профиль
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
