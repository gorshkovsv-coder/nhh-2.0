<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import Checkbox from '@/Components/Checkbox.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

defineOptions({ layout: GuestLayout })

const form = useForm({
  name: '',
  email: '',
  psn: '', // Никнейм (PSN)
  password: '',
  password_confirmation: '',
})


const submit = () => {
  form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <Head title="Регистрация — NHL26" />

  <div class="min-h-[calc(100vh-56px)] bg-neutral-900 flex items-center justify-center px-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-[0_20px_40px_rgba(0,0,0,0.35)]">
      <h1 class="mb-4 text-xl font-semibold text-center">Регистрация</h1>

      <form @submit.prevent="submit">
        <div>
          <InputLabel for="name" value="Имя и фамилия" />
          <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
        </div>

        <div class="mt-4">
          <InputLabel for="email" value="Email" />
          <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required autocomplete="username" />
        </div>
		
		<div class="mt-4">
			<label for="psn" class="block text-sm font-medium text-gray-700">
				Никнейм (PSN)
			</label>
			<input
				id="psn"
				v-model="form.psn"
				type="text"
				class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
				autocomplete="off"
				required
			/>
			<InputError :message="form.errors.psn" class="mt-2" />
		</div>
		
		

        <div class="mt-4">
          <InputLabel for="password" value="Пароль" />
          <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
        </div>

        <div class="mt-4">
          <InputLabel for="password_confirmation" value="Подтверждение пароля" />
          <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
        </div>

        <div class="mt-6 flex items-center justify-between">
          <Link href="/login" class="text-sm text-gray-600 underline hover:text-gray-900">Уже есть аккаунт?</Link>
          <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
            Зарегистрироваться
          </PrimaryButton>
        </div>
      </form>
    </div>
  </div>
</template>
