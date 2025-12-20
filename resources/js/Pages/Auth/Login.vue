<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import Checkbox from '@/Components/Checkbox.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

defineProps({
  canResetPassword: Boolean,
  status: String,
})

defineOptions({ layout: GuestLayout })

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  })
}
</script>

<template>
  <Head title="Войти — NHL26" />

  <div class="min-h-[calc(100vh-56px)] bg-neutral-900 flex items-center justify-center px-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-[0_20px_40px_rgba(0,0,0,0.35)]">
      <h1 class="mb-4 text-xl font-semibold text-center">Войти</h1>

      <form @submit.prevent="submit">
        <div>
          <InputLabel for="email" value="Email" />
          <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required autofocus autocomplete="username" />
        </div>

        <div class="mt-4">
          <InputLabel for="password" value="Пароль" />
          <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required autocomplete="current-password" />
        </div>

        <div class="mt-4 flex items-center justify-between">
          <label class="flex items-center space-x-2">
            <Checkbox name="remember" v-model:checked="form.remember" />
            <span class="text-sm text-gray-600">Запомнить меня</span>
          </label>

          <div v-if="canResetPassword">
            <Link href="/forgot-password" class="text-sm text-gray-600 underline hover:text-gray-900">
              Забыли пароль?
            </Link>
          </div>
        </div>

        <div class="mt-6 flex items-center justify-end">
          <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
            ВОЙТИ
          </PrimaryButton>
        </div>
      </form>
    </div>
  </div>
</template>
