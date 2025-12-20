<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  email: String,
  token: String,
})

defineOptions({ layout: GuestLayout })

const form = useForm({
  token: props.token ?? '',
  email: props.email ?? '',
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <Head title="Сброс пароля — NHL26" />

  <div class="min-h-[calc(100vh-56px)] bg-neutral-900 flex items-center justify-center px-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-[0_20px_40px_rgba(0,0,0,0.35)]">
      <h1 class="mb-1 text-xl font-semibold text-center">Сброс пароля</h1>
      <p class="mb-4 text-sm text-gray-600 text-center">Придумайте новый пароль для вашего аккаунта.</p>

      <form @submit.prevent="submit">
        <div>
          <InputLabel for="email" value="Email" />
          <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required autocomplete="username" />
          <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</div>
        </div>

        <div class="mt-4">
          <InputLabel for="password" value="Новый пароль" />
          <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
          <div v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</div>
        </div>

        <div class="mt-4">
          <InputLabel for="password_confirmation" value="Подтверждение пароля" />
          <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
          <div v-if="form.errors.password_confirmation" class="mt-1 text-sm text-red-600">{{ form.errors.password_confirmation }}</div>
        </div>

        <div class="mt-6 flex items-center justify-end">
          <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
            СМЕНИТЬ ПАРОЛЬ
          </PrimaryButton>
        </div>
      </form>

      <div class="mt-4 text-center">
        <Link href="/login" class="text-sm text-gray-600 underline hover:text-gray-900">Вернуться к входу</Link>
      </div>
    </div>
  </div>
</template>
