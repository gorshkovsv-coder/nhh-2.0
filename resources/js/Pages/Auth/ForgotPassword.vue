<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

defineProps({
  status: String,
})

defineOptions({ layout: GuestLayout })

const form = useForm({
  email: '',
})

const submit = () => {
  form.post('/forgot-password')
}
</script>

<template>
  <Head title="Восстановление пароля — NHL26" />

  <div class="min-h-[calc(100vh-56px)] bg-neutral-900 flex items-center justify-center px-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-[0_20px_40px_rgba(0,0,0,0.35)]">
      <h1 class="mb-1 text-xl font-semibold text-center">Восстановление пароля</h1>
      <p class="mb-4 text-sm text-gray-600 text-center">
        Забыли пароль? Укажите свой email, и мы отправим ссылку для сброса пароля.
      </p>

      <div v-if="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
        {{ status }}
      </div>

      <form @submit.prevent="submit">
        <div>
          <InputLabel for="email" value="Email" />
          <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required autofocus autocomplete="username" />
          <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</div>
        </div>

        <div class="mt-6 flex items-center justify-end">
          <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
            ОТПРАВИТЬ ССЫЛКУ
          </PrimaryButton>
        </div>
      </form>

      <div class="mt-4 text-center">
        <Link href="/login" class="text-sm text-gray-600 underline hover:text-gray-900">Вернуться к входу</Link>
      </div>
    </div>
  </div>
</template>
