<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  status: {
    type: String,
    default: '',
  },
})

const form = useForm({})

const resend = () => {
  form.post('/email/verification-notification', {
    preserveScroll: true,
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Подтверждение email" />

    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Подтверждение email
      </h2>
    </template>

    <main class="py-10">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div
          class="bg-white shadow-sm sm:rounded-2xl px-5 py-6 sm:px-8 sm:py-8 flex flex-col items-center text-center space-y-6"
        >
          <!-- Иконка -->
          <div
            class="inline-flex items-center justify-center w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-slate-900 text-white shadow-md"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-7 w-7 sm:h-8 sm:w-8"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <rect
                x="3"
                y="4"
                width="18"
                height="16"
                rx="2"
                ry="2"
              />
              <polyline points="3 6.5 12 12 21 6.5" />
            </svg>
          </div>

          <!-- Текст -->
          <div class="space-y-2">
            <h1 class="text-lg sm:text-xl font-semibold text-gray-900">
              Проверьте вашу почту
            </h1>
            <p class="text-sm sm:text-base text-gray-600 leading-relaxed">
              Мы отправили письмо с ссылкой для подтверждения email на указанный
              при регистрации адрес.
              <br class="hidden sm:inline" />
              Перед продолжением, пожалуйста, перейдите по ссылке в этом письме.
            </p>
            <p class="text-xs sm:text-sm text-gray-500">
              Если письмо не пришло в течение пары минут, проверьте папку
              «Спам» или запросите отправку повторно.
            </p>
          </div>

          <!-- Уведомление об успешной повторной отправке -->
          <div
            v-if="status === 'verification-link-sent'"
            class="w-full sm:w-auto px-4 py-2 rounded-xl bg-emerald-50 border border-emerald-100 text-xs sm:text-sm text-emerald-700"
          >
            На ваш email отправлена новая ссылка для подтверждения.
          </div>

          <!-- Кнопки -->
          <div
            class="w-full flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3 pt-2"
          >
            <button
              type="button"
              class="inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold bg-slate-900 text-white hover:bg-slate-950 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-700 disabled:opacity-60 transition"
              :disabled="form.processing"
              @click="resend"
            >
              Повторно отправить письмо
            </button>

            <Link
              :href="route('logout')"
              method="post"
              as="button"
              class="inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-xs sm:text-sm font-medium border border-gray-300 text-gray-700 hover:bg-gray-50 transition"
            >
              Выйти из аккаунта
            </Link>
          </div>
        </div>
      </div>
    </main>
  </AuthenticatedLayout>
</template>
